<?php
/**
 * ============================================================
 * VISTA: HORARIOS (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Horarios';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios'],
];

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-calendar-alt text-sena"></i> Horarios de formación</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalNuevoHorario">
        <i class="fas fa-plus"></i> Nuevo horario
      </button>
    </div>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-4 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar por ficha, ambiente o instructor...">
        </div>
      </div>
      <div class="col-md-4 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="0">
          <option value="">Todas las fichas</option>
          <?php foreach ($fichas as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="1">
          <option value="">Todos los días</option>
          <option>Lunes</option>
          <option>Martes</option>
          <option>Miércoles</option>
          <option>Jueves</option>
          <option>Viernes</option>
          <option>Sábado</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Ficha</th>
            <th>Día</th>
            <th>Hora inicio</th>
            <th>Hora fin</th>
            <th>Ambiente</th>
            <th>Instructor</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($horarios as $horario): ?>
            <tr>
              <td><span class="badge badge-info"><?= htmlspecialchars($horario['ficha']) ?></span></td>
              <td><?= htmlspecialchars($horario['dia']) ?></td>
              <td><span class="badge badge-success"><?= htmlspecialchars($horario['hora_inicio']) ?></span></td>
              <td><span class="badge badge-secondary"><?= htmlspecialchars($horario['hora_fin']) ?></span></td>
              <td><?= htmlspecialchars($horario['ambiente']) ?></td>
              <td><?= htmlspecialchars($horario['instructor']) ?></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-warning" title="Editar"><i class="fas fa-edit"></i></button>
                  <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalEliminar" title="Eliminar"><i class="fas fa-trash"></i></button>
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

<!-- Modal: nuevo horario -->
<div class="modal fade" id="modalNuevoHorario" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-calendar-plus text-sena"></i> Nuevo horario</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small">
          <i class="fas fa-info-circle"></i> Prototipo visual: este formulario se conectará a la base de datos más adelante.
        </div>
        <form action="#" method="post">
          <div class="form-group">
            <label>Ficha</label>
            <select class="form-control">
              <option value="">Seleccione...</option>
              <?php foreach ($fichas as $ficha): ?>
                <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Día</label>
            <select class="form-control">
              <option>Lunes</option>
              <option>Martes</option>
              <option>Miércoles</option>
              <option>Jueves</option>
              <option>Viernes</option>
              <option>Sábado</option>
            </select>
          </div>
          <div class="row">
            <div class="col-6">
              <div class="form-group">
                <label>Hora inicio</label>
                <input type="time" class="form-control">
              </div>
            </div>
            <div class="col-6">
              <div class="form-group">
                <label>Hora fin</label>
                <input type="time" class="form-control">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Ambiente</label>
            <input type="text" class="form-control" placeholder="Ej. Aula 204">
          </div>
          <div class="form-group">
            <label>Instructor</label>
            <select class="form-control">
              <option value="">Seleccione...</option>
              <?php foreach ($instructores as $instructor): ?>
                <option value="<?= htmlspecialchars($instructor['id']) ?>"><?= htmlspecialchars($instructor['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
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

<!-- Modal: confirmar eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirmar eliminación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        ¿Está seguro de que desea eliminar este horario? <em>(Acción deshabilitada en el prototipo)</em>
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