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
    : [
        'id_horario' => '', 'fk_ficha' => '', 'instructor_id' => '', 'dia_semana' => '',
        'entrada' => '', 'salida' => '', 'competencia' => '', 'tipo_bloque' => 'completo',
        'ambiente' => '', 'estado' => 'Activo', 'observaciones' => '', 'fecha_inicio' => '', 'fecha_fin' => ''
      ];

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

// obtiene instructores activos para fichas y horarios
$instructores = $conexion->query('SELECT id_usuario, nombre, apellido FROM usuario WHERE fk_rol = 2 ORDER BY nombre, apellido')->fetchAll();

// obtiene fichas para horarios
$fichas = $entidad === 'horario'
    ? $conexion->query('SELECT id_ficha, nombre_programa, ambiente_principal FROM ficha ORDER BY id_ficha')->fetchAll()
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

                <div class="form-group">
                    <label>Ambiente principal *</label>
                    <input class="form-control" name="ambiente_principal" maxlength="100"
                        value="<?= htmlspecialchars($datos['ambiente_principal'] ?? '') ?>">
                    <small class="form-text text-muted">Ej: Taller 9, Aula 101, Laboratorio 3</small>
                </div>

            <?php else: ?>
                <input type="hidden" name="id_horario" value="<?= $datos['id_horario'] ?>">

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Ficha *</label>
                        <select class="form-control" name="id_ficha" required>
                            <option value="">Seleccione una ficha...</option>
                            <?php foreach ($fichas as $f): ?>
                                <option value="<?= $f['id_ficha'] ?>" <?= strval($datos['fk_ficha']) === strval($f['id_ficha']) ? 'selected' : '' ?>>
                                    Ficha #<?= $f['id_ficha'] ?> — <?= htmlspecialchars($f['nombre_programa']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 form-group">
                        <label>Instructor asignado</label>
                        <select class="form-control" name="instructor_id">
                            <option value="">(Usar el instructor líder asignado a la ficha)</option>
                            <?php foreach ($instructores as $i): ?>
                                <option value="<?= $i['id_usuario'] ?>" <?= strval($datos['instructor_id'] ?? '') === strval($i['id_usuario']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($i['nombre'] . ' ' . $i['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Permite asignar instructores diferentes a clases específicas de la misma ficha.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Día de la semana *</label>
                        <select class="form-control" name="dia_semana" required>
                            <?php 
                            $diasSemanaLista = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                            $mapaNorm = ['miercoles' => 'miércoles', 'sabado' => 'sábado'];
                            $diaActualNorm = mb_strtolower($datos['dia_semana'] ?? '', 'UTF-8');
                            $diaActualNorm = $mapaNorm[$diaActualNorm] ?? $diaActualNorm;
                            foreach ($diasSemanaLista as $dia): 
                                $diaNorm = mb_strtolower($dia, 'UTF-8');
                                $selected = ($diaActualNorm === $diaNorm) ? 'selected' : '';
                            ?>
                                <option value="<?= $dia ?>" <?= $selected ?>><?= $dia ?></option>
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

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Formación / Competencia / Nombre del Bloque</label>
                        <input class="form-control" type="text" name="competencia" maxlength="100"
                            placeholder="Ej: TÉCNICO - ESPECIALISTA - COMERCIO Y SERVICIOS 5"
                            value="<?= htmlspecialchars($datos['competencia'] ?? '') ?>">
                        <small class="form-text text-muted">Aparece destacado dentro del bloque verde del calendario.</small>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Ambiente / Aula</label>
                        <input class="form-control" type="text" name="ambiente" maxlength="100"
                            placeholder="Ej: Taller 9, Aula 201"
                            value="<?= htmlspecialchars($datos['ambiente'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Tipo de bloque</label>
                        <select class="form-control" name="tipo_bloque">
                            <option value="completo" <?= (($datos['tipo_bloque'] ?? 'completo') === 'completo') ? 'selected' : '' ?>>Continuo (Completo)</option>
                            <option value="partido" <?= (($datos['tipo_bloque'] ?? '') === 'partido') ? 'selected' : '' ?>>Partido</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Estado</label>
                        <select class="form-control" name="estado">
                            <option value="Activo" <?= (($datos['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                            <option value="Inactivo" <?= (($datos['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Fecha inicio vigencia (opcional)</label>
                        <input class="form-control" type="date" name="fecha_inicio"
                            value="<?= htmlspecialchars($datos['fecha_inicio'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Fecha fin vigencia (opcional)</label>
                        <input class="form-control" type="date" name="fecha_fin"
                            value="<?= htmlspecialchars($datos['fecha_fin'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Observaciones (opcional)</label>
                        <input class="form-control" type="text" name="observaciones" maxlength="255"
                            placeholder="Notas complementarias"
                            value="<?= htmlspecialchars($datos['observaciones'] ?? '') ?>">
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