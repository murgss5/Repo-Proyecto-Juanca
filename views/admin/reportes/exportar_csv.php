<?php
/**
 * Exportación CSV de reporte de asistencias (Admin)
 * Genera un CSV con BOM UTF-8 para que Excel abra correctamente tildes y ñ.
 */

require_once __DIR__ . '/../../../includes/autenticacion.php';
exigirRol(1);
require_once __DIR__ . '/../../../config/conexion.php';

// --- Obtener filtros GET (mismos que en index.php) ---
$filtroFechaIni = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : null;
$filtroFechaFin = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : null;
$filtroFicha    = isset($_GET['ficha']) && $_GET['ficha'] !== '' ? $_GET['ficha'] : null;
$filtroAprendiz = isset($_GET['aprendiz']) && $_GET['aprendiz'] !== '' ? trim($_GET['aprendiz']) : null;
$filtroEntrada  = isset($_GET['estado_entrada']) && $_GET['estado_entrada'] !== '' ? $_GET['estado_entrada'] : null;
$filtroSalida   = isset($_GET['estado_salida']) && $_GET['estado_salida'] !== '' ? $_GET['estado_salida'] : null;

// Si se presionó el botón "Esta semana", calcular lunes y domingo de la semana actual
if (isset($_GET['filtro_semana'])) {
    $hoy = new DateTime();
    $inicioSemana = clone $hoy;
    $inicioSemana->modify('monday this week');
    $finSemana = clone $hoy;
    $finSemana->modify('sunday this week');
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

// Valores por defecto: mes actual
if (!$filtroFechaIni) {
    $filtroFechaIni = date('Y-m-01');
}
if (!$filtroFechaFin) {
    $filtroFechaFin = date('Y-m-t');
}

$conexion = (new Conexion())->conectar();

// --- Construir consulta (igual que en index.php) ---
$where = ["a.fecha_asistencia BETWEEN :fecha_ini AND :fecha_fin"];
$params = [
    ':fecha_ini' => $filtroFechaIni,
    ':fecha_fin' => $filtroFechaFin,
];

if ($filtroFicha) {
    $where[] = "ap.fk_ficha = :ficha";
    $params[':ficha'] = $filtroFicha;
}
if ($filtroAprendiz) {
    $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :aprendiz OR u.identificacion LIKE :aprendiz)";
    $params[':aprendiz'] = "%{$filtroAprendiz}%";
}
if ($filtroEntrada) {
    $where[] = "a.estado_entrada = :estado_entrada";
    $params[':estado_entrada'] = $filtroEntrada;
}
if ($filtroSalida) {
    $where[] = "a.estado_salida = :estado_salida";
    $params[':estado_salida'] = $filtroSalida;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Consulta de resumen por aprendiz
$sqlResumen = "
    SELECT
        CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
        u.identificacion,
        f.nombre_programa,
        f.jornada,
        f.id_ficha,
        SUM(CASE WHEN a.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
        SUM(CASE WHEN a.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
        SUM(CASE WHEN a.estado_entrada = 'RETARDO' THEN COALESCE(a.minutos_retardo, 0) ELSE 0 END) AS total_min_retardo,
        SUM(CASE WHEN a.estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
        SUM(CASE WHEN a.estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_anticipadas,
        SUM(CASE WHEN a.estado_salida = 'SALIDA_ANTICIPADA' THEN COALESCE(a.minutos_salida_anticipada, 0) ELSE 0 END) AS total_min_salida_anticipada,
        SUM(CASE WHEN e.estado = 'APROBADA' THEN 1 ELSE 0 END) AS excusas_aprobadas,
        SUM(CASE WHEN e.estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS excusas_pendientes,
        SUM(CASE WHEN e.estado = 'RECHAZADA' THEN 1 ELSE 0 END) AS excusas_rechazadas,
        SUM(CASE WHEN a.estado_entrada = 'INASISTENCIA' AND e.estado = 'APROBADA' THEN 1 ELSE 0 END) AS inasistencias_justificadas
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
    {$whereSql}
    GROUP BY ap.id_aprendiz, u.nombre, u.apellido, u.identificacion, f.id_ficha, f.nombre_programa, f.jornada
    ORDER BY u.apellido, u.nombre
";

$consultaResumen = $conexion->prepare($sqlResumen);
$consultaResumen->execute($params);
$resumenAprendices = $consultaResumen->fetchAll();

// Consulta de detalle
$sqlDetalle = "
    SELECT
        a.fecha_asistencia,
        CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
        u.identificacion,
        f.id_ficha,
        f.nombre_programa,
        a.entrada,
        a.salida,
        a.estado_entrada,
        a.estado_salida,
        a.minutos_retardo,
        a.minutos_salida_anticipada,
        e.estado AS estado_excusa
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
    {$whereSql}
    ORDER BY a.fecha_asistencia DESC, u.apellido, u.nombre
";

$consultaDetalle = $conexion->prepare($sqlDetalle);
$consultaDetalle->execute($params);
$detalleAsistencias = $consultaDetalle->fetchAll();

// --- Generar CSV ---
// Cabeceras para descarga
$fechaReporte = date('Ymd_His');
$nombreArchivo = "reporte_asistencia_{$fechaReporte}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir salida
$output = fopen('php://output', 'w');

// BOM UTF-8 para Excel
fwrite($output, "\xEF\xBB\xBF");

// --- Hoja 1: Resumen por aprendiz ---
fputcsv($output, ['REPORTE DE ASISTENCIA - RESUMEN POR APRENDIZ']);
fputcsv($output, ['Fecha inicial', $filtroFechaIni]);
fputcsv($output, ['Fecha final', $filtroFechaFin]);
fputcsv($output, ['Generado', date('d/m/Y H:i:s')]);
fputcsv($output, []); // línea vacía

// Encabezados del resumen
fputcsv($output, [
    'Aprendiz',
    'Identificación',
    'Programa',
    'Jornada',
    'Ficha',
    'Asistencias puntuales',
    'Retardos',
    'Minutos de retardo',
    'Inasistencias totales',
    'Inasistencias justificadas (exc. aprobada)',
    'Inasistencias NO justificadas',
    'Salidas anticipadas',
    'Minutos salida anticipada',
    'Excusas aprobadas',
    'Excusas pendientes',
    'Excusas rechazadas'
]);

foreach ($resumenAprendices as $row) {
    $inasistenciasNoJust = (int)$row['inasistencias'] - (int)$row['inasistencias_justificadas'];
    fputcsv($output, [
        $row['aprendiz'],
        $row['identificacion'],
        $row['nombre_programa'],
        $row['jornada'],
        $row['id_ficha'],
        (int)$row['puntuales'],
        (int)$row['retardos'],
        (int)$row['total_min_retardo'],
        (int)$row['inasistencias'],
        (int)$row['inasistencias_justificadas'],
        $inasistenciasNoJust,
        (int)$row['salidas_anticipadas'],
        (int)$row['total_min_salida_anticipada'],
        (int)$row['excusas_aprobadas'],
        (int)$row['excusas_pendientes'],
        (int)$row['excusas_rechazadas']
    ]);
}

// Totales
$totales = [
    'total_aprendices' => count($resumenAprendices),
    'total_retardos' => 0,
    'total_min_retardo' => 0,
    'total_inasistencias' => 0,
    'total_inasistencias_justificadas' => 0,
    'total_salidas_anticipadas' => 0,
    'total_min_salida_anticipada' => 0,
    'total_excusas_aprobadas' => 0,
    'total_excusas_pendientes' => 0,
    'total_excusas_rechazadas' => 0,
];

foreach ($resumenAprendices as $row) {
    $totales['total_retardos'] += (int)$row['retardos'];
    $totales['total_min_retardo'] += (int)$row['total_min_retardo'];
    $totales['total_inasistencias'] += (int)$row['inasistencias'];
    $totales['total_inasistencias_justificadas'] += (int)$row['inasistencias_justificadas'];
    $totales['total_salidas_anticipadas'] += (int)$row['salidas_anticipadas'];
    $totales['total_min_salida_anticipada'] += (int)$row['total_min_salida_anticipada'];
    $totales['total_excusas_aprobadas'] += (int)$row['excusas_aprobadas'];
    $totales['total_excusas_pendientes'] += (int)$row['excusas_pendientes'];
    $totales['total_excusas_rechazadas'] += (int)$row['excusas_rechazadas'];
}
$totales['total_inasistencias_no_justificadas'] = $totales['total_inasistencias'] - $totales['total_inasistencias_justificadas'];

fputcsv($output, []);
fputcsv($output, ['TOTALES']);
fputcsv($output, [
    'TOTAL',
    '',
    '',
    '',
    '',
    '',
    $totales['total_retardos'],
    $totales['total_min_retardo'],
    $totales['total_inasistencias'],
    $totales['total_inasistencias_justificadas'],
    $totales['total_inasistencias_no_justificadas'],
    $totales['total_salidas_anticipadas'],
    $totales['total_min_salida_anticipada'],
    $totales['total_excusas_aprobadas'],
    $totales['total_excusas_pendientes'],
    $totales['total_excusas_rechazadas']
]);

// --- Hoja 2: Detalle de asistencias ---
fputcsv($output, []);
fputcsv($output, ['DETALLE DE ASISTENCIAS']);
fputcsv($output, []);

fputcsv($output, [
    'Fecha',
    'Aprendiz',
    'Identificación',
    'Ficha',
    'Programa',
    'Hora entrada',
    'Hora salida',
    'Estado entrada',
    'Estado salida',
    'Minutos retardo',
    'Minutos salida anticipada',
    'Estado excusa'
]);

foreach ($detalleAsistencias as $row) {
    fputcsv($output, [
        date('d/m/Y', strtotime($row['fecha_asistencia'])),
        $row['aprendiz'],
        $row['identificacion'],
        $row['id_ficha'],
        $row['nombre_programa'],
        $row['entrada'] ? date('H:i', strtotime($row['entrada'])) : '',
        $row['salida'] ? date('H:i', strtotime($row['salida'])) : '',
        $row['estado_entrada'],
        $row['estado_salida'],
        (int)$row['minutos_retardo'],
        (int)$row['minutos_salida_anticipada'],
        $row['estado_excusa'] ? ucfirst(strtolower($row['estado_excusa'])) : ''
    ]);
}

fclose($output);
exit;