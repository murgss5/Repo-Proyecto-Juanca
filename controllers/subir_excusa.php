<?php
/**
 * controlador: Subir excusa medica (Aprendiz)
 * Procesa el formulario de subida de excusa y guarda el archivo de forma segura
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(3);

require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';

$usuarioAprendiz = usuarioActual(3);

// 1. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=Metodo no permitido');
    exit;
}

// 2. Sincronizar y validar token CSRF
$tokenCsrfSesion = (string)($_SESSION['token_csrf'] ?? $_SESSION['csrf_token'] ?? '');
$tokenRecibido = (string)($_POST['csrf_token'] ?? $_POST['token_csrf'] ?? '');

if ($tokenCsrfSesion === '' || !hash_equals($tokenCsrfSesion, $tokenRecibido)) {
    header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=' . urlencode('Token de seguridad inválido'));
    exit;
}

// Validar datos requeridos del formulario
$idAsistencia = filter_input(INPUT_POST, 'id_asistencia', FILTER_VALIDATE_INT);
$observacion = trim($_POST['observaciones'] ?? '');

if ($idAsistencia === false || $idAsistencia === null) {
    header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=Inasistencia no valida');
    exit;
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=Debe seleccionar un archivo');
    exit;
}

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

try {
    // Validar que la asistencia pertenezca al aprendiz de la sesion
    // y que sea una INASISTENCIA sin excusa previa
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
        ':id_usuario' => $usuarioAprendiz['id_usuario'],
    ]);

    if (!$consultaValidar->fetchColumn()) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=La inasistencia seleccionada no es valida o ya tiene una excusa registrada');
        exit;
    }

    // Validar archivo subido
    $archivo = $_FILES['archivo'];
    $tamanoMaximo = 5 * 1024 * 1024; // 5 MB

    // Verifica tamaño maximo
    if ($archivo['size'] > $tamanoMaximo) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=El archivo supera el tamaño maximo de 5 MB');
        exit;
    }

    // Validar tipo MIME real con finfo (no confiar en la extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeReal = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);

    $mimePermitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    // Verifica que el tipo de archivo sea permitido
    if (!isset($mimePermitidos[$mimeReal])) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=Tipo de archivo no permitido. Solo PDF, JPG y PNG');
        exit;
    }

    $extension = $mimePermitidos[$mimeReal];

    // Generar nombre seguro y unico para evitar colisiones
    $nombreSeguro = bin2hex(random_bytes(16)) . '.' . $extension;
    $rutaDestino = __DIR__ . '/../uploads/excusas/' . $nombreSeguro;

    // Mover archivo subido a la carpeta destino
    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=No se pudo guardar el archivo. Intente nuevamente');
        exit;
    }

    // Registrar en base de datos usando procedimiento almacenado
    try {
        $consultaRegistrar = $conexion->prepare('CALL registrar_excusa(:id_asistencia, :archivo, :observacion)');
        $consultaRegistrar->execute([
            ':id_asistencia' => $idAsistencia,
            ':archivo' => $nombreSeguro,
            ':observacion' => $observacion,
        ]);
    } catch (PDOException $e) {
        // Si falla la BD, borrar el archivo subido para no dejar basura
        if (file_exists($rutaDestino)) {
            unlink($rutaDestino);
        }
        header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=No se pudo registrar la excusa. Intente nuevamente');
        exit;
    }

    // Redirigir con mensaje de exito
    header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?mensaje=Excusa enviada correctamente. Queda pendiente de revision por el instructor');
    exit;

} catch (PDOException $e) {
    header('Location: ' . RUTA_BASE . 'views/aprendiz/excusas/create.php?error=Error en la base de datos. Intente mas tarde');
    exit;
}