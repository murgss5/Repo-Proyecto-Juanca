<?php
/**
 * controlador para gestionar fichas y horarios desde el panel de administracion
 * permite crear, editar y eliminar fichas y horarios con validaciones
 */

session_start();
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/../config/conexion.php';

/**
 * funcion auxiliar para redirigir con mensaje al listado correspondiente
 * @param string $modulo Nombre del modulo: 'fichas' u 'horarios'
 * @param string $tipo Tipo de mensaje: 'mensaje' o 'error'
 * @param string $texto Texto del mensaje
 */
function volverAcademico(string $modulo, string $tipo, string $texto): void
{
    header('Location: ../views/admin/' . $modulo . '/index.php?' . $tipo . '=' . urlencode($texto));
    exit;
}

// Verifica que sea POST y que el usuario sea administrador (rol 1)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['admin']['id_usuario'])) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Verifica el token CSRF
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    exit('Solicitud no valida.');
}

// Obtiene la entidad (ficha u horario) y la accion
$entidad = $_POST['entidad'] ?? '';
$accion = $_POST['accion'] ?? '';
$modulo = $entidad === 'ficha' ? 'fichas' : ($entidad === 'horario' ? 'horarios' : '');

// Valida que la entidad y accion sean correctas
if ($modulo === '' || !in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    exit('Datos no validos.');
}

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

