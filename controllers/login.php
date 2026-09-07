<?php
/**
 * controlador de inicio de sesion
 * procesa el formulario de login, valida credenciales y crea la sesion
 */

// inicia la sesion para poder guardar datos del usuario
session_start();
// carga la clase de conexion a la base de datos
require_once __DIR__ . '/../config/conexion.php';

// verifica que el formulario fue enviado por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../views/auth/login.php");
    exit;
}

// obtiene los datos del formulario
$nombre_usuario = trim($_POST["nombre_usuario"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";

// valida que los campos no esten vacios
if ($nombre_usuario === "" || $contrasena === "") {
    header("Location: ../views/auth/login.php?error=Todos los campos son obligatorios");
    exit;
}

// conecta a la base de datos
$conexionBD = new Conexion();
$conexion = $conexionBD->conectar();

// busca el usuario por nombre de usuario
$sql = "Select usuario.id_usuario, usuario.nombre_usuario,
    usuario.contrasena, usuario.nombre, usuario.apellido, usuario.identificacion,
    usuario.telefono, usuario.fk_rol, rol.nombre_rol FROM usuario
    INNER JOIN rol ON usuario.fk_rol = rol.id_rol WHERE usuario.nombre_usuario = :nombre_usuario
    LIMIT 1";
$consulta = $conexion->prepare($sql);
$consulta->bindValue(":nombre_usuario", $nombre_usuario, PDO::PARAM_STR);

$consulta->execute();
$usuario = $consulta->fetch();

// verifica el usuario y la contrasena (comparacion directa porque la BD tiene texto plano)
if (!$usuario || $contrasena !== $usuario["contrasena"]) {
    header("Location: ../views/auth/login.php?error=credenciales");
    exit;
}

// regenera el ID de sesion por seguridad
session_regenerate_id(true);

// crea variables de sesion con los datos del usuario
$_SESSION["id_usuario"] = $usuario["id_usuario"];
$_SESSION["nombre_usuario"] = $usuario["nombre_usuario"];
$_SESSION["nombre"] = $usuario["nombre"];
$_SESSION["apellido"] = $usuario["apellido"];
$_SESSION["fk_rol"] = $usuario["fk_rol"];
$_SESSION["nombre_rol"] = $usuario["nombre_rol"];

// redirige segun el rol del usuario
switch ($usuario["fk_rol"]) {
    case 1: header("Location: ../views/admin/dashboard.php");
    break;

    case 2: header("Location: ../views/instructor/dashboard.php");
    break;

    case 3: header("Location: ../views/aprendiz/dashboard.php");
    break;

    default: session_destroy();
    header("Location: ../views/auth/login.php?error=El usuario no tiene un rol valido");
    break;
}
exit;