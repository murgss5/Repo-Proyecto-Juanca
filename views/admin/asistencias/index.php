<?php
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

function fechaFiltroValida(string $fecha): bool
{
    $convertida = DateTime::createFromFormat('Y-m-d', $fecha);
    return $convertida !== false && $convertida->format('Y-m-d') === $fecha;
}

function claseEstadoAsistenciaAdmin(string $estado): string
{
    return match ($estado) {
        'Presente' => 'badge-success', 'Retardo' => 'badge-warning',
        'Salida temprana' => 'badge-info', 'Inasistencia' => 'badge-danger',
        default => 'badge-secondary',
    };
}

$conexion = (new Conexion())->conectar();
$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';
$estadoSeleccionado = $_GET['estado'] ?? '';
$estadosPermitidos = ['Presente', 'Retardo', 'Salida temprana', 'Inasistencia'];

if ($fechaDesde !== '' && !fechaFiltroValida($fechaDesde)) $fechaDesde = '';
if ($fechaHasta !== '' && !fechaFiltroValida($fechaHasta)) $fechaHasta = '';
if (!in_array($estadoSeleccionado, $estadosPermitidos, true)) $estadoSeleccionado = '';

$fichas = $conexion->query('SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha')->fetchAll();
$sql = "SELECT a.fecha_asistencia, CONCAT(u.nombre, ' ', u.apellido) AS aprendiz,
    f.id_ficha, ap.codigo_rfid, DATE_FORMAT(a.entrada, '%H:%i') AS entrada,
    DATE_FORMAT(a.salida, '%H:%i') AS salida, a.estado_entrada, a.estado_salida, a.minutos_retardo
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha WHERE 1=1";
$parametros = [];
if ($fechaDesde !== '') { $sql .= ' AND a.fecha_asistencia >= :desde'; $parametros[':desde'] = $fechaDesde; }
if ($fechaHasta !== '') { $sql .= ' AND a.fecha_asistencia <= :hasta'; $parametros[':hasta'] = $fechaHasta; }
if ($fichaSeleccionada !== '') { $sql .= ' AND f.id_ficha = :ficha'; $parametros[':ficha'] = $fichaSeleccionada; }
if ($estadoSeleccionado === 'Presente') $sql .= " AND a.estado_entrada = 'PUNTUAL' AND (a.estado_salida IS NULL OR a.estado_salida <> 'SALIDA_ANTICIPADA')";
elseif ($estadoSeleccionado === 'Retardo') $sql .= " AND a.estado_entrada = 'RETARDO' AND (a.estado_salida IS NULL OR a.estado_salida <> 'SALIDA_ANTICIPADA')";
elseif ($estadoSeleccionado === 'Salida temprana') $sql .= " AND a.estado_salida = 'SALIDA_ANTICIPADA'";
elseif ($estadoSeleccionado === 'Inasistencia') $sql .= " AND a.estado_entrada = 'INASISTENCIA'";
$sql .= ' ORDER BY a.fecha_asistencia DESC, a.entrada DESC, a.id_asistencia DESC';
$consulta = $conexion->prepare($sql); $consulta->execute($parametros); $asistencias = $consulta->fetchAll();
foreach ($asistencias as &$asistencia) {
    $asistencia['estado'] = $asistencia['estado_entrada'] === 'INASISTENCIA' ? 'Inasistencia' : ($asistencia['estado_salida'] === 'SALIDA_ANTICIPADA' ? 'Salida temprana' : ($asistencia['estado_entrada'] === 'RETARDO' ? 'Retardo' : 'Presente'));
}
unset($asistencia);

$paginaTitulo = 'Asistencias'; $menuActivo = 'asistencias';
$miga = [['label'=>'Inicio','url'=>RUTA_BASE.'views/admin/dashboard.php'], ['label'=>'Asistencias']];
ob_start();
?>
<div class="card">
  <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-check text-sena"></i> Registro de asistencias</h3></div>
  <div class="card-body">
    <form method="get" class="row mb-3 align-items-end">
      <div class="col-md-2 col-sm-6 mb-2"><label class="small text-muted mb-1">Desde</label><input type="date" name="desde" class="form-control form-control-sm" value="<?=htmlspecialchars($fechaDesde)?>"></div>
      <div class="col-md-2 col-sm-6 mb-2"><label class="small text-muted mb-1">Hasta</label><input type="date" name="hasta" class="form-control form-control-sm" value="<?=htmlspecialchars($fechaHasta)?>"></div>
      <div class="col-md-3 col-sm-6 mb-2"><label class="small text-muted mb-1">Ficha</label><select name="ficha" class="form-control form-control-sm"><option value="">Todas las fichas</option><?php foreach($fichas as $ficha):?><option value="<?=$ficha['id_ficha']?>" <?=strval($fichaSeleccionada)===strval($ficha['id_ficha'])?'selected':''?>>Ficha #<?=$ficha['id_ficha']?> — <?=htmlspecialchars($ficha['nombre_programa'])?></option><?php endforeach;?></select></div>
      <div class="col-md-3 col-sm-6 mb-2"><label class="small text-muted mb-1">Estado</label><select name="estado" class="form-control form-control-sm"><option value="">Todos los estados</option><?php foreach($estadosPermitidos as $estado):?><option value="<?=$estado?>" <?=$estadoSeleccionado===$estado?'selected':''?>><?=$estado?></option><?php endforeach;?></select></div>
      <div class="col-md-2 col-sm-12 mb-2"><button class="btn btn-primary btn-block btn-sm"><i class="fas fa-filter"></i> Filtrar</button></div>
    </form>
    <div class="table-responsive"><table class="table table-hover table-bordered tabla-datos"><thead><tr><th>Fecha</th><th>Aprendiz</th><th>Ficha</th><th>Hora entrada</th><th>Hora salida</th><th>Estado</th><th>Min. retardo</th><th>Código RFID</th></tr></thead><tbody>
    <?php foreach($asistencias as $asistencia):?><tr><td><?=htmlspecialchars($asistencia['fecha_asistencia'])?></td><td><?=htmlspecialchars($asistencia['aprendiz'])?></td><td><span class="badge badge-info">Ficha #<?=$asistencia['id_ficha']?></span></td><td><?=htmlspecialchars($asistencia['entrada']??'--')?></td><td><?=htmlspecialchars($asistencia['salida']??'--')?></td><td><span class="badge badge-estado <?=claseEstadoAsistenciaAdmin($asistencia['estado'])?>"><?=htmlspecialchars($asistencia['estado'])?></span></td><td><?php if($asistencia['minutos_retardo']>0):?><span class="badge badge-warning"><?=$asistencia['minutos_retardo']?> min</span><?php else:?><span class="text-muted">0</span><?php endif;?></td><td><code><?=htmlspecialchars($asistencia['codigo_rfid'])?></code></td></tr><?php endforeach;?>
    </tbody></table></div>
    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Los filtros por fecha, ficha y estado consultan directamente la base de datos.</p>
  </div>
</div>
<?php $contenido=ob_get_clean(); include __DIR__.'/../../layouts/layout_admin.php';
