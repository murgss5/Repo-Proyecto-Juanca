<?php
require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

$rolGestionado = $rolGestionado ?? 3;
$edicion = $edicion ?? false;
$modulo = $rolGestionado === 3 ? 'aprendices' : 'instructores';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$conexion = (new Conexion())->conectar();
$datos = [
    'id_usuario' => '',
    'nombre_usuario' => '',
    'identificacion' => '',
    'nombre' => '',
    'apellido' => '',
    'telefono' => '',
    'correo_electronico' => '',
    'codigo_rfid' => '',
    'id_ficha' => ''
];

if ($edicion) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        header('Location: ' . RUTA_BASE . 'views/admin/' . $modulo . '/index.php?error=' . urlencode('Identificador de usuario no válido.'));
        exit;
    }
    $sql = 'SELECT u.id_usuario, u.nombre_usuario, u.identificacion, u.nombre, u.apellido, u.telefono, u.correo_electronico, ap.codigo_rfid, ap.fk_ficha AS id_ficha '
         . 'FROM usuario u '
         . ($rolGestionado === 3 ? 'INNER JOIN aprendiz ap ON ap.fk_usuario = u.id_usuario' : 'LEFT JOIN aprendiz ap ON 1=0')
         . ' WHERE u.id_usuario = ? AND u.fk_rol = ?';
    $q = $conexion->prepare($sql);
    $q->execute([$id, $rolGestionado]);
    $datos = $q->fetch();
    if (!$datos) {
        header('Location: index.php?error=Usuario no encontrado.');
        exit;
    }
}

$fichas = $rolGestionado === 3
    ? $conexion->query('SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha')->fetchAll()
    : [];

