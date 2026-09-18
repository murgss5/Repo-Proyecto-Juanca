<?php
/**
 * controlador de cierre de sesion
 * destruye la sesion y redirige al login
 */

// inicia la sesion para poder destruirla
session_start();

// limpia todas las variables de sesion
$_SESSION = [];

// elimina la cookie de sesion si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// destruye la sesion completamente
session_destroy();

// cabeceras anti cache para evitar volver atras
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// carga constantes para obtener RUTA_BASE
require_once __DIR__ . '/../includes/constantes.php';

// redirige al formulario de login
header("Location: " . RUTA_BASE . "views/auth/login.php");
exit;