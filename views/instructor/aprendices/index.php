<?php
/**
 * ============================================================
 * VISTA: APRENDICES DE MIS FICHAS (INSTRUCTOR)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Lista los aprendices reales de las fichas asignadas al instructor.
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// --- Variables del layout ---
$paginaTitulo = 'Aprendices de mis fichas';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Aprendices de mis fichas'],
];

$conexion = (new Conexion())->conectar();

// Las fichas se consultan por separado para que el filtro muestre incluso las vacías.
$consultaFichas = $conexion->prepare(
    'SELECT id_ficha
     FROM ficha
     WHERE fk_usuario = :id_instructor
     ORDER BY id_ficha'
);
$consultaFichas->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaFichas->execute();
$fichasInstructor = $consultaFichas->fetchAll();

$consultaAprendices = $conexion->prepare(
    'SELECT
        aprendiz.id_aprendiz,
        usuario.tipo_documento,
        usuario.identificacion AS documento,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
        usuario.telefono,
        aprendiz.codigo_rfid,
        ficha.id_ficha,
        ficha.nombre_programa
     FROM aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
     ORDER BY usuario.apellido, usuario.nombre'
);
$consultaAprendices->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaAprendices->execute();
$aprendicesInstructor = $consultaAprendices->fetchAll();

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-users text-sena"></i> Aprendices a cargo</h3>
    <div class="card-tools">
      <button type="button" class="btn btn-success btn-sm" id="btnRfidGlobal">
        <i class="fas fa-rss"></i> Registrar RFID pendientes
      </button>
    </div>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-5 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar aprendiz...">
        </div>
      </div>
      <div class="col-md-4 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="4">
          <option value="">Todas mis fichas</option>
          <?php foreach ($fichasInstructor as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>">Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Nombre completo</th>
            <th>Teléfono</th>
            <th>RFID</th>
            <th>Ficha</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendicesInstructor as $aprendiz): 
                $esTemporal = strpos($aprendiz['codigo_rfid'], 'RFID') === 0;
          ?>
            <tr data-id-aprendiz="<?= htmlspecialchars((string)$aprendiz['id_aprendiz'], ENT_QUOTES, 'UTF-8') ?>">
              <td><?= htmlspecialchars((string)$aprendiz['documento'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)$aprendiz['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string)($aprendiz['telefono'] ?? 'Sin registro'), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="col-rfid">
                <?php if ($esTemporal): ?>
                  <span class="text-muted font-italic">Pendiente</span>
                <?php else: ?>
                  <code><?= htmlspecialchars((string)$aprendiz['codigo_rfid'], ENT_QUOTES, 'UTF-8') ?></code>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-info">Ficha #<?= htmlspecialchars((string)$aprendiz['id_ficha'], ENT_QUOTES, 'UTF-8') ?></span></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <?php if ($esTemporal): ?>
                    <button type="button" class="btn btn-success btn-registrar-rfid-indiv"
                            data-id-aprendiz="<?= htmlspecialchars((string)$aprendiz['id_aprendiz'], ENT_QUOTES, 'UTF-8') ?>"
                            data-nombre="<?= htmlspecialchars((string)$aprendiz['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                            data-tipo-documento="<?= htmlspecialchars((string)($aprendiz['tipo_documento'] ?? 'No especificado'), ENT_QUOTES, 'UTF-8') ?>"
                            data-documento="<?= htmlspecialchars((string)$aprendiz['documento'], ENT_QUOTES, 'UTF-8') ?>"
                            data-ficha="<?= htmlspecialchars((string)$aprendiz['id_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                            data-programa="<?= htmlspecialchars((string)$aprendiz['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                            data-rfid="<?= htmlspecialchars((string)$aprendiz['codigo_rfid'], ENT_QUOTES, 'UTF-8') ?>"
                            title="Registrar RFID"><i class="fas fa-rss"></i> Registrar RFID</button>
                  <?php endif; ?>
                  
                  <button type="button" class="btn btn-info" data-toggle="modal"
                          data-target="#modalVer"
                          data-nombre="<?= htmlspecialchars((string)$aprendiz['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                          data-documento="<?= htmlspecialchars((string)$aprendiz['documento'], ENT_QUOTES, 'UTF-8') ?>"
                          data-telefono="<?= htmlspecialchars((string)($aprendiz['telefono'] ?? 'Sin registro'), ENT_QUOTES, 'UTF-8') ?>"
                          data-rfid="<?= htmlspecialchars((string)$aprendiz['codigo_rfid'], ENT_QUOTES, 'UTF-8') ?>"
                          data-ficha="<?= htmlspecialchars((string)$aprendiz['id_ficha'], ENT_QUOTES, 'UTF-8') ?>"
                          title="Ver"><i class="fas fa-eye"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

// este es el modal para ver los detalles del aprendiz 
<div class="modal fade" id="modalVer" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user text-sena"></i> Detalle del aprendiz</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-1"><i class="fas fa-user text-sena"></i> <strong>Nombre:</strong> <span class="nombre-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-id-card text-sena"></i> <strong>Documento:</strong> <span class="documento-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-phone text-sena"></i> <strong>Teléfono:</strong> <span class="telefono-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Ficha:</strong> Ficha #<span class="ficha-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-rss text-sena"></i> <strong>RFID:</strong> <span class="rfid-aprendiz"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Registrar RFID (Individual y Secuencial) -->
<div class="modal fade" id="modalRegistroRfid" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-success">
        <h5 class="modal-title text-white"><i class="fas fa-rss"></i> Registrar código RFID</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        
        <div id="rfidProgresoContainer" class="alert alert-info mb-3" style="display: none;">
          <strong>Progreso:</strong> <span id="rfidProgresoTexto"></span>
        </div>

        <div id="rfidErrorContainer" class="alert alert-danger mb-3" style="display: none;"></div>

        <p class="mb-1"><strong>Nombre completo:</strong> <span id="rfidNombre"></span></p>
        <p class="mb-1"><strong>Tipo de documento:</strong> <span id="rfidTipoDoc"></span></p>
        <p class="mb-1"><strong>Número de documento:</strong> <span class="badge badge-secondary" id="rfidDoc"></span></p>
        <p class="mb-1"><strong>Ficha:</strong> <span id="rfidFicha"></span></p>
        <p class="mb-2"><strong>Programa:</strong> <span id="rfidPrograma"></span></p>
        <p class="mb-3 text-muted"><small>RFID actual: <span id="rfidActual"></span></small></p>

        <form id="formRegistroRfid" onsubmit="return false;">
          <input type="hidden" id="rfidIdAprendiz">
          <input type="hidden" id="rfidIdFicha">
          
          <div class="form-group">
            <label for="rfidInput">Acérque la tarjeta al lector:</label>
            <input type="text" class="form-control form-control-lg" id="rfidInput" autocomplete="off" placeholder="Esperando lectura...">
          </div>
        </form>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btnRfidCancelar">Cancelar</button>
        <button type="button" class="btn btn-success" id="btnRfidGuardar">Guardar RFID</button>
      </div>
    </div>
  </div>
</div>

<input type="hidden" id="csrf_token_rfid" value="<?= htmlspecialchars((string)($_SESSION['token_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

<script>
document.addEventListener('DOMContentLoaded', function() {
    let colaGlobal = [];
    let indiceGlobal = 0;
    let modoGlobal = false;
    let isSubmitting = false;

    const $modalRfid = $('#modalRegistroRfid');
    const $rfidInput = $('#rfidInput');
    const $btnGuardar = $('#btnRfidGuardar');
    const $errorContainer = $('#rfidErrorContainer');
    const $progresoContainer = $('#rfidProgresoContainer');

    // Inicializar Individual
    $(document).on('click', '.btn-registrar-rfid-indiv', function() {
        modoGlobal = false;
        colaGlobal = [];
        const btn = $(this);
        
        cargarDatosModal({
            id_aprendiz: btn.data('id-aprendiz'),
            nombre_completo: btn.data('nombre'),
            tipo_documento: btn.data('tipo-documento'),
            identificacion: btn.data('documento'),
            id_ficha: btn.data('ficha'),
            programa: btn.data('programa'),
            codigo_rfid_actual: btn.data('rfid')
        });

        $progresoContainer.hide();
        $errorContainer.hide();
        $modalRfid.modal('show');
    });

    // Inicializar Global
    $('#btnRfidGlobal').on('click', function() {
        const selectFicha = $('.filtro-tabla').val();
        if (!selectFicha || selectFicha === '') {
            alert('Seleccione una ficha específica para registrar RFID pendientes.');
            return;
        }

        const btnSelf = $(this);
        btnSelf.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Consultando...');

        $.ajax({
            url: '../../controllers/ajax/listar_rfid_pendientes.php',
            method: 'POST',
            dataType: 'json',
            data: {
                id_ficha: selectFicha,
                token_csrf: $('#csrf_token_rfid').val()
            },
            success: function(response) {
                btnSelf.prop('disabled', false).html('<i class="fas fa-rss"></i> Registrar RFID pendientes');
                if (response.success) {
                    if (response.pendientes.length === 0) {
                        alert('No hay aprendices pendientes de registro RFID en esta ficha.');
                        return;
                    }
                    colaGlobal = response.pendientes;
                    indiceGlobal = 0;
                    modoGlobal = true;
                    mostrarSiguienteGlobal();
                    $modalRfid.modal('show');
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function() {
                btnSelf.prop('disabled', false).html('<i class="fas fa-rss"></i> Registrar RFID pendientes');
                alert('Error de conexión al consultar pendientes.');
            }
        });
    });

    function cargarDatosModal(aprendiz) {
        $('#rfidIdAprendiz').val(aprendiz.id_aprendiz);
        $('#rfidIdFicha').val(aprendiz.id_ficha);
        $('#rfidNombre').text(aprendiz.nombre_completo);
        $('#rfidTipoDoc').text(aprendiz.tipo_documento);
        $('#rfidDoc').text(aprendiz.identificacion);
        $('#rfidFicha').text(aprendiz.id_ficha);
        $('#rfidPrograma').text(aprendiz.programa);
        $('#rfidActual').text(aprendiz.codigo_rfid_actual);
        $rfidInput.val('').prop('disabled', false);
        $btnGuardar.prop('disabled', false);
    }

    function mostrarSiguienteGlobal() {
        if (indiceGlobal >= colaGlobal.length) {
            $modalRfid.modal('hide');
            alert('Registro RFID finalizado. Todos los aprendices pendientes de la ficha seleccionada tienen código asignado.');
            return;
        }
        $errorContainer.hide();
        $progresoContainer.show();
        $('#rfidProgresoTexto').text((indiceGlobal + 1) + ' de ' + colaGlobal.length + ' pendientes');
        cargarDatosModal(colaGlobal[indiceGlobal]);
        setTimeout(() => $rfidInput.focus(), 100);
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
        $btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $errorContainer.hide();

        const idAprendiz = $('#rfidIdAprendiz').val();
        const idFicha = $('#rfidIdFicha').val();

        $.ajax({
            url: '../../controllers/ajax/registrar_rfid.php',
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
                $btnGuardar.html('Guardar RFID');
                
                if (response.success) {
                    actualizarFilaTabla(idAprendiz, response.codigo_rfid);
                    
                    if (modoGlobal) {
                        indiceGlobal++;
                        mostrarSiguienteGlobal();
                    } else {
                        $modalRfid.modal('hide');
                    }
                } else {
                    $rfidInput.prop('disabled', false).val('').focus();
                    mostrarError(response.error);
                }
            },
            error: function() {
                isSubmitting = false;
                $rfidInput.prop('disabled', false).val('').focus();
                $btnGuardar.html('Guardar RFID');
                mostrarError('Error de conexión con el servidor al guardar el RFID.');
            }
        });
    }

    function mostrarError(mensaje) {
        $errorContainer.text(mensaje).show();
    }

    function actualizarFilaTabla(idAprendiz, nuevoRfid) {
        const $fila = $('tr[data-id-aprendiz="' + idAprendiz + '"]');
        if ($fila.length) {
            $fila.find('.col-rfid').html('<code>' + nuevoRfid + '</code>');
            $fila.find('.btn-registrar-rfid-indiv').remove();
            $fila.find('button[data-target="#modalVer"]').data('rfid', nuevoRfid);
        }
    }
});
</script>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_instructor.php';
