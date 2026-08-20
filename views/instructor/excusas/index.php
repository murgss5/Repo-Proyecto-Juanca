<?php
/**
 * ============================================================
 * VISTA: EXCUSAS MÉDICAS (INSTRUCTOR)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Excusas médicas de mis fichas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Excusas médicas'],
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
    <h3 class="card-title"><i class="fas fa-file-medical text-sena"></i> Excusas de mis aprendices</h3>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-5 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar por aprendiz o ficha...">
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="4" data-exacto="true">
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
            <th>Aprendiz</th>
            <th>Ficha</th>
            <th>Fecha de inasistencia</th>
            <th>Archivo adjunto</th>
            <th>Fecha de envío</th>
            <th>Estado</th>
            <th>Observaciones</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($excusas as $excusa): ?>
            <tr>
              <td><?= htmlspecialchars($excusa['aprendiz']) ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($excusa['ficha']) ?></span></td>
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
              <td class="text-center">
                <?php if ($excusa['estado'] === 'Pendiente'): ?>
                  <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalAprobar"
                            data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>" title="Aprobar"><i class="fas fa-check"></i></button>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalRechazar"
                            data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>" title="Rechazar"><i class="fas fa-times"></i></button>
                  </div>
                <?php else: ?>
                  <span class="text-muted small">Sin acciones</span>
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

<!-- Modal: aprobar excusa -->
<div class="modal fade" id="modalAprobar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-check-circle"></i> Aprobar excusa</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        ¿Desea aprobar la excusa presentada por <strong class="nombre-aprendiz"></strong>?
        <div class="form-group mt-3">
          <label>Observaciones (opcional)</label>
          <textarea class="form-control" rows="2" placeholder="Comentarios sobre la aprobación..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success"><i class="fas fa-check"></i> Aprobar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: rechazar excusa -->
<div class="modal fade" id="modalRechazar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-times-circle"></i> Rechazar excusa</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        ¿Desea rechazar la excusa presentada por <strong class="nombre-aprendiz"></strong>?
        <div class="form-group mt-3">
          <label>Motivo del rechazo <span class="text-danger">*</span></label>
          <textarea class="form-control" rows="2" placeholder="Indique el motivo del rechazo..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger"><i class="fas fa-times"></i> Rechazar</button>
      </div>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_instructor.php';