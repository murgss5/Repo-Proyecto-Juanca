<?php
/**
 * Vista de administración: Registro de Nuevo Instructor
 * Sistema de Control de Ingreso de Aprendices - SENA
 * 
 * Formulario estructurado bajo el estándar corporativo "Enterprise":
 * - Secciones lógicas delimitadas
 * - Input groups con iconografía FontAwesome
 * - Contenedor centrado y adaptativo
 * - Automatización de credenciales por documento
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Variables para el layout principal
$paginaTitulo = 'Nuevo Instructor';
$menuActivo   = 'instructores';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Instructores', 'url' => RUTA_BASE . 'views/admin/instructores/index.php'],
    ['label' => 'Nuevo Instructor']
];

ob_start();
?>

<!-- Alertas de estado -->
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Contenedor Principal Centrado Enterprise -->
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold text-dark m-0">
                    <i class="fas fa-chalkboard-teacher mr-2 text-success"></i> Registro de Nuevo Instructor
                </h3>
                <div class="card-tools ml-auto">
                    <a href="index.php" class="btn btn-secondary btn-sm shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form action="<?= RUTA_BASE ?>controllers/gestionar_usuario.php" method="post" autocomplete="off">
                <div class="card-body py-4 px-4">
                    <!-- Parámetros obligatorios de controlador -->
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="rol" value="2">
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

                    <!-- SECCIÓN 1: Datos Personales -->
                    <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2">
                        <i class="fas fa-address-card text-success mr-2"></i> Datos Personales
                    </h6>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="identificacion" class="font-weight-bold text-dark">
                                Documento de Identidad <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                </div>
                                <input type="text" id="identificacion" name="documento" class="form-control" placeholder="Ej. 1020304050" required autofocus>
                            </div>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="telefono" class="font-weight-bold text-dark">
                                Teléfono Celular
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                </div>
                                <input type="tel" id="telefono" name="telefono" class="form-control" placeholder="Ej. 3001234567" inputmode="numeric" maxlength="10" pattern="3[0-9]{9}">
                            </div>
                            <small class="form-text text-muted">Debe tener 10 dígitos y comenzar por 3 (opcional).</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="nombre" class="font-weight-bold text-dark">
                                Nombres <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                </div>
                                <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Carlos Eduardo" required>
                            </div>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="apellido" class="font-weight-bold text-dark">
                                Apellidos <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                </div>
                                <input type="text" id="apellido" name="apellido" class="form-control" placeholder="Ej. Restrepo Gómez" required>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: Credenciales de Acceso -->
                    <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2 mt-4">
                        <i class="fas fa-user-lock text-success mr-2"></i> Credenciales de Acceso
                    </h6>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="nombre_usuario" class="font-weight-bold text-dark">
                                Nombre de Usuario <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                </div>
                                <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control bg-light" placeholder="Se genera automáticamente" readonly required>
                            </div>
                            <small class="form-text text-muted">Generado automáticamente a partir del documento de identidad.</small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="contrasena" class="font-weight-bold text-dark">
                                Contraseña <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                </div>
                                <input type="password" id="contrasena" name="contrasena" class="form-control bg-light" placeholder="Se asigna automáticamente" readonly required autocomplete="new-password">
                            </div>
                            <small class="form-text text-muted">Asignada automáticamente a partir del documento de identidad.</small>
                        </div>
                    </div>
                </div>

                <!-- Pie del Formulario -->
                <div class="card-footer text-right bg-white border-top">
                    <a href="index.php" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i> Guardar Instructor
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const docInput = document.getElementById('identificacion') || document.getElementById('documento');
    const userInput = document.getElementById('nombre_usuario');
    const passInput = document.getElementById('contrasena');

    if (docInput && userInput && passInput) {
        docInput.addEventListener('input', function() {
            userInput.value = this.value;
            passInput.value = this.value;
        });
    }
});
</script>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';
