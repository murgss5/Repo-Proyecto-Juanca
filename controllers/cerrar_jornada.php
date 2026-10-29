<?php
/**
 * controlador: Cerrar jornada manual por ficha
 * Permite al instructor o administrador cerrar una jornada abierta de una ficha especifica
 * y generar inasistencias para aprendices sin registro de esa ficha
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(1, 2);

require_once __DIR__ . '/../config/conexion.php';

$usuarioSesion = usuarioActual(2) ?? usuarioActual(1);
$rolUsuario = (int)($usuarioSesion['fk_rol'] ?? 0);

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Metodo no permitido');
    header('Location: ../views/instructor/dashboard.php');
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
    setFlash('error', 'Token CSRF invalido');
    header('Location: ../views/instructor/dashboard.php');
    exit;
}

// Recibir ficha_id (requerido) y opcionalmente sesion_id
$fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
$sesionId = filter_input(INPUT_POST, 'sesion_id', FILTER_VALIDATE_INT);

if (!$fichaId) {
    setFlash('error', 'ficha_id es requerido');
    header('Location: ../views/instructor/dashboard.php');
    exit;
}

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

$usuarioId = $usuarioSesion['id_usuario'];
$fechaHoy = date('Y-m-d');

$conexion = (new Conexion())->conectar();

try {
    // Iniciar transaccion
    $conexion->beginTransaction();

    $horarioIdFiltro = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT) ?: null;
    $sesionIdFiltro = filter_input(INPUT_POST, 'sesion_id', FILTER_VALIDATE_INT) ?: null;

    $sqlSesion = "SELECT id_sesion, ficha_id, horario_id, instructor_id, hora_apertura_real
                  FROM sesiones_jornada
                  WHERE ficha_id = :ficha_id
                    AND fecha = :fecha
                    AND estado = 'abierta'";
    $paramsSesion = [
        ':ficha_id' => $fichaId,
        ':fecha'    => $fechaHoy
    ];

    if ($rolUsuario !== 1) {
        $sqlSesion .= " AND instructor_id = :instructor_id";
        $paramsSesion[':instructor_id'] = $usuarioId;
    }
    if ($sesionIdFiltro) {
        $sqlSesion .= " AND id_sesion = :sesion_id";
        $paramsSesion[':sesion_id'] = $sesionIdFiltro;
    } elseif ($horarioIdFiltro) {
        $sqlSesion .= " AND horario_id = :horario_id";
        $paramsSesion[':horario_id'] = $horarioIdFiltro;
    }
    $sqlSesion .= " ORDER BY id_sesion DESC LIMIT 1 FOR UPDATE";

    $q = $conexion->prepare($sqlSesion);
    $q->execute($paramsSesion);
    $sesion = $q->fetch();

    if (!$sesion) {
        throw new Exception('No hay una jornada abierta para cerrar en esta ficha');
    }

    $sesionId = (int)$sesion['id_sesion'];
    $fichaId = (int)$sesion['ficha_id'];
    $horarioId = (int)$sesion['horario_id'];
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

    // GENERAR INASISTENCIAS AUTOMATICAMENTE SOLO PARA ESTA FICHA
    // Buscar todos los aprendices de esta ficha
    $q = $conexion->prepare(
        "SELECT id_aprendiz FROM aprendiz WHERE fk_ficha = :ficha_id"
    );
    $q->execute([':ficha_id' => $fichaId]);
    $aprendices = $q->fetchAll(PDO::FETCH_COLUMN);

    $inasistenciasGeneradas = 0;

    if (!empty($aprendices)) {
        // Preparar consulta para insertar inasistencia si no existe
        $insertInasistencia = $conexion->prepare(
            "INSERT INTO asistencia (
                fecha_asistencia, entrada, salida, estado_entrada, estado_salida, 
                minutos_retardo, minutos_salida_anticipada, fk_aprendiz, fk_sesion_jornada, fk_horario
             )
             SELECT :fecha, NULL, NULL, 'INASISTENCIA', NULL, 0, 0, a.id_aprendiz, :sesion_id, :horario_id
             FROM aprendiz a
             WHERE a.id_aprendiz = :aprendiz_id
               AND NOT EXISTS (
                   SELECT 1 FROM asistencia 
                   WHERE fk_aprendiz = a.id_aprendiz 
                     AND fk_horario = :horario_id
                     AND fecha_asistencia = :fecha
               )"
        );

        foreach ($aprendices as $aprendizId) {
            try {
                $insertInasistencia->execute([
                    ':fecha'       => $fechaHoy,
                    ':sesion_id'   => $sesionId,
                    ':horario_id'  => $horarioId,
                    ':aprendiz_id' => $aprendizId
                ]);
                $inasistenciasGeneradas += $insertInasistencia->rowCount();
            } catch (PDOException $e) {
                // Manejo de condición de carrera: distinguir exclusivamente error 1062
                $codigoErrorDriver = (int)($e->errorInfo[1] ?? 0);
                if ($codigoErrorDriver === 1062) {
                    // Verificar que exista registro para este aprendiz, horario y fecha
                    $qVerif = $conexion->prepare(
                        "SELECT id_asistencia, fk_sesion_jornada 
                         FROM asistencia 
                         WHERE fk_aprendiz = :aprendiz_id 
                           AND fk_horario = :horario_id 
                           AND fecha_asistencia = :fecha 
                         LIMIT 1"
                    );
                    $qVerif->execute([
                        ':aprendiz_id' => $aprendizId,
                        ':horario_id'  => $horarioId,
                        ':fecha'       => $fechaHoy
                    ]);
                    $regExist = $qVerif->fetch(PDO::FETCH_ASSOC);

                    if ($regExist) {
                        // Si ya existe y tenía sesión nula, vincular a esta sesión
                        if ($regExist['fk_sesion_jornada'] === null) {
                            $qVinc = $conexion->prepare(
                                "UPDATE asistencia 
                                 SET fk_sesion_jornada = :sesion_id 
                                 WHERE id_asistencia = :id_asistencia"
                            );
                            $qVinc->execute([
                                ':sesion_id'     => $sesionId,
                                ':id_asistencia' => $regExist['id_asistencia']
                            ]);
                        }
                        continue;
                    }
                }
                // Si es otro error (FK 1452, desconexión, etc.), relanzar
                throw $e;
            }
        }
    }

    // Confirmar transaccion
    $conexion->commit();

    $horaCierreFmt = date('H:i', strtotime($horaCierreReal));

    $mensaje = "La jornada fue cerrada correctamente a las {$horaCierreFmt}. Ya no se aceptaran nuevos registros de asistencia.";
    if ($inasistenciasGeneradas > 0) {
        $mensaje .= " Se generaron {$inasistenciasGeneradas} inasistencia(s) automaticamente.";
    }

    setFlash('success', $mensaje);
    header('Location: ../views/instructor/dashboard.php');
    exit;

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    setFlash('error', $e->getMessage());
    header('Location: ../views/instructor/dashboard.php');
    exit;
}
