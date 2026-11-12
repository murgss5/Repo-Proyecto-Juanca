<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE AUTENTICACIÓN: AuthController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida las operaciones del ciclo de vida de autenticación:
 *   - accion=login  : Procesa credenciales, control de fuerza bruta y sesión
 *   - accion=logout : Cierre selectivo o total de sesión (Multitenancy de roles)
 * =============================================================================
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../config/conexion.php';

// Detectar acción solicitada
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática para compatibilidad retroactiva
if ($accion === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_usuario'])) {
        $accion = 'login';
    } elseif (isset($_GET['rol']) || isset($_POST['rol'])) {
        $accion = 'logout';
    } else {
        $accion = 'login';
    }
}

switch ($accion) {
    case 'login':
        procesarLogin();
        break;

    case 'logout':
        procesarLogout();
        break;

    default:
        header('Location: ' . RUTA_BASE . 'views/auth/login.php');
        exit;
}

/**
 * Procesa el inicio de sesión con validación de credenciales y mitigación de fuerza bruta.
 */
function procesarLogin(): void
{
    require_once __DIR__ . '/../includes/RateLimiter.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ' . RUTA_BASE . 'views/auth/login.php');
        exit;
    }

    $identificador = trim($_POST['nombre_usuario'] ?? '');
    $contrasena    = $_POST['contrasena'] ?? '';

    if ($identificador === '' || $contrasena === '') {
        header('Location: ' . RUTA_BASE . 'views/auth/login.php?error=Todos los campos son obligatorios');
        exit;
    }

    $conexion = (new Conexion())->conectar();
    $rateLimiter = new RateLimiter($conexion);
    $ipCliente = $rateLimiter->obtenerIpCliente();

    // Comprobación de límites e intentos progresivos
    $estadoIntento = $rateLimiter->verificarIntento($identificador, $ipCliente);
    if (!$estadoIntento['permitido']) {
        $motivo = $estadoIntento['motivo'];
        $segundosRestantes = (int)($estadoIntento['segundos_restantes'] ?? 0);
        error_log("Acceso rechazado por limitacion de intentos [IP: {$ipCliente}, Cuenta: {$identificador}, Motivo: {$motivo}, Espera: {$segundosRestantes}s]");
        $_SESSION['login_bloqueo_segundos'] = $segundosRestantes;
        header('Location: ' . RUTA_BASE . 'views/auth/login.php?error=limite');
        exit;
    }

    // Consulta de usuario
    $sql = "SELECT usuario.id_usuario, usuario.nombre_usuario,
                   usuario.contrasena, usuario.nombre, usuario.apellido, usuario.identificacion,
                   usuario.telefono, usuario.fk_rol, rol.nombre_rol 
            FROM usuario
            INNER JOIN rol ON usuario.fk_rol = rol.id_rol
            WHERE usuario.nombre_usuario = :usuario OR usuario.identificacion = :identificacion";
    $consulta = $conexion->prepare($sql);
    $consulta->bindValue(':usuario', $identificador, PDO::PARAM_STR);
    $consulta->bindValue(':identificacion', $identificador, PDO::PARAM_STR);
    $consulta->execute();
    $usuarios = $consulta->fetchAll();

    if (count($usuarios) > 1) {
        $correlacionId = bin2hex(random_bytes(8));
        error_log("Ambigüedad de autenticación [Correlación ID: {$correlacionId}]: múltiples cuentas detectadas.");
        $rateLimiter->registrarFallo($identificador, $ipCliente, 'AMBIGUEDAD_CUENTAS');
        header('Location: ' . RUTA_BASE . 'views/auth/login.php?error=credenciales');
        exit;
    }

    $usuario = $usuarios[0] ?? false;

    // Validación segura de contraseña
    $contrasenaValida = false;
    if ($usuario) {
        $infoHash = password_get_info($usuario['contrasena']);
        if ($infoHash['algo'] !== null) {
            $contrasenaValida = password_verify($contrasena, $usuario['contrasena']);
        } else {
            $contrasenaValida = hash_equals($usuario['contrasena'], $contrasena);
        }
    } else {
        RateLimiter::mitigarEnumeracionTiempo($contrasena);
    }

    if (!$usuario || !$contrasenaValida) {
        $rateLimiter->registrarFallo($identificador, $ipCliente, $usuario ? 'CREDENCIALES_INVALIDAS' : 'USUARIO_INEXISTENTE');
        header('Location: ' . RUTA_BASE . 'views/auth/login.php?error=credenciales');
        exit;
    }

    // Autenticación correcta
    $rateLimiter->registrarExito($identificador, $ipCliente);

    // Migración automática de contraseñas de texto plano a hash
    $infoHash = password_get_info($usuario['contrasena']);
    if ($infoHash['algo'] === null) {
        $nuevaContrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare('UPDATE usuario SET contrasena = ? WHERE id_usuario = ?');
        $stmt->execute([$nuevaContrasenaHash, $usuario['id_usuario']]);
    }

    // Regenerar ID de sesión preservando contexto
    session_regenerate_id(true);

    if (empty($_SESSION['token_csrf']) || !is_string($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }

    $claveRol = iniciarSesionRol($usuario);

    switch ((int)$usuario['fk_rol']) {
        case 1:
            header('Location: ' . RUTA_BASE . 'views/admin/dashboard.php');
            break;
        case 2:
            header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
            break;
        case 3:
            header('Location: ' . RUTA_BASE . 'views/aprendiz/dashboard.php');
            break;
        default:
            unset($_SESSION[$claveRol]);
            header('Location: ' . RUTA_BASE . 'views/auth/login.php?error=El usuario no tiene un rol valido');
            break;
    }
    exit;
}

/**
 * Procesa el cierre selectivo o total de sesión respetando multitenancy.
 */
function procesarLogout(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');

    $rolRecibido = $_POST['rol'] ?? $_GET['rol'] ?? null;
    $rolClaveObjetivo = rolClave($rolRecibido);

    if (!$rolClaveObjetivo && !empty($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        if (strpos($referer, '/views/admin/') !== false || strpos($referer, '/admin/') !== false) {
            $rolClaveObjetivo = 'admin';
        } elseif (strpos($referer, '/views/instructor/') !== false || strpos($referer, '/instructor/') !== false) {
            $rolClaveObjetivo = 'instructor';
        } elseif (strpos($referer, '/views/aprendiz/') !== false || strpos($referer, '/aprendiz/') !== false) {
            $rolClaveObjetivo = 'aprendiz';
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tokenRecibido = null;
        if (isset($_POST['token_csrf']) && is_string($_POST['token_csrf'])) {
            $tokenRecibido = trim($_POST['token_csrf']);
        } else {
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            $tokenHeader = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;
            if (is_string($tokenHeader)) {
                $tokenRecibido = trim($tokenHeader);
            }
        }

        $tokenSesion = $_SESSION['token_csrf'] ?? null;
        if (!is_string($tokenSesion) || $tokenSesion === '' || !is_string($tokenRecibido) || !hash_equals($tokenSesion, $tokenRecibido)) {
            http_response_code(400);
            exit('Token CSRF invalido o sesion no valida.');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: POST, GET');
        exit('Metodo no permitido.');
    }

    cerrarSesionRol($rolClaveObjetivo);
    header('Location: ' . RUTA_BASE . 'views/auth/login.php');
    exit;
}
