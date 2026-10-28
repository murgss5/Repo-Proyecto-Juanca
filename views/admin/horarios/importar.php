<?php
/**
 * Vista: Importar horarios desde Excel oficial SENA (Procesamiento Matricial)
 * Sistema de Control de Ingreso de Aprendices - SENA
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// Token CSRF
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Variables layout
$paginaTitulo = 'Importar horarios SENA';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios', 'url' => RUTA_BASE . 'views/admin/horarios/index.php'],
    ['label' => 'Importar matriz SENA']
];

ob_start();
?>

<!-- Mensajes flash -->
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show shadow-sm" role="alert">
    <i class="fas <?= $flash['tipo'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
    <?= htmlspecialchars($flash['mensaje']) ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<div class="card card-outline card-success shadow-sm">
    <!-- Encabezado moderno y destacado -->
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-file-excel text-success mr-2"></i> Motor Matricial de Importación de Horarios SENA
        </h3>
        <div class="card-tools ml-auto">
            <a href="<?= RUTA_BASE ?>views/admin/horarios/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Volver a Horarios
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Sección de Guía Informativa del Formato Oficial -->
        <div class="alert alert-success border-0 bg-light shadow-sm mb-4 p-3 rounded">
            <div class="d-flex align-items-center">
                <div class="mr-3 flex-shrink-0">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white shadow-sm" style="width: 50px; height: 50px;">
                        <i class="fas fa-calendar-check fa-lg"></i>
                    </span>
                </div>
                <div>
                    <h6 class="font-weight-bold mb-1 text-dark">
                        <i class="fas fa-info-circle text-success mr-1"></i> Formato Oficial de Horarios SENA
                    </h6>
                    <p class="mb-0 text-muted small">
                        El motor analiza la matriz oficial de programación: identifica automáticamente el código de ficha, mapea los días y franjas de clase, asigna instructores y ambientes, y <strong>consolida de forma inteligente las sesiones continuas</strong> para optimizar el calendario de formación.
                    </p>
                </div>
            </div>
        </div>

        <!-- Formulario Principal de Subida -->
        <form id="formImportarHorario" method="post" action="<?= RUTA_BASE ?>controllers/importar_horarios_excel.php" enctype="multipart/form-data">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

            <!-- Selector de Archivo Estilizado (File Upload Moderno) -->
            <div class="upload-dropzone p-4 text-center rounded position-relative mb-4" id="dropzoneUpload" style="border: 2px dashed #28a745 !important; background-color: #fafdf9; transition: all 0.25s ease;">
                <input type="file" class="custom-file-input position-absolute w-100 h-100" id="archivo_excel" name="archivo_excel" accept=".xlsx" required style="top: 0; left: 0; opacity: 0; cursor: pointer; z-index: 5;">
                
                <!-- Prompt Inicial de Carga -->
                <div class="upload-prompt py-3" id="dropzonePrompt">
                    <div class="mb-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white shadow-sm" style="width: 62px; height: 62px;">
                            <i class="fas fa-file-excel fa-2x"></i>
                        </span>
                    </div>
                    <h5 class="font-weight-bold text-dark mb-1">Arrastre su archivo Excel aquí o haga clic para examinar</h5>
                    <p class="text-muted small mb-3">Compatible con matrices oficiales SENA (formato <code>.xlsx</code>)</p>
                    <span class="btn btn-outline-success btn-sm font-weight-bold px-3 shadow-sm">
                        <i class="fas fa-folder-open mr-1"></i> Examinar archivo
                    </span>
                </div>

                <!-- Tarjeta de Previsualización del Archivo Seleccionado -->
                <div class="file-preview-card d-none p-3 bg-white rounded border border-success align-items-center justify-content-between shadow-sm mt-2 mx-auto" id="filePreviewCard" style="max-width: 520px;">
                    <div class="d-flex align-items-center text-left">
                        <div class="mr-3">
                            <span class="d-inline-flex align-items-center justify-content-center rounded bg-success text-white" style="width: 44px; height: 44px;">
                                <i class="fas fa-file-excel fa-lg"></i>
                            </span>
                        </div>
                        <div>
                            <div class="font-weight-bold text-dark text-truncate" id="previewNombre" style="max-width: 320px;">archivo.xlsx</div>
                            <div class="text-muted small" id="previewTamano">0 KB</div>
                        </div>
                    </div>
                    <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i> Listo para procesar</span>
                </div>
            </div>

            <!-- Botones de Acción Alineados -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mt-3 pt-3 border-top">
                <a href="<?= RUTA_BASE ?>views/admin/horarios/index.php" class="btn btn-outline-secondary px-3 shadow-sm mb-2 mb-sm-0">
                    <i class="fas fa-arrow-left mr-1"></i> Volver al listado
                </a>
                <button type="submit" id="btnEnviar" class="btn btn-success font-weight-bold px-4 shadow-sm">
                    <i class="fas fa-cogs mr-1"></i> Procesar e importar horario SENA
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL 1: Ficha Faltante (Creación Rápida) -->
<div class="modal fade" id="modalFichaFaltante" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="modalFichaLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modalFichaLabel">
                    <i class="fas fa-folder-plus mr-2"></i> Registrar Ficha Faltante
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formFichaFaltante">
                <div class="modal-body py-4 px-4">
                    <div class="alert alert-light border border-success py-2 mb-3">
                        <i class="fas fa-info-circle text-success mr-1"></i>
                        La ficha detectada en la matriz no está registrada en el sistema. Complete los datos del programa para crearla dinámicamente y continuar:
                    </div>

                    <div class="form-group mb-3">
                        <label for="inputNumeroFicha" class="font-weight-bold text-dark">Número de Ficha</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                            </div>
                            <input type="text" class="form-control bg-light font-weight-bold" id="inputNumeroFicha" name="numero_ficha" readonly>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="inputNombrePrograma" class="font-weight-bold text-dark">Nombre del Programa de Formación <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                            </div>
                            <input type="text" class="form-control" id="inputNombrePrograma" name="programa" required placeholder="Ej: ANÁLISIS Y DESARROLLO DE SOFTWARE">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label for="selectJornadaFicha" class="font-weight-bold text-dark">Jornada <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-sun"></i></span>
                            </div>
                            <select class="form-control" id="selectJornadaFicha" name="jornada" required>
                                <option value="Mañana">Mañana</option>
                                <option value="Tarde">Tarde</option>
                                <option value="Noche">Noche</option>
                                <option value="Mixta">Mixta</option>
                                <option value="Fines de semana">Fines de semana</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-3">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" id="btnCrearFichaRapida" class="btn btn-success font-weight-bold shadow-sm">
                        <i class="fas fa-plus-circle mr-1"></i> Crear ficha y continuar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL 2: Instructores no registrados (Paso 2) -->
<div class="modal fade" id="modalInstructoresFaltantes" data-backdrop="static" data-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="modalInstructoresLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning py-3">
                <h5 class="modal-title font-weight-bold text-dark" id="modalInstructoresLabel">
                    <i class="fas fa-user-plus mr-2"></i> Instructores no registrados detectados
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formInstructoresFaltantes">
                <div class="modal-body py-4 px-4">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        Se detectaron los siguientes instructores en la matriz de la <strong id="modalFichaTitulo">ficha</strong>. 
                        Complete sus datos para registrarlos en el sistema y continuar automáticamente con la importación:
                    </div>

                    <div id="contenedorInstructoresFaltantes">
                        <!-- Inyección dinámica de instructores faltantes -->
                    </div>
                </div>
                <div class="modal-footer bg-light border-top py-3">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="button" id="btnGuardarInstructores" class="btn btn-success font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i> Registrar y continuar importación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('archivo_excel');
    const dropzone = document.getElementById('dropzoneUpload');
    const dropzonePrompt = document.getElementById('dropzonePrompt');
    const filePreviewCard = document.getElementById('filePreviewCard');
    const previewNombre = document.getElementById('previewNombre');
    const previewTamano = document.getElementById('previewTamano');

    // Función auxiliar para formatear tamaño de archivo
    function formatBytes(bytes) {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Actualizar vista previa al seleccionar archivo
    function actualizarPreviewArchivo(archivo) {
        if (archivo) {
            previewNombre.textContent = archivo.name;
            previewTamano.textContent = formatBytes(archivo.size);
            dropzonePrompt.classList.add('d-none');
            filePreviewCard.classList.remove('d-none');
            filePreviewCard.classList.add('d-flex');
            dropzone.style.backgroundColor = '#f0fdf4';
            dropzone.style.borderColor = '#28a745';
        } else {
            dropzonePrompt.classList.remove('d-none');
            filePreviewCard.classList.add('d-none');
            filePreviewCard.classList.remove('d-flex');
            dropzone.style.backgroundColor = '#fafdf9';
        }
    }

    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            actualizarPreviewArchivo(e.target.files[0] || null);
        });

        // Efectos drag and drop
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.style.backgroundColor = '#eaf6e3';
                dropzone.style.borderColor = '#28a745';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!fileInput.files.length) {
                    dropzone.style.backgroundColor = '#fafdf9';
                }
            }, false);
        });

        dropzone.addEventListener('drop', function(e) {
            if (e.dataTransfer && e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                actualizarPreviewArchivo(e.dataTransfer.files[0]);
            }
        });
    }

    const form = document.getElementById('formImportarHorario');
    const btnEnviar = document.getElementById('btnEnviar');
    const modalFicha = $('#modalFichaFaltante');
    const formFicha = document.getElementById('formFichaFaltante');
    const btnCrearFicha = document.getElementById('btnCrearFichaRapida');

    const modalInstructores = $('#modalInstructoresFaltantes');
    const contenedorInstructores = document.getElementById('contenedorInstructoresFaltantes');
    const btnGuardarInstructores = document.getElementById('btnGuardarInstructores');

    if (!form) return;

    // Función principal para enviar y procesar el archivo Excel
    function enviarArchivoExcel() {
        if (!fileInput.files.length) {
            Swal.fire({
                icon: 'warning',
                title: 'Archivo requerido',
                text: 'Por favor seleccione un archivo Excel (.xlsx) antes de continuar.',
                confirmButtonColor: '#28a745'
            });
            return;
        }

        const formData = new FormData(form);
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Procesando matriz SENA...';

        Swal.fire({
            title: 'Analizando horario SENA',
            text: 'Extrayendo ficha, instructores y agrupando bloques de clase...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = '<i class="fas fa-cogs mr-1"></i> Procesar e importar horario SENA';

            // 1. Detección de Ficha Faltante -> Abrir modal de creación rápida
            if (data.requiere_ficha) {
                Swal.close();
                document.getElementById('inputNumeroFicha').value = data.numero_ficha || '';
                document.getElementById('inputNombrePrograma').value = data.programa_sugerido || '';
                document.getElementById('selectJornadaFicha').value = data.jornada_sugerida || 'Mañana';
                modalFicha.modal('show');
                return;
            }

            // 2. Detección de Instructores Faltantes -> Abrir modal de instructores
            if (!data.success && data.requiere_instructores) {
                Swal.close();
                contenedorInstructores.innerHTML = '';
                document.getElementById('modalFichaTitulo').textContent = 'Ficha #' + data.ficha.id_ficha + ' (' + data.ficha.nombre_programa + ')';

                data.instructores_faltantes.forEach((nombre, idx) => {
                    const card = document.createElement('div');
                    card.className = 'card border mb-3 shadow-sm';
                    card.innerHTML = `
                        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold text-dark">
                                <i class="fas fa-user-tie text-success mr-2"></i> ${escapeHtml(nombre)}
                            </span>
                            <span class="badge badge-warning text-dark"><i class="fas fa-exclamation-triangle mr-1"></i> No registrado</span>
                            <input type="hidden" name="instructores[${idx}][nombre_excel]" value="${escapeHtml(nombre)}">
                        </div>
                        <div class="card-body py-3">
                            <div class="form-row">
                                <div class="form-group col-md-4 mb-2">
                                    <label class="small font-weight-bold">Número de Cédula <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="instructores[${idx}][identificacion]" required placeholder="Ej: 1098765432" autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-group col-md-5 mb-2">
                                    <label class="small font-weight-bold">Correo Electrónico <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" name="instructores[${idx}][correo_electronico]" required placeholder="instructor@sena.edu.co" autocomplete="off">
                                    </div>
                                </div>
                                <div class="form-group col-md-3 mb-2">
                                    <label class="small font-weight-bold">Teléfono (opcional)</label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="instructores[${idx}][telefono]" placeholder="3001234567" autocomplete="off">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    contenedorInstructores.appendChild(card);
                });

                modalInstructores.modal('show');
                return;
            }

            // 3. Importación Exitosa
            if (data.success) {
                mostrarResultadoExitoso(data);
                return;
            }

            // Error no contemplado
            Swal.fire({
                icon: 'error',
                title: 'Error en la importación',
                text: data.error || 'Ocurrió un error inesperado al procesar el archivo.',
                confirmButtonColor: '#28a745'
            });
        })
        .catch(err => {
            btnEnviar.disabled = false;
            btnEnviar.innerHTML = '<i class="fas fa-cogs mr-1"></i> Procesar e importar horario SENA';
            Swal.fire({
                icon: 'error',
                title: 'Error de comunicación',
                text: 'No se pudo conectar con el servidor: ' + err.message,
                confirmButtonColor: '#28a745'
            });
        });
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        enviarArchivoExcel();
    });

    // Envío del Modal 1: Creación Rápida de Ficha
    formFicha.addEventListener('submit', function(e) {
        e.preventDefault();

        const numeroFicha = document.getElementById('inputNumeroFicha').value.trim();
        const programa = document.getElementById('inputNombrePrograma').value.trim();
        const jornada = document.getElementById('selectJornadaFicha').value;
        const tokenCsrf = document.querySelector('input[name="token_csrf"]').value;

        if (!numeroFicha || !programa || !jornada) {
            Swal.fire({
                icon: 'warning',
                title: 'Campos requeridos',
                text: 'Complete todos los campos del formulario.',
                confirmButtonColor: '#28a745'
            });
            return;
        }

        btnCrearFicha.disabled = true;
        btnCrearFicha.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Creando ficha...';

        const formDataFicha = new FormData();
        formDataFicha.append('crear_ficha_rapida', '1');
        formDataFicha.append('numero_ficha', numeroFicha);
        formDataFicha.append('programa', programa);
        formDataFicha.append('jornada', jornada);
        formDataFicha.append('token_csrf', tokenCsrf);

        fetch(form.action, {
            method: 'POST',
            body: formDataFicha,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(res => {
            btnCrearFicha.disabled = false;
            btnCrearFicha.innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Crear ficha y continuar';

            if (res.success) {
                modalFicha.modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Ficha creada',
                    text: 'Ficha #' + numeroFicha + ' registrada exitosamente. Continuando importación...',
                    timer: 1400,
                    showConfirmButton: false
                });

                setTimeout(() => {
                    enviarArchivoExcel();
                }, 1000);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al crear la ficha',
                    text: res.error || 'No se pudo crear la ficha en el sistema.',
                    confirmButtonColor: '#28a745'
                });
            }
        })
        .catch(err => {
            btnCrearFicha.disabled = false;
            btnCrearFicha.innerHTML = '<i class="fas fa-plus-circle mr-1"></i> Crear ficha y continuar';
            Swal.fire({
                icon: 'error',
                title: 'Error de comunicación',
                text: 'No se pudo conectar con el servidor: ' + err.message,
                confirmButtonColor: '#28a745'
            });
        });
    });

    // Envío del Modal 2: Registrar instructores y finalizar importación
    btnGuardarInstructores.addEventListener('click', function() {
        const inputsRequeridos = contenedorInstructores.querySelectorAll('input[required]');
        for (let inp of inputsRequeridos) {
            if (!inp.value.trim()) {
                inp.focus();
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos obligatorios',
                    text: 'Por favor complete el documento y correo de todos los instructores detectados.',
                    confirmButtonColor: '#28a745'
                });
                return;
            }
        }

        const formModal = document.getElementById('formInstructoresFaltantes');
        const formDataModal = new FormData(formModal);
        formDataModal.append('token_csrf', document.querySelector('input[name="token_csrf"]').value);

        if (fileInput.files.length) {
            formDataModal.append('archivo_excel', fileInput.files[0]);
        }

        btnGuardarInstructores.disabled = true;
        btnGuardarInstructores.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...';

        Swal.fire({
            title: 'Completando importación',
            text: 'Registrando instructores y guardando bloques de horarios...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        fetch(form.action, {
            method: 'POST',
            body: formDataModal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            btnGuardarInstructores.disabled = false;
            btnGuardarInstructores.innerHTML = '<i class="fas fa-save mr-1"></i> Registrar y continuar importación';

            if (data.success) {
                modalInstructores.modal('hide');
                mostrarResultadoExitoso(data);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al finalizar importación',
                    text: data.error || 'Ocurrió un error al registrar los instructores y horarios.',
                    confirmButtonColor: '#28a745'
                });
            }
        })
        .catch(err => {
            btnGuardarInstructores.disabled = false;
            btnGuardarInstructores.innerHTML = '<i class="fas fa-save mr-1"></i> Registrar y continuar importación';
            Swal.fire({
                icon: 'error',
                title: 'Error de comunicación',
                text: 'No se pudo conectar con el servidor: ' + err.message,
                confirmButtonColor: '#28a745'
            });
        });
    });

    function mostrarResultadoExitoso(data) {
        let detalleHtml = '';
        if (data.bloques && data.bloques.length > 0) {
            detalleHtml = `
                <div class="table-responsive text-left mt-3" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm table-bordered table-striped small">
                        <thead class="thead-light">
                            <tr>
                                <th>Día</th>
                                <th>Horario</th>
                                <th>Instructor</th>
                                <th>Ambiente</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.bloques.map(b => `
                                <tr>
                                    <td><strong>${escapeHtml(b.dia)}</strong></td>
                                    <td>${escapeHtml(b.inicio)} - ${escapeHtml(b.fin)}</td>
                                    <td>${escapeHtml(b.instructor)}</td>
                                    <td>${escapeHtml(b.ambiente)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        Swal.fire({
            icon: 'success',
            title: '¡Importación Matricial Exitosa!',
            html: `
                <p class="mb-2 font-weight-bold text-success">${escapeHtml(data.mensaje)}</p>
                <div class="text-left bg-light p-3 rounded small border mb-2">
                    <div><strong>Ficha destino:</strong> #${data.ficha}</div>
                    <div><strong>Bloques consolidados guardados:</strong> ${data.insertados}</div>
                    ${data.duplicados > 0 ? `<div><strong>Bloques existentes omitidos:</strong> ${data.duplicados}</div>` : ''}
                </div>
                ${detalleHtml}
            `,
            confirmButtonText: '<i class="fas fa-calendar-check mr-1"></i> Ir a Horarios de la Ficha',
            confirmButtonColor: '#28a745',
            showCancelButton: true,
            cancelButtonText: 'Importar otro archivo',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?= RUTA_BASE ?>views/admin/horarios/index.php';
            } else {
                form.reset();
                actualizarPreviewArchivo(null);
            }
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
});
</script>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';
