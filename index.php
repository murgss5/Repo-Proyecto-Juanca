<?php
/**
 * Archivo principal que redirige al login.
 * Es el punto de entrada cuando se accede a la raíz del proyecto.
 */

// Redirige al formulario de inicio de sesión
header("Location: views/auth/login.php");

exit;