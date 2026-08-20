<?php
/**
 * Funciones compartidas de autenticación y autorización.
 *
 * Las vistas protegidas indican los roles que pueden acceder a ellas.
 * Los identificadores coinciden con la tabla rol:
 * 1 = Administrador, 2 = Instructor, 3 = Aprendiz.
 */

function redirigirAlLogin(): void
{
    $profundidad = substr_count(trim(dirname($_SERVER['SCRIPT_NAME']), '/'), '/');
    $rutaBase = str_repeat('../', max(0, $profundidad));

    header('Location: ' . $rutaBase . 'views/auth/login.php');
    exit;
}

function exigirRol(int ...$rolesPermitidos): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $rolActual = $_SESSION['fk_rol'] ?? null;

    if (!is_int($rolActual) && !ctype_digit((string) $rolActual)) {
        redirigirAlLogin();
    }

    if (!in_array((int) $rolActual, $rolesPermitidos, true)) {
        redirigirAlLogin();
    }
}
