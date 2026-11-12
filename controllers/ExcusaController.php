<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE EXCUSAS: ExcusaController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida el ciclo de radicación y dictamen de justificaciones médicas:
 *   - accion=subir   : Radicación de excusa con soporte PDF/imagen (Aprendiz)
 *   - accion=revisar : Evaluación, dictamen y sincronización de asistencia (Instructor)
 * =============================================================================
 */

require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Bogota');

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: ' . RUTA_BASE . 'index.php');
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática por compatibilidad retroactiva
if ($accion === '') {
    if (isset($_FILES['archivo']) || isset($_POST['id_asistencia'])) {
        $accion = 'subir';
    } elseif (isset($_POST['decision']) || isset($_POST['id_excusa'])) {
        $accion = 'revisar';
    } else {
        $accion = 'subir';
    }
}

switch ($accion) {
    case 'subir':
        procesarSubirExcusa();
        break;

    case 'revisar':
        procesarRevisarExcusa();
        break;

    default:
        http_response_code(400);
        header('Location: ' . RUTA_BASE . 'index.php');
        exit;
}

/**
 * =============================================================================
 * ACCIÓN 1: SUBIR EXCUSA MÉDICA (ROL APRENDIZ)
 * =============================================================================
 */
function procesarSubirExcusa(): void
{
    exigirRol(3);
    $usuarioAprendiz = usuarioActual(3);

    // Sincronizar y validar token CSRF
    $tokenCsrfSesion = (string)($_SESSION['token_csrf'] ?? $_SESSION['csrf_token'] ?? '');
    $tokenRecibido = (string)($_POST['csrf_token'] ?? $_POST['token_csrf'] ?? '');

    if ($tokenCsrfSesion === '' || !hash_equals($tokenCsrfSesion, $tokenRecibido)) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Token de seguridad inválido'));
        exit;
    }

    $idAsistencia = filter_input(INPUT_POST, 'id_asistencia', FILTER_VALIDATE_INT);
    $observacion = trim($_POST['observaciones'] ?? '');

    if ($idAsistencia === false || $idAsistencia === null) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Inasistencia no válida'));
        exit;
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Debe seleccionar un archivo'));
        exit;
    }

    $conexion = (new Conexion())->conectar();

    try {
        // Validar pertenencia de la inasistencia al aprendiz autenticado
        $consultaValidar = $conexion->prepare(
            "SELECT a.id_asistencia
             FROM asistencia a
             INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
             LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
             WHERE a.id_asistencia = :id_asistencia
               AND ap.fk_usuario = :id_usuario
               AND a.estado_entrada = 'INASISTENCIA'
               AND e.id_excusa IS NULL
             LIMIT 1"
        );
        $consultaValidar->execute([
            ':id_asistencia' => $idAsistencia,
            ':id_usuario'    => $usuarioAprendiz['id_usuario'],
        ]);

        if (!$consultaValidar->fetch()) {
            header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('La inasistencia no es válida o ya tiene una excusa registrada'));
            exit;
        }

        // Subir archivo al directorio seguro
        $directorioDestino = dirname(__DIR__) . '/uploads/excusas/';
        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0755, true);
        }

        $archivo = $_FILES['archivo'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        $extensionesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        if (!in_array($extension, $extensionesPermitidas, true)) {
            header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Formato de archivo no permitido. Solo PDF, JPG o PNG'));
            exit;
        }

        $tamanoMaximo = 5 * 1024 * 1024;
        if ($archivo['size'] > $tamanoMaximo) {
            header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('El archivo excede el tamaño máximo permitido (5MB)'));
            exit;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);
        $mimesPermitidos = ['application/pdf', 'image/jpeg', 'image/png'];

        if (!in_array($mimeReal, $mimesPermitidos, true)) {
            header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Tipo de archivo no válido'));
            exit;
        }

        $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
        $rutaCompleta = $directorioDestino . $nombreArchivo;

        if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Error al guardar el archivo en el servidor'));
            exit;
        }

        $rutaRelativaBD = 'uploads/excusas/' . $nombreArchivo;

        $insertarExcusa = $conexion->prepare(
            "INSERT INTO excusa (fk_asistencia, url_documento, motivo, estado, fecha_radicacion)
             VALUES (:fk_asistencia, :url_documento, :motivo, 'PENDIENTE', NOW())"
        );
        $insertarExcusa->execute([
            ':fk_asistencia'  => $idAsistencia,
            ':url_documento'  => $rutaRelativaBD,
            ':motivo'         => $observacion !== '' ? $observacion : 'Sin observaciones',
        ]);

        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?mensaje=' . urlencode('Excusa radicada exitosamente'));
        exit;

    } catch (PDOException $e) {
        error_log('Error al registrar excusa en ExcusaController: ' . $e->getMessage());
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Error interno al registrar la excusa'));
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 2: REVISAR EXCUSA MÉDICA (ROL INSTRUCTOR)
 * =============================================================================
 */
function procesarRevisarExcusa(): void
{
    exigirRol(2);
    $usuarioInstructor = usuarioActual(2);

    $volver = function(string $parametro, string $mensaje): void {
        header('Location: ' . RUTA_BASE . 'views/instructor/excusas/index.php?' . $parametro . '=' . urlencode($mensaje));
        exit;
    };

    if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
        $volver('error', 'La solicitud no es válida. Token CSRF inválido.');
    }

    $idExcusa = filter_input(INPUT_POST, 'id_excusa', FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';

    if ($idExcusa === false || $idExcusa === null || !in_array($decision, ['APROBADA', 'RECHAZADA'], true)) {
        $volver('error', 'Los datos de la revisión no son válidos.');
    }

    $conexion = (new Conexion())->conectar();

    // Verificar pertenencia al instructor autenticado
    $consultaPropiedad = $conexion->prepare(
        'SELECT 1
         FROM excusa
         INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
         INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
         INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
         WHERE excusa.id_excusa = :id_excusa
           AND ficha.fk_usuario = :id_instructor
         LIMIT 1'
    );
    $consultaPropiedad->execute([
        ':id_excusa'      => $idExcusa,
        ':id_instructor'  => $usuarioInstructor['id_usuario'],
    ]);

    if (!$consultaPropiedad->fetch()) {
        $volver('error', 'No tiene permisos para revisar esta excusa.');
    }

    try {
        $consultaSP = $conexion->prepare('CALL revisar_excusa(:id_excusa, :estado, :id_instructor)');
        $consultaSP->execute([
            ':id_excusa'     => $idExcusa,
            ':estado'        => $decision,
            ':id_instructor' => $usuarioInstructor['id_usuario'],
        ]);
        $consultaSP->closeCursor();

        $mensajeExito = $decision === 'APROBADA'
            ? 'La excusa ha sido aprobada exitosamente y la inasistencia fue justificada.'
            : 'La excusa ha sido rechazada.';

        $volver('mensaje', $mensajeExito);

    } catch (PDOException $e) {
        error_log('Error al revisar excusa en ExcusaController: ' . $e->getMessage());
        $volver('error', 'Error en el servidor al procesar la revisión.');
    }
}
