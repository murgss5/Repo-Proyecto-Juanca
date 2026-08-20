<?php
/**
 * ============================================================
 * VISTA: NUEVO APRENDIZ (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. El registro no se guarda en la base
 * de datos (prototipo).
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Nuevo aprendiz';
$menuActivo   = 'aprendices-nuevo';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php'],
    ['label' => 'Nuevo aprendiz'],
];

ob_start();
?>
<div class="alert alert-warning py-2" role="alert">
  <i class="fas fa-info-circle"></i> Formulario de <strong>demostración</strong>. El guardado real se implementará con el controlador y el modelo correspondiente.
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-plus text-sena"></i> Registrar aprendiz</h3>
  </div>
  <form action="#" method="post" autocomplete="off">
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label for="documento">Documento <span class="text-danger">*</span></label>
            <input type="text" id="documento" name="documento" class="form-control" placeholder="Número de identificación" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="nombres">Nombres <span class="text-danger">*</span></label>
            <input type="text" id="nombres" name="nombres" class="form-control" placeholder="Ej. Juan David" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="apellidos">Apellidos <span class="text-danger">*</span></label>
            <input type="text" id="apellidos" name="apellidos" class="form-control" placeholder="Ej. Pérez García" required>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="correo">Correo electrónico <span class="text-danger">*</span></label>
            <input type="email" id="correo" name="correo" class="form-control" placeholder="nombre@misena.edu.co" required>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" class="form-control" placeholder="300 000 0000">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label for="ficha">Ficha <span class="text-danger">*</span></label>
            <select id="ficha" name="ficha" class="form-control" required>
              <option value="">Seleccione...</option>
              <?php foreach ($fichas as $ficha): ?>
                <option value="<?= htmlspecialchars($ficha['numero']) ?>"><?= htmlspecialchars($ficha['numero']) ?> &mdash; <?= htmlspecialchars($ficha['programa']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label for="rfid">Código RFID <span class="text-danger">*</span></label>
            <div class="input-group">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-rss"></i></span>
              </div>
              <input type="text" id="rfid" name="rfid" class="form-control" placeholder="RF-2026-XXX" required>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="estado">Estado <span class="text-danger">*</span></label>
            <select id="estado" name="estado" class="form-control">
              <option value="Activo">Activo</option>
              <option value="Inactivo">Inactivo</option>
              <option value="Suspendido">Suspendido</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card-footer text-right">
      <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="btn btn-secondary">
        <i class="fas fa-times"></i> Cancelar
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save"></i> Guardar aprendiz
      </button>
    </div>
  </form>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
