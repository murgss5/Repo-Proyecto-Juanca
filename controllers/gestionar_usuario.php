<?php

/**
 * controlador para gestionar usuarios (aprendices e instructores) desde el panel de administracion
 * permite crear, editar y eliminar usuarios con validaciones y transacciones
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(1);
require_once __DIR__ . '/../config/conexion.php';

/**
 * funcion auxiliar para redirigir con mensaje al listado correspondiente
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
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/admin/dashboard.php');
    exit;
}

// Verifica el token CSRF para evitar envios falsos
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    exit('Solicitud no valida.');
}

// Obtiene la accion, rol e ID del usuario
$accion = $_POST['accion'] ?? '';
$rol = (int)($_POST['rol'] ?? 0);
$id = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);

// Valida que el rol y la accion sean correctos
if (!in_array($rol, [2, 3], true) || !in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    exit('Datos no validos.');
}

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

try {
    // ELIMINAR USUARIO
    if ($accion === 'eliminar') {
        if (!$id) volver('error', 'Usuario no valido.', $rol);
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
    $correo = trim($_POST['correo_electronico'] ?? '');
    $clave = $_POST['contrasena'] ?? '';

    if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        volver('error', 'El formato del correo electrónico no es válido.', $rol, $id ?: null);
    }

    // Valida campos obligatorios
    if ($usuario === '' || $documento === '' || $nombre === '' || $apellido === '' || ($accion === 'crear' && $clave === '')) {
        volver('error', 'Complete los campos obligatorios.', $rol, $id ?: null);
    }

    // Validación de contraseña (solo si se proporciona, ya sea en crear o editar)
    if ($clave !== '') {
        if (strlen($clave) < 8) {
            volver('error', 'La contraseña debe tener al menos 8 caracteres.', $rol, $id ?: null);
        }
        if (!preg_match('/[A-Z]/', $clave)) {
            volver('error', 'La contraseña debe contener al menos una letra mayúscula.', $rol, $id ?: null);
        }
        if (!preg_match('/[a-z]/', $clave)) {
            volver('error', 'La contraseña debe contener al menos una letra minúscula.', $rol, $id ?: null);
        }
        if (!preg_match('/[0-9]/', $clave)) {
            volver('error', 'La contraseña debe contener al menos un número.', $rol, $id ?: null);
        }
    }

    // Validación de teléfono (si se proporciona)
    if ($telefono !== '') {
        if (!preg_match('/^3[0-9]{9}$/', $telefono)) {
            volver('error', 'El teléfono debe tener 10 dígitos, comenzar por 3 y contener solo números.', $rol, $id ?: null);
        }
    }

    // Verifica que el usuario o documento no esten duplicados (excluyendo el actual si edita)
    $q = $conexion->prepare('SELECT id_usuario FROM usuario WHERE (nombre_usuario=:u OR identificacion=:d) AND id_usuario<>:id');
    $q->execute([':u' => $usuario, ':d' => $documento, ':id' => $id ?: 0]);
    if ($q->fetch()) volver('error', 'El usuario o documento ya esta registrado.', $rol, $id ?: null);

    // Validaciones adicionales para aprendices
    if ($rol === 3) {
        $ficha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
        // RFID se genera automaticamente en creacion, no se pide en formulario
        $rfid = '';
        if ($accion === 'editar') {
            $rfid = trim($_POST['codigo_rfid'] ?? '');
        }
        if (!$ficha) volver('error', 'Ficha es obligatoria.', $rol, $id ?: null);
        // Verifica que la ficha exista
        $q = $conexion->prepare('SELECT id_ficha FROM ficha WHERE id_ficha=?');
        $q->execute([$ficha]);
        if (!$q->fetch()) volver('error', 'La ficha seleccionada no existe.', $rol, $id ?: null);
        // Verifica que el RFID no este duplicado (solo en edicion, en creacion se genera unico)
        if ($accion === 'editar' && $rfid !== '') {
            $q = $conexion->prepare('SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid=? AND fk_usuario<>?');
            $q->execute([$rfid, $id ?: 0]);
            if ($q->fetch()) volver('error', 'El codigo RFID ya esta registrado.', $rol, $id ?: null);
        }
    }

    // Inicia transaccion para asegurar atomicidad
    $conexion->beginTransaction();

    if ($accion === 'crear') {
        // Inserta en tabla usuario con contrasena hasheada
        $q = $conexion->prepare('INSERT INTO usuario(nombre_usuario,contrasena,nombre,apellido,identificacion,telefono,correo_electronico,fk_rol) VALUES(?,?,?,?,?,?,?,?)');
        $q->execute([$usuario, password_hash($clave, PASSWORD_DEFAULT), $nombre, $apellido, $documento, $telefono ?: null, $correo !== '' ? $correo : null, $rol]);
        $id = (int)$conexion->lastInsertId();
        // Si es aprendiz, genera RFID automatico e inserta en tabla aprendiz
        if ($rol === 3) {
            // Obtiene el numero mas alto de codigos RFID con formato RFID### (ignora DEMO-RFID-*)
            $q = $conexion->prepare("SELECT MAX(CAST(SUBSTRING(codigo_rfid, 5) AS UNSIGNED)) AS max_num FROM aprendiz WHERE codigo_rfid LIKE 'RFID%' AND codigo_rfid REGEXP '^RFID[0-9]{3}$'");
            $q->execute();
            $resultado = $q->fetch();
            $siguienteNum = (int)($resultado['max_num'] ?? 0) + 1;
            $rfid = 'RFID' . str_pad($siguienteNum, 3, '0', STR_PAD_LEFT);
            // Verifica que el RFID generado no exista (por seguridad)
            $q = $conexion->prepare('SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid=?');
            $q->execute([$rfid]);
            if ($q->fetch()) {
                throw new Exception('Error generando RFID unico');
            }
            // Inserta en tabla aprendiz con RFID generado
            $conexion->prepare('INSERT INTO aprendiz(codigo_rfid,fk_ficha,fk_usuario) VALUES(?,?,?)')->execute([$rfid, $ficha, $id]);
        }
    } else {
        // EDITAR: actualiza usuario (incluye contrasena solo si se escribio una nueva)
        if (!$id) throw new RuntimeException();
        if ($rol === 2) {
            $sql = 'UPDATE usuario SET nombre_usuario=?,nombre=?,apellido=?,identificacion=?,telefono=?,correo_electronico=?' . ($clave !== '' ? ',contrasena=?' : '') . ' WHERE id_usuario=? AND fk_rol=?';
            $val = [$usuario, $nombre, $apellido, $documento, $telefono ?: null, $correo !== '' ? $correo : null];
        } else {
            // Para aprendices (rol 3), el correo es gestionado exclusivamente por el aprendiz desde su propio perfil
            $sql = 'UPDATE usuario SET nombre_usuario=?,nombre=?,apellido=?,identificacion=?,telefono=?' . ($clave !== '' ? ',contrasena=?' : '') . ' WHERE id_usuario=? AND fk_rol=?';
            $val = [$usuario, $nombre, $apellido, $documento, $telefono ?: null];
    
        }

        if ($clave !== '') $val[] = password_hash($clave, PASSWORD_DEFAULT);
        $val[] = $id;
        $val[] = $rol;
        $q = $conexion->prepare($sql);
        $q->execute($val);
        // Si es aprendiz, actualiza tabla aprendiz
        if ($rol === 3) $conexion->prepare('UPDATE aprendiz SET codigo_rfid=?,fk_ficha=? WHERE fk_usuario=?')->execute([$rfid, $ficha, $id]);
    }

    // Confirma la transaccion
    $conexion->commit();
    volver('mensaje', $accion === 'crear' ? 'Usuario creado correctamente.' : 'Usuario actualizado correctamente.', $rol);
} catch (Throwable $e) {
    // Si hay error, revierte la transaccion
    if ($conexion->inTransaction()) $conexion->rollBack();
    volver('error', 'No fue posible guardar los datos.', $rol, $id ?: null);
}
