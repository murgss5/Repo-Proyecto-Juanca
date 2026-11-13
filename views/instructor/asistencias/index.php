<?php
/**
 * ============================================================
 * VISTA: ASISTENCIAS (INSTRUCTOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Vista delegada en el modelo AsistenciaModel y el componente
 * unificado tabla_asistencias.php (Arquitectura MVC Estricta).
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/models/AsistenciaModel.php';
require_once dirname(__DIR__, 3) . '/models/FichaModel.php';

// Token CSRF para formularios seguros
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);

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
$fichas      = FichaModel::obtenerPorInstructor($idInstructor);
$asistencias = AsistenciaModel::obtenerListadoAsistencias($idInstructor, $filtros);

// Variables de vista y layout
$origen = 'instructor';
$tokenCsrf = $_SESSION['token_csrf'];
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' .
    ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');

$paginaTitulo = 'Asistencias de Mis Fichas';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Asistencias'],
];

ob_start();
require_once dirname(__DIR__, 2) . '/components/tabla_asistencias.php';
$contenido = ob_get_clean();

require_once dirname(__DIR__, 2) . '/layouts/layout_instructor.php';
