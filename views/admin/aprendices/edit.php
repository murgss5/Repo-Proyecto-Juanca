<?php
/**
 * ============================================================
 * VISTA: EDITAR APRENDIZ (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. Los datos se muestran prellenados con
 * información de demostración; la actualización real se
 * implementará con el controlador y el modelo correspondiente.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';

// Aprendiz de demostración (prellenado)
$aprendizDemo = [
    'documento' => '1002345678',
    'nombres'   => 'Juan David',
    'apellidos' => 'Pérez García',
    'correo'    => 'jdperez@misena.edu.co',
    'telefono'  => '3001112233',
    'ficha'     => '2693854',
    'rfid'      => 'RF-2026-001',
    'estado'    => 'Activo',
];

// --- Variables del layout ---
$paginaTitulo = 'Editar aprendiz';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php'],
    ['label' => 'Editar aprendiz'],
];

ob_start();
?>
<div class="alert alert-warning py-2" role="alert">
  <i class="fas fa-info-circle"></i> Formulario de <strong>demostración</strong> con datos prellenados. La actualización real se implementará con el controlador y el modelo correspondiente.
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-user-edit text-sena"></i> Editar aprendiz</h3>
  </div>
  <form action="#" method="post" autocomplete="off">
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="form-group">
            <label for="documento">Documento <span class="text-danger">*</span></label>
            <input type="text" id="documento" name="documento" class="form-control" value="<?= htmlspecialchars($aprendizDemo['documento']) ?>" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="nombres">Nombres <span class="text-danger">*</span></label>
            <input type="text" id="nombres" name="nombres" class="form-control" value="<?= htmlspecialchars($aprendizDemo['nombres']) ?>" required>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="apellidos">Apellidos <span class="text-danger">*</span></label>
            <input type="text" id="apellidos" name="apellidos" class="form-control" value="<?= htmlspecialchars($aprendizDemo['apellidos']) ?>" required>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-md-6">
          <div class="form-group">
            <label for="correo">Correo electrónico <span class="text-danger">*</span></label>
            <input type="email" id="correo" name="correo" class="form-control" value="<?= htmlspecialchars($aprendizDemo['correo']) ?>" required>
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label for="telefono">Teléfono</label>
            <input type="text" id="telefono" name="telefono" class="form-control" value="<?= htmlspecialchars($aprendizDemo['telefono']) ?>">
          </div>
        </div>
        <div class="col-md-3">
          <div class="form-group">
            <label for="ficha">Ficha <span class="text-danger">*</span></label>
            <select id="ficha" name="ficha" class="form-control" required>
              <?php foreach ($fichas as $ficha): ?>
                <option value="<?= htmlspecialchars($ficha['numero']) ?>" <?= $ficha['numero'] === $aprendizDemo['ficha'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ficha['numero']) ?> &mdash; <?= htmlspecialchars($ficha['programa']) ?>
                </option>
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
              <input type="text" id="rfid" name="rfid" class="form-control" value="<?= htmlspecialchars($aprendizDemo['rfid']) ?>" required>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label for="estado">Estado <span class="text-danger">*</span></label>
            <select id="estado" name="estado" class="form-control">
              <?php foreach (['Activo', 'Inactivo', 'Suspendido'] as $opcion): ?>
                <option value="<?= $opcion ?>" <?= $aprendizDemo['estado'] === $opcion ? 'selected' : '' ?>><?= $opcion ?></option>
              <?php endforeach; ?>
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
        <i class="fas fa-save"></i> Actualizar aprendiz
      </button>
    </div>
  </form>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
