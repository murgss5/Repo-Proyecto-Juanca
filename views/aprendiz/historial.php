<?php
// apartado donde muestra el historial real del aprendiz

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

// variables del layout 
$paginaTitulo = 'Mi historial';
$menuActivo   = 'historial';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi historial'],
];

$conexion = (new Conexion())->conectar();
$consultaHistorial = $conexion->prepare(
    "SELECT
        asistencia.fecha_asistencia,
        DATE_FORMAT(asistencia.entrada, '%H:%i') AS entrada,
        DATE_FORMAT(asistencia.salida, '%H:%i') AS salida,
        asistencia.estado_entrada,
        asistencia.estado_salida
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
     ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC"
);
$consultaHistorial->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaHistorial->execute();
$historialAprendiz = $consultaHistorial->fetchAll();

$resumenHistorial = [
    'presentes' => 0,
    'retardos' => 0,
    'inasistencias' => 0,
    'salidas_tempranas' => 0,
];

foreach ($historialAprendiz as &$registro) {
    if ($registro['estado_entrada'] === 'INASISTENCIA') {
        $registro['estado'] = 'Inasistencia';
        $resumenHistorial['inasistencias']++;
    } elseif ($registro['estado_salida'] === 'SALIDA_ANTICIPADA') {
        $registro['estado'] = 'Salida temprana';
        $resumenHistorial['salidas_tempranas']++;
    } elseif ($registro['estado_entrada'] === 'RETARDO') {
        $registro['estado'] = 'Retardo';
        $resumenHistorial['retardos']++;
    } else {
        $registro['estado'] = 'Presente';
        $resumenHistorial['presentes']++;
    }
}
unset($registro);

function claseEstadoHistorial($estado) {
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

<!-- aqui se ve le resumen del historial -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Asistencias</span>
        <span class="info-box-number"><?= $resumenHistorial['presentes'] ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Retardos</span>
        <span class="info-box-number"><?= $resumenHistorial['retardos'] ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Inasistencias</span>
        <span class="info-box-number"><?= $resumenHistorial['inasistencias'] ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-sign-out-alt"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Salidas tempranas</span>
        <span class="info-box-number"><?= $resumenHistorial['salidas_tempranas'] ?></span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-history text-sena"></i> Historial de asistencia personal</h3>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-5 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar por fecha...">
        </div>
      </div>
      <div class="col-md-4 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="3" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Presente</option>
          <option>Retardo</option>
          <option>Salida temprana</option>
          <option>Inasistencia</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora entrada</th>
            <th>Hora salida</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historialAprendiz as $registro): ?>
            <tr>
              <td><?= htmlspecialchars($registro['fecha_asistencia']) ?></td>
              <td><?= htmlspecialchars($registro['entrada'] ?? '--') ?></td>
              <td><?= htmlspecialchars($registro['salida'] ?? '--') ?></td>
              <td><span class="badge badge-estado <?= claseEstadoHistorial($registro['estado']) ?>"><?= htmlspecialchars($registro['estado']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> El historial y su resumen provienen de la base de datos.</p>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';
