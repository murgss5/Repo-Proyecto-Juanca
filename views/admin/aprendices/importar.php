<?php
/**
 * Vista de administración: Importación de Aprendices
 * Sistema de Control de Ingreso de Aprendices - SENA
 */

require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../../models/FichaModel.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Variables para el layout
$paginaTitulo = 'Importación de Aprendices';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => (defined('RUTA_BASE') ? RUTA_BASE : '/proyecto_juanca/') . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices', 'url' => (defined('RUTA_BASE') ? RUTA_BASE : '/proyecto_juanca/') . 'views/admin/aprendices/index.php'],
    ['label' => 'Importación de Aprendices']
];

// Obtener fichas activas mediante el modelo
$fichas = FichaModel::obtenerFichasActivas();

// Inclusión de la estructura base del layout
include __DIR__ . '/../../layouts/header.php';
include __DIR__ . '/../../layouts/navbar.php';
include __DIR__ . '/../../layouts/sidebar.php';
?>

<!-- Contenido principal -->
<div class="content-wrapper">
  <!-- Cabecera de contenido (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 text-dark font-weight-bold">
            <i class="fas fa-file-excel text-success mr-2"></i><?= htmlspecialchars($paginaTitulo) ?>
          </h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <?php foreach ($miga as $indice => $migaItem): ?>
              <li class="breadcrumb-item <?= ($indice === count($miga) - 1) ? 'active' : '' ?>">
                <?php if (isset($migaItem['url']) && $indice < count($miga) - 1): ?>
                  <a href="<?= $migaItem['url'] ?>"><?= htmlspecialchars($migaItem['label']) ?></a>
                <?php else: ?>
                  <?= htmlspecialchars($migaItem['label']) ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- Contenido de la vista -->
  <section class="content">
    <div class="container-fluid">

      <!-- Contenedor principal con estándar visual Enterprise -->
      <div class="card card-outline card-success shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title font-weight-bold text-dark m-0">
            <i class="fas fa-users-cog text-success mr-2"></i> Importación de Aprendices
          </h3>
          <div class="card-tools ml-auto d-flex align-items-center">
            <button type="button" class="btn btn-warning btn-sm mr-2 shadow-sm font-weight-bold" data-toggle="modal" data-target="#modalTestCorreo">
              <i class="fas fa-envelope mr-1"></i> Probar SMTP
            </button>
            <a href="index.php" class="btn btn-secondary btn-sm shadow-sm">
              <i class="fas fa-arrow-left mr-1"></i> Volver al listado
            </a>
          </div>
        </div>

        <div class="card-body py-4">
          <div class="row">
            <div class="col-md-8 col-lg-6 mx-auto">
              <div class="p-4 border rounded bg-white shadow-sm">
                <form id="formImportarAprendices" enctype="multipart/form-data" method="post" action="<?= (defined('RUTA_BASE') ? RUTA_BASE : '/proyecto_juanca/') ?>controllers/admin/importar_aprendices_excel.php">
                  <?php if (!empty($_SESSION['token_csrf'])): ?>
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                  <?php endif; ?>

                  <div class="form-group mb-4">
                    <label for="fk_ficha" class="font-weight-bold text-dark">
                      <i class="fas fa-layer-group text-success mr-1"></i> Ficha de Destino <span class="text-danger">*</span>
                    </label>
                    <select id="fk_ficha" name="fk_ficha" class="form-control custom-select" required>
                      <option value="">-- Seleccione una ficha activa --</option>
                      <?php foreach ($fichas as $ficha): ?>
                        <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>">
                          Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?> &mdash; <?= htmlspecialchars($ficha['nombre_programa']) ?> (<?= htmlspecialchars($ficha['jornada'] ?? 'Jornada no asignada') ?>)
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">
                      Seleccione la ficha técnica a la cual se asignará el grupo de aprendices importados.
                    </small>
                  </div>

                  <div class="form-group mb-4">
                    <label for="archivo_excel" class="font-weight-bold text-dark">
                      <i class="fas fa-file-excel text-success mr-1"></i> Archivo Excel (.xlsx, .xls) <span class="text-danger">*</span>
                    </label>
                    <input type="file" id="archivo_excel" name="archivo_excel" class="form-control" accept=".xlsx, .xls" required>
                    <small class="form-text text-muted">
                      Seleccione el archivo en formato Microsoft Excel (.xlsx o .xls).
                    </small>
                  </div>

                  <div class="mt-4 pt-2">
                    <button type="submit" class="btn btn-success btn-lg w-100 font-weight-bold shadow-sm">
                      <i class="fas fa-upload mr-2"></i> Procesar Archivo Excel
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de Prueba de Envío SMTP -->
      <div class="modal fade" id="modalTestCorreo" tabindex="-1" role="dialog" aria-labelledby="modalTestCorreoLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
          <div class="modal-content shadow">
            <div class="modal-header bg-warning">
              <h5 class="modal-title font-weight-bold text-dark" id="modalTestCorreoLabel">
                <i class="fas fa-envelope mr-2"></i> Prueba de Envío de Credenciales
              </h5>
              <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <form id="formTestCorreo" method="post" action="<?= (defined('RUTA_BASE') ? RUTA_BASE : '/proyecto_juanca/') ?>controllers/admin/probar_correo.php">
              <div class="modal-body py-4">
                <p class="text-muted small mb-3">
                  Permite simular el envío de credenciales mediante PHPMailer sin modificar la base de datos ni alterar los registros de aprendices.
                </p>
                <div class="form-group mb-3">
                  <label for="test_documento" class="font-weight-bold text-dark">
                    <i class="fas fa-id-card text-muted mr-1"></i> Documento (CC) <span class="text-danger">*</span>
                  </label>
                  <input type="text" id="test_documento" name="documento" class="form-control" placeholder="Ej. 1020304050" required>
                </div>
                <div class="form-group mb-2">
                  <label for="test_correo" class="font-weight-bold text-dark">
                    <i class="fas fa-at text-muted mr-1"></i> Correo Electrónico de Destino <span class="text-danger">*</span>
                  </label>
                  <input type="email" id="test_correo" name="correo" class="form-control" placeholder="ejemplo@correo.com" required>
                </div>
              </div>
              <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                  <i class="fas fa-times mr-1"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-warning font-weight-bold shadow-sm">
                  <i class="fas fa-paper-plane mr-1"></i> Enviar Correo de Prueba
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

    </div>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- 1. Gestión de Formulario de Importación Excel ---
    const form = document.getElementById('formImportarAprendices');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('archivo_excel');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Archivo requerido',
                    text: 'Por favor seleccione un archivo Excel (.xlsx o .xls).',
                    confirmButtonColor: '#28a745'
                });
                return;
            }

            const fichaSelect = document.getElementById('fk_ficha');
            if (!fichaSelect || !fichaSelect.value) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ficha requerida',
                    text: 'Por favor seleccione la ficha de destino.',
                    confirmButtonColor: '#28a745'
                });
                return;
            }

            // Alerta de carga mientras el servidor procesa el archivo
            Swal.fire({
                title: 'Procesando archivo...',
                text: 'Por favor espere mientras se valida e importan los aprendices.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData(form);
            const url = form.getAttribute('action') || '<?= (defined("RUTA_BASE") ? RUTA_BASE : "/proyecto_juanca/") ?>controllers/admin/importar_aprendices_excel.php';

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor (HTTP ' + response.status + ')');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    let detalleHtml = `
                        <div style="text-align: left; font-size: 1.05em; color: #333;">
                            <div class="mb-2">
                                <i class="fas fa-check-circle text-success mr-2"></i> <strong>Nuevos registros:</strong> ${data.importados}
                            </div>
                            <div>
                                <i class="fas fa-exclamation-triangle text-warning mr-2"></i> <strong>Omitidos (Duplicados):</strong> ${data.duplicados}
                            </div>
                    `;

                    if (data.duplicados > 0 && data.detalle_omitidos && data.detalle_omitidos.length > 0) {
                        detalleHtml += `
                            <div class="mt-3 p-3 bg-light border rounded" style="font-size: 0.85em; max-height: 120px; overflow-y: auto;">
                                <div class="text-muted mb-1"><i class="fas fa-info-circle mr-1"></i> No se modificaron para proteger su ficha actual:</div>
                                <ul class="mb-0 pl-3 text-muted">
                                    ${data.detalle_omitidos.map(item => `<li>${item}</li>`).join('')}
                                </ul>
                            </div>
                        `;
                    }

                    detalleHtml += `</div>`;

                    const iconoSwal = data.importados > 0 ? 'success' : 'warning';
                    const tituloSwal = data.importados > 0 ? 'Importación Exitosa' : 'Proceso Finalizado';

                    Swal.fire({
                        icon: iconoSwal,
                        title: tituloSwal,
                        html: detalleHtml,
                        confirmButtonText: '<i class="fas fa-check mr-1"></i> Ir al listado',
                        confirmButtonColor: '#28a745',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en la importación',
                        text: data.message || 'No fue posible importar los aprendices.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de comunicación',
                    text: error.message || 'Ocurrió un error inesperado al procesar la solicitud.',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    }

    // --- 2. Gestión de Prueba de Envío SMTP (Modal) ---
    const formTestCorreo = document.getElementById('formTestCorreo');
    if (formTestCorreo) {
        formTestCorreo.addEventListener('submit', function(e) {
            e.preventDefault();

            const docInput = document.getElementById('test_documento');
            const correoInput = document.getElementById('test_correo');

            if (!docInput || !docInput.value.trim() || !correoInput || !correoInput.value.trim()) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    text: 'Por favor complete el documento y el correo de destino.',
                    confirmButtonColor: '#ffc107'
                });
                return;
            }

            Swal.fire({
                title: 'Enviando correo de prueba...',
                text: 'Por favor espere mientras se establece conexión con el servidor SMTP.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData(formTestCorreo);
            const urlTest = formTestCorreo.getAttribute('action') || '<?= (defined("RUTA_BASE") ? RUTA_BASE : "/proyecto_juanca/") ?>controllers/admin/probar_correo.php';

            fetch(urlTest, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor (HTTP ' + response.status + ')');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Cerrar el modal y resetear campos
                    if (typeof $ !== 'undefined') {
                        $('#modalTestCorreo').modal('hide');
                    }
                    formTestCorreo.reset();

                    Swal.fire({
                        icon: 'success',
                        title: 'Correo Enviado',
                        text: data.message || 'El correo de prueba fue enviado satisfactoriamente.',
                        confirmButtonText: '<i class="fas fa-check mr-1"></i> Aceptar',
                        confirmButtonColor: '#28a745'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fallo en el Envío SMTP',
                        text: data.message || 'No fue posible enviar el correo de prueba.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Comunicación',
                    text: error.message || 'Ocurrió un error inesperado al conectar con el servidor.',
                    confirmButtonColor: '#dc3545'
                });
            });
        });
    }
});
</script>

<?php
include __DIR__ . '/../../layouts/footer.php';
include __DIR__ . '/../../layouts/scripts.php';
?>
