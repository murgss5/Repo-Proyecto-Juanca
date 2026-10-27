<?php
/**
 * Exportación a formato compatible con Microsoft Excel (Admin)
 * Genera un CSV delimitado por punto y coma (;) con BOM UTF-8 para apertura directa en Excel.
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

// --- Construir consulta ---
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

// Consulta de asistencias
$sql = "
    SELECT
        a.fecha_asistencia,
        CONCAT(u.nombre, ' ', u.apellido) AS aprendiz,
        u.identificacion AS documento,
        f.id_ficha AS ficha,
        a.entrada,
        a.salida,
        a.estado_entrada,
        a.estado_salida,
        a.minutos_retardo,
        ap.codigo_rfid
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
    {$whereSql}
    ORDER BY a.fecha_asistencia DESC, u.apellido, u.nombre
";

$consulta = $conexion->prepare($sql);
$consulta->execute($params);

// Headers HTTP para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Reporte_Asistencias_SENA.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Inyección de BOM UTF-8 para compatibilidad nativa con Excel
echo "\xEF\xBB\xBF";

// Apertura del flujo de salida
$output = fopen('php://output', 'w');

// Cabecera de columnas con delimitador ';'
fputcsv($output, ['Fecha', 'Aprendiz', 'Documento', 'Ficha', 'Hora Entrada', 'Hora Salida', 'Estado', 'Min. Retardo', 'Código RFID'], ';');

// Escritura de registros
while ($row = $consulta->fetch()) {
    $estado = $row['estado_entrada'] === 'INASISTENCIA' 
        ? 'Inasistencia' 
        : ($row['estado_salida'] === 'SALIDA_ANTICIPADA' 
            ? 'Salida temprana' 
            : ($row['estado_entrada'] === 'RETARDO' ? 'Retardo' : 'Presente'));

    $fila = [
        !empty($row['fecha_asistencia']) ? date('d/m/Y', strtotime($row['fecha_asistencia'])) : '',
        $row['aprendiz'] ?? '',
        $row['documento'] ?? '',
        $row['ficha'] ?? '',
        !empty($row['entrada']) ? date('H:i', strtotime($row['entrada'])) : '--',
        !empty($row['salida']) ? date('H:i', strtotime($row['salida'])) : '--',
        $estado,
        (int)($row['minutos_retardo'] ?? 0),
        $row['codigo_rfid'] ?? ''
    ];

    fputcsv($output, $fila, ';');
}

fclose($output);
exit;
