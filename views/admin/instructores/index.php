<?php
require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$db = (new Conexion())->conectar();
$instructores = $db->query("
    SELECT 
        u.id_usuario,
        u.identificacion,
        CONCAT(u.nombre, CHAR(32), u.apellido) AS nombre,
        u.telefono,
        COUNT(f.id_ficha) AS fichas 
    FROM usuario u 
    LEFT JOIN ficha f ON f.fk_usuario = u.id_usuario 
    WHERE u.fk_rol = 2 
    GROUP BY u.id_usuario 
    ORDER BY u.apellido, u.nombre
")->fetchAll();

$paginaTitulo = 'Instructores';
$menuActivo   = 'instructores';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Instructores'],
];

ob_start();
?>

<!-- Contenedor Principal Estándar Enterprise -->
<div class="card card-outline card-primary shadow-sm">
    <div class="card-header border-bottom-0">
        <h3 class="card-title font-weight-bold">
            <i class="fas fa-chalkboard-teacher mr-2 text-primary"></i> Listado de Instructores
        </h3>
        <div class="card-tools">
            <a href="<?= RUTA_BASE ?>views/admin/instructores/create.php" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus mr-1"></i> Nuevo instructor
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-1"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <!-- Barra de búsqueda rápida integrada -->
        <div class="row mb-3">
            <div class="col-md-4 col-sm-6">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control buscador-tabla" placeholder="Buscar instructor...">
                </div>
            </div>
        </div>

        <!-- Tabla de instructores -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle table-sm tabla-datos">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0">Documento</th>
                        <th class="border-top-0">Nombre</th>
                        <th class="border-top-0">Teléfono</th>
                        <th class="text-center border-top-0">Fichas asignadas</th>
                        <th class="text-center border-top-0" style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructores as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['identificacion']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($i['nombre']) ?></td>
                            <td><?= htmlspecialchars($i['telefono'] ?? 'Sin registro') ?></td>
                            <td class="text-center">
                                <span class="badge badge-info px-2 py-1"><?= (int)$i['fichas'] ?></span>
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-warning" href="<?= RUTA_BASE ?>views/admin/instructores/edit.php?id=<?= $i['id_usuario'] ?>" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="<?= RUTA_BASE ?>controllers/gestionar_usuario.php" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este instructor?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="rol" value="2">
                                    <input type="hidden" name="id_usuario" value="<?= $i['id_usuario'] ?>">
                                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                                    <button class="btn btn-sm btn-danger" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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
include __DIR__ . '/../../layouts/layout_admin.php';
