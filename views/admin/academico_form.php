<?php
/**
 * Formulario unificado de gestión académica: Fichas y Horarios
 * Permite la creación y edición de Fichas y Horarios con interfaz AdminLTE moderna.
 */

// Carga autenticación y verifica rol administrador
require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);

// Carga constantes y conexión
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Determina entidad y modo edición
$entidad = $entidadAcademica ?? 'ficha';
$edicion = $edicionAcademica ?? false;
$modulo = $entidad === 'ficha' ? 'fichas' : 'horarios';

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

// Datos por defecto según entidad
$datos = $entidad === 'ficha'
    ? ['id_ficha' => '', 'nombre_programa' => '', 'jornada' => '', 'fk_usuario' => '', 'ambiente_principal' => '']
    : [
        'id_horario' => '', 'fk_ficha' => '', 'instructor_id' => '', 'dia_semana' => '',
        'entrada' => '', 'salida' => '', 'competencia' => '', 'tipo_bloque' => 'completo',
        'ambiente' => '', 'estado' => 'Activo', 'observaciones' => '', 'fecha_inicio' => '', 'fecha_fin' => ''
      ];

// Si es edición, carga y valida los datos existentes con tolerancia de parámetros
if ($edicion) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $id = (int)$_GET['id'];
    }
    if (!$id && isset($_POST['id']) && is_numeric($_POST['id'])) {
        $id = (int)$_POST['id'];
    }

    $vistaParam = !empty($_GET['vista']) ? '&vista=' . urlencode($_GET['vista']) : '';

    if (!$id || $id <= 0) {
        header('Location: ' . RUTA_BASE . 'views/admin/' . $modulo . '/index.php?error=' . urlencode('Identificador de ' . $entidad . ' no especificado o inválido.') . $vistaParam);
        exit;
    }

    $clave = $entidad === 'ficha' ? 'id_ficha' : 'id_horario';
    try {
        $q = $conexion->prepare('SELECT * FROM ' . $entidad . ' WHERE ' . $clave . ' = ?');
        $q->execute([$id]);
        $datos = $q->fetch();
    } catch (PDOException $e) {
        error_log("Error al consultar {$entidad} con ID {$id}: " . $e->getMessage());
        $datos = false;
    }

    if (!$datos) {
        header('Location: ' . RUTA_BASE . 'views/admin/' . $modulo . '/index.php?error=' . urlencode('El registro de ' . $entidad . ' no fue encontrado o ya no existe.') . $vistaParam);
        exit;
    }
}

// Verifica historial de sesiones y asistencias para proteger la ficha si existen registros previos
$tieneHistorial = false;
$totalSesionesHist = 0;
$totalAsistenciasHist = 0;
if ($edicion && $entidad === 'horario' && !empty($datos['id_horario'])) {
    try {
        $qHistS = $conexion->prepare('SELECT COUNT(*) FROM sesiones_jornada WHERE horario_id = ?');
        $qHistS->execute([(int)$datos['id_horario']]);
        $totalSesionesHist = (int)$qHistS->fetchColumn();
    } catch (PDOException $e) {
        $totalSesionesHist = 0;
    }

    try {
        // Vinculación segura de asistencias mediante sesiones_jornada
        $qHistA = $conexion->prepare('SELECT COUNT(*) FROM asistencia a INNER JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion WHERE s.horario_id = ?');
        $qHistA->execute([(int)$datos['id_horario']]);
        $totalAsistenciasHist = (int)$qHistA->fetchColumn();
    } catch (PDOException $e) {
        $totalAsistenciasHist = 0;
    }

    $tieneHistorial = ($totalSesionesHist > 0 || $totalAsistenciasHist > 0);
}

// Obtiene instructores activos para asignación
$instructores = [];
try {
    $instructores = $conexion->query('SELECT id_usuario, nombre, apellido FROM usuario WHERE fk_rol = 2 ORDER BY nombre, apellido')->fetchAll();
} catch (PDOException $e) {
    $instructores = [];
}

// Obtiene fichas activas para asignación de horarios
$fichas = [];
if ($entidad === 'horario') {
    try {
        $fichas = $conexion->query('SELECT id_ficha, nombre_programa, ambiente_principal FROM ficha ORDER BY id_ficha')->fetchAll();
    } catch (PDOException $e) {
        $fichas = [];
    }
}

// Variables para layout institucional
$tituloEntidad = $entidad === 'ficha' ? 'Ficha de Formación' : 'Horario de Formación';
$paginaTitulo = ($edicion ? 'Editar ' : 'Nueva ') . $tituloEntidad;
$menuActivo = $modulo;
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => ucfirst($modulo), 'url' => RUTA_BASE . 'views/admin/' . $modulo . '/index.php'],
    ['label' => $paginaTitulo],
];

