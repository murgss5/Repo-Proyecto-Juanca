<?php
/**
 * controlador de cierre de sesion
 * destruye la sesion y redirige al login
 */

// inicia la sesion para poder destruirla
session_start();

// limpia todas las variables de sesion
$_SESSION = [];

// destruye la sesion completamente
session_destroy();

// redirige al formulario de login
header("Location: ../views/auth/login.php");
exit;