$paginaTitulo = ($edicion ? 'Editar ' : 'Nuevo ') . ($rolGestionado === 3 ? 'Aprendiz' : 'Instructor');
$menuActivo = $modulo;
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => ucfirst($modulo), 'url' => RUTA_BASE . 'views/admin/' . $modulo . '/index.php'],
    ['label' => $paginaTitulo]
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
    <div class="col-lg-9 col-12">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas <?= $rolGestionado === 3 ? 'fa-user-graduate' : 'fa-chalkboard-teacher' ?> text-success mr-2"></i> <?= htmlspecialchars($paginaTitulo) ?>
                </h3>
                <div class="card-tools ml-auto">
                    <a href="<?= RUTA_BASE ?>views/admin/<?= $modulo ?>/index.php" class="btn btn-outline-secondary btn-sm shadow-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Volver al listado
                    </a>
                </div>
            </div>

            <form action="<?= RUTA_BASE ?>controllers/gestionar_usuario.php" method="post" autocomplete="off">
                <div class="card-body py-4 px-4">
                    <!-- Parámetros obligatorios de controlador -->
                    <input type="hidden" name="accion" value="<?= $edicion ? 'editar' : 'crear' ?>">
                    <input type="hidden" name="rol" value="<?= $rolGestionado ?>">
                    <?php if ($edicion): ?>
                        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($datos['id_usuario']) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

                    <!-- SECCIÓN 1: Datos Personales -->
                    <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2">
                        <i class="fas fa-address-card text-success mr-2"></i> Datos Personales
                    </h6>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="documento" class="font-weight-bold text-dark">
                                Documento de Identidad <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                </div>
                                <input type="text" id="documento" name="documento" class="form-control" placeholder="Ej. 1020304050" value="<?= htmlspecialchars($datos['identificacion']) ?>" required <?= $edicion ? '' : 'autofocus' ?>>
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
                                <input type="tel" id="telefono" name="telefono" class="form-control" placeholder="Ej. 3001234567" value="<?= htmlspecialchars($datos['telefono'] ?? '') ?>" inputmode="numeric" maxlength="10" pattern="3[0-9]{9}">
                            </div>
                            <small class="form-text text-muted">Debe tener 10 dígitos y comenzar por 3.</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="nombre" class="font-weight-bold text-dark">
                                Nombres <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej. Carlos Eduardo" value="<?= htmlspecialchars($datos['nombre']) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="apellido" class="font-weight-bold text-dark">
                                Apellidos <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-tag"></i></span>
                                </div>
                                <input type="text" id="apellido" name="apellido" class="form-control" placeholder="Ej. Restrepo Gómez" value="<?= htmlspecialchars($datos['apellido']) ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 form-group mb-3">
                            <label for="correo_electronico" class="font-weight-bold text-dark">
                                Correo Electrónico
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                </div>
                                <?php if ($rolGestionado === 3 && $edicion): ?>
                                    <input type="email" id="correo_electronico" name="correo_electronico" class="form-control bg-light" placeholder="Sin correo registrado" value="<?= htmlspecialchars($datos['correo_electronico'] ?? '') ?>" readonly>
                                <?php elseif ($rolGestionado === 3): ?>
                                    <input type="email" id="correo_electronico" name="correo_electronico" class="form-control" placeholder="Ej. aprendiz@correo.com" value="<?= htmlspecialchars($datos['correo_electronico'] ?? '') ?>">
                                <?php else: ?>
                                    <input type="email" id="correo_electronico" name="correo_electronico" class="form-control" placeholder="Ej. instructor@correo.com" value="<?= htmlspecialchars($datos['correo_electronico'] ?? '') ?>">
                                <?php endif; ?>
                            </div>
                            <?php if ($rolGestionado === 3 && $edicion): ?>
                                <small class="text-muted"><i class="fas fa-info-circle text-info mr-1"></i> El correo es actualizado directamente por el aprendiz desde su perfil.</small>
                            <?php elseif ($rolGestionado === 3): ?>
                                <small class="text-muted">Opcional. El aprendiz podrá registrarlo o modificarlo directamente desde su perfil.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: Información de Credenciales -->
                    <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2 mt-4">
                        <i class="fas fa-user-shield text-success mr-2"></i> Información de Credenciales
                    </h6>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="usuario" class="font-weight-bold text-dark">
                                Usuario <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                </div>
                                <input type="text" id="usuario" name="nombre_usuario" class="form-control bg-light" placeholder="Se genera automáticamente" value="<?= htmlspecialchars($datos['nombre_usuario']) ?>" required readonly>
                            </div>
                            <small class="text-muted">Se sincroniza automáticamente con el documento.</small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="contrasena" class="font-weight-bold text-dark">
                                Contraseña <?= $edicion ? '<span class="text-muted font-weight-normal">(dejar vacía para conservar)</span>' : '<span class="text-danger">*</span>' ?>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                </div>
                                <input type="password" id="contrasena" name="contrasena" class="form-control bg-light" placeholder="<?= $edicion ? '•••••••• (conservar actual)' : 'Se asigna automáticamente' ?>" <?= $edicion ? '' : 'required' ?> autocomplete="new-password" readonly>
                            </div>
                            <small class="text-muted">Se sincroniza automáticamente con el documento.</small>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: Datos de Aprendiz (si aplica) -->
                    <?php if ($rolGestionado === 3): ?>
                        <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2 mt-4">
                            <i class="fas fa-graduation-cap text-success mr-2"></i> Información Académica
                        </h6>
                        <div class="row">
                            <div class="col-md-<?= $edicion ? '6' : '12' ?> form-group mb-3">
                                <label for="id_ficha" class="font-weight-bold text-dark">
                                    Ficha de Formación <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                    </div>
                                    <select id="id_ficha" name="id_ficha" class="form-control custom-select" required>
                                        <option value="">Seleccione una ficha...</option>
                                        <?php foreach ($fichas as $ficha): ?>
                                            <option value="<?= $ficha['id_ficha'] ?>" <?= strval($datos['id_ficha']) === strval($ficha['id_ficha']) ? 'selected' : '' ?>>
                                                Ficha #<?= $ficha['id_ficha'] ?> — <?= htmlspecialchars($ficha['nombre_programa']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <?php if ($edicion): ?>
                                <div class="col-md-6 form-group mb-3">
                                    <label for="codigo_rfid" class="font-weight-bold text-dark">
                                        Código RFID <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                                        </div>
                                        <input type="text" id="codigo_rfid" name="codigo_rfid" class="form-control" placeholder="Ej. RFID00123" value="<?= htmlspecialchars($datos['codigo_rfid'] ?? '') ?>" required>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pie de Tarjeta con Botones de Acción -->
                <div class="card-footer text-right bg-white border-top py-3">
                    <a href="<?= RUTA_BASE ?>views/admin/<?= $modulo ?>/index.php" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var doc = document.getElementById('documento');
    if (doc) {
        document.getElementById('documento').addEventListener('input', function() {
            let docValue = this.value;
            var user = document.getElementById('usuario');
            var pass = document.getElementById('contrasena');
            if (user) user.value = docValue;
            if (pass) pass.value = docValue;
        });
    }
});
</script>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../layouts/layout_admin.php';
