<?php
/**
 * Descarga segura de una excusa para el instructor asignado a su ficha.
 */

require_once __DIR__ . '/../../../includes/autenticacion.php';
exigirRol(2);
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
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE excusa.id_excusa = :id_excusa
       AND ficha.fk_usuario = :id_instructor
     LIMIT 1'
);
$consultaExcusa->execute([
    ':id_excusa' => $idExcusa,
    ':id_instructor' => $_SESSION['instructor']['id_usuario'],
]);
$excusa = $consultaExcusa->fetch();

if (!$excusa) {
    http_response_code(404);
    exit('Excusa no encontrada o no tiene permiso para descargarla.');
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
