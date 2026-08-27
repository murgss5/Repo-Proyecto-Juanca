<?php
/**
 * Descarga segura de excusas médicas (Aprendiz)
 * Valida que la excusa pertenezca al aprendiz autenticado antes de permitir la descarga.
 */

session_start();
require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../includes/autenticacion.php';

// Validar sesión y rol
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (($_SESSION['fk_rol'] ?? null) != 3) {
    http_response_code(403);
    exit('Acceso denegado: se requiere rol Aprendiz');
}

// Validar id_excusa por GET
$idExcusa = filter_input(INPUT_GET, 'id_excusa', FILTER_VALIDATE_INT);

if ($idExcusa === false || $idExcusa === null) {
    http_response_code(400);
    exit('ID de excusa no válido');
}

$conexion = (new Conexion())->conectar();

try {
    // Consultar que la excusa pertenezca al aprendiz autenticado
    // y obtener el nombre del archivo almacenado
    $consultaExcusa = $conexion->prepare(
        "SELECT e.archivo
         FROM excusa e
         INNER JOIN asistencia a ON a.id_asistencia = e.fk_asistencia
         INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
         WHERE e.id_excusa = :id_excusa
         AND ap.fk_usuario = :id_usuario
         LIMIT 1"
    );
    $consultaExcusa->execute([
        ':id_excusa' => $idExcusa,
        ':id_usuario' => $_SESSION['id_usuario'],
    ]);

    $excusa = $consultaExcusa->fetch();

    if (!$excusa) {
        http_response_code(404);
        exit('Excusa no encontrada o no tiene permiso para descargarla');
    }

    $nombreArchivoBD = $excusa['archivo'];

    // Construir ruta absoluta del archivo
    // Usamos basename para evitar path traversal
    $nombreSeguro = basename($nombreArchivoBD);
    $rutaArchivo = __DIR__ . '/../../../uploads/excusas/' . $nombreSeguro;

    // Validar que el archivo exista y esté dentro de la carpeta permitida
    $rutaCarpetaPermitida = realpath(__DIR__ . '/../../../uploads/excusas');
    $rutaArchivoReal = realpath($rutaArchivo);

    if (!$rutaArchivoReal || !$rutaCarpetaPermitida) {
        http_response_code(404);
        exit('Archivo no encontrado');
    }

    // Verificar que el archivo está dentro de la carpeta permitida (evitar path traversal)
    if (strpos($rutaArchivoReal, $rutaCarpetaPermitida) !== 0) {
        http_response_code(403);
        exit('Acceso denegado');
    }

    if (!file_exists($rutaArchivoReal) || !is_file($rutaArchivoReal)) {
        http_response_code(404);
        exit('Archivo no encontrado en el servidor');
    }

    // Obtener tipo MIME para la descarga
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $rutaArchivoReal);
    finfo_close($finfo);

    // Enviar cabeceras para descarga
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($rutaArchivoReal));
    header('Content-Disposition: attachment; filename="' . basename($nombreSeguro) . '"');
    header('Cache-Control: private, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Limpiar buffers de salida previos
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Enviar archivo
    readfile($rutaArchivoReal);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    exit('Error en la base de datos');
}
