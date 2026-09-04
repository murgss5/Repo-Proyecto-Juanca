<?php
/**
 * Reporte de asistencias para las fichas del instructor autenticado.
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

function fechaReporteValida(string $fecha): bool
{
    $fechaConvertida = DateTime::createFromFormat('Y-m-d', $fecha);

    return $fechaConvertida !== false && $fechaConvertida->format('Y-m-d') === $fecha;
}

function claseEstadoReporte(string $estado): string
{
    return match ($estado) {
        'Presente' => 'badge-success',
        'Retardo' => 'badge-warning',
        'Salida temprana' => 'badge-info',
        'Inasistencia' => 'badge-danger',
        default => 'badge-secondary',
    };
}

$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';

if ($fechaDesde !== '' && !fechaReporteValida($fechaDesde)) {
    $fechaDesde = '';
}
if ($fechaHasta !== '' && !fechaReporteValida($fechaHasta)) {
    $fechaHasta = '';
}

$conexion = (new Conexion())->conectar();
$consultaFichas = $conexion->prepare('SELECT id_ficha FROM ficha WHERE fk_usuario = :id_instructor ORDER BY id_ficha');
$consultaFichas->execute([':id_instructor' => $_SESSION['id_usuario']]);
$fichasInstructor = $consultaFichas->fetchAll();

$sqlReporte = "SELECT
    asistencia.fecha_asistencia,
    CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
    ficha.id_ficha,
    DATE_FORMAT(asistencia.entrada, '%H:%i') AS entrada,
    DATE_FORMAT(asistencia.salida, '%H:%i') AS salida,
    asistencia.estado_entrada,
    asistencia.estado_salida,
    asistencia.minutos_retardo
 FROM asistencia
 INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
 INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
 INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
 WHERE ficha.fk_usuario = :id_instructor";

if ($fechaDesde !== '') {
    $sqlReporte .= ' AND asistencia.fecha_asistencia >= :fecha_desde';
}
if ($fechaHasta !== '') {
    $sqlReporte .= ' AND asistencia.fecha_asistencia <= :fecha_hasta';
}
if ($fichaSeleccionada !== '') {
    $sqlReporte .= ' AND ficha.id_ficha = :id_ficha';
}
$sqlReporte .= ' ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC';

$consultaReporte = $conexion->prepare($sqlReporte);
$consultaReporte->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
if ($fechaDesde !== '') {
    $consultaReporte->bindValue(':fecha_desde', $fechaDesde, PDO::PARAM_STR);
}
if ($fechaHasta !== '') {
    $consultaReporte->bindValue(':fecha_hasta', $fechaHasta, PDO::PARAM_STR);
}
if ($fichaSeleccionada !== '') {
    $consultaReporte->bindValue(':id_ficha', $fichaSeleccionada, PDO::PARAM_INT);
}
$consultaReporte->execute();
$registrosReporte = $consultaReporte->fetchAll();

$totales = ['retardos' => 0, 'inasistencias' => 0, 'salidas_tempranas' => 0];
foreach ($registrosReporte as &$registro) {
    if ($registro['estado_entrada'] === 'INASISTENCIA') {
        $registro['estado'] = 'Inasistencia';
        $totales['inasistencias']++;
    } elseif ($registro['estado_salida'] === 'SALIDA_ANTICIPADA') {
        $registro['estado'] = 'Salida temprana';
        $totales['salidas_tempranas']++;
    } elseif ($registro['estado_entrada'] === 'RETARDO') {
        $registro['estado'] = 'Retardo';
        $totales['retardos']++;
    } else {
        $registro['estado'] = 'Presente';
    }
}
unset($registro);

$paginaTitulo = 'Reportes de mis fichas';
$menuActivo = 'reportes';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Reportes'],
];

ob_start();
?>
<div class="card no-print">
  <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-bar text-sena"></i> Reporte de asistencias</h3></div>
  <div class="card-body">
    <form method="get" class="row">
      <div class="col-md-3 col-sm-6 mb-2"><label class="small text-muted">Fecha inicial</label><input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($fechaDesde) ?>"></div>
      <div class="col-md-3 col-sm-6 mb-2"><label class="small text-muted">Fecha final</label><input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($fechaHasta) ?>"></div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted">Ficha</label>
        <select name="ficha" class="form-control"><option value="">Todas mis fichas</option>
          <?php foreach ($fichasInstructor as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>" <?= (string) $fichaSeleccionada === (string) $ficha['id_ficha'] ? 'selected' : '' ?>>Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-block"><i class="fas fa-play"></i> Generar reporte</button></div>
    </form>
  </div>
</div>

<div class="mb-3 no-print text-right">
  <a href="<?= RUTA_BASE ?>views/instructor/reportes/exportar_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success"><i class="fas fa-file-excel"></i> Exportar CSV</a>
  <button type="button" class="btn btn-outline-danger" onclick="window.print()"><i class="fas fa-file-pdf"></i> Imprimir / Guardar como PDF</button>
</div>

<div class="row">
  <div class="col-lg-4 col-md-6 mb-3"><div class="info-box info-box-amarillo"><span class="info-box-icon"><i class="fas fa-clock"></i></span><div class="info-box-content"><span class="info-box-text">Total de retardos</span><span class="info-box-number"><?= $totales['retardos'] ?></span></div></div></div>
  <div class="col-lg-4 col-md-6 mb-3"><div class="info-box info-box-rojo"><span class="info-box-icon"><i class="fas fa-user-slash"></i></span><div class="info-box-content"><span class="info-box-text">Total de inasistencias</span><span class="info-box-number"><?= $totales['inasistencias'] ?></span></div></div></div>
  <div class="col-lg-4 col-md-6 mb-3"><div class="info-box info-box-azul"><span class="info-box-icon"><i class="fas fa-sign-out-alt"></i></span><div class="info-box-content"><span class="info-box-text">Salidas tempranas</span><span class="info-box-number"><?= $totales['salidas_tempranas'] ?></span></div></div></div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title"><i class="fas fa-table text-sena"></i> Resultados</h3></div>
  <div class="card-body"><div class="table-responsive">
    <table class="table table-hover table-bordered tabla-datos"><thead><tr><th>Fecha</th><th>Aprendiz</th><th>Ficha</th><th>Entrada</th><th>Salida</th><th>Estado</th><th>Min. retardo</th></tr></thead><tbody>
      <?php foreach ($registrosReporte as $registro): ?>
        <tr><td><?= htmlspecialchars($registro['fecha_asistencia']) ?></td><td><?= htmlspecialchars($registro['aprendiz']) ?></td><td><span class="badge badge-info">Ficha #<?= htmlspecialchars($registro['id_ficha']) ?></span></td><td><?= htmlspecialchars($registro['entrada'] ?? '--') ?></td><td><?= htmlspecialchars($registro['salida'] ?? '--') ?></td><td><span class="badge badge-estado <?= claseEstadoReporte($registro['estado']) ?>"><?= htmlspecialchars($registro['estado']) ?></span></td><td><?= $registro['minutos_retardo'] > 0 ? htmlspecialchars($registro['minutos_retardo']) . ' min' : '0' ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </div>
</div>
</div>
<style media="print">
  .no-print, .main-header, .main-sidebar, .content-header, .main-footer { display: none !important; }
  .content-wrapper { margin-left: 0 !important; }
  .card { border: none !important; box-shadow: none !important; }
  .table { font-size: 11px !important; }
  @page { margin: 1cm; }
</style>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_instructor.php';
