<?php
/**
 * ============================================================
 * VISTA: PRUEBA DE ENVÍO DE CORREOS DE CREDENCIALES
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Permite al administrador probar el despacho de correos electrónicos
 * de bienvenida con credenciales (Usuario / Contraseña basados en CC)
 * para verificar la operatividad en el servidor de producción (Hostinger).
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 2) . '/includes/constantes.php';

// Token CSRF
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Variables de layout
$paginaTitulo = 'Prueba de envío de correos';
$menuActivo   = 'prueba_correo';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Sistema'],
    ['label' => 'Prueba de correo']
];

// Capturar parámetros GET para persistencia en mensajes
$mensaje = $_GET['mensaje'] ?? null;
$error   = $_GET['error'] ?? null;
$correoDestino = htmlspecialchars($_GET['correo'] ?? '');
$ccSimulada    = htmlspecialchars($_GET['cc'] ?? '');

ob_start();
?>

<!-- Alertas de Estado / Mensajes Flash -->
<?php if ($mensaje === 'enviado'): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-check-circle fa-2x mr-3 text-white"></i>
            <div>
                <h6 class="font-weight-bold mb-1">¡Correo despachado exitosamente!</h6>
                <p class="mb-0 small">
                    El correo con las credenciales demo se ha enviado a <strong><?= $correoDestino ?></strong> (Documento: <strong><?= $ccSimulada ?></strong>). Por favor revise su bandeja de entrada o carpeta de correo no deseado (Spam).
                </p>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php elseif ($mensaje === 'simulado_local'): ?>
    <div class="alert alert-info alert-dismissible fade show shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle fa-2x mr-3 text-white"></i>
            <div>
                <h6 class="font-weight-bold mb-1">Estructura validada en entorno local</h6>
                <p class="mb-0 small">
                    Se validaron los datos para <strong><?= $correoDestino ?></strong> y se generó la plantilla HTML. En servidores de producción como <strong>Hostinger</strong>, la función <code>mail()</code> nativa procesará el envío hacia la bandeja del destinatario.
                </p>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php elseif ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-2x mr-3 text-white"></i>
            <div>
                <h6 class="font-weight-bold mb-1">Error al procesar la prueba</h6>
                <p class="mb-0 small">
                    <?php
                    switch ($error) {
                        case 'campos_requeridos':
                            echo 'Por favor complete todos los campos obligatorios del formulario.';
                            break;
                        case 'correo_invalido':
                            echo 'La dirección de correo electrónico ingresada no es válida.';
                            break;
                        case 'csrf':
                            echo 'Token de seguridad inválido o sesión expirada. Intente de nuevo.';
                            break;
                        case 'fallo_envio':
                            echo 'El servidor no pudo procesar el correo. Verifique la configuración de correo en el hosting.';
                            break;
                        default:
                            echo 'Ocurrió un error inesperado al procesar la solicitud.';
                            break;
                    }
                    ?>
                </p>
            </div>
        </div>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Columna Izquierda: Formulario de Prueba -->
    <div class="col-lg-6 mb-4">
        <div class="card card-outline card-success shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-paper-plane text-success mr-2"></i> Enviar Correo de Prueba
                </h3>
                <span class="badge badge-success px-2 py-1"><i class="fas fa-shield-alt mr-1"></i> Solo Administrador</span>
            </div>

            <div class="card-body p-4">
                <!-- Banner Informativo -->
                <div class="alert alert-success border-0 bg-light shadow-sm mb-4 p-3 rounded">
                    <div class="d-flex align-items-start">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white mr-3 mt-1" style="width: 38px; height: 38px; min-width: 38px;">
                            <i class="fas fa-envelope-open-text"></i>
                        </span>
                        <div>
                            <h6 class="font-weight-bold text-dark mb-1">Simulación de Notificación SENA</h6>
                            <p class="text-muted small mb-0">
                                Esta herramienta permite verificar la función <code>mail()</code> en Hostinger. Enviará una plantilla HTML idéntica a la que recibirán los aprendices e instructores con su <strong>Usuario y Contraseña sincronizados con el número de cédula</strong>.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <form action="<?= RUTA_BASE ?>controllers/enviar_correo_prueba.php" method="post" id="formPruebaCorreo">
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

                    <!-- Campo Cédula / Documento -->
                    <div class="form-group mb-3">
                        <label for="inputCC" class="font-weight-bold text-dark">
                            Cédula / Documento de Identidad (CC) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0">
                                    <i class="fas fa-id-card text-success"></i>
                                </span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control border-left-0" 
                                id="inputCC" 
                                name="cc" 
                                required 
                                maxlength="20"
                                placeholder="Ej. 1061789456" 
                                value="<?= $ccSimulada ?: '1061789456' ?>"
                            >
                        </div>
                        <small class="form-text text-muted">
                            Se utilizará como el <strong>Usuario</strong> y la <strong>Contraseña</strong> simulados en el mensaje.
                        </small>
                    </div>

                    <!-- Campo Correo Destino -->
                    <div class="form-group mb-3">
                        <label for="inputCorreo" class="font-weight-bold text-dark">
                            Correo Electrónico de Destino <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0">
                                    <i class="fas fa-envelope text-success"></i>
                                </span>
                            </div>
                            <input 
                                type="email" 
                                class="form-control border-left-0" 
                                id="inputCorreo" 
                                name="correo" 
                                required 
                                placeholder="ejemplo@correo.com" 
                                value="<?= $correoDestino ?>"
                            >
                        </div>
                        <small class="form-text text-muted">
                            Ingrese la dirección de correo electrónico personal o corporativa donde desea recibir el correo demo.
                        </small>
                    </div>

                    <!-- Campo Opcional: Nombre del Aprendiz -->
                    <div class="form-group mb-4">
                        <label for="inputNombre" class="font-weight-bold text-dark">
                            Nombre del Aprendiz <span class="text-muted font-weight-normal">(Opcional)</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0">
                                    <i class="fas fa-user text-muted"></i>
                                </span>
                            </div>
                            <input 
                                type="text" 
                                class="form-control border-left-0" 
                                id="inputNombre" 
                                name="nombre" 
                                placeholder="Ej. Juan Carlos Pérez" 
                                value="Aprendiz Demo SENA"
                            >
                        </div>
                    </div>

                    <!-- Botón de Envío -->
                    <div class="pt-3 border-top d-flex align-items-center justify-content-between">
                        <a href="<?= RUTA_BASE ?>views/admin/dashboard.php" class="btn btn-outline-secondary px-3 shadow-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Volver al Dashboard
                        </a>
                        <button type="submit" id="btnEnviarCorreo" class="btn btn-success font-weight-bold px-4 shadow-sm">
                            <i class="fas fa-paper-plane mr-1"></i> Enviar Correo de Prueba
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Previsualización en Vivo de la Plantilla -->
    <div class="col-lg-6 mb-4">
        <div class="card card-outline card-secondary shadow-sm h-100">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-eye text-primary mr-2"></i> Vista Previa de la Notificación
                </h3>
                <span class="badge badge-light border text-muted">Plantilla HTML</span>
            </div>

            <div class="card-body p-4 bg-light">
                <!-- Contenedor Maqueta del Correo -->
                <div class="bg-white rounded border shadow-sm p-4 mx-auto" style="max-width: 500px; border-top: 5px solid #39a900 !important;">
                    <!-- Cabecera -->
                    <div class="text-center pb-3 border-bottom mb-3">
                        <span class="badge badge-success px-2 py-1 mb-2 font-weight-bold" style="font-size: 10px; letter-spacing: 0.5px;">
                            SERVICIO NACIONAL DE APRENDIZAJE &bull; SENA
                        </span>
                        <h5 class="font-weight-bold text-dark mb-0" style="color: #00324d !important;">
                            Control de Ingreso y Asistencia
                        </h5>
                        <p class="text-success small font-weight-bold mb-0 text-uppercase" style="font-size: 11px;">
                            Notificación Oficial de Credenciales
                        </p>
                    </div>

                    <!-- Cuerpo -->
                    <div>
                        <div class="font-weight-bold mb-2 text-dark" id="previewNombreLive">
                            Cordial saludo, Aprendiz Demo SENA
                        </div>
                        <p class="small text-muted mb-3" style="line-height: 1.5;">
                            Le informamos que se han generado sus credenciales de acceso para la plataforma de <strong>Control de Ingreso y Asistencia SENA</strong>.
                        </p>

                        <!-- Tarjeta de Credenciales -->
                        <div class="rounded p-3 mb-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #39a900;">
                            <h6 class="font-weight-bold text-dark mb-2" style="font-size: 13px;">Credenciales Asignadas</h6>
                            <div class="small mb-1">
                                <span class="text-muted d-inline-block" style="width: 90px;">Usuario:</span>
                                <code class="font-weight-bold text-dark px-2 py-1 rounded bg-light" id="previewUsuarioLive">1061789456</code>
                            </div>
                            <div class="small">
                                <span class="text-muted d-inline-block" style="width: 90px;">Contraseña:</span>
                                <code class="font-weight-bold text-dark px-2 py-1 rounded bg-light" id="previewClaveLive">1061789456</code>
                            </div>
                        </div>

                        <p class="text-muted small mb-3" style="font-size: 11px;">
                            <em>Por política institucional, su usuario y contraseña corresponden inicialmente a su número de documento de identidad (CC / TI).</em>
                        </p>

                        <!-- Botón Simulado -->
                        <div class="text-center my-3">
                            <span class="btn btn-success btn-sm font-weight-bold px-3 py-2 shadow-sm" style="pointer-events: none; background-color: #39a900; border: none;">
                                <i class="fas fa-sign-in-alt mr-1"></i> Ingresar al Sistema SENA
                            </span>
                        </div>
                    </div>

                    <!-- Pie -->
                    <div class="text-center pt-3 border-top mt-3 text-muted" style="font-size: 10px;">
                        <div><strong>Sistema de Asistencia SENA</strong> &bull; Centro de Teleinformática y Producción Industrial</div>
                        <div>Este mensaje es automático, por favor no responda a esta dirección de correo.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputCC = document.getElementById('inputCC');
    const inputNombre = document.getElementById('inputNombre');
    const previewUsuario = document.getElementById('previewUsuarioLive');
    const previewClave = document.getElementById('previewClaveLive');
    const previewNombre = document.getElementById('previewNombreLive');
    const form = document.getElementById('formPruebaCorreo');
    const btnEnviar = document.getElementById('btnEnviarCorreo');

    // Sincronización en vivo de la maqueta
    function actualizarPreview() {
        const ccVal = inputCC.value.trim() || '1061789456';
        const nombreVal = inputNombre.value.trim() || 'Aprendiz Demo SENA';

        previewUsuario.textContent = ccVal;
        previewClave.textContent = ccVal;
        previewNombre.textContent = 'Cordial saludo, ' + nombreVal;
    }

    inputCC.addEventListener('input', actualizarPreview);
    inputNombre.addEventListener('input', actualizarPreview);

    // Animación de carga al presionar enviar
    form.addEventListener('submit', function() {
        btnEnviar.disabled = true;
        btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Despachando correo...';
    });
});
</script>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../layouts/layout_admin.php';

