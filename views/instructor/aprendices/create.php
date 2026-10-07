<?php
/**
 * ============================================================
 * VISTA: REGISTRAR APRENDICES (INSTRUCTOR)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Pantalla principal: Importación masiva por Excel (.xlsx) de Sofia Plus.
 * Botón superior derecho: "Registrar manual" para el registro individual.
 * Estructura directa sin pestañas.
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';
require_once dirname(__DIR__, 3) . '/models/AprendizModel.php';

// Generar token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$idInstructor = (int)$_SESSION['id_usuario'];
$fichasInstructor = AprendizModel::obtenerFichasInstructor($idInstructor);

// Verificar modo de visualización: manual o importación Excel (por defecto)
$modoManual = (isset($_GET['modo']) && $_GET['modo'] === 'manual');

// Variables del layout
if ($modoManual) {
    $paginaTitulo = 'Registrar aprendiz manualmente';
    $menuActivo   = 'registrar-aprendices';
    $miga = [
        ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
        ['label' => 'Registrar aprendices', 'url' => RUTA_BASE . 'views/instructor/aprendices/create.php'],
        ['label' => 'Registrar manual'],
    ];
} else {
    $paginaTitulo = 'Registrar aprendices';
    $menuActivo   = 'registrar-aprendices';
    $miga = [
        ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
        ['label' => 'Registrar aprendices'],
    ];
}

// Verificar si hay previsualización de Excel pendiente en sesión
$previewData = $_SESSION['import_preview_instructor'] ?? null;
$tienePreview = ($previewData !== null && !empty($previewData['filas']));

ob_start();
?>

<?php if (isset($_GET['mensaje'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
  </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($_GET['error']) ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
  </div>
<?php endif; ?>

<?php if ($modoManual): ?>
  <!-- ======================================================== -->
  <!-- FORMULARIO DE REGISTRO MANUAL -->
  <!-- ======================================================== -->
  <div class="card card-outline card-primary shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title font-weight-bold mb-0">
        <i class="fas fa-user-plus text-primary mr-1"></i> Registrar aprendiz manualmente
      </h3>
      <a href="<?= RUTA_BASE ?>views/instructor/aprendices/create.php" class="btn btn-outline-success btn-sm">
        <i class="fas fa-file-excel mr-1"></i> Importación por Excel
      </a>
    </div>

    <div class="card-body">
      <form action="<?= RUTA_BASE ?>controllers/gestionar_aprendiz_instructor.php" method="post">
        <input type="hidden" name="accion" value="registrar_manual">
        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

        <div class="row">
          <div class="col-md-6 form-group">
            <label for="id_ficha_manual">Ficha asignada <span class="text-danger">*</span></label>
            <select name="id_ficha" id="id_ficha_manual" class="form-control" required>
              <option value="">Seleccione una de sus fichas...</option>
              <?php foreach ($fichasInstructor as $f): ?>
                <option value="<?= htmlspecialchars($f['id_ficha']) ?>">
                  Ficha #<?= htmlspecialchars($f['id_ficha']) ?> &mdash; <?= htmlspecialchars($f['nombre_programa']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6 form-group">
            <label for="tipo_documento_manual">Tipo de Documento</label>
            <select name="tipo_documento" id="tipo_documento_manual" class="form-control">
              <option value="">Seleccione (opcional)...</option>
              <option value="CC">Cédula de Ciudadanía (CC)</option>
              <option value="TI">Tarjeta de Identidad (TI)</option>
              <option value="CE">Cédula de Extranjería (CE)</option>
              <option value="PEP">Permiso Especial de Permanencia (PEP)</option>
              <option value="PPT">Permiso por Protección Temporal (PPT)</option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 form-group">
            <label for="numero_documento_manual">Número de Documento</label>
            <input type="text" name="numero_documento" id="numero_documento_manual" class="form-control" placeholder="Ej. 10203040">
          </div>

          <div class="col-md-4 form-group">
            <label for="nombre_manual">Nombre</label>
            <input type="text" name="nombre" id="nombre_manual" class="form-control" placeholder="Ej. Carlos">
          </div>

          <div class="col-md-4 form-group">
            <label for="apellidos_manual">Apellidos</label>
            <input type="text" name="apellidos" id="apellidos_manual" class="form-control" placeholder="Ej. Pérez">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12 form-group">
            <label for="correo_manual">Correo Electrónico</label>
            <input type="email" name="correo" id="correo_manual" class="form-control" placeholder="Ej. aprendiz@correo.com">
          </div>
        </div>

        <div class="text-right mt-3">
          <a href="<?= RUTA_BASE ?>views/instructor/aprendices/create.php" class="btn btn-secondary mr-2">
            <i class="fas fa-arrow-left mr-1"></i> Volver a Importación Excel
          </a>
          <a href="<?= RUTA_BASE ?>views/instructor/aprendices/index.php" class="btn btn-outline-secondary mr-2">
            <i class="fas fa-users mr-1"></i> Volver al listado
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save mr-1"></i> Registrar Aprendiz
          </button>
        </div>
      </form>
    </div>
  </div>

<?php else: ?>

  <!-- ======================================================== -->
  <!-- PANTALLA PRINCIPAL: REGISTRAR APRENDICES (IMPORTACIÓN EXCEL) -->
  <!-- ======================================================== -->
  <div class="card card-outline card-primary shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h3 class="card-title font-weight-bold mb-0">
        <i class="fas fa-file-excel text-success mr-1"></i> Registrar aprendices
      </h3>
      <a href="<?= RUTA_BASE ?>views/instructor/aprendices/create.php?modo=manual" class="btn btn-primary btn-sm">
        <i class="fas fa-user-plus mr-1"></i> Registrar manual
      </a>
    </div>

    <div class="card-body">

      <?php if ($tienePreview): ?>
        <!-- ---------------------------------------------------- -->
        <!-- FASE DE PREVISUALIZACIÓN -->
        <!-- ---------------------------------------------------- -->
        <div class="callout callout-info mb-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
              <h5 class="font-weight-bold text-info mb-1">
                <i class="fas fa-eye mr-1"></i> Vista previa de los aprendices encontrados
              </h5>
              <p class="mb-0 text-muted">
                Ficha de destino: <strong>#<?= htmlspecialchars($previewData['id_ficha']) ?> &mdash; <?= htmlspecialchars($previewData['nombre_programa'] ?? '') ?></strong>
              </p>
            </div>
            <div class="mt-2 mt-md-0">
              <span class="badge badge-info p-2 mr-1">Total: <?= (int)$previewData['total'] ?></span>
              <span class="badge badge-success p-2 mr-1">Nuevos a registrar: <?= (int)$previewData['nuevos'] ?></span>
              <?php if ((int)$previewData['duplicados'] > 0): ?>
                <span class="badge badge-warning p-2">Existentes (se omitirán): <?= (int)$previewData['duplicados'] ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="table-responsive mb-4" style="max-height: 420px; overflow-y: auto;">
          <table class="table table-bordered table-striped table-hover text-nowrap">
            <thead class="thead-light">
              <tr>
                <th style="width: 50px;">#</th>
                <th>Tipo de Documento</th>
                <th>Número de Documento</th>
                <th>Nombre</th>
                <th>Apellidos</th>
                <th>Celular</th>
                <th>Correo Electrónico</th>
                <th>Estado</th>
                <th>Validación</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($previewData['filas'] as $idx => $fila): ?>
                <tr class="<?= !empty($fila['es_duplicado']) ? 'table-warning' : '' ?>">
                  <td><?= $idx + 1 ?></td>
                  <td><?= htmlspecialchars($fila['tipo_documento'] ?? '') ?></td>
                  <td class="font-weight-bold"><?= htmlspecialchars($fila['numero_documento'] ?? '') ?></td>
                  <td><?= htmlspecialchars($fila['nombre'] ?? '') ?></td>
                  <td><?= htmlspecialchars($fila['apellidos'] ?? '') ?></td>
                  <td><?= htmlspecialchars($fila['celular'] ?? '') ?></td>
                  <td><?= htmlspecialchars($fila['correo'] ?? '') ?></td>
                  <td><?= htmlspecialchars($fila['estado'] ?? '') ?></td>
                  <td>
                    <?php if (!empty($fila['es_duplicado'])): ?>
                      <span class="badge badge-warning">
                        <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($fila['observacion'] ?? 'Ya existe') ?>
                      </span>
                    <?php else: ?>
                      <span class="badge badge-success">
                        <i class="fas fa-check-circle mr-1"></i> Listo para importar
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap pt-2 border-top">
          <form action="<?= RUTA_BASE ?>controllers/gestionar_aprendiz_instructor.php" method="post" class="mb-2">
            <input type="hidden" name="accion" value="cancelar_importacion_excel">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
            <button type="submit" class="btn btn-secondary">
              <i class="fas fa-times mr-1"></i> Cancelar operación
            </button>
          </form>

          <form action="<?= RUTA_BASE ?>controllers/gestionar_aprendiz_instructor.php" method="post" class="mb-2">
            <input type="hidden" name="accion" value="confirmar_importar_excel">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
            <button type="submit" class="btn btn-success font-weight-bold">
              <i class="fas fa-check-circle mr-1"></i> Confirmar importación
            </button>
          </form>
        </div>

      <?php else: ?>

        <!-- ---------------------------------------------------- -->
        <!-- 1. FORMULARIO PRINCIPAL: FICHA Y ARCHIVO EXCEL ARRIBA -->
        <!-- ---------------------------------------------------- -->
        <form action="<?= RUTA_BASE ?>controllers/gestionar_aprendiz_instructor.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="accion" value="validar_previsualizar_excel">
          <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

          <div class="row">
            <div class="col-md-6 form-group">
              <label for="id_ficha_excel" class="font-weight-bold">Ficha de destino <span class="text-danger">*</span></label>
              <select name="id_ficha" id="id_ficha_excel" class="form-control" required>
                <option value="">Seleccione una de sus fichas...</option>
                <?php foreach ($fichasInstructor as $f): ?>
                  <option value="<?= htmlspecialchars($f['id_ficha']) ?>">
                    Ficha #<?= htmlspecialchars($f['id_ficha']) ?> &mdash; <?= htmlspecialchars($f['nombre_programa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6 form-group">
              <label for="archivo_excel" class="font-weight-bold">Archivo Excel (.xlsx) <span class="text-danger">*</span></label>
              <div class="input-group">
                <div class="custom-file">
                  <input type="file" name="archivo_excel" id="archivo_excel" class="custom-file-input" accept=".xlsx" required>
                  <label class="custom-file-label" for="archivo_excel" id="archivo_excel_label" data-browse="Elegir archivo">
                    Seleccionar archivo Excel...
                  </label>
                </div>
              </div>
              <div id="nombre_archivo_contenedor" class="mt-2 text-success small font-weight-bold d-none">
                <i class="fas fa-file-excel mr-1"></i> Archivo seleccionado: <span id="nombre_archivo_texto"></span>
              </div>
            </div>
          </div>

          <div class="text-right mt-2 mb-4">
            <a href="<?= RUTA_BASE ?>views/instructor/aprendices/index.php" class="btn btn-secondary mr-2">
              <i class="fas fa-arrow-left mr-1"></i> Volver al listado
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-search mr-1"></i> Validar y previsualizar
            </button>
          </div>
        </form>

        <hr class="my-4">

        <!-- ---------------------------------------------------- -->
        <!-- 2. BLOQUE COMPACTO: FORMATO REQUERIDO (SECUNDARIO) -->
        <!-- ---------------------------------------------------- -->
        <div class="card card-outline card-info shadow-none bg-light mb-0" style="border-width: 1px;">
          <div class="card-header py-2 px-3 d-flex justify-content-between align-items-center bg-white border-bottom">
            <h6 class="card-title text-info font-weight-bold mb-0" style="font-size: 0.95rem;">
              <i class="fas fa-info-circle mr-1"></i> Formato requerido
            </h6>
            <a href="<?= RUTA_BASE ?>controllers/descargar_plantilla_aprendices.php" class="btn btn-outline-success btn-sm py-1 px-2" style="font-size: 0.82rem;">
              <i class="fas fa-download mr-1"></i> Descargar Plantilla Excel (.xlsx)
            </a>
          </div>
          <div class="card-body py-2 px-3">
            <p class="text-muted mb-2 small">
              El archivo debe ser un Excel original (<strong>.xlsx</strong>) 
            </p>
            
            <div class="table-responsive mb-2">
              <table class="table table-sm table-bordered text-center mb-0 bg-white" style="font-size: 0.8rem;">
                <thead class="thead-light">
                  <tr>
                    <th class="py-1 px-2">Tipo de Documento</th>
                    <th class="py-1 px-2">Número de Documento</th>
                    <th class="py-1 px-2">Nombre</th>
                    <th class="py-1 px-2">Apellidos</th>
                    <th class="py-1 px-2">Celular</th>
                    <th class="py-1 px-2">Correo Electrónico</th>
                    <th class="py-1 px-2">Estado</th>
                  </tr>
                </thead>
              </table>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('archivo_excel');
    const fileLabel = document.getElementById('archivo_excel_label');
    const nameContainer = document.getElementById('nombre_archivo_contenedor');
    const nameText = document.getElementById('nombre_archivo_texto');

    if (fileInput) {
      fileInput.addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
          const fileName = e.target.files[0].name;
          if (fileLabel) {
            fileLabel.textContent = fileName;
          }
          if (nameContainer && nameText) {
            nameText.textContent = fileName;
            nameContainer.classList.remove('d-none');
          }
        } else {
          if (fileLabel) {
            fileLabel.textContent = 'Seleccionar archivo Excel...';
          }
          if (nameContainer) {
            nameContainer.classList.add('d-none');
          }
        }
      });
    }
  });
  </script>

<?php endif; ?>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_instructor.php';
