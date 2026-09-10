<?php
/**
 * controlador para gestionar fichas y horarios desde el panel de administracion
 * permite crear, editar y eliminar fichas y horarios con validaciones
 */

session_start();
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['fk_rol'] ?? null) != 1) {
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

        // Verifica que el instructor exista y sea rol 2
        $q = $conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? AND fk_rol=2');
        $q->execute([$instructor]);
        if (!$q->fetch()) volverAcademico($modulo, 'error', 'El instructor seleccionado no existe.');

        if ($accion === 'crear') {
            $conexion->prepare('INSERT INTO ficha(nombre_programa,jornada,fk_usuario) VALUES(?,?,?)')->execute([$programa, $jornada, $instructor]);
            volverAcademico($modulo, 'mensaje', 'Ficha creada correctamente.');
        }
        if (!$id) volverAcademico($modulo, 'error', 'Ficha no valida.');
        $conexion->prepare('UPDATE ficha SET nombre_programa=?,jornada=?,fk_usuario=? WHERE id_ficha=?')->execute([$programa, $jornada, $instructor, $id]);
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
    $dia = trim($_POST['dia_semana'] ?? '');
    $entrada = $_POST['entrada'] ?? '';
    $salida = $_POST['salida'] ?? '';
    $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];

    // Valida datos obligatorios y formato
    if (!$ficha || !in_array($dia, $dias, true) || !preg_match('/^\d{2}:\d{2}$/', $entrada) || !preg_match('/^\d{2}:\d{2}$/', $salida) || $salida <= $entrada) {
        volverAcademico($modulo, 'error', 'Revise la ficha, el dia y el intervalo de horas.');
    }

    // Verifica que la ficha exista
    $q = $conexion->prepare('SELECT id_ficha FROM ficha WHERE id_ficha=?');
    $q->execute([$ficha]);
    if (!$q->fetch()) volverAcademico($modulo, 'error', 'La ficha seleccionada no existe.');

    // Verifica que no haya horario duplicado para esa ficha y dia
    $q = $conexion->prepare('SELECT id_horario FROM horario WHERE fk_ficha=? AND dia_semana=? AND id_horario<>?');
    $q->execute([$ficha, $dia, $id ?: 0]);
    if ($q->fetch()) volverAcademico($modulo, 'error', 'Esta ficha ya tiene un horario para ese dia.');

    if ($accion === 'crear') {
        $conexion->prepare('INSERT INTO horario(dia_semana,entrada,salida,fk_ficha) VALUES(?,?,?,?)')->execute([$dia, $entrada, $salida, $ficha]);
        volverAcademico($modulo, 'mensaje', 'Horario creado correctamente.');
    }
    if (!$id) volverAcademico($modulo, 'error', 'Horario no valido.');
    $conexion->prepare('UPDATE horario SET dia_semana=?,entrada=?,salida=?,fk_ficha=? WHERE id_horario=?')->execute([$dia, $entrada, $salida, $ficha, $id]);
    volverAcademico($modulo, 'mensaje', 'Horario actualizado correctamente.');

} catch (Throwable $e) {
    volverAcademico($modulo, 'error', 'No fue posible guardar los datos.');
}