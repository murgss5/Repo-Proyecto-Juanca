<?php
/**
 * ============================================================
 * VISTA: REGISTRAR RFID (INSTRUCTOR) - FLUJO POST-IMPORTACIÓN
 * Sistema de Control de Ingreso de Aprendices - SENA
 * =====
 * =======================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$colaRfid = $_SESSION['rfid_importacion_pendiente'] ?? null;

// Validar existencia, pertenencia y caducidad de la cola
if (!$colaRfid ||
    !isset($colaRfid['aprendices']) ||
    count($colaRfid['aprendices']) === 0 ||
    $colaRfid['id_instructor'] !== (int)($_SESSION['instructor']['id_usuario'] ?? 0) ||
    $colaRfid['expires_at'] < time()
) {
    unset($_SESSION['rfid_importacion_pendiente']);
    header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?mensaje=' . urlencode('No hay una cola de importación activa o esta ha caducado.'));
    exit;
}

$idFicha = $colaRfid['id_ficha'];
$aprendices = array_values($colaRfid['aprendices']); // Reindexar por si acaso
$total = count($aprendices);

// Variables del layout
$paginaTitulo = 'Registro de Códigos RFID';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Aprendices de mis fichas', 'url' => RUTA_BASE . 'views/instructor/aprendices/index.php'],
    ['label' => 'Registro RFID Post-Importación'],
];

ob_start();
?>

<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card card-outline card-success shadow">
      <div class="card-header">
        <h3 class="card-title font-weight-bold">
          <i class="fas fa-rss text-success mr-1"></i> Asignación de Códigos RFID
        </h3>
      </div>
      
      <div class="card-body">
        <div class="alert alert-info">
          <h5><i class="icon fas fa-info"></i> Importación exitosa</h5>
          Se han creado <strong><?= $total ?></strong> aprendices nuevos para la Ficha #<?= htmlspecialchars((string)$idFicha, ENT_QUOTES, 'UTF-8') ?>.<br>
          Por favor, acerque la tarjeta al lector para registrar el código de cada uno de ellos.
        </div>
        
        <p class="text-center mt-4">
          <button type="button" class="btn btn-success btn-lg" id="btnIniciarRfid">
            <i class="fas fa-play-circle mr-1"></i> Iniciar lectura RFID
          </button>
        </p>
      </div>

      <div class="card-footer text-right">
        <a href="<?= RUTA_BASE ?>views/instructor/aprendices/index.php" class="btn btn-secondary">
          <i class="fas fa-clock mr-1"></i> Finalizar más tarde
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Registro RFID -->
<div class="modal fade" id="modalRegistroRfid" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success">
        <h5 class="modal-title text-white"><i class="fas fa-rss"></i> Registrar código RFID</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        
        <div id="rfidProgresoContainer" class="alert alert-warning mb-3">
          <strong>Progreso:</strong> <span id="rfidProgresoTexto"></span>
        </div>

        <div id="rfidErrorContainer" class="alert alert-danger mb-3" style="display: none;"></div>
        <div id="rfidExitoContainer" class="alert alert-success mb-3" style="display: none;"></div>

        <p class="mb-1"><strong>Nombre completo:</strong> <span id="rfidNombre"></span></p>
        <p class="mb-1"><strong>Tipo de documento:</strong> <span id="rfidTipoDoc"></span></p>
        <p class="mb-1"><strong>Número de documento:</strong> <span class="badge badge-secondary" id="rfidDoc"></span></p>
        <p class="mb-1"><strong>Ficha:</strong> <span id="rfidFicha"></span></p>
        <p class="mb-2"><strong>Programa:</strong> <span id="rfidPrograma"></span></p>
        <p class="mb-3 text-muted"><small>RFID temporal: <span id="rfidActual"></span></small></p>

        <form id="formRegistroRfid" autocomplete="off" onsubmit="return false;">
          <input type="hidden" id="rfidIdAprendiz">
          <input type="hidden" id="rfidIdFicha">
          
          <div class="form-group">
            <label for="rfidInput">Acerque la tarjeta al lector:</label>
            <input type="text" class="form-control form-control-lg" id="rfidInput" autocomplete="off" placeholder="Esperando lectura...">
          </div>
        </form>

      </div>
      <div class="modal-footer d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar actual</button>
        <button type="button" class="btn btn-success" id="btnRfidGuardar">Guardar RFID</button>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="csrf_token_rfid" value="<?= htmlspecialchars((string)($_SESSION['token_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

<script>
document.addEventListener('DOMContentLoaded', function() {
    let colaPendientes = <?= json_encode($aprendices, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let indiceActual = 0;
    let isSubmitting = false;

    const $modalRfid = $('#modalRegistroRfid');
    const $rfidInput = $('#rfidInput');
    const $btnGuardar = $('#btnRfidGuardar');
    const $errorContainer = $('#rfidErrorContainer');
    const $exitoContainer = $('#rfidExitoContainer');
    
    $('#btnIniciarRfid').on('click', function() {
        if (colaPendientes.length === 0) {
            window.location.href = '<?= RUTA_BASE ?>views/instructor/aprendices/index.php';
            return;
        }
        indiceActual = 0;
        mostrarAprendiz();
        $modalRfid.modal('show');
    });

    function mostrarAprendiz() {
        if (indiceActual >= colaPendientes.length) {
            $modalRfid.modal('hide');
            window.location.href = '<?= RUTA_BASE ?>views/instructor/aprendices/index.php?mensaje=' + encodeURIComponent('Todos los aprendices importados tienen un código RFID registrado correctamente.');
            return;
        }
        
        $errorContainer.hide();
        $exitoContainer.hide();
        
        let aprendiz = colaPendientes[indiceActual];
        
        $('#rfidProgresoTexto').text((indiceActual + 1) + ' de ' + colaPendientes.length);
        
        $('#rfidIdAprendiz').val(aprendiz.id_aprendiz);
        $('#rfidIdFicha').val(aprendiz.id_ficha);
        $('#rfidNombre').text(aprendiz.nombre_completo);
        $('#rfidTipoDoc').text(aprendiz.tipo_documento);
        $('#rfidDoc').text(aprendiz.identificacion);
        $('#rfidFicha').text(aprendiz.id_ficha);
        $('#rfidPrograma').text(aprendiz.programa);
        $('#rfidActual').text(aprendiz.codigo_rfid_temporal);
        
        $rfidInput.val('').prop('disabled', false);
        $btnGuardar.prop('disabled', false).html('Guardar RFID');
        
        setTimeout(() => $rfidInput.focus(), 300);
    }

    $modalRfid.on('shown.bs.modal', function () {
        $rfidInput.focus();
    });

    $('#formRegistroRfid').on('submit', function(e) {
        e.preventDefault();
        guardarRfid();
    });

    $btnGuardar.on('click', function() {
        guardarRfid();
    });

    function guardarRfid() {
        if (isSubmitting) return;
        
        const codigo = $rfidInput.val().trim();
        if (codigo === '') {
            mostrarError('El código no puede estar vacío.');
            $rfidInput.focus();
            return;
        }

        isSubmitting = true;
        $rfidInput.prop('disabled', true);
        $btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
        $errorContainer.hide();
        $exitoContainer.hide();

        const idAprendiz = $('#rfidIdAprendiz').val();
        const idFicha = $('#rfidIdFicha').val();

        $.ajax({
            url: '../../../controllers/ajax/registrar_rfid.php',
            method: 'POST',
            dataType: 'json',
            data: {
                id_aprendiz: idAprendiz,
                id_ficha: idFicha,
                codigo_rfid: codigo,
                token_csrf: $('#csrf_token_rfid').val()
            },
            success: function(response) {
                isSubmitting = false;
                
                if (response.success) {
                    $exitoContainer.text(response.message).show();
                    setTimeout(() => {
                        indiceActual++;
                        mostrarAprendiz();
                    }, 800);
                } else {
                    $btnGuardar.html('Guardar RFID');
                    $rfidInput.prop('disabled', false).val('').focus();
                    mostrarError(response.error);
                }
            },
            error: function() {
                isSubmitting = false;
                $rfidInput.prop('disabled', false).val('').focus();
                $btnGuardar.prop('disabled', false).html('Guardar RFID');
                mostrarError('Error de conexión con el servidor.');
            }
        });
    }

    function mostrarError(mensaje) {
        $errorContainer.text(mensaje).show();
    }
    
    // Auto start
    setTimeout(() => {
        $('#btnIniciarRfid').click();
    }, 500);
});
</script>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_instructor.php';

