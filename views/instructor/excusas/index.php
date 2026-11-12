<?php
/**
 * ============================================================
 * VISTA: GESTIÓN DE EXCUSAS MÉDICAS (INSTRUCTOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Vista delegada en el modelo ExcusaModel y el componente
 * unificado tabla_excusas.php (Arquitectura MVC Estricta).
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/models/ExcusaModel.php';
require_once dirname(__DIR__, 2) . '/models/FichaModel.php';

// Token CSRF para dictamen seguro de excusas
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);

// Filtros GET
$filtroEstado   = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
$filtroFechaIni = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : null;
$filtroFechaFin = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : null;
$filtroAprendiz = isset($_GET['aprendiz']) && trim($_GET['aprendiz']) !== '' ? trim($_GET['aprendiz']) : null;
$filtroFicha    = isset($_GET['ficha']) && $_GET['ficha'] !== '' ? (int)$_GET['ficha'] : null;

// Validar formato de fechas
if ($filtroFechaIni && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaIni)) {
    $filtroFechaIni = null;
}
if ($filtroFechaFin && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaFin)) {
    $filtroFechaFin = null;
}

$filtros = [
    'estado'       => $filtroEstado,
    'fecha_inicio' => $filtroFechaIni,
    'fecha_fin'    => $filtroFechaFin,
    'ficha'        => $filtroFicha,
    'aprendiz'     => $filtroAprendiz,
];

// Obtención de datos contextualizados al instructor
$excusas = ExcusaModel::obtenerExcusas($idInstructor, null, $filtros);
$fichas  = FichaModel::obtenerPorInstructor($idInstructor);

// Parámetros para el componente
$origen = 'instructor';
$urlDescargaBase = RUTA_BASE . 'views/instructor/excusas/descargar.php';
$tokenCsrf = $_SESSION['token_csrf'];

// Variables de layout
$paginaTitulo = 'Revisión de Excusas Médicas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Excusas Médicas'],
];

ob_start();
require_once dirname(__DIR__, 2) . '/components/tabla_excusas.php';
$contenido = ob_get_clean();

require_once dirname(__DIR__, 2) . '/layouts/layout_instructor.php';
