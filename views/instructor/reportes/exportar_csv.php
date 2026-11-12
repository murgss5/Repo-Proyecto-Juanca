<?php
/**
 * ============================================================
 * EXPORTACIÓN CSV: REPORTES (INSTRUCTOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * ============================================================
 * Genera un CSV estructurado con BOM UTF-8 y protección contra
 * inyección de fórmulas CSV mediante neutralizarFilaCsv(),
 * filtrado por las fichas asignadas al instructor.
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/../includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 2) . '/../models/ReporteModel.php';

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
$resumenAprendices = ReporteModel::obtenerResumenAsistencias($idInstructor, $filtros);
$detalleAsistencias = ReporteModel::obtenerDetalleAsistencias($idInstructor, $filtros);
$totales = ReporteModel::calcularTotalesKPIs($resumenAprendices);

// Encabezados HTTP para descarga de archivo CSV
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="reporte_mis_fichas_' . date('Ymd_His') . '.csv"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8 para visualización adecuada de tildes en Excel
fwrite($output, "\xEF\xBB\xBF");

// --- Hoja 1: Resumen por aprendiz ---
fputcsv($output, neutralizarFilaCsv(['REPORTE DE ASISTENCIA - MIS FICHAS (SENA)']));
fputcsv($output, neutralizarFilaCsv(['Fecha inicial', $filtroFechaIni]));
fputcsv($output, neutralizarFilaCsv(['Fecha final', $filtroFechaFin]));
fputcsv($output, neutralizarFilaCsv(['Generado', date('d/m/Y H:i:s')]));
fputcsv($output, []);

fputcsv($output, neutralizarFilaCsv([
    'Aprendiz',
    'Identificación',
    'Programa',
    'Jornada',
    'Ficha',
    'Puntuales',
    'Retardos',
    'Total min. retardo',
    'Inasistencias',
    'Inasistencias justificadas',
    'Inasistencias injustificadas',
    'Salidas anticipadas',
    'Total min. salida ant.',
    'Excusas aprobadas',
    'Excusas pendientes',
    'Excusas rechazadas'
]));

foreach ($resumenAprendices as $row) {
    fputcsv($output, neutralizarFilaCsv([
        $row['aprendiz'],
        $row['identificacion'],
        $row['nombre_programa'],
        $row['jornada'],
        $row['id_ficha'],
        (int)($row['puntuales'] ?? 0),
        (int)$row['retardos'],
        (int)$row['total_min_retardo'],
        (int)$row['inasistencias'],
        (int)$row['inasistencias_justificadas'],
        (int)$row['inasistencias_no_justificadas'],
        (int)$row['salidas_anticipadas'],
        (int)$row['total_min_salida_anticipada'],
        (int)$row['excusas_aprobadas'],
        (int)$row['excusas_pendientes'],
        (int)$row['excusas_rechazadas']
    ]));
}

// Totales
fputcsv($output, []);
fputcsv($output, neutralizarFilaCsv(['TOTALES']));
fputcsv($output, neutralizarFilaCsv([
    'TOTAL',
    '',
    '',
    '',
    '',
    $totales['total_puntuales'],
    $totales['total_retardos'],
    $totales['total_min_retardo'],
    $totales['total_inasistencias'],
    $totales['total_justificadas'],
    $totales['total_no_justificadas'],
    $totales['total_salidas_ant'],
    $totales['total_min_salida_ant'],
    $totales['total_excusas_aprobadas'],
    $totales['total_excusas_pendientes'],
    $totales['total_excusas_rechazadas']
]));

// --- Hoja 2: Detalle de asistencias ---
fputcsv($output, []);
fputcsv($output, neutralizarFilaCsv(['DETALLE DE ASISTENCIAS']));
fputcsv($output, []);

fputcsv($output, neutralizarFilaCsv([
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
]));

foreach ($detalleAsistencias as $row) {
    fputcsv($output, neutralizarFilaCsv([
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
    ]));
}

fclose($output);
exit;
