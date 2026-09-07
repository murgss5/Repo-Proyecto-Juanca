<?php
/**
 * Controlador para gestionar usuarios (aprendices e instructores) desde el panel de administración.
 * Permite crear, editar y eliminar usuarios con validaciones y transacciones.
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';

/**
 * Función auxiliar para redirigir con mensaje al listado correspondiente.
 * @param string $tipo Tipo de mensaje: 'mensaje' o 'error'
 * @param string $mensaje Texto del mensaje
 * @param int $rol Rol del usuario (2=instructor, 3=aprendiz)
 * @param int|null $id ID del usuario si aplica
 */
function volver(string $tipo, string $mensaje, int $rol, ?int $id = null): void
{
    $modulo = $rol === 3 ? 'aprendices' : 'instructores';
    $url = '../views/admin/' . $modulo . '/index.php?' . $tipo . '=' . urlencode($mensaje);
    if ($id !== null) $url .= '&id=' . $id;
    header('Location: ' . $url);
    exit;
}

// Verifica que sea POST y que el usuario sea administrador (rol 1)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['fk_rol'] ?? null) != 1) {
    header('Location: ../views/auth/login.php');
    exit;
}

// Verifica el token CSRF para evitar envíos falsos
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    exit('Solicitud no válida.');
}

// Obtiene la acción, rol e ID del usuario
$accion = $_POST['accion'] ?? '';
$rol = (int)($_POST['rol'] ?? 0);
$id = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

// Valida que el rol y la acción sean correctos
if (!in_array($rol, [2, 3], true) || !in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    exit('Datos no válidos.');
}

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

try {
    // ELIMINAR USUARIO
    if ($accion === 'eliminar') {
        if (!$id) volver('error', 'Usuario no válido.', $rol);
        // Verifica que el usuario exista y tenga el rol correcto
        $q = $conexion->prepare('SELECT id_usuario FROM usuario WHERE id_usuario=? AND fk_rol=?');
        $q->execute([$id, $rol]);
        if (!$q->fetch()) volver('error', 'Usuario no encontrado.', $rol);

        if ($rol === 3) {
            // Para aprendiz: verifica que no tenga asistencias registradas
            $q = $conexion->prepare('SELECT COUNT(*) FROM asistencia a INNER JOIN aprendiz ap ON a.fk_aprendiz=ap.id_aprendiz WHERE ap.fk_usuario=?');
            $q->execute([$id]);
            if ($q->fetchColumn()) volver('error', 'No se puede eliminar un aprendiz con asistencias registradas.', $rol);
            // Elimina primero el registro de aprendiz
            $conexion->prepare('DELETE FROM aprendiz WHERE fk_usuario=?')->execute([$id]);
        } else {
            // Para instructor: verifica que no tenga fichas asignadas
            $q = $conexion->prepare('SELECT COUNT(*) FROM ficha WHERE fk_usuario=?');
            $q->execute([$id]);
            if ($q->fetchColumn()) volver('error', 'No se puede eliminar un instructor con fichas asignadas.', $rol);
        }
        // Elimina el usuario
        $conexion->prepare('DELETE FROM usuario WHERE id_usuario=?')->execute([$id]);
        volver('mensaje', 'Usuario eliminado correctamente.', $rol);
    }

    // CREAR O EDITAR USUARIO
    $usuario = trim($_POST['nombre_usuario'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $clave = $_POST['contrasena'] ?? '';

    // Valida campos obligatorios
    if ($usuario === '' || $documento === '' || $nombre === '' || $apellido === '' || ($accion === 'crear' && $clave === '')) {
        volver('error', 'Complete los campos obligatorios.', $rol, $id ?: null);
    }

    // Verifica que el usuario o documento no estén duplicados (excluyendo el actual si edita)
    $q = $conexion->prepare('SELECT id_usuario FROM usuario WHERE (nombre_usuario=:u OR identificacion=:d) AND id_usuario<>:id');
    $q->execute([':u' => $usuario, ':d' => $documento, ':id' => $id ?: 0]);
    if ($q->fetch()) volver('error', 'El usuario o documento ya está registrado.', $rol, $id ?: null);

    // Validaciones adicionales para aprendices
    if ($rol === 3) {
        $ficha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
        $rfid = trim($_POST['codigo_rfid'] ?? '');
        if (!$ficha || $rfid === '') volver('error', 'Ficha y RFID son obligatorios.', $rol, $id ?: null);
        // Verifica que la ficha exista
        $q = $conexion->prepare('SELECT id_ficha FROM ficha WHERE id_ficha=?');
        $q->execute([$ficha]);
        if (!$q->fetch()) volver('error', 'La ficha seleccionada no existe.', $rol, $id ?: null);
        // Verifica que el RFID no esté duplicado
        $q = $conexion->prepare('SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid=? AND fk_usuario<>?');
        $q->execute([$rfid, $id ?: 0]);
        if ($q->fetch()) volver('error', 'El código RFID ya está registrado.', $rol, $id ?: null);
    }

    // Inicia transacción para asegurar atomicidad
    $conexion->beginTransaction();

    if ($accion === 'crear') {
        // Inserta en tabla usuario con contraseña hasheada
        $q = $conexion->prepare('INSERT INTO usuario(nombre_usuario,contrasena,nombre,apellido,identificacion,telefono,fk_rol) VALUES(?,?,?,?,?,?,?)');
        $q->execute([$usuario, password_hash($clave, PASSWORD_DEFAULT), $nombre, $apellido, $documento, $telefono ?: null, $rol]);
        $id = (int)$conexion->lastInsertId();
        // Si es aprendiz, inserta en tabla aprendiz
        if ($rol === 3) $conexion->prepare('INSERT INTO aprendiz(codigo_rfid,fk_ficha,fk_usuario) VALUES(?,?,?)')->execute([$rfid, $ficha, $id]);
    } else {
        // EDITAR: actualiza usuario (incluye contraseña solo si se escribió una nueva)
        if (!$id) throw new RuntimeException();
        $sql = 'UPDATE usuario SET nombre_usuario=?,nombre=?,apellido=?,identificacion=?,telefono=?' . ($clave !== '' ? ',contrasena=?' : '') . ' WHERE id_usuario=? AND fk_rol=?';
        $val = [$usuario, $nombre, $apellido, $documento, $telefono ?: null];
        if ($clave !== '') $val[] = password_hash($clave, PASSWORD_DEFAULT);
        $val[] = $id;
        $val[] = $rol;
        $q = $conexion->prepare($sql);
        $q->execute($val);
        // Si es aprendiz, actualiza tabla aprendiz
        if ($rol === 3) $conexion->prepare('UPDATE aprendiz SET codigo_rfid=?,fk_ficha=? WHERE fk_usuario=?')->execute([$rfid, $ficha, $id]);
    }

    // Confirma la transacción
    $conexion->commit();
    volver('mensaje', $accion === 'crear' ? 'Usuario creado correctamente.' : 'Usuario actualizado correctamente.', $rol);

} catch (Throwable $e) {
    // Si hay error, revierte la transacción
    if ($conexion->inTransaction()) $conexion->rollBack();
    volver('error', 'No fue posible guardar los datos.', $rol, $id ?: null);
}
