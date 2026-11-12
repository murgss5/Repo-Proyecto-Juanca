<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE ASISTENCIAS: AsistenciaController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida la administración y automatización de asistencias:
 *   - accion=actualizar_salida    : Ajuste manual de hora de salida (Instructor)
 *   - accion=generar_inasistencias: Ejecución de inasistencias por cron o admin
 * =============================================================================
 */

require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/AsistenciaModel.php';

date_default_timezone_set('America/Bogota');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática por compatibilidad
if ($accion === '') {
    if (isset($_POST['hora_salida']) || isset($_POST['id_asistencia'])) {
        $accion = 'actualizar_salida';
    } elseif (php_sapi_name() === 'cli' || isset($_GET['token']) || isset($_SERVER['HTTP_X_CRON_TOKEN'])) {
        $accion = 'generar_inasistencias';
    } else {
        $accion = 'actualizar_salida';
    }
}

switch ($accion) {
    case 'actualizar_salida':
        procesarActualizarSalida();
        break;

    case 'generar_inasistencias':
        procesarGenerarInasistencias();
        break;

    default:
        http_response_code(400);
        header('Location: ' . RUTA_BASE . 'index.php');
        exit;
}

/**
 * =============================================================================
 * ACCIÓN 1: ACTUALIZACIÓN MANUAL DE HORA DE SALIDA (ROL INSTRUCTOR)
 * =============================================================================
 */
function procesarActualizarSalida(): void
{
    exigirRol(2);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . RUTA_BASE . 'views/instructor/asistencias/index.php');
        exit;
    }

    $returnUrl = trim($_POST['return_url'] ?? '');
    if (empty($returnUrl) || strpos($returnUrl, RUTA_BASE) !== 0) {
        $returnUrl = RUTA_BASE . 'views/instructor/asistencias/index.php';
    }

    $redirigir = function (string $urlBase, string $clave, string $valor): void {
        $separador = (strpos($urlBase, '?') !== false) ? '&' : '?';
        header('Location: ' . $urlBase . $separador . urlencode($clave) . '=' . urlencode($valor));
        exit;
    };

    if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
        $redirigir($returnUrl, 'error', 'Token de seguridad inválido o sesión expirada.');
    }

    $idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
    $idAsistencia = filter_input(INPUT_POST, 'id_asistencia', FILTER_VALIDATE_INT);
    $horaSalidaRaw = trim($_POST['hora_salida'] ?? '');

    if (!$idAsistencia) {
        $redirigir($returnUrl, 'error', 'Identificador de asistencia no proporcionado o inválido.');
    }

    if ($horaSalidaRaw === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $horaSalidaRaw)) {
        $redirigir($returnUrl, 'error', 'Formato de hora de salida inválido. Ingrese una hora válida (HH:MM).');
    }

    $horaSalidaTime = strlen($horaSalidaRaw) === 5 ? $horaSalidaRaw . ':00' : $horaSalidaRaw;

    try {
        $conexion = (new Conexion())->conectar();

        $stmt = $conexion->prepare(
            "SELECT a.id_asistencia, a.fecha_asistencia, a.entrada, a.salida,
                    a.fk_horario, a.fk_sesion_jornada,
                    h.hora_salida AS horario_salida,
                    CONCAT(u.nombre, ' ', u.apellido) AS aprendiz,
                    f.id_ficha
             FROM asistencia a
             INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
             INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
             INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
             LEFT JOIN horario h ON a.fk_horario = h.id_horario
             WHERE a.id_asistencia = :id_asistencia AND f.fk_usuario = :id_instructor
             LIMIT 1"
        );
        $stmt->execute([
            ':id_asistencia' => $idAsistencia,
            ':id_instructor' => $idInstructor,
        ]);
        $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$asistencia) {
            $redirigir($returnUrl, 'error', 'No se encontró el registro de asistencia o no tiene permisos sobre la ficha asignada.');
        }

        $fechaAsistencia = $asistencia['fecha_asistencia'];
        $salidaDatetime  = $fechaAsistencia . ' ' . $horaSalidaTime;

        if (!empty($asistencia['entrada'])) {
            $timestampEntrada = strtotime($asistencia['entrada']);
            $timestampSalida  = strtotime($salidaDatetime);
            if ($timestampSalida < $timestampEntrada) {
                $horaEntradaFmt = date('H:i', $timestampEntrada);
                $redirigir($returnUrl, 'error', "La hora de salida ({$horaSalidaRaw}) no puede ser anterior a la hora de entrada ({$horaEntradaFmt}).");
            }
        }

        $minutosSalidaAnticipada = 0;
        $estadoSalida = 'SALIDA_NORMAL';

        $horaProgramada = $asistencia['horario_salida'] ?? null;
        if (!empty($horaProgramada)) {
            $secProgramada = strtotime("{$fechaAsistencia} {$horaProgramada}");
            $secReal = strtotime($salidaDatetime);
            if ($secReal < $secProgramada) {
                $minutosSalidaAnticipada = (int)round(($secProgramada - $secReal) / 60);
                if ($minutosSalidaAnticipada < 1) {
                    $minutosSalidaAnticipada = 1;
                }
                $estadoSalida = 'SALIDA_ANTICIPADA';
            }
        }

        $stmtUpdate = $conexion->prepare(
            "UPDATE asistencia
             SET salida = :salida,
                 hora_salida = :hora_salida,
                 estado_salida = :estado_salida,
                 minutos_salida_anticipada = :minutos_salida_anticipada
             WHERE id_asistencia = :id_asistencia"
        );
        $stmtUpdate->execute([
            ':salida'                    => $salidaDatetime,
            ':hora_salida'               => $horaSalidaTime,
            ':estado_salida'             => $estadoSalida,
            ':minutos_salida_anticipada' => $minutosSalidaAnticipada,
            ':id_asistencia'             => $idAsistencia,
        ]);

        $horaFmt = substr($horaSalidaTime, 0, 5);
        $mensaje = "Hora de salida actualizada correctamente para {$asistencia['aprendiz']} a las {$horaFmt}.";
        $redirigir($returnUrl, 'mensaje', $mensaje);

    } catch (PDOException $e) {
        error_log("Error al actualizar hora de salida (ID Asistencia {$idAsistencia}): " . $e->getMessage());
        $redirigir($returnUrl, 'error', 'Ocurrió un error al guardar la hora de salida en la base de datos.');
    }
}