// Inicia captura de salida
ob_start();
?>

<div class="container-fluid py-2">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            
            <div class="card card-outline card-success shadow-sm">
                <!-- Encabezado con título e icono institucional -->
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold text-dark mb-0">
                        <i class="fas <?= $entidad === 'ficha' ? 'fa-layer-group' : 'fa-calendar-alt' ?> text-success mr-2"></i> <?= htmlspecialchars($paginaTitulo) ?>
                    </h3>
                    <div class="card-tools ml-auto">
                        <a href="<?= RUTA_BASE ?>views/admin/<?= $modulo ?>/index.php<?= !empty($_GET['vista']) ? '?vista=' . urlencode($_GET['vista']) : '' ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Volver a <?= $modulo ?>
                        </a>
                    </div>
                </div>

                <form method="post" action="<?= RUTA_BASE ?>controllers/gestionar_academico.php" autocomplete="off">
                    <div class="card-body py-4 px-4">
                        <!-- Campos de control ocultos -->
                        <input type="hidden" name="entidad" value="<?= htmlspecialchars($entidad) ?>">
                        <input type="hidden" name="accion" value="<?= $edicion ? 'editar' : 'crear' ?>">
                        <input type="hidden" name="vista" value="<?= htmlspecialchars($_GET['vista'] ?? 'tabla') ?>">
                        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

                        <?php if ($entidad === 'ficha'): ?>
                            <!-- ========================================== -->
                            <!-- FORMULARIO DE FICHAS                       -->
                            <!-- ========================================== -->
                            <?php if ($edicion): ?>
                                <input type="hidden" name="id_ficha" value="<?= htmlspecialchars($datos['id_ficha']) ?>">
                            <?php endif; ?>

                            <!-- SECCIÓN 1: Datos de la Ficha / Programa -->
                            <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2">
                                <i class="fas fa-graduation-cap text-success mr-2"></i> Información del Programa
                            </h6>

                            <div class="row">
                                <?php if ($edicion): ?>
                                    <div class="col-md-4 form-group mb-3">
                                        <label class="font-weight-bold text-dark">Código / Número de Ficha</label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                            </div>
                                            <input type="text" class="form-control bg-light font-weight-bold" value="#<?= htmlspecialchars($datos['id_ficha']) ?>" readonly>
                                        </div>
                                        <small class="form-text text-muted">Identificador único asignado por el sistema.</small>
                                    </div>
                                <?php endif; ?>

                                <div class="<?= $edicion ? 'col-md-8' : 'col-md-12' ?> form-group mb-3">
                                    <label for="nombre_programa" class="font-weight-bold text-dark">
                                        Nombre del Programa de Formación <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-book-reader"></i></span>
                                        </div>
                                        <input type="text" id="nombre_programa" name="nombre_programa" class="form-control" maxlength="100" placeholder="Ej. Análisis y Desarrollo de Software" required value="<?= htmlspecialchars($datos['nombre_programa'] ?? '') ?>" <?= $edicion ? '' : 'autofocus' ?>>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: Configuración y Asignación -->
                            <h6 class="text-muted font-weight-bold mb-3 mt-4 border-bottom pb-2">
                                <i class="fas fa-cog text-success mr-2"></i> Configuración y Asignación
                            </h6>

                            <div class="row">
                                <div class="col-md-4 form-group mb-3">
                                    <label for="jornada" class="font-weight-bold text-dark">
                                        Jornada <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-sun"></i></span>
                                        </div>
                                        <select id="jornada" name="jornada" class="form-control" required>
                                            <option value="" disabled <?= empty($datos['jornada']) ? 'selected' : '' ?>>Seleccione una jornada...</option>
                                            <option value="Diurna" <?= (($datos['jornada'] ?? '') === 'Diurna') ? 'selected' : '' ?>>Diurna</option>
                                            <option value="Tarde" <?= (($datos['jornada'] ?? '') === 'Tarde') ? 'selected' : '' ?>>Tarde</option>
                                            <option value="Nocturna" <?= (($datos['jornada'] ?? '') === 'Nocturna') ? 'selected' : '' ?>>Nocturna</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4 form-group mb-3">
                                    <label for="id_instructor" class="font-weight-bold text-dark">
                                        Instructor Líder <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                        </div>
                                        <select id="id_instructor" name="id_instructor" class="form-control" required>
                                            <option value="">Seleccione un instructor...</option>
                                            <?php foreach ($instructores as $i): ?>
                                                <option value="<?= $i['id_usuario'] ?>" <?= strval($datos['fk_usuario'] ?? '') === strval($i['id_usuario']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($i['nombre'] . ' ' . $i['apellido']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4 form-group mb-3">
                                    <label for="ambiente_principal" class="font-weight-bold text-dark">
                                        Ambiente Principal <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-door-open"></i></span>
                                        </div>
                                        <input type="text" id="ambiente_principal" name="ambiente_principal" class="form-control" maxlength="100" placeholder="Ej. Taller 9, Aula 101, Lab 3" value="<?= htmlspecialchars($datos['ambiente_principal'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- ========================================== -->
                            <!-- FORMULARIO DE HORARIOS                     -->
                            <!-- ========================================== -->
                            <?php if ($edicion): ?>
                                <input type="hidden" name="id_horario" value="<?= htmlspecialchars($datos['id_horario']) ?>">
                            <?php endif; ?>

                            <?php if ($tieneHistorial): ?>
                                <div class="alert alert-warning border-0 shadow-sm py-2 mb-4 d-flex align-items-center">
                                    <i class="fas fa-shield-alt text-warning fa-2x mr-3"></i>
                                    <div>
                                        <strong class="text-dark">Aviso de integridad institucional:</strong><br>
                                        <span class="text-dark">Este horario cuenta con historial registrado (<strong><?= $totalSesionesHist ?></strong> sesión(es) de jornada y <strong><?= $totalAsistenciasHist ?></strong> asistencia(s)). Por seguridad y trazabilidad, la <strong>ficha</strong> está bloqueada. Puede actualizar libremente el instructor asignado, día, franja horaria, ambiente y demás datos.</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- SECCIÓN 1: Vinculación Académica -->
                            <h6 class="text-muted font-weight-bold mb-3 border-bottom pb-2">
                                <i class="fas fa-layer-group text-success mr-2"></i> Vinculación Académica
                            </h6>

                            <div class="row">
                                <div class="col-md-6 form-group mb-3">
                                    <label for="id_ficha" class="font-weight-bold text-dark">
                                        Ficha de Formación <span class="text-danger">*</span>
                                        <?php if ($tieneHistorial): ?>
                                            <span class="badge badge-warning ml-1"><i class="fas fa-lock"></i> Bloqueada por historial</span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        </div>
                                        <?php if ($tieneHistorial): ?>
                                            <select class="form-control bg-light" disabled>
                                                <?php foreach ($fichas as $f): ?>
                                                    <option value="<?= $f['id_ficha'] ?>" <?= strval($datos['fk_ficha'] ?? '') === strval($f['id_ficha']) ? 'selected' : '' ?>>
                                                        Ficha #<?= $f['id_ficha'] ?> — <?= htmlspecialchars($f['nombre_programa']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="id_ficha" value="<?= htmlspecialchars($datos['fk_ficha']) ?>">
                                        <?php else: ?>
                                            <select id="id_ficha" name="id_ficha" class="form-control" required <?= $edicion ? '' : 'autofocus' ?>>
                                                <option value="">Seleccione una ficha formativa...</option>
                                                <?php foreach ($fichas as $f): ?>
                                                    <option value="<?= $f['id_ficha'] ?>" <?= strval($datos['fk_ficha'] ?? '') === strval($f['id_ficha']) ? 'selected' : '' ?>>
                                                        Ficha #<?= $f['id_ficha'] ?> — <?= htmlspecialchars($f['nombre_programa']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-text text-muted">Grupo de formación al que aplica este horario.</small>
                                </div>

                                <div class="col-md-6 form-group mb-3">
                                    <label for="instructor_id" class="font-weight-bold text-dark">
                                        Instructor Asignado
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-chalkboard-teacher"></i></span>
                                        </div>
                                        <select id="instructor_id" name="instructor_id" class="form-control">
                                            <option value="">(Usar el instructor líder asignado a la ficha)</option>
                                            <?php foreach ($instructores as $i): ?>
                                                <option value="<?= $i['id_usuario'] ?>" <?= strval($datos['instructor_id'] ?? '') === strval($i['id_usuario']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($i['nombre'] . ' ' . $i['apellido']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <small class="form-text text-muted">Permite asignar instructores específicos para este bloque.</small>
                                </div>
                            </div>

                            <!-- SECCIÓN 2: Franja Horaria y Programación -->
                            <h6 class="text-muted font-weight-bold mb-3 mt-4 border-bottom pb-2">
                                <i class="fas fa-clock text-success mr-2"></i> Franja Horaria y Programación
                            </h6>

                            <div class="row">
                                <div class="col-md-4 form-group mb-3">
                                    <label for="dia_semana" class="font-weight-bold text-dark">
                                        Día de la Semana <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                        </div>
                                        <select id="dia_semana" name="dia_semana" class="form-control" required>
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
                                </div>

                                <div class="col-md-4 form-group mb-3">
                                    <label for="entrada" class="font-weight-bold text-dark">
                                        Hora de Entrada <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-sign-in-alt"></i></span>
                                        </div>
                                        <input type="time" id="entrada" name="entrada" class="form-control" required value="<?= htmlspecialchars(substr($datos['entrada'] ?? '', 0, 5)) ?>">
                                    </div>
                                </div>

                                <div class="col-md-4 form-group mb-3">
                                    <label for="salida" class="font-weight-bold text-dark">
                                        Hora de Salida <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-sign-out-alt"></i></span>
                                        </div>
                                        <input type="time" id="salida" name="salida" class="form-control" required value="<?= htmlspecialchars(substr($datos['salida'] ?? '', 0, 5)) ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 3: Formación y Ambiente -->
                            <h6 class="text-muted font-weight-bold mb-3 mt-4 border-bottom pb-2">
                                <i class="fas fa-book text-success mr-2"></i> Formación y Ambiente
                            </h6>

                            <div class="row">
                                <div class="col-md-6 form-group mb-3">
                                    <label for="competencia" class="font-weight-bold text-dark">
                                        Formación / Competencia / Nombre del Bloque
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                        </div>
                                        <input type="text" id="competencia" name="competencia" class="form-control" maxlength="100" placeholder="Ej. TÉCNICO - COMERCIO Y SERVICIOS" value="<?= htmlspecialchars($datos['competencia'] ?? '') ?>">
                                    </div>
                                    <small class="form-text text-muted">Aparece destacado en la tarjeta visual del calendario.</small>
                                </div>

                                <div class="col-md-3 form-group mb-3">
                                    <label for="ambiente" class="font-weight-bold text-dark">
                                        Ambiente / Aula
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-door-open"></i></span>
                                        </div>
                                        <input type="text" id="ambiente" name="ambiente" class="form-control" maxlength="100" placeholder="Ej. Aula 201" value="<?= htmlspecialchars($datos['ambiente'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="col-md-3 form-group mb-3">
                                    <label for="tipo_bloque" class="font-weight-bold text-dark">
                                        Tipo de Bloque
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-th-large"></i></span>
                                        </div>
                                        <select id="tipo_bloque" name="tipo_bloque" class="form-control">
                                            <option value="completo" <?= (($datos['tipo_bloque'] ?? 'completo') === 'completo') ? 'selected' : '' ?>>Continuo (Completo)</option>
                                            <option value="partido" <?= (($datos['tipo_bloque'] ?? '') === 'partido') ? 'selected' : '' ?>>Partido</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN 4: Vigencia, Estado y Observaciones -->
                            <h6 class="text-muted font-weight-bold mb-3 mt-4 border-bottom pb-2">
                                <i class="fas fa-calendar-check text-success mr-2"></i> Vigencia y Observaciones
                            </h6>

                            <div class="row">
                                <div class="col-md-3 form-group mb-3">
                                    <label for="estado" class="font-weight-bold text-dark">
                                        Estado
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                        </div>
                                        <select id="estado" name="estado" class="form-control">
                                            <option value="Activo" <?= (($datos['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                                            <option value="Inactivo" <?= (($datos['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3 form-group mb-3">
                                    <label for="fecha_inicio" class="font-weight-bold text-dark">
                                        Inicio Vigencia (Opcional)
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-calendar-plus"></i></span>
                                        </div>
                                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($datos['fecha_inicio'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="col-md-3 form-group mb-3">
                                    <label for="fecha_fin" class="font-weight-bold text-dark">
                                        Fin Vigencia (Opcional)
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-calendar-minus"></i></span>
                                        </div>
                                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($datos['fecha_fin'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="col-md-3 form-group mb-3">
                                    <label for="observaciones" class="font-weight-bold text-dark">
                                        Observaciones (Opcional)
                                    </label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-comment-alt"></i></span>
                                        </div>
                                        <input type="text" id="observaciones" name="observaciones" class="form-control" maxlength="255" placeholder="Notas adicionales" value="<?= htmlspecialchars($datos['observaciones'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pie de Tarjeta con Botones de Acción Estilizados -->
                    <div class="card-footer text-right bg-white border-top py-3">
                        <a href="<?= RUTA_BASE ?>views/admin/<?= $modulo ?>/index.php<?= !empty($_GET['vista']) ? '?vista=' . urlencode($_GET['vista']) : '' ?>" class="btn btn-outline-secondary mr-2">
                            <i class="fas fa-times mr-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-success font-weight-bold shadow-sm">
                            <i class="fas fa-save mr-1"></i> <?= $edicion ? 'Guardar cambios' : 'Guardar ' . ($entidad === 'ficha' ? 'Ficha' : 'Horario') ?>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../layouts/layout_admin.php';
