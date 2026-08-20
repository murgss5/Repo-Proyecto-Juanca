<?php
/**
 * ============================================================
 * VISTA: NUEVA FICHA (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. El registro no se guarda en la base
 * de datos (prototipo).
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Nueva ficha';
$menuActivo   = 'fichas-nueva';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Fichas', 'url' => RUTA_BASE . 'views/admin/fichas/index.php'],
    ['label' => 'Nueva ficha'],
];

ob_start();
?>
<div class="alert alert-warning py-2" role="alert">
  <i class="fas fa-info-circle"></i> Formulario de <strong>demostración</strong>. El guardado real se implementará con el controlador y el modelo correspondiente.
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-layer-group text-sena"></i> Registrar ficha</h3>
  </div>
  <form action="#" method="post" autocomplete="off">
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label for="numero">N° de ficha <span class="text-danger">*</span></label>
            <input type="text" id="numero" name="numero" class="form-control" placeholder="Ej. 2693854" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="jornada">Jornada <span class="text-danger">*</span></label>
            <select id="jornada" name="jornada" class="form-control">
              <option value="Diurna">Diurna</option>
              <option value="Nocturna">Nocturna</option>
              <option value="Mixta">Mixta</option>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="instructor">Instructor <span class="text-danger">*</span></label>
            <select id="instructor" name="instructor" class="form-control">
              <option value="">Seleccione...</option>
              <?php foreach ($instructores as $instructor): ?>
                <option value="<?= htmlspecialchars($instructor['id']) ?>"><?= htmlspecialchars($instructor['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label for="programa">Programa de formación <span class="text-danger">*</span></label>
        <input type="text" id="programa" name="programa" class="form-control"
               placeholder="Ej. Tecnólogo en Análisis y Desarrollo de Software" required>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="fecha_inicio">Fecha de inicio</label>
            <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control">
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label for="fecha_fin">Fecha de finalización</label>
            <input type="date" id="fecha_fin" name="fecha_fin" class="form-control">
          </div>
        </div>
      </div>
    </div>

    <div class="card-footer text-right">
      <a href="<?= RUTA_BASE ?>views/admin/fichas/index.php" class="btn btn-secondary">
        <i class="fas fa-times"></i> Cancelar
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Guardar ficha
      </button>
    </div>
  </form>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';