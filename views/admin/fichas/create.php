<?php
// Carga autenticación y verifica rol administrador
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);

// Carga constantes y conexión
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

// Obtiene instructores activos (rol 2)
$instructores = $conexion->query('SELECT id_usuario, nombre, apellido FROM usuario WHERE fk_rol = 2 ORDER BY nombre, apellido')->fetchAll();

// Variables para layout
$paginaTitulo = 'Nueva Ficha';
$menuActivo   = 'fichas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Fichas', 'url' => RUTA_BASE . 'views/admin/fichas/index.php'],
    ['label' => 'Nueva Ficha'],
];

ob_start();
?>

<!-- Contenedor Centrado Estándar Enterprise -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-outline card-success shadow-sm">
            <div class="card-header border-bottom-0">
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-layer-group mr-2 text-success"></i> Registro de Nueva Ficha
                </h3>
                <div class="card-tools">
                    <a href="<?= RUTA_BASE ?>views/admin/fichas/index.php" class="btn btn-tool" title="Volver al listado">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>

            <form method="post" action="<?= RUTA_BASE ?>controllers/gestionar_academico.php">
                <div class="card-body">
                    <input type="hidden" name="entidad" value="ficha">
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="vista" value="<?= htmlspecialchars($_GET['vista'] ?? 'tabla') ?>">
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

                    <?php if (isset($_GET['mensaje'])): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($_GET['error']) ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- SECCIÓN 1: Programa Académico -->
                    <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2">
                        <i class="fas fa-graduation-cap mr-2 text-success"></i> Programa Académico
                    </h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small text-muted">
                                    Programa de formación <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-graduation-cap text-muted"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="nombre_programa" maxlength="100" required placeholder="Ej: Análisis y Desarrollo de Software">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: Detalles Operativos -->
                    <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2 mt-4">
                        <i class="fas fa-cogs mr-2 text-success"></i> Detalles Operativos
                    </h6>
                    <div class="row">
                        <!-- Jornada -->
                        <div class="col-md-5">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small text-muted">
                                    Jornada <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-clock text-muted"></i></span>
                                    </div>
                                    <select class="form-control custom-select" name="jornada" required>
                                        <option value="" disabled selected>Seleccione una jornada...</option>
                                        <option value="Diurna">Diurna</option>
                                        <option value="Tarde">Tarde</option>
                                        <option value="Nocturna">Nocturna</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Instructor Líder -->
                        <div class="col-md-7">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small text-muted">
                                    Instructor líder <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user-tie text-muted"></i></span>
                                    </div>
                                    <select class="form-control custom-select" name="id_instructor" required>
                                        <option value="" disabled selected>Seleccione un instructor...</option>
                                        <?php foreach ($instructores as $i): ?>
                                            <option value="<?= $i['id_usuario'] ?>">
                                                <?= htmlspecialchars($i['nombre'] . ' ' . $i['apellido']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Ambiente Principal -->
                        <div class="col-md-12 mt-3">
                            <div class="form-group mb-0">
                                <label class="font-weight-bold small text-muted">
                                    Ambiente principal <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-door-open text-muted"></i></span>
                                    </div>
                                    <input type="text" class="form-control" name="ambiente_principal" maxlength="100" required placeholder="Ej: Taller 9, Aula 101, Laboratorio 3">
                                </div>
                                <small class="form-text text-muted mt-1">Ej: Taller 9, Aula 101, Laboratorio 3</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pie de formulario -->
                <div class="card-footer text-right bg-white border-top">
                    <a href="<?= RUTA_BASE ?>views/admin/fichas/index.php" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success shadow-sm font-weight-bold">
                        <i class="fas fa-save mr-1"></i> Guardar Ficha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';
