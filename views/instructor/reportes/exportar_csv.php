<?php
/**
 * Exporta en CSV los registros visibles en el reporte del instructor.
 * La consulta limita siempre los resultados a sus propias fichas.
 */
require_once __DIR__ . '/../../../includes/autenticacion.php';
exigirRol(2);
require_once __DIR__ . '/../../../config/conexion.php';

function fechaExportacionValida(string $fecha): bool
{
    $convertida = DateTime::createFromFormat('Y-m-d', $fecha);
    return $convertida !== false && $convertida->format('Y-m-d') === $fecha;
}

$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';

// Si se presionó el botón "Esta semana", calcular lunes y domingo de la semana actual
if (isset($_GET['filtro_semana'])) {
    $hoy = new DateTime();
    $inicioSemana = clone $hoy;
    $inicioSemana->modify('monday this week');
    $finSemana = clone $hoy;
    $finSemana->modify('sunday this week');
    $fechaDesde = $inicioSemana->format('Y-m-d');
    $fechaHasta = $finSemana->format('Y-m-d');
}

if ($fechaDesde !== '' && !fechaExportacionValida($fechaDesde)) $fechaDesde = '';
if ($fechaHasta !== '' && !fechaExportacionValida($fechaHasta)) $fechaHasta = '';

$conexion = (new Conexion())->conectar();
$sql = "SELECT a.fecha_asistencia, CONCAT(u.nombre, ' ', u.apellido) AS aprendiz,
    u.identificacion, f.id_ficha, f.nombre_programa, a.entrada, a.salida,
    a.estado_entrada, a.estado_salida, a.minutos_retardo, a.minutos_salida_anticipada
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    WHERE f.fk_usuario = :id_instructor";
$parametros = [':id_instructor' => $_SESSION['id_usuario']];
if ($fechaDesde !== '') { $sql .= ' AND a.fecha_asistencia >= :fecha_desde'; $parametros[':fecha_desde'] = $fechaDesde; }
if ($fechaHasta !== '') { $sql .= ' AND a.fecha_asistencia <= :fecha_hasta'; $parametros[':fecha_hasta'] = $fechaHasta; }
if ($fichaSeleccionada !== '') { $sql .= ' AND f.id_ficha = :id_ficha'; $parametros[':id_ficha'] = $fichaSeleccionada; }
$sql .= ' ORDER BY a.fecha_asistencia DESC, u.apellido, u.nombre';
$consulta = $conexion->prepare($sql);
$consulta->execute($parametros);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="reporte_mis_fichas_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['REPORTE DE ASISTENCIAS - MIS FICHAS']);
fputcsv($salida, ['Generado', date('d/m/Y H:i:s')]);
fputcsv($salida, ['Fecha inicial', $fechaDesde ?: 'Sin filtro']);
fputcsv($salida, ['Fecha final', $fechaHasta ?: 'Sin filtro']);
fputcsv($salida, []);
fputcsv($salida, ['Fecha', 'Aprendiz', 'Identificación', 'Ficha', 'Programa', 'Entrada', 'Salida', 'Estado entrada', 'Estado salida', 'Minutos retardo', 'Minutos salida anticipada']);
while ($fila = $consulta->fetch()) {
    fputcsv($salida, [
        date('d/m/Y', strtotime($fila['fecha_asistencia'])), $fila['aprendiz'], $fila['identificacion'],
        $fila['id_ficha'], $fila['nombre_programa'],
        $fila['entrada'] ? date('H:i', strtotime($fila['entrada'])) : '',
        $fila['salida'] ? date('H:i', strtotime($fila['salida'])) : '',
        $fila['estado_entrada'], $fila['estado_salida'], (int) $fila['minutos_retardo'],
        (int) $fila['minutos_salida_anticipada'],
    ]);
}
fclose($salida);
exit;