try {
    // GESTION DE FICHAS
    if ($entidad === 'ficha') {
        $id = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);

        // ELIMINAR FICHA
        if ($accion === 'eliminar') {
            if (!$id) volverAcademico($modulo, 'error', 'Ficha no valida.');
            // Verifica que no tenga aprendices asignados
            $q = $conexion->prepare('SELECT COUNT(*) FROM aprendiz WHERE fk_ficha=?');
            $q->execute([$id]);
            if ($q->fetchColumn() > 0) volverAcademico($modulo, 'error', 'No se puede eliminar una ficha que tiene aprendices asignados.');
            // Elimina la ficha
            $q = $conexion->prepare('DELETE FROM ficha WHERE id_ficha=?');
            $q->execute([$id]);
            if (!$q->rowCount()) volverAcademico($modulo, 'error', 'La ficha no existe.');
            volverAcademico($modulo, 'mensaje', 'Ficha eliminada correctamente.');
        }

        // CREAR O EDITAR FICHA
        $programa = trim($_POST['nombre_programa'] ?? '');
        $jornada = trim($_POST['jornada'] ?? '');
        $instructor = filter_input(INPUT_POST, 'id_instructor', FILTER_VALIDATE_INT);
        $ambiente = trim($_POST['ambiente_principal'] ?? '');

        // jornadas permitidas
        $jornadasPermitidas = ['Diurna', 'Tarde', 'Nocturna'];

        // Valida campos obligatorios y jornada valida
        if ($programa === '' || mb_strlen($programa) > 100 || !$instructor) {
            volverAcademico($modulo, 'error', 'Complete los datos obligatorios de la ficha.');
        }
        // valida que la jornada sea una de las permitidas
        if ($jornada === '' || !in_array($jornada, $jornadasPermitidas, true)) {
            volverAcademico($modulo, 'error', 'Jornada no valida. Seleccione Diurna, Tarde o Nocturna.');
        }
        // valida ambiente principal
        if ($accion === 'crear' && $ambiente === '') {
            volverAcademico($modulo, 'error', 'Ambiente principal es obligatorio.');
        }
        if ($ambiente !== '' && mb_strlen($ambiente) > 100) {
            volverAcademico($modulo, 'error', 'Ambiente principal no puede exceder 100 caracteres.');
        }

        // Verifica que el instructor exista y sea rol 2
        $q = $conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? AND fk_rol=2');
        $q->execute([$instructor]);
        if (!$q->fetch()) volverAcademico($modulo, 'error', 'El instructor seleccionado no existe.');

        if ($accion === 'crear') {
            $conexion->prepare('INSERT INTO ficha(nombre_programa,jornada,ambiente_principal,fk_usuario) VALUES(?,?,?,?)')->execute([$programa, $jornada, $ambiente, $instructor]);
            volverAcademico($modulo, 'mensaje', 'Ficha creada correctamente.');
        }
        if (!$id) volverAcademico($modulo, 'error', 'Ficha no valida.');
        $conexion->prepare('UPDATE ficha SET nombre_programa=?,jornada=?,ambiente_principal=?,fk_usuario=? WHERE id_ficha=?')->execute([$programa, $jornada, $ambiente, $instructor, $id]);
        volverAcademico($modulo, 'mensaje', 'Ficha actualizada correctamente.');
    }

    // GESTION DE HORARIOS
    $id = filter_input(INPUT_POST, 'id_horario', FILTER_VALIDATE_INT);

    // ELIMINAR HORARIO
    if ($accion === 'eliminar') {
        if (!$id) volverAcademico($modulo, 'error', 'Horario no valido.');
        $q = $conexion->prepare('DELETE FROM horario WHERE id_horario=?');
        $q->execute([$id]);
        if (!$q->rowCount()) volverAcademico($modulo, 'error', 'El horario no existe.');
        volverAcademico($modulo, 'mensaje', 'Horario eliminado correctamente.');
    }

    // CREAR O EDITAR HORARIO
    $ficha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
    $instructorId = filter_input(INPUT_POST, 'instructor_id', FILTER_VALIDATE_INT) ?: null;
    $dia = trim($_POST['dia_semana'] ?? '');
    $entrada = trim($_POST['entrada'] ?? '');
    $salida = trim($_POST['salida'] ?? '');
    $competencia = trim($_POST['competencia'] ?? '');
    $ambiente = trim($_POST['ambiente'] ?? '');
    $tipoBloque = in_array($_POST['tipo_bloque'] ?? '', ['completo', 'partido'], true) ? $_POST['tipo_bloque'] : 'completo';
    $estado = in_array($_POST['estado'] ?? '', ['Activo', 'Inactivo'], true) ? $_POST['estado'] : 'Activo';
    $observaciones = trim($_POST['observaciones'] ?? '');
    $fechaInicio = !empty($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : null;
    $fechaFin = !empty($_POST['fecha_fin']) ? trim($_POST['fecha_fin']) : null;

    $mapaDias = [
        'lunes'     => 'Lunes',
        'martes'    => 'Martes',
        'miercoles' => 'Miércoles',
        'miércoles' => 'Miércoles',
        'jueves'    => 'Jueves',
        'viernes'   => 'Viernes',
        'sabado'    => 'Sábado',
        'sábado'    => 'Sábado',
        'domingo'   => 'Domingo'
    ];
    $diaKey = mb_strtolower($dia, 'UTF-8');
    $diaNormalizado = $mapaDias[$diaKey] ?? null;

    $regexHora = '/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/';
    // Valida datos obligatorios y formato
    if (!$ficha || !$diaNormalizado || !preg_match($regexHora, $entrada) || !preg_match($regexHora, $salida)) {
        volverAcademico($modulo, 'error', 'Revise la ficha, el día y el intervalo de horas.');
    }

    // Normalizar a formato HH:MM:00
    if (strlen($entrada) === 5) $entrada .= ':00';
    if (strlen($salida) === 5) $salida .= ':00';

    if ($salida <= $entrada) {
        volverAcademico($modulo, 'error', 'La hora de salida debe ser posterior a la hora de entrada en el mismo día.');
    }

    // Verifica que la ficha exista
    $q = $conexion->prepare('SELECT id_ficha, fk_usuario, ambiente_principal FROM ficha WHERE id_ficha=?');
    $q->execute([$ficha]);
    $fichaData = $q->fetch();
    if (!$fichaData) volverAcademico($modulo, 'error', 'La ficha seleccionada no existe.');

    // Si se asignó instructor específico, validar que exista y tenga rol 2
    if ($instructorId) {
        $qInstr = $conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? AND fk_rol=2');
        $qInstr->execute([$instructorId]);
        if (!$qInstr->fetch()) volverAcademico($modulo, 'error', 'El instructor seleccionado no es válido.');
    }

    $mapaAlt = ['Miércoles' => 'Miercoles', 'Sábado' => 'Sabado'];
    $diaAlt = $mapaAlt[$diaNormalizado] ?? $diaNormalizado;
    $idExcluir = $id ?: 0;

    // Validación 1: Conflicto de horario en la misma ficha (no solaparse en el mismo día)
    $qConfFicha = $conexion->prepare("
        SELECT id_horario, entrada, salida FROM horario
        WHERE fk_ficha = ? AND (dia_semana = ? OR dia_semana = ?)
          AND id_horario <> ? AND (estado = 'Activo' OR estado IS NULL)
          AND entrada < ? AND salida > ?
        LIMIT 1
    ");
    $qConfFicha->execute([$ficha, $diaNormalizado, $diaAlt, $idExcluir, $salida, $entrada]);
    if ($cruce = $qConfFicha->fetch()) {
        volverAcademico($modulo, 'error', "La ficha ya tiene una clase programada el {$diaNormalizado} de " . substr($cruce['entrada'], 0, 5) . " a " . substr($cruce['salida'], 0, 5) . ".");
    }

    // Validación 2: Conflicto de instructor (el mismo instructor no puede estar en dos fichas distintas a la misma hora)
    $instructorEfectivo = $instructorId ?: (int)$fichaData['fk_usuario'];
    $qConfInstr = $conexion->prepare("
        SELECT h.id_horario, h.fk_ficha, h.entrada, h.salida FROM horario h
        INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
        WHERE COALESCE(h.instructor_id, f.fk_usuario) = ?
          AND h.fk_ficha <> ?
          AND (h.dia_semana = ? OR h.dia_semana = ?)
          AND h.id_horario <> ? AND (h.estado = 'Activo' OR h.estado IS NULL)
          AND h.entrada < ? AND h.salida > ?
        LIMIT 1
    ");
    $qConfInstr->execute([$instructorEfectivo, $ficha, $diaNormalizado, $diaAlt, $idExcluir, $salida, $entrada]);
    if ($cruce = $qConfInstr->fetch()) {
        volverAcademico($modulo, 'error', "El instructor ya tiene clase programada en la ficha #{$cruce['fk_ficha']} el {$diaNormalizado} de " . substr($cruce['entrada'], 0, 5) . " a " . substr($cruce['salida'], 0, 5) . ".");
    }

    // Validación 3: Conflicto de ambiente (el mismo ambiente no puede tener dos fichas distintas a la misma hora)
    $ambienteEfectivo = $ambiente !== '' ? $ambiente : ($fichaData['ambiente_principal'] ?? '');
    if ($ambienteEfectivo !== '') {
        $qConfAmb = $conexion->prepare("
            SELECT h.id_horario, h.fk_ficha, h.entrada, h.salida FROM horario h
            INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
            WHERE COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal) = ?
              AND h.fk_ficha <> ?
              AND (h.dia_semana = ? OR h.dia_semana = ?)
              AND h.id_horario <> ? AND (h.estado = 'Activo' OR h.estado IS NULL)
              AND h.entrada < ? AND h.salida > ?
            LIMIT 1
        ");
        $qConfAmb->execute([$ambienteEfectivo, $ficha, $diaNormalizado, $diaAlt, $idExcluir, $salida, $entrada]);
        if ($cruce = $qConfAmb->fetch()) {
            volverAcademico($modulo, 'error', "El ambiente '{$ambienteEfectivo}' ya está ocupado por la ficha #{$cruce['fk_ficha']} el {$diaNormalizado} de " . substr($cruce['entrada'], 0, 5) . " a " . substr($cruce['salida'], 0, 5) . ".");
        }
    }

    if ($accion === 'crear') {
        $stmt = $conexion->prepare("
            INSERT INTO horario (dia_semana, entrada, salida, competencia, tipo_bloque, ambiente, estado, observaciones, fecha_inicio, fecha_fin, fk_ficha, instructor_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $diaNormalizado, $entrada, $salida,
            $competencia !== '' ? $competencia : null,
            $tipoBloque,
            $ambiente !== '' ? $ambiente : null,
            $estado,
            $observaciones !== '' ? $observaciones : null,
            $fechaInicio, $fechaFin,
            $ficha, $instructorId
        ]);
        volverAcademico($modulo, 'mensaje', 'Horario de clase programado correctamente.');
    }

    if (!$id) volverAcademico($modulo, 'error', 'Horario no válido.');

    $stmt = $conexion->prepare("
        UPDATE horario
        SET dia_semana = ?, entrada = ?, salida = ?, competencia = ?, tipo_bloque = ?, ambiente = ?, estado = ?, observaciones = ?, fecha_inicio = ?, fecha_fin = ?, fk_ficha = ?, instructor_id = ?
        WHERE id_horario = ?
    ");
    $stmt->execute([
        $diaNormalizado, $entrada, $salida,
        $competencia !== '' ? $competencia : null,
        $tipoBloque,
        $ambiente !== '' ? $ambiente : null,
        $estado,
        $observaciones !== '' ? $observaciones : null,
        $fechaInicio, $fechaFin,
        $ficha, $instructorId,
        $id
    ]);
    volverAcademico($modulo, 'mensaje', 'Horario actualizado correctamente.');

} catch (Throwable $e) {
    volverAcademico($modulo, 'error', 'No fue posible guardar los datos.');
}