<?php
/**
 * =============================================================================
 * ENDPOINT AJAX: Simular Ingreso RFID del Siguiente Aprendiz
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Herramienta de pruebas para el panel del Instructor.
 * Permite simular una a una las entradas de aprendices con RFID asignado
 * para una jornada que se encuentre actualmente abierta.
 * =============================================================================
 */

require_once __DIR__ . '/../../includes/constantes.php';
require_once __DIR__ . '/../../includes/autenticacion.php';
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../models/AsistenciaModel.php';

exigirRol(2);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('America/Bogota');

// 1. Solo peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validar que el modo de pruebas esté habilitado en el servidor
if (!defined('MODO_PRUEBAS_RFID') || !MODO_PRUEBAS_RFID) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'El modo de pruebas para simular ingresos RFID está deshabilitado en el servidor'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Validar sesión y rol Instructor (rol 2)
if (empty($_SESSION['instructor']['id_usuario'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado: se requiere sesión activa con rol Instructor'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Validar token CSRF (POST o Header)
$tokenRecibido = $_POST['token_csrf'] ?? '';
if ($tokenRecibido === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}
if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Validar parámetro ficha_id
$fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT) ?: (isset($_POST['ficha_id']) ? (int)$_POST['ficha_id'] : null);
if (!$fichaId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetro ficha_id es requerido'], JSON_UNESCAPED_UNICODE);
    exit;
}

$instructorId = (int)$_SESSION['instructor']['id_usuario'];
$fechaHoy = date('Y-m-d');

$conexion = (new Conexion())->conectar();

try {
    // 6. Validar que la ficha pertenezca al instructor autenticado
    $q = $conexion->prepare(
        "SELECT id_ficha FROM ficha WHERE id_ficha = :ficha_id AND fk_usuario = :instructor_id"
    );
    $q->execute([
        ':ficha_id'       => $fichaId,
        ':instructor_id'  => $instructorId
    ]);
    if (!$q->fetch()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'No tiene autorización para gestionar esta ficha'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 7. Validar que la jornada esté actualmente abierta hoy
    $horarioIdFiltro = (filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT) ?: (isset($_POST['horario_id']) ? (int)$_POST['horario_id'] : null)) ?: null;
    $sqlSesion = "SELECT id_sesion, horario_id, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
                  FROM sesiones_jornada
                  WHERE ficha_id = :ficha_id
                    AND fecha = :fecha
                    AND estado = 'abierta'";
    $paramsSesion = [
        ':ficha_id' => $fichaId,
        ':fecha'    => $fechaHoy
    ];
    if ($horarioIdFiltro) {
        $sqlSesion .= " AND (horario_id = :horario_id OR horario_id IS NULL)
                        ORDER BY (CASE WHEN horario_id = :horario_id THEN 0 ELSE 1 END) ASC, id_sesion DESC";
        $paramsSesion[':horario_id'] = $horarioIdFiltro;
    } else {
        $sqlSesion .= " ORDER BY id_sesion DESC";
    }
    $sqlSesion .= " LIMIT 1";

    $q = $conexion->prepare($sqlSesion);
    $q->execute($paramsSesion);
    $sesion = $q->fetch(PDO::FETCH_ASSOC);

    if (!$sesion || $sesion['hora_cierre_real'] !== null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'La jornada no está abierta para esta ficha. El instructor debe abrirla primero.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sesionId = (int)$sesion['id_sesion'];
    $horarioIdSesion = !empty($sesion['horario_id']) ? (int)$sesion['horario_id'] : $horarioIdFiltro;

    // 8. Verificar si la ficha tiene aprendices con RFID registrado
    $q = $conexion->prepare(
        "SELECT COUNT(*) FROM aprendiz 
         WHERE fk_ficha = :ficha_id 
           AND codigo_rfid IS NOT NULL 
           AND TRIM(codigo_rfid) <> ''"
    );
    $q->execute([':ficha_id' => $fichaId]);
    $totalConRfid = (int)$q->fetchColumn();

    if ($totalConRfid === 0) {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'message' => 'No hay aprendices con tarjeta RFID asignada en esta ficha',
            'pendientes_restantes' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 9. Buscar aprendices de la ficha con RFID que NO hayan registrado ingreso en esta sesión/horario
    $q = $conexion->prepare(
        "SELECT a.id_aprendiz, a.codigo_rfid, u.nombre, u.apellido
         FROM aprendiz a
         INNER JOIN usuario u ON u.id_usuario = a.fk_usuario
         WHERE a.fk_ficha = :ficha_id
           AND a.codigo_rfid IS NOT NULL 
           AND TRIM(a.codigo_rfid) <> ''
           AND NOT EXISTS (
               SELECT 1 FROM asistencia ast 
               WHERE ast.fk_aprendiz = a.id_aprendiz 
                 AND (
                     ast.fk_sesion_jornada = :sesion_id
                     OR (ast.fecha_asistencia = :fecha AND :horario_id IS NOT NULL AND ast.fk_horario = :horario_id)
                 )
           )
         ORDER BY a.id_aprendiz ASC"
    );
    $q->execute([
        ':ficha_id'   => $fichaId,
        ':sesion_id'  => $sesionId,
        ':fecha'      => $fechaHoy,
        ':horario_id' => $horarioIdSesion
    ]);
    $pendientes = $q->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendientes)) {
        http_response_code(200);
        echo json_encode([
            'success' => false,
            'message' => 'No quedan aprendices con RFID pendientes de ingreso',
            'pendientes_restantes' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 10. Seleccionar el siguiente pendiente
    $siguiente = $pendientes[0];
    $pendientesRestantes = count($pendientes) - 1;

    // 11. Procesar entrada utilizando la lógica real compartida
    $resultado = AsistenciaModel::registrarPorRfid($siguiente['codigo_rfid'], 'entrada', $conexion, $fichaId);

    if (!$resultado['success']) {
        http_response_code($resultado['http_code'] ?? 400);
        echo json_encode([
            'success' => false,
            'message' => $resultado['message'] ?? 'No fue posible registrar el ingreso'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 12. Devolver respuesta estructurada sin exponer el código RFID completo
    echo json_encode([
        'success' => true,
        'message' => 'Ingreso registrado correctamente',
        'data'    => [
            'aprendiz'             => $siguiente['nombre'] . ' ' . $siguiente['apellido'],
            'hora'                 => $resultado['data']['hora'] ?? date('H:i'),
            'estado'               => $resultado['data']['estado'] ?? 'PUNTUAL',
            'minutos_retardo'      => $resultado['data']['minutos_retardo'] ?? 0,
            'pendientes_restantes' => $pendientesRestantes
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log("Error al simular ingreso RFID: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No fue posible completar la simulación de ingreso. Inténtalo nuevamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
