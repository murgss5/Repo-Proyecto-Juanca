<?php
/**
 * ============================================================
 * VISTA: REPORTES (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * La generación y exportación se implementarán más adelante.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Reportes';
$menuActivo   = 'reportes';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Reportes'],
];

// Totales de demostración
$totalRetardos       = 6;
$totalInasistencias  = 4;
$totalSalidasTempranas = 2;

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-chart-bar text-sena"></i> Generador de reportes</h3>
  </div>
  <div class="card-body">
    <div class="alert alert-warning py-2" role="alert">
      <i class="fas fa-info-circle"></i> Prototipo visual: los reportes se generarán con consultas a la base de datos y la exportación PDF/Excel se implementará más adelante.
    </div>

    <!-- Filtros del reporte -->
    <form action="#" method="get">
      <div class="row">
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted">Fecha inicial</label>
          <input type="date" class="form-control" value="2026-08-01">
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted">Fecha final</label>
          <input type="date" class="form-control" value="2026-08-20">
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted">Ficha</label>
          <select class="form-control">
            <option value="">Todas las fichas</option>
            <?php foreach ($fichas as $ficha): ?>
              <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
          <label class="small text-muted">Aprendiz</label>
          <select class="form-control">
            <option value="">Todos los aprendices</option>
            <?php foreach ($aprendices as $aprendiz): ?>
              <option value="<?= htmlspecialchars($aprendiz['id']) ?>"><?= htmlspecialchars($aprendiz['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-12">
          <button type="button" class="btn btn-primary"><i class="fas fa-play"></i> Generar reporte</button>
          <button type="button" class="btn btn-outline-danger"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
          <button type="button" class="btn btn-outline-success"><i class="fas fa-file-excel"></i> Exportar Excel</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Tarjetas de totales -->
<div class="row">
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total de retardos</span>
        <span class="info-box-number"><?= $totalRetardos ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total de inasistencias</span>
        <span class="info-box-number"><?= $totalInasistencias ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-sign-out-alt"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Salidas tempranas</span>
        <span class="info-box-number"><?= $totalSalidasTempranas ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Tabla de resultados -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-table text-sena"></i> Resultados del reporte</h3>
  </div>
  <div class="card-body">
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
          <?php foreach ($asistencias as $asistencia): ?>
            <tr>
              <td><?= htmlspecialchars($asistencia['fecha']) ?></td>
              <td><?= htmlspecialchars($asistencia['aprendiz']) ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($asistencia['ficha']) ?></span></td>
              <td><?= htmlspecialchars($asistencia['entrada']) ?></td>
              <td><?= htmlspecialchars($asistencia['salida']) ?></td>
              <td>
                <?php
                  $claseBadge = 'badge-secondary';
                  if ($asistencia['estado'] === 'Presente')        { $claseBadge = 'badge-success'; }
                  if ($asistencia['estado'] === 'Retardo')         { $claseBadge = 'badge-warning'; }
                  if ($asistencia['estado'] === 'Salida temprana') { $claseBadge = 'badge-info'; }
                  if ($asistencia['estado'] === 'Inasistencia')    { $claseBadge = 'badge-danger'; }
                ?>
                <span class="badge badge-estado <?= $claseBadge ?>"><?= htmlspecialchars($asistencia['estado']) ?></span>
              </td>
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
    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Datos de demostración: la paginación, búsqueda y filtros funcionan visualmente.</p>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
