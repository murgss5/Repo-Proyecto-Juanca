<?php
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}
$conexion = (new Conexion())->conectar();
$horarios = $conexion->query(
    'SELECT h.id_horario, h.dia_semana, h.entrada, h.salida, f.id_ficha, f.nombre_programa,
            CONCAT(u.nombre, " ", u.apellido) instructor
     FROM horario h
     INNER JOIN ficha f ON f.id_ficha = h.fk_ficha
     INNER JOIN usuario u ON u.id_usuario = f.fk_usuario
     ORDER BY f.id_ficha, h.id_horario'
)->fetchAll();

$paginaTitulo = 'Horarios';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios']
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
        <h3 class="card-title"><i class="fas fa-calendar-alt text-sena"></i> Horarios de formación</h3>
        <div class="card-tools">
            <a href="<?= RUTA_BASE ?>views/admin/horarios/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nuevo horario
            </a>
            <a href="<?= RUTA_BASE ?>views/admin/horarios/importar.php" class="btn btn-info btn-sm ml-2">
                <i class="fas fa-file-import"></i> Importar desde Excel
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
                        <th>Día</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Instructor</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($horarios as $h): ?>
                    <tr>
                        <td><span class="badge badge-info">#<?= $h['id_ficha'] ?></span></td>
                        <td><?= htmlspecialchars($h['nombre_programa']) ?></td>
                        <td><?= htmlspecialchars($h['dia_semana']) ?></td>
                        <td><?= htmlspecialchars(substr($h['entrada'], 0, 5)) ?></td>
                        <td><?= htmlspecialchars(substr($h['salida'], 0, 5)) ?></td>
                        <td><?= htmlspecialchars($h['instructor']) ?></td>
                        <td class="text-center">
                            <a class="btn btn-warning btn-sm" href="<?= RUTA_BASE ?>views/admin/horarios/edit.php?id=<?= $h['id_horario'] ?>" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form class="d-inline" method="post" action="<?= RUTA_BASE ?>controllers/gestionar_academico.php" onsubmit="return confirm('¿Eliminar este horario?');">
                                <input type="hidden" name="entidad" value="horario">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id_horario" value="<?= $h['id_horario'] ?>">
                                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                                <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
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