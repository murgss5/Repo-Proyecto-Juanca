<?php
/**
 * funciones compartidas de autenticacion y autorizacion
 *
 * las vistas protegidas indican los roles que pueden acceder a ellas
 * los identificadores coinciden con la tabla rol:
 * 1 = administrador, 2 = instructor, 3 = aprendiz
 */

// cabeceras anti cache para evitar cache de paginas protegidas
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

/**
 * Inicia la sesion con configuracion de cookies segura e inicializa token CSRF
 */
function asegurarSesionIniciada(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $esHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_start();
    }

    if (empty($_SESSION['token_csrf']) || !is_string($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
}

/**
 * redirige al formulario de login calculando la ruta correcta segun la profundidad del script
 */
function redirigirAlLogin(): void
{
    $profundidad = substr_count(trim(dirname($_SERVER['SCRIPT_NAME']), '/'), '/');
    $rutaBase = str_repeat('../', max(0, $profundidad));

    // Si es peticion AJAX o solicita JSON, responder HTTP 401 sin redireccion HTML
    $solicitadoCon = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $acepta = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (strtolower($solicitadoCon) === 'xmlhttprequest' || stripos($acepta, 'application/json') !== false) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode([
            'success'   => false,
            'error'     => 'Sesion no activa o expirada.',
            'redirect'  => $rutaBase . 'views/auth/login.php'
        ]);
        exit;
    }

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
    asegurarSesionIniciada();

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

    // Cabeceras anti-cache estrictas justo despues de validar la sesion
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }
}

/**
 * guarda un mensaje flash en la sesion
 * @param string $tipo tipo de mensaje: 'success', 'error', 'info', 'warning'
 * @param string $mensaje texto del mensaje
 */
function setFlash(string $tipo, string $mensaje): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensaje' => $mensaje
    ];
}

/**
 * obtiene y elimina el mensaje flash de la sesion
 * @return array|null array con 'tipo' y 'mensaje' o null si no hay
 */
function getFlash(): ?array
{
    asegurarSesionIniciada();
    $flash = $_SESSION['flash'] ?? null;
    if ($flash) {
        unset($_SESSION['flash']);
    }
    return $flash;
}

/**
 * obtiene el usuario actual desde la sesion
 * @return array|null array con datos del usuario o null si no hay sesion valida
 */
function usuarioActual(): ?array
{
    asegurarSesionIniciada();

    // valida que exista id_usuario en sesion
    if (!isset($_SESSION['id_usuario']) || $_SESSION['id_usuario'] === '') {
        return null;
    }

    // valida fk_rol: entero o cadena numerica
    $fkRol = $_SESSION['fk_rol'] ?? null;
    if ($fkRol === null || (!is_int($fkRol) && !ctype_digit((string) $fkRol))) {
        return null;
    }

    $fkRolInt = (int) $fkRol;

    // acepta solamente roles 1, 2 y 3
    if (!in_array($fkRolInt, [1, 2, 3], true)) {
        return null;
    }

    // Cabeceras anti-cache estrictas justo despues de validar la sesion
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    // construye nombre_completo
    $nombre = $_SESSION['nombre'] ?? '';
    $apellido = $_SESSION['apellido'] ?? '';
    $nombreCompleto = trim($nombre . ' ' . $apellido);
    if ($nombreCompleto === '') {
        $nombreCompleto = $_SESSION['nombre_usuario'] ?? '';
    }
    if ($nombreCompleto === '') {
        $nombreCompleto = 'Usuario';
    }

    // retorna arreglo seguro con datos del usuario
    return [
        'id_usuario' => (int) $_SESSION['id_usuario'],
        'nombre_usuario' => $_SESSION['nombre_usuario'] ?? '',
        'nombre' => $_SESSION['nombre'] ?? '',
        'apellido' => $_SESSION['apellido'] ?? '',
        'nombre_completo' => $nombreCompleto,
        'fk_rol' => (int) $_SESSION['fk_rol'],
        'nombre_rol' => $_SESSION['nombre_rol'] ?? '',
    ];
}