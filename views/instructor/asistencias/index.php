<?php
/**
 * ============================================================
 * VISTA: ASISTENCIAS (INSTRUCTOR)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Asistencias de mis fichas';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Asistencias'],
];

// Fichas del instructor (demostración)
$fichasInstructor = ['2693854', '2743910', '2890345'];

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
    <div class="row mb-3">
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Desde</label>
        <input type="date" class="form-control form-control-sm" value="2026-08-19">
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Hasta</label>
        <input type="date" class="form-control form-control-sm" value="2026-08-20">
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <label class="small text-muted mb-1">Ficha</label>
        <select class="form-control form-control-sm filtro-tabla" data-col="2">
          <option value="">Todas mis fichas</option>
          <?php foreach ($fichasInstructor as $numero): ?>
            <option value="<?= htmlspecialchars($numero) ?>"><?= htmlspecialchars($numero) ?></option>
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
          </tr>
        </thead>
        <tbody>
          <?php foreach ($asistencias as $asistencia): ?>
            <?php if (!in_array($asistencia['ficha'], $fichasInstructor)) { continue; } ?>
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
include __DIR__ . '/../layouts/layout_instructor.php';