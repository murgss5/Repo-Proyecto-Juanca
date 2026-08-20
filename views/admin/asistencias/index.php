<?php
/**
 * ============================================================
 * VISTA: ASISTENCIAS (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Asistencias';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Asistencias'],
];

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
      <button type="button" class="btn btn-outline-sena btn-sm" title="Exportar PDF (visual)"><i class="fas fa-file-pdf"></i> PDF</button>
      <button type="button" class="btn btn-outline-sena btn-sm" title="Exportar Excel (visual)"><i class="fas fa-file-excel"></i> Excel</button>
    </div>
  </div>

  <div class="card-body">
    <!-- Filtros visuales -->
    <div class="row mb-3 align-items-end">
      <div class="col-md-2 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Desde</label>
        <input type="date" class="form-control form-control-sm" value="2026-08-19">
      </div>
      <div class="col-md-2 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Hasta</label>
        <input type="date" class="form-control form-control-sm" value="2026-08-20">
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Ficha</label>
        <select class="form-control form-control-sm filtro-tabla" data-col="2">
          <option value="">Todas las fichas</option>
          <?php foreach ($fichas as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Estado</label>
        <select class="form-control form-control-sm filtro-tabla" data-col="5" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Presente</option>
          <option>Retardo</option>
          <option>Salida temprana</option>
          <option>Inasistencia</option>
        </select>
      </div>
      <div class="col-md-2 col-sm-12 mb-2">
        <button type="button" class="btn btn-primary btn-block btn-sm"><i class="fas fa-filter"></i> Aplicar</button>
      </div>
    </div>

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
            <th>Código RFID</th>
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
              <td><span class="badge badge-estado <?= claseEstadoAsistencia($asistencia['estado']) ?>"><?= htmlspecialchars($asistencia['estado']) ?></span></td>
              <td>
                <?php if ($asistencia['minutos_retardo'] > 0): ?>
                  <span class="badge badge-warning"><?= htmlspecialchars($asistencia['minutos_retardo']) ?> min</span>
                <?php else: ?>
                  <span class="text-muted">0</span>
                <?php endif; ?>
              </td>
              <td><code><?= htmlspecialchars($asistencia['rfid']) ?></code></td>
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
