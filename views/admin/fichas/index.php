<?php
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}
$conexion = (new Conexion())->conectar();
$fichas = $conexion->query(
    "SELECT f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal,
            CONCAT(u.nombre, ' ', u.apellido) instructor,
            COUNT(ap.id_aprendiz) aprendices
     FROM ficha f
     INNER JOIN usuario u ON u.id_usuario = f.fk_usuario
     LEFT JOIN aprendiz ap ON ap.fk_ficha = f.id_ficha
     GROUP BY f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal, u.nombre, u.apellido
     ORDER BY f.id_ficha DESC"
)->fetchAll();

$paginaTitulo = 'Fichas';
$menuActivo   = 'fichas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Fichas']
];

ob_start();
?>

<?php if (isset($_GET['mensaje'])): ?>
<div class="alert alert-success"><?= htmlspecialchars($_GET['mensaje']) ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-layer-group text-sena"></i> Fichas de formación</h3>
        <div class="card-tools">
            <a href="<?= RUTA_BASE ?>views/admin/fichas/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nueva ficha
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-bordered tabla-datos">
                <thead>
                    <tr>
                        <th>Ficha</th>
                        <th>Programa</th>
                        <th>Jornada</th>
                        <th>Ambiente principal</th>
                        <th>Instructor</th>
                        <th>Aprendices</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fichas as $f): ?>
                    <tr>
                        <td><span class="badge badge-info">#<?= $f['id_ficha'] ?></span></td>
                        <td><?= htmlspecialchars($f['nombre_programa']) ?></td>
                        <td><?= htmlspecialchars($f['jornada']) ?></td>
                        <td><?= htmlspecialchars($f['ambiente_principal'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($f['instructor']) ?></td>
                        <td><?= $f['aprendices'] ?></td>
                        <td class="text-center">
                            <a class="btn btn-warning btn-sm" href="<?= RUTA_BASE ?>views/admin/fichas/edit.php?id=<?= $f['id_ficha'] ?>" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form class="d-inline" method="post" action="<?= RUTA_BASE ?>controllers/gestionar_academico.php" onsubmit="return confirm('¿Eliminar esta ficha?');">
                                <input type="hidden" name="entidad" value="ficha">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_ficha" value="<?= $f['id_ficha'] ?>">
                                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                                <button class="btn btn-danger btn-sm" title="Eliminar"><i class="fas fa-trash"></i></button>
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