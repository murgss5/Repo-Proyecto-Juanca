<?php
/**
 * ============================================================
 * VISTA: GESTIÓN DE EXCUSAS MÉDICAS (ADMIN)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Vista delegada en el modelo ExcusaModel y el componente
 * unificado tabla_excusas.php (Arquitectura MVC Estricta).
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/models/ExcusaModel.php';
require_once dirname(__DIR__, 3) . '/models/FichaModel.php';

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

// Obtención de datos mediante Modelos
$excusas = ExcusaModel::obtenerExcusas(null, null, $filtros);
$fichas  = FichaModel::obtenerFichasActivas();

// Parámetros para el componente
$origen = 'admin';
$urlDescargaBase = RUTA_BASE . 'views/admin/excusas/descargar.php';

// Variables de layout
$paginaTitulo = 'Excusas Médicas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Excusas Médicas'],
];

ob_start();
require_once dirname(__DIR__, 2) . '/components/tabla_excusas.php';
$contenido = ob_get_clean();

require_once dirname(__DIR__, 2) . '/layouts/layout_admin.php';
