<?php
/**
 * controlador de inicio de sesion
 * procesa el formulario de login, valida credenciales y crea la sesion
 */

// inicia la sesion para poder guardar datos del usuario
session_start();
// carga la clase de conexion a la base de datos
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/RateLimiter.php';

// verifica que el formulario fue enviado por POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . RUTA_BASE . "views/auth/login.php");
    exit;
}

// obtiene los datos del formulario
$identificador = trim($_POST["nombre_usuario"] ?? "");
$contrasena = $_POST["contrasena"] ?? "";

// valida que los campos no esten vacios
if ($identificador === "" || $contrasena === "") {
    header("Location: " . RUTA_BASE . "views/auth/login.php?error=Todos los campos son obligatorios");
    exit;
}

// conecta a la base de datos
$conexionBD = new Conexion();
$conexion = $conexionBD->conectar();

// Instancia el servicio de mitigacion de fuerza bruta y control de intentos
$rateLimiter = new RateLimiter($conexion);
$ipCliente = $rateLimiter->obtenerIpCliente();

// Comprueba atómicamente si la cuenta o la IP han superado los límites o están en espera progresiva
$estadoIntento = $rateLimiter->verificarIntento($identificador, $ipCliente);
if (!$estadoIntento['permitido']) {
    $motivo = $estadoIntento['motivo'];
    $segundosRestantes = (int)($estadoIntento['segundos_restantes'] ?? 0);
    error_log("Acceso rechazado por limitacion de intentos [IP: {$ipCliente}, Cuenta: {$identificador}, Motivo: {$motivo}, Espera: {$segundosRestantes}s]");
    $_SESSION['login_bloqueo_segundos'] = $segundosRestantes;
    header("Location: " . RUTA_BASE . "views/auth/login.php?error=limite");
    exit;
}

// busca el usuario por nombre de usuario o numero de identificacion (cedula)
$sql = "SELECT usuario.id_usuario, usuario.nombre_usuario,
    usuario.contrasena, usuario.nombre, usuario.apellido, usuario.identificacion,
    usuario.telefono, usuario.fk_rol, rol.nombre_rol FROM usuario
    INNER JOIN rol ON usuario.fk_rol = rol.id_rol
    WHERE usuario.nombre_usuario = :usuario OR usuario.identificacion = :identificacion";
$consulta = $conexion->prepare($sql);
$consulta->bindValue(":usuario", $identificador, PDO::PARAM_STR);
$consulta->bindValue(":identificacion", $identificador, PDO::PARAM_STR);

$consulta->execute();
$usuarios = $consulta->fetchAll();

// Si hay ambigüedad (mas de una cuenta coincide con el identificador), no resolver silenciosamente
if (count($usuarios) > 1) {
    $correlacionId = bin2hex(random_bytes(8));
    error_log("Ambiguedad de autenticacion [Correlacion ID: {$correlacionId}]: multiples cuentas detectadas para la solicitud.");
    $rateLimiter->registrarFallo($identificador, $ipCliente, 'AMBIGUEDAD_CUENTAS');
    header("Location: " . RUTA_BASE . "views/auth/login.php?error=credenciales");
    exit;
}

$usuario = $usuarios[0] ?? false;

// funcion simple para detectar si la contrasena guardada es un hash valido de PHP
function esHashContrasena(string $contrasenaGuardada): bool
{
    $info = password_get_info($contrasenaGuardada);
    return $info['algo'] !== null;
}

// verifica el usuario y la contrasena
// validacion de hash o texto plano segun corresponda
$contrasenaValida = false;
if ($usuario) {
    if (esHashContrasena($usuario["contrasena"])) {
        // contrasena guardada es hash: usar password_verify
        $contrasenaValida = password_verify($contrasena, $usuario["contrasena"]);
    } else {
        // contrasena guardada en texto plano: comparacion segura
        $contrasenaValida = hash_equals($usuario["contrasena"], $contrasena);
    }
} else {
    // Si el usuario no existe, ejecuta calculo de hash simulado para mitigar ataques de temporizacion (timing attacks)
    RateLimiter::mitigarEnumeracionTiempo($contrasena);
}

if (!$usuario || !$contrasenaValida) {
    // Registra fallo atomico por cuenta e IP (sin almacenar credenciales)
    $rateLimiter->registrarFallo($identificador, $ipCliente, $usuario ? 'CREDENCIALES_INVALIDAS' : 'USUARIO_INEXISTENTE');
    header("Location: " . RUTA_BASE . "views/auth/login.php?error=credenciales");
    exit;
}

// Autenticacion exitosa: restablece contadores de la cuenta
$rateLimiter->registrarExito($identificador, $ipCliente);

// migracion automatica: si la contrasena era texto plano, actualizar a hash
if (!esHashContrasena($usuario["contrasena"])) {
    $nuevaContrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare("UPDATE usuario SET contrasena = ? WHERE id_usuario = ?");
    $stmt->execute([$nuevaContrasenaHash, $usuario["id_usuario"]]);
    // si falla el UPDATE, no impedimos el login ya validado
}

// regenera el ID de sesion por seguridad preservando datos existentes
session_regenerate_id(true);

// genera token CSRF seguro para la sesion si aun no existe
if (empty($_SESSION['token_csrf']) || !is_string($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Guarda la sesion en el namespace correspondiente al rol (Multitenancy)
$claveRol = iniciarSesionRol($usuario);

// redirige segun el rol del usuario
switch ((int) $usuario["fk_rol"]) {
    case 1:
        header("Location: " . RUTA_BASE . "views/admin/dashboard.php");
        break;

    case 2:
        header("Location: " . RUTA_BASE . "views/instructor/dashboard.php");
        break;

    case 3:
        header("Location: " . RUTA_BASE . "views/aprendiz/dashboard.php");
        break;

    default:
        // En caso de rol no valido, limpiar solo el rol no valido
        unset($_SESSION[$claveRol]);
        header("Location: " . RUTA_BASE . "views/auth/login.php?error=El usuario no tiene un rol valido");
        break;
}
exit;