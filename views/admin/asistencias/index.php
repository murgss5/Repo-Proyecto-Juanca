<?php
/**
 * ============================================================
 * VISTA: ASISTENCIAS (ADMIN)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Vista delegada en el modelo AsistenciaModel y el componente
 * unificado tabla_asistencias.php (Arquitectura MVC Estricta).
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/models/AsistenciaModel.php';
require_once dirname(__DIR__, 2) . '/models/FichaModel.php';

// Filtros GET
$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';
$estadoSeleccionado = $_GET['estado'] ?? '';
$estadosPermitidos = ['Presente', 'Retardo', 'Salida temprana', 'Inasistencia', 'Justificada'];

if ($fechaDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
    $fechaDesde = '';
}
if ($fechaHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
    $fechaHasta = '';
}
if (!in_array($estadoSeleccionado, $estadosPermitidos, true)) {
    $estadoSeleccionado = '';
}

$filtros = [
    'desde'  => $fechaDesde,
    'hasta'  => $fechaHasta,
    'ficha'  => $fichaSeleccionada,
    'estado' => $estadoSeleccionado,
];

// Obtención de datos mediante Modelos
$fichas      = FichaModel::obtenerFichasActivas();
$asistencias = AsistenciaModel::obtenerListadoAsistencias(null, $filtros);

// Variables de vista y layout
$origen = 'admin';
$paginaTitulo = 'Registro de Asistencias';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Asistencias'],
];

ob_start();
require_once dirname(__DIR__, 2) . '/components/tabla_asistencias.php';
$contenido = ob_get_clean();

require_once dirname(__DIR__, 2) . '/layouts/layout_admin.php';
