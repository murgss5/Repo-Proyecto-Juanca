<?php
/**
 * Vista: Listado de instructores (Administrador).
 * Muestra el listado real de instructores con opciones de editar y eliminar.
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$db = (new Conexion())->conectar();
$instructores = $db->query(
    "SELECT u.id_usuario, u.identificacion, CONCAT(u.nombre, CHAR(32), u.apellido) AS nombre, u.telefono, COUNT(f.id_ficha) AS fichas 
     FROM usuario u 
     LEFT JOIN ficha f ON f.fk_usuario = u.id_usuario 
     WHERE u.fk_rol = 2 
     GROUP BY u.id_usuario 
     ORDER BY u.apellido, u.nombre"
)->fetchAll();

$paginaTitulo = 'Instructores';
$menuActivo   = 'instructores';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Instructores'],
];

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

<!-- Tarjeta Principal de Instructores -->
<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-2 mb-sm-0">
            <i class="fas fa-chalkboard-teacher text-success mr-2"></i> Listado de Instructores
        </h3>
        <div class="card-tools ml-auto">
            <a href="<?= RUTA_BASE ?>views/admin/instructores/create.php" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-plus mr-1"></i> Nuevo Instructor
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Barra superior interna: Buscador -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-5 col-sm-12 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control buscador-tabla" placeholder="Buscar instructor por nombre, documento o teléfono...">
                </div>
            </div>
        </div>

        <!-- Tabla de Datos Optimizada para Moviles -->
        <div class="table-responsive">
            <table class="table table-hover table-striped text-nowrap tabla-datos mb-0" data-empty-message="No se encontraron instructores registrados.">
                <thead class="thead-light">
                    <tr>
                        <th class="col-documento" style="width: 110px;">Documento</th>
                        <th>Nombre Completo</th>
                        <th class="d-none d-md-table-cell">Teléfono</th>
                        <th class="d-none d-md-table-cell">Fichas Asignadas</th>
                        <th class="text-center col-acciones" style="width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructores as $i): ?>
                        <tr>
                            <td class="align-middle font-weight-bold col-documento"><?= htmlspecialchars($i['identificacion']) ?></td>
                            <td class="align-middle">
                                <span class="font-weight-bold text-dark"><?= htmlspecialchars($i['nombre']) ?></span>
                                <div class="d-md-none text-muted small mt-1">
                                    <?php if (!empty($i['telefono'])): ?>
                                        <span class="mr-2"><i class="fas fa-phone-alt text-success mr-1"></i><?= htmlspecialchars($i['telefono']) ?></span>
                                    <?php endif; ?>
                                    <span class="badge <?= (int)$i['fichas'] > 0 ? 'badge-info' : 'badge-secondary' ?> px-2 py-0">
                                        <i class="fas fa-layer-group mr-1"></i><?= (int)$i['fichas'] ?> <?= (int)$i['fichas'] === 1 ? 'ficha' : 'fichas' ?>
                                    </span>
                                </div>
                            </td>
                            <td class="align-middle text-muted d-none d-md-table-cell"><?= htmlspecialchars($i['telefono'] ?? 'Sin registro') ?></td>
                            <td class="align-middle d-none d-md-table-cell">
                                <span class="badge <?= (int)$i['fichas'] > 0 ? 'badge-info' : 'badge-secondary' ?> px-2 py-1">
                                    <i class="fas fa-layer-group mr-1"></i> <?= (int)$i['fichas'] ?> <?= (int)$i['fichas'] === 1 ? 'ficha' : 'fichas' ?>
                                </span>
                            </td>
                            <td class="text-center align-middle col-acciones">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-warning btn-sm shadow-sm" href="<?= RUTA_BASE ?>views/admin/instructores/edit.php?id=<?= htmlspecialchars($i['id_usuario']) ?>" title="Editar instructor">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="<?= RUTA_BASE ?>controllers/gestionar_usuario.php" method="post" class="d-inline ml-1" onsubmit="return confirm('¿Está seguro de eliminar este instructor?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="rol" value="2">
                                        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($i['id_usuario']) ?>">
                                        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm shadow-sm" title="Eliminar instructor">
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
