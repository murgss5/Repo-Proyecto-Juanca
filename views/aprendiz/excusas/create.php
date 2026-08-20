<?php
/**
 * ============================================================
 * VISTA: SUBIR EXCUSA MÉDICA (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. El archivo no se sube realmente;
 * la carga y el guardado se implementarán más adelante.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Subir excusa médica';
$menuActivo   = 'subir-excusa';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mis excusas', 'url' => RUTA_BASE . 'views/aprendiz/excusas/index.php'],
    ['label' => 'Subir excusa'],
];

ob_start();
?>
<div class="alert alert-warning py-2" role="alert">
  <i class="fas fa-info-circle"></i> Formulario de <strong>demostración</strong>. La carga real del archivo y el guardado se implementarán con el controlador y el modelo correspondiente.
</div>

<div class="row">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-cloud-upload-alt text-sena"></i> Nueva excusa médica</h3>
      </div>
      <form action="#" method="post" enctype="multipart/form-data">
        <div class="card-body">
          <div class="form-group">
            <label for="fecha_inasistencia">Fecha de inasistencia <span class="text-danger">*</span></label>
            <input type="date" id="fecha_inasistencia" name="fecha_inasistencia" class="form-control" required>
            <small class="form-text text-muted">Seleccione la fecha en la que faltó a la formación.</small>
          </div>

          <div class="form-group">
            <label for="archivo">Adjuntar excusa médica <span class="text-danger">*</span></label>
            <div class="custom-file">
              <input type="file" class="custom-file-input" id="archivo" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>
              <label class="custom-file-label" for="archivo">Seleccione un archivo (PDF, JPG o PNG)</label>
            </div>
            <small class="form-text text-muted">Tamaño máximo permitido: 5 MB.</small>
          </div>

          <div class="form-group">
            <label for="observaciones">Observaciones</label>
            <textarea id="observaciones" name="observaciones" class="form-control" rows="3"
                      placeholder="Describa brevemente el motivo de la inasistencia (opcional)..."></textarea>
          </div>
        </div>

        <div class="card-footer text-right">
          <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/index.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancelar
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-cloud-upload-alt"></i> Enviar excusa
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-md-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-info-circle text-sena"></i> ¿Cómo funciona?</h3>
      </div>
      <div class="card-body">
        <ol class="pl-3 small">
          <li>Seleccione la fecha de la inasistencia.</li>
          <li>Adjunte la excusa médica en formato PDF, JPG o PNG.</li>
          <li>Escriba una breve observación si lo desea.</li>
          <li>Envié la solicitud; el instructor podrá aprobarla o rechazarla.</li>
        </ol>
        <div class="alert alert-warning py-2 small mb-0">
          <i class="fas fa-info-circle"></i> En este prototipo visual el botón "Enviar excusa" no realiza la carga real.
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';