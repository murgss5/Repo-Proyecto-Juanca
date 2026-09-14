<?php
// carga autenticacion y verifica rol administrador
require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);
// carga constantes y conexion
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

// genera token csrf si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// determina entidad y modo edicion
$entidad = $entidadAcademica ?? 'ficha';
$edicion = $edicionAcademica ?? false;
$modulo = $entidad === 'ficha' ? 'fichas' : 'horarios';

// conecta a la base de datos
$conexion = (new Conexion())->conectar();

// datos por defecto segun entidad
$datos = $entidad === 'ficha'
    ? ['id_ficha' => '', 'nombre_programa' => '', 'jornada' => '', 'fk_usuario' => '']
    : ['id_horario' => '', 'fk_ficha' => '', 'dia_semana' => '', 'entrada' => '', 'salida' => ''];

// si es edicion, carga datos existentes
if ($edicion) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $clave = $entidad === 'ficha' ? 'id_ficha' : 'id_horario';
    $q = $conexion->prepare('SELECT * FROM ' . $entidad . ' WHERE ' . $clave . ' = ?');
    $q->execute([$id]);
    $datos = $q->fetch();
    if (!$datos) {
        header('Location: index.php?error=Registro no encontrado.');
        exit;
    }
}

// obtiene instructores para fichas
$instructores = $entidad === 'ficha'
    ? $conexion->query('SELECT id_usuario, nombre, apellido FROM usuario WHERE fk_rol = 2 ORDER BY nombre, apellido')->fetchAll()
    : [];

// obtiene fichas para horarios
$fichas = $entidad === 'horario'
    ? $conexion->query('SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha')->fetchAll()
    : [];

// variables para layout
$paginaTitulo = ($edicion ? 'Editar ' : 'Nuevo ') . $entidad;
$menuActivo = $modulo;
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => ucfirst($modulo), 'url' => RUTA_BASE . 'views/admin/' . $modulo . '/index.php'],
    ['label' => $paginaTitulo],
];

// inicia captura de salida
ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= htmlspecialchars($paginaTitulo) ?></h3>
    </div>
    <form method="post" action="<?= RUTA_BASE ?>controllers/gestionar_academico.php">
        <div class="card-body">
            <input type="hidden" name="entidad" value="<?= $entidad ?>">
            <input type="hidden" name="accion" value="<?= $edicion ? 'editar' : 'crear' ?>">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

            <?php if ($entidad === 'ficha'): ?>
                <input type="hidden" name="id_ficha" value="<?= $datos['id_ficha'] ?>">

                <div class="form-group">
                    <label>Programa de formación *</label>
                    <input class="form-control" name="nombre_programa" maxlength="100" required
                        value="<?= htmlspecialchars($datos['nombre_programa']) ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Jornada *</label>
                        <select class="form-control" name="jornada" required>
                            <option value="" disabled selected>Seleccione una jornada</option>
                            <option value="Diurna" <?= ($datos['jornada'] === 'Diurna') ? 'selected' : '' ?>>Diurna</option>
                            <option value="Tarde" <?= ($datos['jornada'] === 'Tarde') ? 'selected' : '' ?>>Tarde</option>
                            <option value="Nocturna" <?= ($datos['jornada'] === 'Nocturna') ? 'selected' : '' ?>>Nocturna
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Instructor *</label>
                        <select class="form-control" name="id_instructor" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($instructores as $i): ?>
                                <option value="<?= $i['id_usuario'] ?>"
                                    <?= strval($datos['fk_usuario']) === strval($i['id_usuario']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($i['nombre'] . ' ' . $i['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

            <?php else: ?>
                <input type="hidden" name="id_horario" value="<?= $datos['id_horario'] ?>">

                <div class="form-group">
                    <label>Ficha *</label>
                    <select class="form-control" name="id_ficha" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($fichas as $f): ?>
                            <option value="<?= $f['id_ficha'] ?>" <?= strval($datos['fk_ficha']) === strval($f['id_ficha']) ? 'selected' : '' ?>>
                                Ficha #<?= $f['id_ficha'] ?> — <?= htmlspecialchars($f['nombre_programa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Día *</label>
                        <select class="form-control" name="dia_semana" required>
                            <?php foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $dia): ?>
                                <option <?= $datos['dia_semana'] === $dia ? 'selected' : '' ?>><?= $dia ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Hora de entrada *</label>
                        <input class="form-control" type="time" name="entrada" required
                            value="<?= htmlspecialchars(substr($datos['entrada'], 0, 5)) ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Hora de salida *</label>
                        <input class="form-control" type="time" name="salida" required
                            value="<?= htmlspecialchars(substr($datos['salida'], 0, 5)) ?>">
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-right">
            <a class="btn btn-secondary" href="<?= RUTA_BASE ?>views/admin/<?= $modulo ?>/index.php">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
    </form>
</div>
<?php
$contenido = ob_get_clean();
include __DIR__ . '/../layouts/layout_admin.php';