<?php
/**
 * controlador: Cerrar jornada manual
 * Permite al instructor o administrador cerrar una jornada abierta
 * y generar inasistencias para aprendices sin registro
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';

// Validar sesion y rol (instructor=2 o administrador=1)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$rolUsuario = $_SESSION['fk_rol'] ?? null;
if (!in_array($rolUsuario, [1, 2], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado: se requiere rol Instructor o Administrador']);
    exit;
}

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

// Validar token CSRF (desde POST o header)
$tokenRecibido = $_POST['token_csrf'] ?? '';
if ($tokenRecibido === '') {
    $headers = getallheaders();
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}
if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

$usuarioId = $_SESSION['id_usuario'];
$fechaHoy = date('Y-m-d');

$conexion = (new Conexion())->conectar();

try {
    // Iniciar transaccion
    $conexion->beginTransaction();

    // Buscar sesion abierta para hoy
    // Si es admin, puede cerrar cualquier sesion abierta de hoy
    // Si es instructor, solo la suya
    if ($rolUsuario === 1) {
        $q = $conexion->prepare(
            "SELECT id_sesion, ficha_id, instructor_id, hora_apertura_real
             FROM sesiones_jornada
             WHERE fecha = :fecha
               AND estado = 'abierta'
             FOR UPDATE"
        );
        $q->execute([':fecha' => $fechaHoy]);
    } else {
        $q = $conexion->prepare(
            "SELECT id_sesion, ficha_id, instructor_id, hora_apertura_real
             FROM sesiones_jornada
             WHERE instructor_id = :instructor_id
               AND fecha = :fecha
               AND estado = 'abierta'
             FOR UPDATE"
        );
        $q->execute([
            ':instructor_id' => $usuarioId,
            ':fecha' => $fechaHoy
        ]);
    }
    $sesion = $q->fetch();

    if (!$sesion) {
        throw new Exception('No hay una jornada abierta para cerrar');
    }

    $sesionId = (int)$sesion['id_sesion'];
    $fichaId = (int)$sesion['ficha_id'];
    $instructorIdSesion = (int)$sesion['instructor_id'];

    // Obtener hora actual del servidor
    $horaCierreReal = date('Y-m-d H:i:s');

    // Actualizar sesion a cerrada
    $q = $conexion->prepare(
        "UPDATE sesiones_jornada
         SET hora_cierre_real = :hora_cierre, estado = 'cerrada_manual', actualizado_at = :actualizado
         WHERE id_sesion = :id_sesion"
    );
    $q->execute([
        ':hora_cierre' => $horaCierreReal,
        ':actualizado' => $horaCierreReal,
        ':id_sesion' => $sesionId
    ]);

    if (!$q->rowCount()) {
        throw new Exception('No se pudo cerrar la jornada');
    }

    // GENERAR INASISTENCIAS AUTOMATICAMENTE
    // Buscar todos los aprendices de la ficha
    $q = $conexion->prepare(
        "SELECT id_aprendiz FROM aprendiz WHERE fk_ficha = :ficha_id"
    );
    $q->execute([':ficha_id' => $fichaId]);
    $aprendices = $q->fetchAll(PDO::FETCH_COLUMN);

    $inasistenciasGeneradas = 0;

    if (!empty($aprendices)) {
        // Preparar consulta para insertar inasistencia si no existe
        $insertInasistencia = $conexion->prepare(
            "INSERT INTO asistencia (fecha_asistencia, entrada, salida, estado_entrada, estado_salida, minutos_retardo, minutos_salida_anticipada, fk_aprendiz, fk_sesion_jornada)
             SELECT :fecha, NULL, NULL, 'INASISTENCIA', NULL, 0, 0, a.id_aprendiz, :sesion_id
             FROM aprendiz a
             WHERE a.id_aprendiz = :aprendiz_id
               AND NOT EXISTS (
                   SELECT 1 FROM asistencia 
                   WHERE fk_aprendiz = a.id_aprendiz 
                     AND fecha_asistencia = :fecha
               )"
        );

        foreach ($aprendices as $aprendizId) {
            $insertInasistencia->execute([
                ':fecha' => $fechaHoy,
                ':sesion_id' => $sesionId,
                ':aprendiz_id' => $aprendizId
            ]);
            $inasistenciasGeneradas += $insertInasistencia->rowCount();
        }
    }

    // Confirmar transaccion
    $conexion->commit();

    $horaCierreFmt = date('H:i', strtotime($horaCierreReal));

    $mensaje = "La jornada fue cerrada correctamente a las {$horaCierreFmt}. Ya no se aceptaran nuevos registros de asistencia.";
    if ($inasistenciasGeneradas > 0) {
        $mensaje .= " Se generaron {$inasistenciasGeneradas} inasistencia(s) automaticamente.";
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'data' => [
            'sesion_id' => $sesionId,
            'hora_cierre' => $horaCierreFmt,
            'ficha_id' => $fichaId,
            'inasistencias_generadas' => $inasistenciasGeneradas
        ]
    ]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}