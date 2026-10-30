<?php
/**
 * Controlador: Actualización manual de hora de salida de asistencia
 * Permite al instructor registrar o editar la hora de salida de un aprendiz.
 * Valida autenticación (Rol 2 - Instructor), token CSRF y pertenencia de ficha.
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(2);

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

date_default_timezone_set('America/Bogota');

// 1. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/instructor/asistencias/index.php');
    exit;
}

// 2. Determinar URL de retorno segura
$returnUrl = trim($_POST['return_url'] ?? '');
if (empty($returnUrl) || strpos($returnUrl, RUTA_BASE) !== 0) {
    $returnUrl = RUTA_BASE . 'views/instructor/asistencias/index.php';
}

function redirigirConParametro(string $urlBase, string $clave, string $valor): void
{
    $separador = (strpos($urlBase, '?') !== false) ? '&' : '?';
    header('Location: ' . $urlBase . $separador . urlencode($clave) . '=' . urlencode($valor));
    exit;
}

// 3. Validar token CSRF
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    redirigirConParametro($returnUrl, 'error', 'Token de seguridad inválido o sesión expirada.');
}

// 4. Capturar y validar parámetros
$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
$idAsistencia = filter_input(INPUT_POST, 'id_asistencia', FILTER_VALIDATE_INT);
$horaSalidaRaw = trim($_POST['hora_salida'] ?? '');

if (!$idAsistencia) {
    redirigirConParametro($returnUrl, 'error', 'Identificador de asistencia no proporcionado o inválido.');
}

if ($horaSalidaRaw === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $horaSalidaRaw)) {
    redirigirConParametro($returnUrl, 'error', 'Formato de hora de salida inválido. Ingrese una hora válida (HH:MM).');
}

// Normalizar formato a HH:MM:SS
if (strlen($horaSalidaRaw) === 5) {
    $horaSalidaTime = $horaSalidaRaw . ':00';
} else {
    $horaSalidaTime = $horaSalidaRaw;
}

try {
    $conexion = (new Conexion())->conectar();

    // 5. Verificar que la asistencia pertenezca a una ficha del instructor
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
        ':id_instructor' => $idInstructor
    ]);
    $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$asistencia) {
        redirigirConParametro($returnUrl, 'error', 'No se encontró el registro de asistencia o no tiene permisos sobre la ficha asignada.');
    }

    $fechaAsistencia = $asistencia['fecha_asistencia'];
    $salidaDatetime  = $fechaAsistencia . ' ' . $horaSalidaTime;

    // 6. Validar que la hora de salida no sea menor a la hora de entrada si existe
    if (!empty($asistencia['entrada'])) {
        $timestampEntrada = strtotime($asistencia['entrada']);
        $timestampSalida  = strtotime($salidaDatetime);
        if ($timestampSalida < $timestampEntrada) {
            $horaEntradaFmt = date('H:i', $timestampEntrada);
            redirigirConParametro($returnUrl, 'error', "La hora de salida ({$horaSalidaRaw}) no puede ser anterior a la hora de entrada ({$horaEntradaFmt}).");
        }
    }

    // 7. Calcular estado de salida y minutos de salida anticipada si hay horario programado
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

    // 8. Actualizar registro en base de datos
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
        ':id_asistencia'             => $idAsistencia
    ]);

    $horaFmt = substr($horaSalidaTime, 0, 5);
    $mensaje = "Hora de salida actualizada correctamente para {$asistencia['aprendiz']} a las {$horaFmt}.";
    redirigirConParametro($returnUrl, 'mensaje', $mensaje);

} catch (PDOException $e) {
    error_log("Error al actualizar hora de salida (ID Asistencia {$idAsistencia}): " . $e->getMessage());
    redirigirConParametro($returnUrl, 'error', 'Ocurrió un error al guardar la hora de salida en la base de datos.');
}

