<?php
/**
 * Controlador de cierre seguro de sesion (Multitenancy de roles)
 *
 * Permite cerrar selectivamente la sesion de un rol especifico (admin, instructor, aprendiz)
 * preservando las sesiones de los demas roles que continuen abiertas en el mismo navegador.
 * Si no quedan roles activos en la sesion, la sesion se destruye por completo y se expira la cookie.
 *
 * Soporta peticiones POST (recomendado, desde formularios con CSRF) y enlaces directos GET con parametro ?rol=.
 */

// Cabeceras anti cache obligatorias
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Iniciar sesion para poder acceder a los datos
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../includes/autenticacion.php';

// 1. Obtener el rol que se desea cerrar
$rolRecibido = $_POST['rol'] ?? $_GET['rol'] ?? null;
$rolClaveObjetivo = rolClave($rolRecibido);

// Si no vino parametro rol, intentar inferirlo por el HTTP_REFERER
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

// 2. Si la peticion es POST, validar token CSRF
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
    // Si no es POST ni GET, responder 405
    http_response_code(405);
    header('Allow: POST, GET');
    exit('Metodo no permitido.');
}

// 3. Ejecutar el cierre selectivo de la sesion del rol
cerrarSesionRol($rolClaveObjetivo);

// 4. Redirigir al login
header("Location: " . RUTA_BASE . "views/auth/login.php");
exit;
