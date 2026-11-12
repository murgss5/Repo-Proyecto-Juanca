<?php
/**
 * ============================================================
 * DESCARGA SEGURA DE EXCUSA MÉDICA (INSTRUCTOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/models/ExcusaModel.php';

$idExcusa = filter_input(INPUT_GET, 'id_excusa', FILTER_VALIDATE_INT);

if ($idExcusa === false || $idExcusa === null) {
    http_response_code(400);
    exit('ID de excusa no válido.');
}

$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
$archivo = ExcusaModel::obtenerRutaDocumento($idExcusa, $idInstructor, null);

if (!$archivo) {
    http_response_code(404);
    exit('Excusa no encontrada o no tiene permisos para consultar este adjunto.');
}

$inline = (isset($_GET['inline']) && (string)$_GET['inline'] === '1');
ExcusaModel::servirArchivo($archivo, $inline);
