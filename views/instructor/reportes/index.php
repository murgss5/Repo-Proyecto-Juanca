<?php
/**
 * ============================================================
 * VISTA: REPORTES (INSTRUCTOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Vista delegada en el modelo ReporteModel y el componente
 * unificado modulo_reportes.php (Arquitectura MVC Estricta).
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/models/ReporteModel.php';

$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);

// Filtros GET
$filtroFechaIni = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : ($_GET['desde'] ?? null);
$filtroFechaFin = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : ($_GET['hasta'] ?? null);
$filtroFicha    = isset($_GET['ficha']) && $_GET['ficha'] !== '' ? (int)$_GET['ficha'] : null;
$filtroAprendiz = isset($_GET['aprendiz']) && trim($_GET['aprendiz']) !== '' ? trim($_GET['aprendiz']) : null;
$filtroEntrada  = isset($_GET['estado_entrada']) && $_GET['estado_entrada'] !== '' ? $_GET['estado_entrada'] : null;
$filtroSalida   = isset($_GET['estado_salida']) && $_GET['estado_salida'] !== '' ? $_GET['estado_salida'] : null;

// Rápido "Esta semana"
if (isset($_GET['filtro_semana'])) {
    $hoy = new DateTime();
    $inicioSemana = (clone $hoy)->modify('monday this week');
    $finSemana = (clone $hoy)->modify('sunday this week');
    $filtroFechaIni = $inicioSemana->format('Y-m-d');
    $filtroFechaFin = $finSemana->format('Y-m-d');
}

// Validar formato de fechas
if ($filtroFechaIni && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaIni)) {
    $filtroFechaIni = null;
}
if ($filtroFechaFin && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaFin)) {
    $filtroFechaFin = null;
}
if (!$filtroFechaIni) {
    $filtroFechaIni = date('Y-m-01');
}
if (!$filtroFechaFin) {
    $filtroFechaFin = date('Y-m-t');
}

$filtros = [
    'fecha_inicio'   => $filtroFechaIni,
    'fecha_fin'      => $filtroFechaFin,
    'ficha'          => $filtroFicha,
    'aprendiz'       => $filtroAprendiz,
    'estado_entrada' => $filtroEntrada,
    'estado_salida'  => $filtroSalida,
];

// Obtención de datos mediante ReporteModel contextualizados al instructor
$fichas  = ReporteModel::obtenerFichas($idInstructor);
$resumen = ReporteModel::obtenerResumenAsistencias($idInstructor, $filtros);
$detalle = ReporteModel::obtenerDetalleAsistencias($idInstructor, $filtros);
$kpis    = ReporteModel::calcularTotalesKPIs($resumen);

// Parámetros para el componente
$origen = 'instructor';
$urlExportarCsv = 'exportar_csv.php';

// Variables de layout
$paginaTitulo = 'Reportes de Asistencia';
$menuActivo   = 'reportes';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Reportes'],
];

ob_start();
require_once dirname(__DIR__, 2) . '/components/modulo_reportes.php';
$contenido = ob_get_clean();

require_once dirname(__DIR__, 2) . '/layouts/layout_instructor.php';
