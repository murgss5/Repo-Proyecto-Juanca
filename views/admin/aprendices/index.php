<?php
/**
 * Vista: Listado de aprendices (Administrador).
 * Muestra el listado real de aprendices con opciones de editar y eliminar.
 */

// Carga la autenticacion y verifica que sea administrador
require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);

// Carga constantes y conexion a la base de datos
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

// Variables para el layout
$paginaTitulo = 'Aprendices';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices'],
];

$conexion = (new Conexion())->conectar();

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Obtiene las fichas para el filtro
$consultaFichas = $conexion->query('SELECT id_ficha FROM ficha ORDER BY id_ficha');
$fichasRegistradas = $consultaFichas->fetchAll();

// Consulta los aprendices con sus datos relacionados
$consultaAprendices = $conexion->query(
    'SELECT
        aprendiz.id_aprendiz,
        aprendiz.fk_usuario AS id_usuario,
        usuario.identificacion AS documento,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
        usuario.telefono,
        ficha.id_ficha,
        aprendiz.codigo_rfid
     FROM aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     ORDER BY usuario.apellido, usuario.nombre'
);
$aprendicesRegistrados = $consultaAprendices->fetchAll();

ob_start();
?>

<!-- Alertas de estado -->
<?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Tarjeta Principal de Aprendices -->
<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-user-graduate text-success mr-2"></i> Listado de Aprendices
        </h3>
        <div class="card-tools ml-auto">
            <a href="<?= RUTA_BASE ?>views/admin/aprendices/importar.php" class="btn btn-outline-success btn-sm font-weight-bold shadow-sm mr-2">
                <i class="fas fa-file-excel mr-1"></i> Importar Excel
            </a>
            <a href="<?= RUTA_BASE ?>views/admin/aprendices/create.php" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-plus mr-1"></i> Nuevo Aprendiz
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Barra superior interna: Buscador y Filtros -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-5 col-sm-12 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control buscador-tabla" placeholder="Buscar aprendiz por nombre, documento o RFID...">
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-layer-group text-muted"></i></span>
                    </div>
                    <select class="form-control custom-select filtro-tabla" data-col="3">
                        <option value="">Todas las fichas</option>
                        <?php foreach ($fichasRegistradas as $ficha): ?>
                            <option value="Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?>">Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabla de Datos -->
        <div class="table-responsive">
            <table class="table table-hover table-striped text-nowrap tabla-datos" data-empty-message="No se encontraron aprendices registrados.">
                <thead class="thead-light">
                    <tr>
                        <th>Documento</th>
                        <th>Nombre Completo</th>
                        <th>Teléfono</th>
                        <th>Ficha</th>
                        <th>Código RFID</th>
                        <th class="text-center" style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aprendicesRegistrados as $aprendiz): ?>
                        <tr>
                            <td class="align-middle font-weight-bold"><?= htmlspecialchars($aprendiz['documento']) ?></td>
                            <td class="align-middle"><?= htmlspecialchars($aprendiz['nombre']) ?></td>
                            <td class="align-middle text-muted"><?= htmlspecialchars($aprendiz['telefono'] ?? 'Sin registro') ?></td>
                            <td class="align-middle">
                                <span class="badge badge-info px-2 py-1">Ficha #<?= htmlspecialchars($aprendiz['id_ficha']) ?></span>
                            </td>
                            <td class="align-middle">
                                <code><?= htmlspecialchars($aprendiz['codigo_rfid']) ?></code>
                            </td>
                            <td class="text-center align-middle">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-warning btn-sm shadow-sm" href="<?= RUTA_BASE ?>views/admin/aprendices/edit.php?id=<?= htmlspecialchars($aprendiz['id_usuario']) ?>" title="Editar aprendiz">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="<?= RUTA_BASE ?>controllers/gestionar_usuario.php" method="post" class="d-inline ml-1" onsubmit="return confirm('¿Está seguro de eliminar este aprendiz?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="rol" value="3">
                                        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($aprendiz['id_usuario']) ?>">
                                        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm shadow-sm" title="Eliminar aprendiz">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();

// Render del layout principal del panel de administracion
include __DIR__ . '/../../layouts/layout_admin.php';
