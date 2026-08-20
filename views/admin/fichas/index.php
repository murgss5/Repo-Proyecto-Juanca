<?php
/**
 * ============================================================
 * VISTA: LISTADO DE FICHAS (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Fichas';
$menuActivo   = 'fichas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Fichas'],
];

function claseEstadoFicha($estado) {
    return $estado === 'Activa' ? 'badge-success' : 'badge-secondary';
}

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-layer-group text-sena"></i> Fichas de formación</h3>
    <div class="card-tools">
      <a href="<?= RUTA_BASE ?>views/admin/fichas/create.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Nueva ficha
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
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar ficha por número o programa...">
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="2">
          <option value="">Todas las jornadas</option>
          <option>Diurna</option>
          <option>Nocturna</option>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="5" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Activa</option>
          <option>Inactiva</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>N° Ficha</th>
            <th>Programa de formación</th>
            <th>Jornada</th>
            <th>Instructor</th>
            <th>Aprendices</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($fichas as $ficha): ?>
            <tr>
              <td><span class="badge badge-info"><?= htmlspecialchars($ficha['numero']) ?></span></td>
              <td><?= htmlspecialchars($ficha['programa']) ?></td>
              <td><?= htmlspecialchars($ficha['jornada']) ?></td>
              <td><?= htmlspecialchars($ficha['instructor']) ?></td>
              <td><?= htmlspecialchars($ficha['aprendices']) ?></td>
              <td><span class="badge badge-estado <?= claseEstadoFicha($ficha['estado']) ?>"><?= htmlspecialchars($ficha['estado']) ?></span></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-info" data-toggle="modal" data-target="#modalVerFicha"
                          data-ficha="<?= htmlspecialchars($ficha['numero']) ?>" title="Ver"><i class="fas fa-eye"></i></button>
                  <button type="button" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></button>
                  <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalEliminar"
                          data-ficha="<?= htmlspecialchars($ficha['numero']) ?>" title="Eliminar"><i class="fas fa-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Datos de demostración: la paginación, búsqueda y filtros funcionan visualmente.</p>
  </div>
</div>

<!-- Modal: ver ficha -->
<div class="modal fade" id="modalVerFicha" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-layer-group text-sena"></i> Detalle de la ficha</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-1"><i class="fas fa-hashtag text-sena"></i> <strong>N° Ficha:</strong> <span class="ficha-numero"></span></p>
        <p class="mb-1"><i class="fas fa-graduation-cap text-sena"></i> <strong>Programa:</strong> Tecnólogo en Análisis y Desarrollo de Software</p>
        <p class="mb-1"><i class="far fa-clock text-sena"></i> <strong>Jornada:</strong> Diurna</p>
        <p class="mb-1"><i class="fas fa-chalkboard-teacher text-sena"></i> <strong>Instructor:</strong> Carlos Gómez</p>
        <p class="mb-1"><i class="fas fa-users text-sena"></i> <strong>Aprendices:</strong> 28</p>
        <p class="mb-1"><i class="fas fa-calendar-alt text-sena"></i> <strong>Fase:</strong> Formación</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: confirmar eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar eliminación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        ¿Está seguro de que desea eliminar la ficha <strong class="ficha-numero"></strong>?
        <em>(Acción deshabilitada en el prototipo)</em>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger"><i class="fas fa-trash"></i> Eliminar</button>
      </div>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
