<?php
/**
 * ============================================================
 * VISTA: LISTADO DE INSTRUCTORES (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Instructores';
$menuActivo   = 'instructores';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Instructores'],
];

function claseEstadoInstructor($estado) {
    return $estado === 'Activo' ? 'badge-success' : 'badge-secondary';
}

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-chalkboard-teacher text-sena"></i> Listado de instructores</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNuevoInstructor">
        <i class="fas fa-user-plus"></i> Nuevo instructor
      </button>
    </div>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-6 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar instructor por nombre, documento o especialidad...">
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="5" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Activo</option>
          <option>Inactivo</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Nombre</th>
            <th>Correo</th>
            <th>Teléfono</th>
            <th>Especialidad</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($instructores as $instructor): ?>
            <tr>
              <td><?= htmlspecialchars($instructor['documento']) ?></td>
              <td><?= htmlspecialchars($instructor['nombre']) ?></td>
              <td><?= htmlspecialchars($instructor['correo']) ?></td>
              <td><?= htmlspecialchars($instructor['telefono']) ?></td>
              <td><?= htmlspecialchars($instructor['especialidad']) ?></td>
              <td><span class="badge badge-estado <?= claseEstadoInstructor($instructor['estado']) ?>"><?= htmlspecialchars($instructor['estado']) ?></span></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-info" data-toggle="modal" data-target="#modalVerInstructor"
                          data-nombre="<?= htmlspecialchars($instructor['nombre']) ?>" title="Ver"><i class="fas fa-eye"></i></button>
                  <button type="button" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></button>
                  <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalEliminar"
                          data-nombre="<?= htmlspecialchars($instructor['nombre']) ?>" title="Eliminar"><i class="fas fa-trash"></i></button>
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

<!-- Modal: nuevo instructor -->
<div class="modal fade" id="modalNuevoInstructor" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user-plus text-sena"></i> Nuevo instructor</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small">
          <i class="fas fa-info-circle"></i> Prototipo visual: este formulario se conectará a la base de datos más adelante.
        </div>
        <form action="#" method="post">
          <div class="form-group">
            <label>Documento</label>
            <input type="text" class="form-control" placeholder="Número de identificación">
          </div>
          <div class="form-group">
            <label>Nombre completo</label>
            <input type="text" class="form-control" placeholder="Nombre y apellidos">
          </div>
          <div class="form-group">
            <label>Correo electrónico</label>
            <input type="email" class="form-control" placeholder="correo@sena.edu.co">
          </div>
          <div class="form-group">
            <label>Especialidad</label>
            <input type="text" class="form-control" placeholder="Ej. Desarrollo de Software">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal: ver instructor -->
<div class="modal fade" id="modalVerInstructor" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user text-sena"></i> Detalle del instructor</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-1"><i class="fas fa-user text-sena"></i> <strong>Nombre:</strong> <span class="nombre-instructor"></span></p>
        <p class="mb-1"><i class="fas fa-id-card text-sena"></i> <strong>Documento:</strong> 1032504321</p>
        <p class="mb-1"><i class="fas fa-envelope text-sena"></i> <strong>Correo:</strong> cgomez@sena.edu.co</p>
        <p class="mb-1"><i class="fas fa-book text-sena"></i> <strong>Especialidad:</strong> Desarrollo de Software</p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Fichas asignadas:</strong> 2</p>
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
        ¿Está seguro de que desea eliminar al instructor <strong class="nombre-instructor"></strong>?
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
