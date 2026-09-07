<?php
/**
 * Controlador de cierre de sesión.
 * Destruye la sesión y redirige al login.
 */

// Inicia la sesión para poder destruirla
session_start();

// Limpia todas las variables de sesión
$_SESSION = [];

// Destruye la sesión completamente
session_destroy();

// Redirige al formulario de login
header("Location: ../views/auth/login.php");
exit;
