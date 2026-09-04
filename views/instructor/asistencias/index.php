<?php
// esta es la vista de asistencias que tiene el docente en este caso el (instructor)

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// estas son las variables del layout 
$paginaTitulo = 'Asistencias de mis fichas';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Asistencias'],
];

$conexion = (new Conexion())->conectar();

$consultaFichas = $conexion->prepare(
    'SELECT id_ficha FROM ficha WHERE fk_usuario = :id_instructor ORDER BY id_ficha'
);
$consultaFichas->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaFichas->execute();
$fichasInstructor = $consultaFichas->fetchAll();

function esFechaValida(string $fecha): bool
{
    $fechaConvertida = DateTime::createFromFormat('Y-m-d', $fecha);

    return $fechaConvertida !== false && $fechaConvertida->format('Y-m-d') === $fecha;
}

$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';
$estadoSeleccionado = $_GET['estado'] ?? '';
$estadosPermitidos = ['Presente', 'Retardo', 'Salida temprana', 'Inasistencia'];

if ($fechaDesde !== '' && !esFechaValida($fechaDesde)) {
    $fechaDesde = '';
}

if ($fechaHasta !== '' && !esFechaValida($fechaHasta)) {
    $fechaHasta = '';
}

if (!in_array($estadoSeleccionado, $estadosPermitidos, true)) {
    $estadoSeleccionado = '';
}

$sqlAsistencias = "SELECT
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
    $sqlAsistencias .= ' AND asistencia.fecha_asistencia >= :fecha_desde';
}

if ($fechaHasta !== '') {
    $sqlAsistencias .= ' AND asistencia.fecha_asistencia <= :fecha_hasta';
}

if ($fichaSeleccionada !== '') {
    $sqlAsistencias .= ' AND ficha.id_ficha = :id_ficha';
}

if ($estadoSeleccionado === 'Presente') {
    $sqlAsistencias .= " AND asistencia.estado_entrada = 'PUNTUAL'
                          AND (asistencia.estado_salida IS NULL OR asistencia.estado_salida <> 'SALIDA_ANTICIPADA')";
} elseif ($estadoSeleccionado === 'Retardo') {
    $sqlAsistencias .= " AND asistencia.estado_entrada = 'RETARDO'
                          AND (asistencia.estado_salida IS NULL OR asistencia.estado_salida <> 'SALIDA_ANTICIPADA')";
} elseif ($estadoSeleccionado === 'Salida temprana') {
    $sqlAsistencias .= " AND asistencia.estado_salida = 'SALIDA_ANTICIPADA'";
} elseif ($estadoSeleccionado === 'Inasistencia') {
    $sqlAsistencias .= " AND asistencia.estado_entrada = 'INASISTENCIA'";
}

$sqlAsistencias .= ' ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC';

$consultaAsistencias = $conexion->prepare(
    $sqlAsistencias
);
$consultaAsistencias->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);

if ($fechaDesde !== '') {
    $consultaAsistencias->bindValue(':fecha_desde', $fechaDesde, PDO::PARAM_STR);
}

if ($fechaHasta !== '') {
    $consultaAsistencias->bindValue(':fecha_hasta', $fechaHasta, PDO::PARAM_STR);
}

if ($fichaSeleccionada !== '') {
    $consultaAsistencias->bindValue(':id_ficha', $fichaSeleccionada, PDO::PARAM_INT);
}

$consultaAsistencias->execute();
$asistenciasInstructor = $consultaAsistencias->fetchAll();

foreach ($asistenciasInstructor as &$asistencia) {
    if ($asistencia['estado_entrada'] === 'INASISTENCIA') {
        $asistencia['estado'] = 'Inasistencia';
    } elseif ($asistencia['estado_salida'] === 'SALIDA_ANTICIPADA') {
        $asistencia['estado'] = 'Salida temprana';
    } elseif ($asistencia['estado_entrada'] === 'RETARDO') {
        $asistencia['estado'] = 'Retardo';
    } else {
        $asistencia['estado'] = 'Presente';
    }
}
unset($asistencia);

function claseEstadoAsistencia($estado) {
    switch ($estado) {
        case 'Presente':        return 'badge-success';
        case 'Retardo':         return 'badge-warning';
        case 'Salida temprana': return 'badge-info';
        case 'Inasistencia':    return 'badge-danger';
        default:                return 'badge-secondary';
    }
}

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-clipboard-check text-sena"></i> Registro de asistencias</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-outline-sena btn-sm"><i class="fas fa-file-pdf"></i> PDF</button>
    </div>
  </div>

  <div class="card-body">
    <form method="get" class="row mb-3">
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Desde</label>
        <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaDesde) ?>">
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Hasta</label>
        <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaHasta) ?>">
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Ficha</label>
        <select name="ficha" class="form-control form-control-sm">
          <option value="">Todas mis fichas</option>
          <?php foreach ($fichasInstructor as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>" <?= (string) $fichaSeleccionada === (string) $ficha['id_ficha'] ? 'selected' : '' ?>>Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Estado</label>
        <select name="estado" class="form-control form-control-sm">
          <option value="">Todos los estados</option>
          <?php foreach ($estadosPermitidos as $estado): ?>
            <option value="<?= htmlspecialchars($estado) ?>" <?= $estadoSeleccionado === $estado ? 'selected' : '' ?>><?= htmlspecialchars($estado) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2 d-flex align-items-end">
        <button type="submit" class="btn btn-outline-sena btn-sm btn-block"><i class="fas fa-filter"></i> Filtrar</button>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Aprendiz</th>
            <th>Ficha</th>
            <th>Hora entrada</th>
            <th>Hora salida</th>
            <th>Estado</th>
            <th>Min. retardo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asistenciasInstructor as $asistencia): ?>
            <tr>
              <td><?= htmlspecialchars($asistencia['fecha_asistencia']) ?></td>
              <td><?= htmlspecialchars($asistencia['aprendiz']) ?></td>
              <td><span class="badge badge-info">Ficha #<?= htmlspecialchars($asistencia['id_ficha']) ?></span></td>
              <td><?= htmlspecialchars($asistencia['entrada'] ?? '--') ?></td>
              <td><?= htmlspecialchars($asistencia['salida'] ?? '--') ?></td>
              <td><span class="badge badge-estado <?= claseEstadoAsistencia($asistencia['estado']) ?>"><?= htmlspecialchars($asistencia['estado']) ?></span></td>
              <td>
                <?php if ($asistencia['minutos_retardo'] > 0): ?>
                  <span class="badge badge-warning"><?= htmlspecialchars($asistencia['minutos_retardo']) ?> min</span>
                <?php else: ?>
                  <span class="text-muted">0</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_instructor.php';
