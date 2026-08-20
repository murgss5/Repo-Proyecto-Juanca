<?php
/**
 * ============================================================
 * VISTA: DASHBOARD DEL ADMINISTRADOR
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Dashboard';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Dashboard'],
];

// --- Datos calculados (demostración) ---
$fechaHoy = '2026-08-20';
$totalAprendices   = count($aprendices);
$presentesHoy      = 0;
$retardosHoy       = 0;
$inasistenciasHoy  = 0;
foreach ($asistencias as $asistencia) {
    if ($asistencia['fecha'] !== $fechaHoy) { continue; }
    if ($asistencia['estado'] === 'Presente')        { $presentesHoy++; }
    if ($asistencia['estado'] === 'Retardo')         { $retardosHoy++; }
    if ($asistencia['estado'] === 'Inasistencia')    { $inasistenciasHoy++; }
}
$excusasPendientes = 0;
foreach ($excusas as $excusa) {
    if ($excusa['estado'] === 'Pendiente') { $excusasPendientes++; }
}
$fichasActivas = 0;
foreach ($fichas as $ficha) {
    if ($ficha['estado'] === 'Activa') { $fichasActivas++; }
}

// --- Captura del contenido de la vista ---
ob_start();
?>
<!-- ==================== TARJETAS DE ESTADÍSTICAS ==================== -->
<div class="row">
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total de aprendices</span>
        <span class="info-box-number"><?= $totalAprendices ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena-oscuro">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Aprendices presentes hoy</span>
        <span class="info-box-number"><?= $presentesHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Retardos del día</span>
        <span class="info-box-number"><?= $retardosHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Inasistencias del día</span>
        <span class="info-box-number"><?= $inasistenciasHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-file-medical"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Excusas pendientes</span>
        <span class="info-box-number"><?= $excusasPendientes ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-gris">
      <span class="info-box-icon"><i class="fas fa-layer-group"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Fichas activas</span>
        <span class="info-box-number"><?= $fichasActivas ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ==================== GRÁFICAS ==================== -->
<div class="row">
  <div class="col-md-7 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar text-sena"></i> Asistencia de la semana</h3>
      </div>
      <div class="card-body">
        <div style="height: 300px;">
          <canvas id="graficoAsistenciaSemanal"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-5 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie text-sena"></i> Asistencia de hoy</h3>
      </div>
      <div class="card-body">
        <div style="height: 300px;">
          <canvas id="graficoAsistenciaHoy"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== TABLAS RECIENTES ==================== -->
<div class="row">
  <div class="col-md-6 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sign-in-alt text-sena"></i> Últimos ingresos</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Aprendiz</th>
              <th>Ficha</th>
              <th>RFID</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ultimosIngresos as $ingreso): ?>
              <tr>
                <td><span class="badge badge-success"><?= htmlspecialchars($ingreso['hora']) ?></span></td>
                <td><?= htmlspecialchars($ingreso['aprendiz']) ?></td>
                <td><?= htmlspecialchars($ingreso['ficha']) ?></td>
                <td><code><?= htmlspecialchars($ingreso['rfid']) ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-exclamation-triangle text-warning"></i> Retardos recientes</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Aprendiz</th>
              <th>Ficha</th>
              <th>Min. retardo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($retardosRecientes as $retardo): ?>
              <tr>
                <td><?= htmlspecialchars($retardo['hora']) ?></td>
                <td><?= htmlspecialchars($retardo['aprendiz']) ?></td>
                <td><?= htmlspecialchars($retardo['ficha']) ?></td>
                <td><span class="badge badge-warning"><?= htmlspecialchars($retardo['minutos']) ?> min</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ==================== ACCIONES RÁPIDAS ==================== -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-cog text-sena"></i> Administración rápida</h3>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-users fa-2x mb-2"></i><br>Aprendices
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/fichas/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-layer-group fa-2x mb-2"></i><br>Fichas
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/asistencias/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-clipboard-check fa-2x mb-2"></i><br>Asistencias
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/excusas/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-file-medical fa-2x mb-2"></i><br>Excusas
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/reportes/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-chart-bar fa-2x mb-2"></i><br>Reportes
        </a>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-info py-2" role="alert">
  <i class="fas fa-info-circle"></i> Datos de <strong>demostración</strong>. Esta vista se conectará a la base de datos cuando se implementen los modelos y controladores.
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_admin.php';