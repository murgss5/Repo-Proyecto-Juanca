<?php
/**
 * ============================================================
 * VISTA: MIS EXCUSAS (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Mis excusas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mis excusas'],
];

function claseEstadoExcusa($estado) {
    switch ($estado) {
        case 'Pendiente': return 'badge-warning';
        case 'Aprobada':  return 'badge-success';
        case 'Rechazada': return 'badge-danger';
        default:          return 'badge-secondary';
    }
}

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-file-medical text-sena"></i> Excusas enviadas</h3>
    <div class="card-tools">
      <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-primary btn-sm">
        <i class="fas fa-cloud-upload-alt"></i> Subir excusa
      </a>
    </div>
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
          <option>Pendiente</option>
          <option>Aprobada</option>
          <option>Rechazada</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Fecha de inasistencia</th>
            <th>Archivo adjunto</th>
            <th>Fecha de envío</th>
            <th>Estado</th>
            <th>Observaciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($excusas as $excusa): ?>
            <tr>
              <td><?= htmlspecialchars($excusa['fecha_inasistencia']) ?></td>
              <td>
                <a href="#" class="text-sena" data-toggle="tooltip" title="Descargar (visual)">
                  <i class="fas fa-paperclip"></i> <?= htmlspecialchars($excusa['archivo']) ?>
                </a>
              </td>
              <td><?= htmlspecialchars($excusa['fecha_envio']) ?></td>
              <td><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado']) ?>"><?= htmlspecialchars($excusa['estado']) ?></span></td>
              <td>
                <?php if ($excusa['observaciones'] !== ''): ?>
                  <span class="small"><?= htmlspecialchars($excusa['observaciones']) ?></span>
                <?php else: ?>
                  <span class="text-muted">&mdash;</span>
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
include __DIR__ . '/../layouts/layout_aprendiz.php';