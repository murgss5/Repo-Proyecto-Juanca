<?php
/**
 * Controlador de cierre seguro de sesion
 * Requiere peticion POST y token CSRF valido.
 * Destruye la sesion en el servidor, expira la cookie y redirige al login.
 */

// Cabeceras anti cache obligatorias
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Iniciar sesion para poder acceder a los datos y token CSRF
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/constantes.php';

// 1. Rechazar peticiones que no sean POST con HTTP 405 (sin cerrar sesion)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Metodo no permitido. El cierre de sesion requiere POST.');
}

// 2. Extraer token CSRF desde POST o encabezado HTTP
$tokenRecibido = null;
if (isset($_POST['token_csrf'])) {
    if (is_string($_POST['token_csrf'])) {
        $tokenRecibido = trim($_POST['token_csrf']);
    } else {
        // Rechazar si fue enviado como array u otro tipo
        http_response_code(400);
        exit('Token CSRF con formato no valido.');
    }
}

if ($tokenRecibido === null || $tokenRecibido === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenHeader = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;
    if (is_string($tokenHeader)) {
        $tokenRecibido = trim($tokenHeader);
    }
}

// 3. Validar token contra la sesion activa antes de modificarla
$tokenSesion = $_SESSION['token_csrf'] ?? null;

if (!is_string($tokenSesion) || $tokenSesion === '' || !is_string($tokenRecibido) || $tokenRecibido === '') {
    http_response_code(400);
    exit('Token CSRF ausente o sesion no valida.');
}

if (!hash_equals($tokenSesion, $tokenRecibido)) {
    http_response_code(400);
    exit('Token CSRF invalido.');
}

// 4. Vaciar la sesion en memoria
$_SESSION = [];

// 5. Expirar la cookie de sesion con los atributos correspondientes
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => $esHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

// 6. Destruir la sesion en el servidor
session_destroy();

// 7. Redirigir al formulario de login y finalizar ejecucion
header("Location: " . RUTA_BASE . "views/auth/login.php");
exit;