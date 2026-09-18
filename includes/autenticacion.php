<?php
/**
 * funciones compartidas de autenticacion y autorizacion
 *
 * las vistas protegidas indican los roles que pueden acceder a ellas
 * los identificadores coinciden con la tabla rol:
 * 1 = administrador, 2 = instructor, 3 = aprendiz
 */

// cabeceras anti cache para evitar cache de paginas protegidas
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/**
 * redirige al formulario de login calculando la ruta correcta segun la profundidad del script
 */
function redirigirAlLogin(): void
{
    $profundidad = substr_count(trim(dirname($_SERVER['SCRIPT_NAME']), '/'), '/');
    $rutaBase = str_repeat('../', max(0, $profundidad));

    header('Location: ' . $rutaBase . 'views/auth/login.php');
    exit;
}

/**
 * verifica que la sesion este activa y que el usuario tenga uno de los roles permitidos
 * si no hay sesion o el rol no coincide, redirige al login
 * @param int ...$rolesPermitidos Roles que pueden acceder (1=Admin, 2=Instructor, 3=Aprendiz)
 */
function exigirRol(int ...$rolesPermitidos): void
{
    // inicia la sesion si no esta activa
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // obtiene el rol del usuario de la sesion
    $rolActual = $_SESSION['fk_rol'] ?? null;

    // verifica que el rol sea un numero valido
    if (!is_int($rolActual) && !ctype_digit((string) $rolActual)) {
        redirigirAlLogin();
    }

    // verifica que el rol este en la lista de permitidos
    if (!in_array((int) $rolActual, $rolesPermitidos, true)) {
        redirigirAlLogin();
    }
}