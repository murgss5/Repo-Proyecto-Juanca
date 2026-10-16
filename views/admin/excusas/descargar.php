<?php
/**
 * Descarga segura de una excusa para administrador.
 * El administrador puede descargar cualquier adjunto.
 */

require_once __DIR__ . '/../../../includes/autenticacion.php';
exigirRol(1);
require_once __DIR__ . '/../../../config/conexion.php';

$idExcusa = filter_input(INPUT_GET, 'id_excusa', FILTER_VALIDATE_INT);

if ($idExcusa === false || $idExcusa === null) {
    http_response_code(400);
    exit('ID de excusa no válido.');
}

$conexion = (new Conexion())->conectar();
$consultaExcusa = $conexion->prepare(
    'SELECT excusa.archivo
     FROM excusa
     WHERE excusa.id_excusa = :id_excusa
     LIMIT 1'
);
$consultaExcusa->execute([':id_excusa' => $idExcusa]);
$excusa = $consultaExcusa->fetch();

if (!$excusa) {
    http_response_code(404);
    exit('Excusa no encontrada.');
}

$nombreArchivo = basename($excusa['archivo']);
$carpetaExcusas = realpath(__DIR__ . '/../../../uploads/excusas');
$rutaArchivo = $carpetaExcusas ? realpath($carpetaExcusas . DIRECTORY_SEPARATOR . $nombreArchivo) : false;

if (!$carpetaExcusas || !$rutaArchivo || !is_file($rutaArchivo)
    || strpos($rutaArchivo, $carpetaExcusas . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$tipoMime = finfo_file($finfo, $rutaArchivo);
finfo_close($finfo);

header('Content-Type: ' . $tipoMime);
header('Content-Length: ' . filesize($rutaArchivo));
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

while (ob_get_level()) {
    ob_end_clean();
}

readfile($rutaArchivo);
exit;