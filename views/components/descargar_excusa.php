<?php
/**
 * ============================================================
 * COMPONENTE UNIFICADO: DESCARGA SEGURA DE EXCUSAS MÉDICAS
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Soporta parametrización por rol ($origen = 'admin' | 'instructor' | 'aprendiz').
 * Valida autenticación, permisos de consulta y sirve el archivo de forma segura.
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
require_once dirname(__DIR__, 2) . '/models/ExcusaModel.php';

$origen = $origen ?? 'admin';
$idInstructor = null;
$idUsuarioAprendiz = null;

if ($origen === 'admin') {
    exigirRol(1);
} elseif ($origen === 'instructor') {
    exigirRol(2);
    $idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
} elseif ($origen === 'aprendiz') {
    exigirRol(3);
    $idUsuarioAprendiz = (int)($_SESSION['aprendiz']['id_usuario'] ?? 0);
} else {
    http_response_code(403);
    exit('Rol de acceso no autorizado.');
}

$idExcusa = filter_input(INPUT_GET, 'id_excusa', FILTER_VALIDATE_INT);

if ($idExcusa === false || $idExcusa === null) {
    http_response_code(400);
    exit('ID de excusa no válido.');
}

$archivo = ExcusaModel::obtenerRutaDocumento($idExcusa, $idInstructor, $idUsuarioAprendiz);

if (!$archivo) {
    http_response_code(404);
    exit('Excusa no encontrada o no tiene permisos para consultar este adjunto.');
}

$inline = (isset($_GET['inline']) && (string)$_GET['inline'] === '1');
ExcusaModel::servirArchivo($archivo, $inline);