/**
 * =============================================================================
 * ACCIÓN 2: GENERACIÓN AUTOMÁTICA DE INASISTENCIAS (CRON / ADMIN)
 * =============================================================================
 */
function procesarGenerarInasistencias(): void
{
    if (!defined('GENERAR_INASISTENCIAS_TOKEN')) {
        define('GENERAR_INASISTENCIAS_TOKEN', 'cambiar_esta_clave_2026');
    }

    if (php_sapi_name() !== 'cli') {
        asegurarSesionIniciada();
        $esAdmin = !empty($_SESSION['admin']['id_usuario']);

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $tokenHeader = $headers['X-Cron-Token'] ?? $headers['X-CRON-TOKEN'] ?? '';
        $tokenRecibido = (string)($_GET['token'] ?? $tokenHeader);

        $tokenValido = ($tokenRecibido !== '' && hash_equals(GENERAR_INASISTENCIAS_TOKEN, $tokenRecibido));

        if (!$esAdmin && !$tokenValido) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            exit('Acceso denegado: se requiere sesión de Administrador o token de autorización válido.');
        }
    }

    try {
        $conexion = (new Conexion())->conectar();

        $consulta = $conexion->prepare('CALL generar_inasistencias_v2()');
        $consulta->execute();

        do {
            $consulta->fetchAll();
        } while ($consulta->nextRowset());

        $consulta->closeCursor();

        echo 'Proceso de inasistencias ejecutado correctamente.';
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Error en generar_inasistencias: ' . $e->getMessage());
        echo 'No fue posible ejecutar el proceso de inasistencias.';
        exit;
    }
}
