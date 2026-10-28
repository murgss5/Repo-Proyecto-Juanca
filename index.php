<?php
/**
 * Archivo principal que redirige al login.
 * Es el punto de entrada cuando se accede a la raíz del proyecto.
 */

require_once __DIR__ . '/includes/constantes.php';

// Redirige al formulario de inicio de sesión utilizando la ruta base absoluta
header("Location: " . RUTA_BASE . "views/auth/login.php");

exit;