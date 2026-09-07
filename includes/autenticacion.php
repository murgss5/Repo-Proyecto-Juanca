<?php
/**
 * Funciones compartidas de autenticación y autorización.
 *
 * Las vistas protegidas indican los roles que pueden acceder a ellas.
 * Los identificadores coinciden con la tabla rol:
 * 1 = Administrador, 2 = Instructor, 3 = Aprendiz.
 */

/**
 * Redirige al formulario de login calculando la ruta correcta según la profundidad del script.
 */
function redirigirAlLogin(): void
{
    $profundidad = substr_count(trim(dirname($_SERVER['SCRIPT_NAME']), '/'), '/');
    $rutaBase = str_repeat('../', max(0, $profundidad));

    header('Location: ' . $rutaBase . 'views/auth/login.php');
    exit;
}

/**
 * Verifica que la sesión esté activa y que el usuario tenga uno de los roles permitidos.
 * Si no hay sesión o el rol no coincide, redirige al login.
 * @param int ...$rolesPermitidos Roles que pueden acceder (1=Admin, 2=Instructor, 3=Aprendiz)
 */
function exigirRol(int ...$rolesPermitidos): void
{
    // Inicia la sesión si no está activa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Obtiene el rol del usuario de la sesión
    $rolActual = $_SESSION['fk_rol'] ?? null;

    // Verifica que el rol sea un número válido
    if (!is_int($rolActual) && !ctype_digit((string) $rolActual)) {
        redirigirAlLogin();
    }

    // Verifica que el rol esté en la lista de permitidos
    if (!in_array((int) $rolActual, $rolesPermitidos, true)) {
        redirigirAlLogin();
    }
}
