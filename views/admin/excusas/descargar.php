<?php
/**
 * ============================================================
 * DESCARGA SEGURA DE EXCUSA MÉDICA (ADMIN)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/models/ExcusaModel.php';

$idExcusa = filter_input(INPUT_GET, 'id_excusa', FILTER_VALIDATE_INT);

if ($idExcusa === false || $idExcusa === null) {
    http_response_code(400);
    exit('ID de excusa no válido.');
}

$archivo = ExcusaModel::obtenerRutaDocumento($idExcusa, null, null);

if (!$archivo) {
    http_response_code(404);
    exit('Excusa o archivo no encontrado.');
}

$inline = (isset($_GET['inline']) && (string)$_GET['inline'] === '1');
ExcusaModel::servirArchivo($archivo, $inline);
