<?php
/**
 * ============================================================
 * VISTA: MI PERFIL (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Muestra información personal, académica y de seguridad del
 * aprendiz autenticado. Permite el cambio seguro de contraseña.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

asegurarSesionIniciada();

$conexion = (new Conexion())->conectar();

// Consulta completa de datos de usuario, aprendiz y ficha
$consultaPerfil = $conexion->prepare(
    'SELECT
        usuario.id_usuario,
        usuario.nombre,
        usuario.apellido,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre_completo,
        usuario.tipo_documento,
        usuario.identificacion AS documento,
        usuario.telefono,
        usuario.correo_electronico,
        usuario.fecha_ultimo_cambio_clave,
        aprendiz.codigo_rfid,
        ficha.id_ficha,
        ficha.nombre_programa,
        ficha.jornada,
        ficha.ambiente_principal
     FROM usuario
     INNER JOIN aprendiz ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE usuario.id_usuario = :id_usuario
     LIMIT 1'
);
$consultaPerfil->bindValue(':id_usuario', $_SESSION['aprendiz']['id_usuario'], PDO::PARAM_INT);
$consultaPerfil->execute();
$perfilAprendiz = $consultaPerfil->fetch(PDO::FETCH_ASSOC);

// Variables del layout
$paginaTitulo = 'Mi perfil';
$menuActivo   = 'perfil';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi perfil'],
];

ob_start();
?>

<?php if (!$perfilAprendiz): ?>
  <div class="row">
    <div class="col-12">
      <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle mr-2"></i> No se encontró un perfil de aprendiz asociado a esta cuenta.
      </div>
    </div>
  </div>
<?php else: ?>

  <!-- Encabezado / Identidad del Aprendiz -->
  <div class="card card-outline card-success mb-4 shadow-sm">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div class="d-flex align-items-center mb-3 mb-md-0">
          <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mr-3" style="width: 64px; height: 64px; min-width: 64px; border: 2px solid #39a900;">
            <i class="fas fa-user-graduate fa-2x text-sena"></i>
          </div>
          <div>
            <h2 class="h4 font-weight-bold mb-1 text-dark" id="perfilHeaderNombre"><?= htmlspecialchars($perfilAprendiz['nombre_completo']) ?></h2>
            <div class="d-flex flex-wrap align-items-center">
              <span class="badge badge-success px-2 py-1 mr-2"><i class="fas fa-check-circle mr-1"></i> Aprendiz</span>
              <span class="text-muted"><i class="fas fa-book-reader mr-1"></i> <?= htmlspecialchars($perfilAprendiz['nombre_programa'] ?: 'No registrado') ?></span>
            </div>
          </div>
        </div>
        <div class="text-md-right">
          <span class="text-muted small d-block">Identificador interno de ficha</span>
          <span class="h5 font-weight-bold text-dark">#<?= htmlspecialchars((string)$perfilAprendiz['id_ficha']) ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Columna 1: Datos Personales -->
    <div class="col-lg-6 col-12 mb-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
          <h3 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-id-card text-sena mr-2"></i> Datos personales
          </h3>
          <button type="button" class="btn btn-sm btn-outline-success" id="btnAbrirModalEditarDatos">
            <i class="fas fa-user-edit mr-1"></i> Editar datos
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <tbody>
                <tr>
                  <th scope="row" class="text-muted pl-4" style="width: 40%;">Tipo de documento</th>
                  <td class="font-weight-medium pr-4" id="perfilTipoDoc"><?= htmlspecialchars($perfilAprendiz['tipo_documento'] ?: 'No registrado') ?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Número de documento</th>
                  <td class="font-weight-medium pr-4" id="perfilDocumento"><?= htmlspecialchars($perfilAprendiz['documento'] ?: 'No registrado') ?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Nombres</th>
                  <td class="font-weight-medium pr-4" id="perfilNombre"><?= htmlspecialchars($perfilAprendiz['nombre'] ?: 'No registrado') ?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Apellidos</th>
                  <td class="font-weight-medium pr-4" id="perfilApellido"><?= htmlspecialchars($perfilAprendiz['apellido'] ?: 'No registrado') ?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Teléfono celular</th>
                  <td class="font-weight-medium pr-4" id="perfilTelefono">
                    <?php
                      $tel = trim((string)($perfilAprendiz['telefono'] ?? ''));
                      echo ($tel !== '' && $tel !== '#N/A') ? htmlspecialchars($tel) : '<span class="text-muted font-italic">No registrado</span>';
                    ?>
                  </td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Correo electrónico</th>
                  <td class="font-weight-medium pr-4" id="perfilCorreo">
                    <?php
                      $correo = trim((string)($perfilAprendiz['correo_electronico'] ?? ''));
                      echo ($correo !== '' && $correo !== '#N/A') ? htmlspecialchars($correo) : '<span class="text-muted font-italic">No registrado</span>';
                    ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Columna 2: Información Académica -->
    <div class="col-lg-6 col-12 mb-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-white border-bottom">
          <h3 class="card-title font-weight-bold text-dark">
            <i class="fas fa-graduation-cap text-sena mr-2"></i> Información académica
          </h3>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <tbody>
                <tr>
                  <th scope="row" class="text-muted pl-4" style="width: 45%;">Identificador interno de ficha</th>
                  <td class="font-weight-medium pr-4">
                    <span class="badge badge-light border text-monospace">#<?= htmlspecialchars((string)$perfilAprendiz['id_ficha']) ?></span>
                  </td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Programa de formación</th>
                  <td class="font-weight-medium pr-4"><?= htmlspecialchars($perfilAprendiz['nombre_programa'] ?: 'No registrado') ?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Jornada</th>
                  <td class="font-weight-medium pr-4"><?= htmlspecialchars($perfilAprendiz['jornada'] ?: 'No registrada') ?></td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Ambiente principal</th>
                  <td class="font-weight-medium pr-4">
                    <?php
                      $ambiente = trim((string)($perfilAprendiz['ambiente_principal'] ?? ''));
                      echo ($ambiente !== '' && $ambiente !== '#N/A') ? htmlspecialchars($ambiente) : '<span class="text-muted font-italic">No registrado</span>';
                    ?>
                  </td>
                </tr>
                <tr>
                  <th scope="row" class="text-muted pl-4">Tarjeta RFID</th>
                  <td class="font-weight-medium pr-4">
                    <?php if (!empty($perfilAprendiz['codigo_rfid'])): ?>
                      <div class="d-flex align-items-center">
                        <span class="badge badge-success mr-2"><i class="fas fa-check mr-1"></i> Código registrado</span>
                        <span id="rfidMascara" class="text-monospace">••••••••••</span>
                        <code id="rfidTexto" class="d-none text-monospace"><?= htmlspecialchars($perfilAprendiz['codigo_rfid']) ?></code>
                        <button type="button" class="btn btn-sm btn-outline-secondary ml-2 py-0 px-2" id="btnToggleRfid" title="Mostrar/ocultar código RFID" aria-label="Mostrar código RFID">
                          <i class="fas fa-eye" id="iconoToggleRfid"></i>
                        </button>
                      </div>
                    <?php else: ?>
                      <span class="badge badge-secondary"><i class="fas fa-minus-circle mr-1"></i> Sin código registrado</span>
                    <?php endif; ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sección de Seguridad de la Cuenta -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-white border-bottom">
      <h3 class="card-title font-weight-bold text-dark">
        <i class="fas fa-shield-alt text-sena mr-2"></i> Seguridad de la cuenta
      </h3>
    </div>
    <div class="card-body p-4">
      <div class="row align-items-center">
        <div class="col-md-8 mb-3 mb-md-0">
          <p class="mb-2 text-dark font-weight-medium">Puedes actualizar tu contraseña para proteger tu cuenta.</p>
          <p class="text-muted small mb-0">
            <i class="fas fa-history mr-1"></i> <strong>Último cambio registrado:</strong>
            <span id="textoUltimoCambio">
              <?php if (!empty($perfilAprendiz['fecha_ultimo_cambio_clave'])): ?>
                <?= htmlspecialchars(date('d/m/Y h:i a', strtotime($perfilAprendiz['fecha_ultimo_cambio_clave']))) ?>
              <?php else: ?>
                <span class="font-italic">Sin cambios registrados</span>
              <?php endif; ?>
            </span>
          </p>
        </div>
        <div class="col-md-4 text-md-right">
          <button type="button" class="btn btn-primary btn-block-sm px-4" id="btnCambiarClavePerfil">
            <i class="fas fa-key mr-2"></i> Cambiar contraseña
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para editar datos personales -->
  <div class="modal fade" id="modalEditarDatos" tabindex="-1" role="dialog" aria-labelledby="modalEditarDatosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content shadow">
        <div class="modal-header bg-light">
          <h5 class="modal-title font-weight-bold text-dark" id="modalEditarDatosLabel">
            <i class="fas fa-user-edit text-sena mr-2"></i> Editar datos personales
          </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <form id="formEditarDatosPersonales" novalidate>
          <div class="modal-body p-4">
            <div id="alertaErrorModal" class="alert alert-danger d-none" role="alert"></div>

            <div class="form-row">
              <div class="form-group col-md-5">
                <label for="edit_tipo_documento" class="font-weight-bold small text-dark mb-1">Tipo de documento *</label>
                <select id="edit_tipo_documento" name="tipo_documento" class="form-control" required>
                  <option value="CC">CC - Cédula de Ciudadanía</option>
                  <option value="TI">TI - Tarjeta de Identidad</option>
                  <option value="CE">CE - Cédula de Extranjería</option>
                  <option value="PEP">PEP - Permiso Especial</option>
                  <option value="PPT">PPT - Permiso por Protección Temporal</option>
                  <option value="PAS">PAS - Pasaporte</option>
                </select>
              </div>
              <div class="form-group col-md-7">
                <label for="edit_identificacion" class="font-weight-bold small text-dark mb-1">Número de documento *</label>
                <input type="text" id="edit_identificacion" name="identificacion" class="form-control" maxlength="45" required autocomplete="off">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="edit_nombre" class="font-weight-bold small text-dark mb-1">Nombres *</label>
                <input type="text" id="edit_nombre" name="nombre" class="form-control" maxlength="45" required autocomplete="given-name">
              </div>
              <div class="form-group col-md-6">
                <label for="edit_apellido" class="font-weight-bold small text-dark mb-1">Apellidos *</label>
                <input type="text" id="edit_apellido" name="apellido" class="form-control" maxlength="45" required autocomplete="family-name">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="edit_telefono" class="font-weight-bold small text-dark mb-1">Teléfono celular</label>
                <input type="tel" id="edit_telefono" name="telefono" class="form-control" maxlength="45" placeholder="Opcional" autocomplete="tel">
              </div>
              <div class="form-group col-md-6">
                <label for="edit_correo_electronico" class="font-weight-bold small text-dark mb-1">Correo electrónico</label>
                <input type="email" id="edit_correo_electronico" name="correo_electronico" class="form-control" maxlength="100" placeholder="Ej. aprendiz@correo.com" autocomplete="email">
                <small class="form-text text-muted">Puedes actualizar libremente tu correo personal.</small>
              </div>
            </div>

            <!-- Sección condicional para confirmar modificación de documento -->
            <div id="seccionClaveDoc" class="card border-warning bg-light d-none mt-2 mb-0">
              <div class="card-body p-3">
                <div class="d-flex align-items-start mb-2">
                  <i class="fas fa-exclamation-triangle text-warning fa-lg mr-2 mt-1"></i>
                  <div>
                    <span class="font-weight-bold text-dark d-block">Modificación del número de documento</span>
                    <small class="text-muted">
                      Has cambiado tu número de documento. Dado que se utiliza para iniciar sesión, ingresa tu contraseña actual para autorizar este cambio.
                    </small>
                  </div>
                </div>
                <div class="form-group mb-0">
                  <label for="edit_clave_autorizacion" class="font-weight-bold small text-dark mb-1">Contraseña actual *</label>
                  <div class="input-group">
                    <input type="password" id="edit_clave_autorizacion" name="clave_autorizacion" class="form-control" placeholder="••••••••" autocomplete="current-password">
                    <div class="input-group-append">
                      <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="edit_clave_autorizacion" tabindex="-1" title="Mostrar/ocultar contraseña">
                        <i class="fas fa-eye"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">
              <i class="fas fa-times mr-1"></i> Cancelar
            </button>
            <button type="submit" class="btn btn-success" id="btnGuardarDatosPersonales">
              <i class="fas fa-save mr-1"></i> Guardar cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Script interactivo específico del perfil -->
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    // 1. Mostrar/ocultar código RFID registrado
    var btnToggleRfid = document.getElementById('btnToggleRfid');
    if (btnToggleRfid) {
      btnToggleRfid.addEventListener('click', function () {
        var mascara = document.getElementById('rfidMascara');
        var texto   = document.getElementById('rfidTexto');
        var icono   = document.getElementById('iconoToggleRfid');
        if (mascara && texto && icono) {
          var estaOculto = texto.classList.contains('d-none');
          if (estaOculto) {
            mascara.classList.add('d-none');
            texto.classList.remove('d-none');
            icono.classList.replace('fa-eye', 'fa-eye-slash');
            btnToggleRfid.setAttribute('aria-label', 'Ocultar código RFID');
          } else {
            texto.classList.add('d-none');
            mascara.classList.remove('d-none');
            icono.classList.replace('fa-eye-slash', 'fa-eye');
            btnToggleRfid.setAttribute('aria-label', 'Mostrar código RFID');
          }
        }
      });
    }

    // 2. Formulario modal SweetAlert2 para cambiar contraseña desde el perfil
    var btnCambiarClave = document.getElementById('btnCambiarClavePerfil');
    if (btnCambiarClave) {
      btnCambiarClave.addEventListener('click', function () {
        var tokenCsrf = <?= json_encode($_SESSION['token_csrf'] ?? '') ?>;
        var urlCambio = <?= json_encode(RUTA_BASE . 'controllers/aprendiz/cambiar_contrasena.php') ?>;

        var formHtml = ''
          + '<div class="text-left">'
          + '  <div class="form-group mb-3">'
          + '    <label for="swalClaveActual" class="font-weight-bold small text-dark mb-1">Contraseña actual *</label>'
          + '    <div class="input-group">'
          + '      <input type="password" id="swalClaveActual" class="form-control" placeholder="••••••••" autocomplete="current-password">'
          + '      <div class="input-group-append">'
          + '        <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="swalClaveActual" tabindex="-1">'
          + '          <i class="fas fa-eye"></i>'
          + '        </button>'
          + '      </div>'
          + '    </div>'
          + '  </div>'
          + '  <div class="form-group mb-3">'
          + '    <label for="swalNuevaClave" class="font-weight-bold small text-dark mb-1">Nueva contraseña *</label>'
          + '    <div class="input-group">'
          + '      <input type="password" id="swalNuevaClave" class="form-control" placeholder="Mínimo 8 caracteres" autocomplete="new-password">'
          + '      <div class="input-group-append">'
          + '        <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="swalNuevaClave" tabindex="-1">'
          + '          <i class="fas fa-eye"></i>'
          + '        </button>'
          + '      </div>'
          + '    </div>'
          + '    <small class="form-text text-muted">Mínimo 8 caracteres. Se admiten espacios, frases y pegado.</small>'
          + '  </div>'
          + '  <div class="form-group mb-0">'
          + '    <label for="swalConfirmarClave" class="font-weight-bold small text-dark mb-1">Confirmar nueva contraseña *</label>'
          + '    <div class="input-group">'
          + '      <input type="password" id="swalConfirmarClave" class="form-control" placeholder="Repite la nueva contraseña" autocomplete="new-password">'
          + '      <div class="input-group-append">'
          + '        <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="swalConfirmarClave" tabindex="-1">'
          + '          <i class="fas fa-eye"></i>'
          + '        </button>'
          + '      </div>'
          + '    </div>'
          + '  </div>'
          + '</div>';

        Swal.fire({
          title: 'Cambiar contraseña',
          html: formHtml,
          icon: 'info',
          showCancelButton: true,
          confirmButtonText: '<i class="fas fa-save mr-1"></i> Guardar contraseña',
          cancelButtonText: '<i class="fas fa-times mr-1"></i> Cancelar',
          confirmButtonColor: '#39a900',
          cancelButtonColor: '#6c757d',
          focusConfirm: false,
          didOpen: function () {
            // Configurar botones de ojo para mostrar/ocultar contraseñas
            var popup = Swal.getPopup();
            var eyeButtons = popup.querySelectorAll('.btn-toggle-eye');
            eyeButtons.forEach(function (btn) {
              btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-target');
                var input = document.getElementById(targetId);
                var icon = btn.querySelector('i');
                if (input && icon) {
                  if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.replace('fa-eye', 'fa-eye-slash');
                  } else {
                    input.type = 'password';
                    icon.classList.replace('fa-eye-slash', 'fa-eye');
                  }
                }
              });
            });
          },
          preConfirm: function () {
            var claveActual    = document.getElementById('swalClaveActual').value;
            var nuevaClave     = document.getElementById('swalNuevaClave').value;
            var confirmarClave = document.getElementById('swalConfirmarClave').value;

            if (!claveActual || !nuevaClave || !confirmarClave) {
              Swal.showValidationMessage('Todos los campos son obligatorios.');
              return false;
            }

            if (nuevaClave.length < 8) {
              Swal.showValidationMessage('La nueva contraseña debe tener al menos 8 caracteres.');
              return false;
            }

            // Validar límite en bytes (límite de 72 bytes de Bcrypt)
            var byteLength = new TextEncoder().encode(nuevaClave).length;
            if (byteLength > 72) {
              Swal.showValidationMessage('La nueva contraseña no puede exceder 72 bytes por seguridad.');
              return false;
            }

            if (nuevaClave !== confirmarClave) {
              Swal.showValidationMessage('La nueva contraseña y su confirmación no coinciden.');
              return false;
            }

            if (claveActual === nuevaClave) {
              Swal.showValidationMessage('La nueva contraseña no puede ser idéntica a la actual.');
              return false;
            }

            // Enviar petición POST al backend
            return fetch(urlCambio, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: new URLSearchParams({
                token_csrf: tokenCsrf,
                clave_actual: claveActual,
                nueva_clave: nuevaClave,
                confirmar_clave: confirmarClave
              })
            })
            .then(function (response) {
              return response.json().then(function (data) {
                if (!response.ok || !data.success) {
                  throw new Error(data.error || 'No fue posible actualizar la contraseña.');
                }
                return data;
              });
            })
            .catch(function (error) {
              Swal.showValidationMessage(error.message || 'Error en el servidor.');
            });
          }
        }).then(function (result) {
          if (result.isConfirmed && result.value && result.value.success) {
            // Actualizar la fecha del último cambio en pantalla si está disponible
            var spanFecha = document.getElementById('textoUltimoCambio');
            if (spanFecha && result.value.fecha) {
              spanFecha.textContent = result.value.fecha;
            }
            Swal.fire({
              icon: 'success',
              title: '¡Contraseña actualizada!',
              text: 'Tu contraseña ha sido modificada correctamente.',
              confirmButtonColor: '#39a900'
            });
          }
        });
      });
    }

    // 3. Edición de datos personales
    var datosOriginales = {
      tipo_documento: <?= json_encode($perfilAprendiz['tipo_documento'] ?? 'CC') ?>,
      identificacion: <?= json_encode((string)($perfilAprendiz['documento'] ?? '')) ?>,
      nombre: <?= json_encode((string)($perfilAprendiz['nombre'] ?? '')) ?>,
      apellido: <?= json_encode((string)($perfilAprendiz['apellido'] ?? '')) ?>,
      telefono: <?= json_encode(in_array(trim((string)($perfilAprendiz['telefono'] ?? '')), ['', '#N/A'], true) ? '' : trim((string)$perfilAprendiz['telefono'])) ?>,
      correo_electronico: <?= json_encode(in_array(trim((string)($perfilAprendiz['correo_electronico'] ?? '')), ['', '#N/A'], true) ? '' : trim((string)$perfilAprendiz['correo_electronico'])) ?>
    };

    function limpiarValor(val) {
      if (val === null || val === undefined) return '';
      var s = String(val).trim();
      if (s === '#N/A' || s.toLowerCase() === 'no registrado') return '';
      return s;
    }

    // Toggle para botones de mostrar/ocultar contraseñas en el modal de edición
    var eyeButtonsModal = document.querySelectorAll('#modalEditarDatos .btn-toggle-eye');
    eyeButtonsModal.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-target');
        var input = document.getElementById(targetId);
        var icon = btn.querySelector('i');
        if (input && icon) {
          if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
          } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
          }
        }
      });
    });

    var btnAbrirModal = document.getElementById('btnAbrirModalEditarDatos');
    if (btnAbrirModal) {
      btnAbrirModal.addEventListener('click', function () {
        document.getElementById('edit_tipo_documento').value = datosOriginales.tipo_documento || 'CC';
        document.getElementById('edit_identificacion').value  = limpiarValor(datosOriginales.identificacion);
        document.getElementById('edit_nombre').value          = limpiarValor(datosOriginales.nombre);
        document.getElementById('edit_apellido').value        = limpiarValor(datosOriginales.apellido);
        document.getElementById('edit_telefono').value        = limpiarValor(datosOriginales.telefono);
        document.getElementById('edit_correo_electronico').value = limpiarValor(datosOriginales.correo_electronico);
        document.getElementById('edit_clave_autorizacion').value = '';
        document.getElementById('seccionClaveDoc').classList.add('d-none');
        document.getElementById('edit_clave_autorizacion').removeAttribute('required');
        var alerta = document.getElementById('alertaErrorModal');
        alerta.classList.add('d-none');
        alerta.textContent = '';
        $('#modalEditarDatos').modal('show');
      });
    }

    var inputDoc = document.getElementById('edit_identificacion');
    if (inputDoc) {
      inputDoc.addEventListener('input', function () {
        var valorActual = this.value.trim();
        var seccionClave = document.getElementById('seccionClaveDoc');
        var inputClave   = document.getElementById('edit_clave_autorizacion');
        if (valorActual !== datosOriginales.identificacion) {
          seccionClave.classList.remove('d-none');
          inputClave.setAttribute('required', 'required');
        } else {
          seccionClave.classList.add('d-none');
          inputClave.removeAttribute('required');
          inputClave.value = '';
        }
      });
    }

    var formEditar = document.getElementById('formEditarDatosPersonales');
    if (formEditar) {
      formEditar.addEventListener('submit', function (e) {
        e.preventDefault();
        var alerta = document.getElementById('alertaErrorModal');
        alerta.classList.add('d-none');
        alerta.textContent = '';

        var tipoDoc = document.getElementById('edit_tipo_documento').value.trim();
        var ident   = document.getElementById('edit_identificacion').value.trim();
        var nom     = document.getElementById('edit_nombre').value.trim();
        var ape     = document.getElementById('edit_apellido').value.trim();
        var tel     = document.getElementById('edit_telefono').value.trim();
        var mail    = document.getElementById('edit_correo_electronico').value.trim();
        var clave   = document.getElementById('edit_clave_autorizacion').value;

        if (!tipoDoc || !ident || !nom || !ape) {
          alerta.textContent = 'El tipo de documento, número de documento, nombres y apellidos son obligatorios.';
          alerta.classList.remove('d-none');
          return;
        }

        if (ident !== datosOriginales.identificacion && !clave) {
          alerta.textContent = 'Debes ingresar tu contraseña actual para autorizar el cambio de número de documento.';
          alerta.classList.remove('d-none');
          return;
        }

        var btnGuardar = document.getElementById('btnGuardarDatosPersonales');
        btnGuardar.disabled = true;
        var originalBtnHtml = btnGuardar.innerHTML;
        btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...';

        var tokenCsrf = <?= json_encode($_SESSION['token_csrf'] ?? '') ?>;
        var urlUpdate = <?= json_encode(RUTA_BASE . 'controllers/aprendiz/actualizar_perfil.php') ?>;

        fetch(urlUpdate, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({
            token_csrf: tokenCsrf,
            tipo_documento: tipoDoc,
            identificacion: ident,
            nombre: nom,
            apellido: ape,
            telefono: tel,
            correo_electronico: mail,
            clave_autorizacion: clave
          })
        })
        .then(function (res) {
          return res.json().then(function (data) {
            if (!res.ok || !data.success) {
              throw new Error(data.error || 'No se pudo guardar la información.');
            }
            return data;
          });
        })
        .then(function (data) {
          // Si no hubo cambios:
          if (data.sin_cambios) {
            $('#modalEditarDatos').modal('hide');
            Swal.fire({
              icon: 'info',
              title: 'Sin modificaciones',
              text: data.message,
              confirmButtonColor: '#39a900'
            });
            return;
          }

          // Actualizar datosOriginales en memoria
          datosOriginales.tipo_documento     = data.datos.tipo_documento;
          datosOriginales.identificacion     = data.datos.documento || data.datos.identificacion;
          datosOriginales.nombre             = data.datos.nombre;
          datosOriginales.apellido           = data.datos.apellido;
          datosOriginales.telefono           = data.datos.telefono;
          datosOriginales.correo_electronico = data.datos.correo_electronico;

          // Actualizar celdas en el DOM
          var tdTipo = document.getElementById('perfilTipoDoc');
          if (tdTipo) tdTipo.textContent = data.datos.tipo_documento || 'No registrado';

          var tdDoc = document.getElementById('perfilDocumento');
          if (tdDoc) tdDoc.textContent = data.datos.documento || 'No registrado';

          var tdNom = document.getElementById('perfilNombre');
          if (tdNom) tdNom.textContent = data.datos.nombre || 'No registrado';

          var tdApe = document.getElementById('perfilApellido');
          if (tdApe) tdApe.textContent = data.datos.apellido || 'No registrado';

          var tdTel = document.getElementById('perfilTelefono');
          if (tdTel) {
            if (data.datos.telefono) {
              tdTel.textContent = data.datos.telefono;
            } else {
              tdTel.innerHTML = '<span class="text-muted font-italic">No registrado</span>';
            }
          }

          var tdMail = document.getElementById('perfilCorreo');
          if (tdMail) {
            if (data.datos.correo_electronico) {
              tdMail.textContent = data.datos.correo_electronico;
            } else {
              tdMail.innerHTML = '<span class="text-muted font-italic">No registrado</span>';
            }
          }

          // Actualizar cabecera de la página
          var elHeader = document.getElementById('perfilHeaderNombre');
          if (elHeader) {
            elHeader.textContent = data.datos.nombre_completo || (data.datos.nombre + ' ' + data.datos.apellido).trim();
          }

          // Actualizar nombre en el panel lateral del usuario si existe
          var sidebarUserName = document.querySelector('.user-panel .info a');
          if (sidebarUserName) {
            sidebarUserName.textContent = data.datos.nombre_completo || (data.datos.nombre + ' ' + data.datos.apellido).trim();
          }

          // Cerrar modal de Bootstrap
          $('#modalEditarDatos').modal('hide');

          // Mostrar notificación de éxito SweetAlert2
          Swal.fire({
            icon: 'success',
            title: '¡Datos actualizados!',
            text: data.message,
            confirmButtonColor: '#39a900'
          });
        })
        .catch(function (err) {
          alerta.textContent = err.message || 'Error de comunicación con el servidor.';
          alerta.classList.remove('d-none');
        })
        .finally(function () {
          btnGuardar.disabled = false;
          btnGuardar.innerHTML = originalBtnHtml;
        });
      });
    }
  });
  </script>

<?php endif; ?>

<?php
$contenido = ob_get_clean();

// Render del layout del panel de aprendiz
include __DIR__ . '/../layouts/layout_aprendiz.php';
