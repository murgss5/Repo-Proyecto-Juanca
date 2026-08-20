<?php
/**
 * ============================================================
 * VISTA: LISTADO DE APRENDICES (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Aprendices';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices'],
];

// --- Clase del badge según el estado del aprendiz ---
function claseEstadoAprendiz($estado) {
    switch ($estado) {
        case 'Activo':     return 'badge-success';
        case 'Suspendido': return 'badge-warning';
        case 'Inactivo':   return 'badge-secondary';
        case 'Retirado':   return 'badge-danger';
        default:           return 'badge-secondary';
    }
}

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-users text-sena"></i> Listado de aprendices</h3>
    <div class="card-tools">
      <a href="<?= RUTA_BASE ?>views/admin/aprendices/create.php" class="btn btn-primary btn-sm">
        <i class="fas fa-user-plus"></i> Nuevo aprendiz
      </a>
    </div>
  </div>

  <div class="card-body">
    <!-- Buscador y filtros visuales -->
    <div class="row mb-3">
      <div class="col-md-4 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar aprendiz por nombre, documento o RFID...">
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="3">
          <option value="">Todas las fichas</option>
          <?php foreach ($fichas as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="5" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Activo</option>
          <option>Suspendido</option>
          <option>Inactivo</option>
          <option>Retirado</option>
        </select>
      </div>
      <div class="col-md-2 col-sm-12 mb-2 text-right">
        <button type="button" class="btn btn-outline-sena btn-block" data-toggle="modal" data-target="#modalFiltrosAvanzados">
          <i class="fas fa-sliders-h"></i> Filtros
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Nombre completo</th>
            <th>Correo</th>
            <th>Ficha</th>
            <th>Código RFID</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendices as $aprendiz): ?>
            <tr>
              <td><?= htmlspecialchars($aprendiz['documento']) ?></td>
              <td><?= htmlspecialchars($aprendiz['nombre']) ?></td>
              <td><?= htmlspecialchars($aprendiz['correo']) ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($aprendiz['ficha']) ?></span></td>
              <td><code><?= htmlspecialchars($aprendiz['rfid']) ?></code></td>
              <td><span class="badge badge-estado <?= claseEstadoAprendiz($aprendiz['estado']) ?>"><?= htmlspecialchars($aprendiz['estado']) ?></span></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <a href="<?= RUTA_BASE ?>views/admin/aprendices/show.php?id=<?= $aprendiz['id'] ?>"
                     class="btn btn-info" data-toggle="tooltip" title="Ver"><i class="fas fa-eye"></i></a>
                  <a href="<?= RUTA_BASE ?>views/admin/aprendices/edit.php?id=<?= $aprendiz['id'] ?>"
                     class="btn btn-warning" data-toggle="tooltip" title="Editar"><i class="fas fa-edit"></i></a>
                  <button type="button" class="btn btn-danger" data-toggle="modal"
                          data-target="#modalEliminar" data-nombre="<?= htmlspecialchars($aprendiz['nombre']) ?>"
                          title="Eliminar"><i class="fas fa-trash"></i></button>
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

<!-- Modal: filtros avanzados -->
<div class="modal fade" id="modalFiltrosAvanzados" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-sliders-h text-sena"></i> Filtros avanzados</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small">
          <i class="fas fa-info-circle"></i> Prototipo visual: los filtros se conectarán a la base de datos más adelante.
        </div>
        <form action="#" method="get">
          <div class="form-group">
            <label>Ficha</label>
            <select class="form-control">
              <option value="">Todas</option>
              <?php foreach ($fichas as $ficha): ?>
                <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Estado</label>
            <select class="form-control">
              <option value="">Todos</option>
              <option>Activo</option>
              <option>Suspendido</option>
              <option>Inactivo</option>
              <option>Retirado</option>
            </select>
          </div>
          <div class="form-group mb-0">
            <label>Programa de formación</label>
            <select class="form-control">
              <option value="">Todos</option>
              <?php foreach ($fichas as $ficha): ?>
                <option value="<?= htmlspecialchars($ficha['programa']) ?>"><?= htmlspecialchars($ficha['programa']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary">Aplicar filtros</button>
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
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        ¿Está seguro de que desea eliminar al aprendiz <strong class="nombre-aprendiz"></strong>?
        Esta acción no se puede deshacer. <em>(Acción deshabilitada en el prototipo)</em>
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
