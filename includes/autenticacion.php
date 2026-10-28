<?php
/**
 * funciones compartidas de autenticacion y autorizacion
 *
 * Arquitectura Multitenancy de Roles:
 * Permite la sesion simultanea en el mismo navegador para Admin, Instructor y Aprendiz
 * almacenando la informacion de cada rol en su propio namespace dentro de $_SESSION:
 *   - $_SESSION['admin']
 *   - $_SESSION['instructor']
 *   - $_SESSION['aprendiz']
 *
 * Mapeo de roles (tabla rol):
 * 1 = administrador (admin)
 * 2 = instructor
 * 3 = aprendiz
 */

// cabeceras anti cache para evitar cache de paginas protegidas
if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
}

require_once __DIR__ . '/constantes.php';

/**
 * Mapea un identificador de rol o clave a su clave canonica de sesion ('admin', 'instructor', 'aprendiz')
 * @param int|string|null $rol
 * @return string|null
 */
function rolClave(int|string|null $rol): ?string
{
    if ($rol === null) {
        return null;
    }
    $r = is_numeric($rol) ? (int)$rol : strtolower(trim((string)$rol));
    return match ($r) {
        1, 'admin', 'administrador' => 'admin',
        2, 'instructor' => 'instructor',
        3, 'aprendiz' => 'aprendiz',
        default => null,
    };
}

/**
 * Mapea una clave de rol o identificador al ID numerico correspondiente (1, 2, 3)
 * @param int|string|null $rol
 * @return int|null
 */
function rolId(int|string|null $rol = null): ?int
{
    if ($rol === null) {
        $usuario = usuarioActual();
        return $usuario['fk_rol'] ?? detectarRolContexto() ?? 1;
    }
    if (is_numeric($rol)) {
        $id = (int)$rol;
        return in_array($id, [1, 2, 3], true) ? $id : null;
    }
    return match (strtolower(trim((string)$rol))) {
        'admin', 'administrador' => 1,
        'instructor' => 2,
        'aprendiz' => 3,
        default => null,
    };
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
 * Detecta el rol contextual actual segun la variable global o la ruta del script en ejecucion
 * @return int|null ID de rol (1, 2, 3) o null
 */
function detectarRolContexto(): ?int
{
    if (isset($GLOBALS['ROL_CONTEXTO_ACTUAL']) && is_int($GLOBALS['ROL_CONTEXTO_ACTUAL'])) {
        return $GLOBALS['ROL_CONTEXTO_ACTUAL'];
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/views/admin/') !== false || strpos($script, '/admin/') !== false) {
        return 1;
    }
    if (strpos($script, '/views/instructor/') !== false || strpos($script, '/instructor/') !== false) {
        return 2;
    }
    if (strpos($script, '/views/aprendiz/') !== false || strpos($script, '/aprendiz/') !== false) {
        return 3;
    }

    return null;
}

/**
 * redirige al formulario de login calculando la ruta correcta segun la profundidad del script
 */
function redirigirAlLogin(): void
{
    if (!defined('RUTA_BASE')) {
        require_once __DIR__ . '/constantes.php';
    }

    $loginUrl = RUTA_BASE . 'views/auth/login.php';

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
            'redirect'  => $loginUrl
        ]);
        exit;
    }

    header('Location: ' . $loginUrl);
    exit;
}

/**
 * verifica que la sesion este activa y que el usuario tenga uno de los roles permitidos en su namespace
 * si no hay sesion activa para ninguno de los roles solicitados, redirige al login
 * @param int ...$rolesPermitidos Roles que pueden acceder (1=Admin, 2=Instructor, 3=Aprendiz)
 */
function exigirRol(int ...$rolesPermitidos): void
{
    asegurarSesionIniciada();

    $rolEncontrado = null;
    foreach ($rolesPermitidos as $rolReq) {
        $clave = rolClave($rolReq);
        if ($clave && isset($_SESSION[$clave]) && is_array($_SESSION[$clave]) && !empty($_SESSION[$clave]['id_usuario'])) {
            $rolEncontrado = (int)$rolReq;
            break;
        }
    }

    // Compatibilidad transicional: si aun existe la sesion plana previa y coincide con los permitidos
    if ($rolEncontrado === null && isset($_SESSION['id_usuario'], $_SESSION['fk_rol'])) {
        $rolPlano = (int)$_SESSION['fk_rol'];
        if (in_array($rolPlano, $rolesPermitidos, true)) {
            $rolEncontrado = $rolPlano;
            $clavePlana = rolClave($rolPlano);
            if ($clavePlana && !isset($_SESSION[$clavePlana])) {
                $_SESSION[$clavePlana] = [
                    'id_usuario'     => (int)$_SESSION['id_usuario'],
                    'nombre_usuario' => $_SESSION['nombre_usuario'] ?? '',
                    'nombre'         => $_SESSION['nombre'] ?? '',
                    'apellido'       => $_SESSION['apellido'] ?? '',
                    'nombre_completo'=> trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? '')) ?: 'Usuario',
                    'identificacion' => $_SESSION['identificacion'] ?? '',
                    'fk_rol'         => $rolPlano,
                    'nombre_rol'     => $_SESSION['nombre_rol'] ?? ucfirst($clavePlana),
                ];
            }
        }
    }

    // Si no se encontro ningun rol autorizado en la sesion activa
    if ($rolEncontrado === null) {
        redirigirAlLogin();
    }

    // Establecer el contexto del rol actual para la ejecucion de la peticion
    $GLOBALS['ROL_CONTEXTO_ACTUAL'] = $rolEncontrado;

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
 * Registra o actualiza la sesion de un usuario dentro del namespace de su rol
 * @param array $usuario Datos del usuario obtenidos de la base de datos
 * @return string Clave de sesion utilizada ('admin', 'instructor', 'aprendiz')
 */
function iniciarSesionRol(array $usuario): string
{
    asegurarSesionIniciada();

    $fkRol = (int)($usuario['fk_rol'] ?? 0);
    $clave = rolClave($fkRol);
    if (!$clave) {
        throw new InvalidArgumentException("El rol proporcionado no es valido para iniciar sesion.");
    }

    $nombre = $usuario['nombre'] ?? '';
    $apellido = $usuario['apellido'] ?? '';
    $nombreCompleto = trim($nombre . ' ' . $apellido);
    if ($nombreCompleto === '') {
        $nombreCompleto = $usuario['nombre_usuario'] ?? 'Usuario';
    }

    $_SESSION[$clave] = [
        'id_usuario'     => (int)$usuario['id_usuario'],
        'nombre_usuario' => $usuario['nombre_usuario'] ?? '',
        'nombre'         => $nombre,
        'apellido'       => $apellido,
        'nombre_completo'=> $nombreCompleto,
        'identificacion' => $usuario['identificacion'] ?? '',
        'fk_rol'         => $fkRol,
        'nombre_rol'     => $usuario['nombre_rol'] ?? ucfirst($clave),
        'telefono'       => $usuario['telefono'] ?? null,
    ];

    // Limpiar variables planas obsoletas para no provocar colisiones
    unset($_SESSION['id_usuario'], $_SESSION['nombre_usuario'], $_SESSION['nombre'], $_SESSION['apellido'], $_SESSION['identificacion'], $_SESSION['fk_rol'], $_SESSION['nombre_rol']);

    return $clave;
}

/**
 * Cierra la sesion de un rol especifico. Si no quedan otros roles activos, destruye la sesion por completo.
 * @param int|string|null $rol Rol a cerrar. Si es null, intenta detectarlo del contexto o cierra todo si no se detecta.
 * @return bool Retorna true si quedan otros roles activos, o false si la sesion fue totalmente destruida.
 */
function cerrarSesionRol(int|string|null $rol = null): bool
{
    asegurarSesionIniciada();

    $clave = rolClave($rol) ?? rolClave(detectarRolContexto());

    if ($clave && isset($_SESSION[$clave])) {
        unset($_SESSION[$clave]);
        if ($clave === 'admin') {
            unset($_SESSION['import_preview'], $_SESSION['import_resultado']);
        } elseif ($clave === 'instructor') {
            unset($_SESSION['import_preview_instructor'], $_SESSION['rfid_importacion_pendiente']);
        }
    } else {
        // Si no se especifico rol o no coincide, remover todos los roles
        unset($_SESSION['admin'], $_SESSION['instructor'], $_SESSION['aprendiz']);
    }

    // Verificar si queda al menos un rol autenticado
    $quedanRoles = (!empty($_SESSION['admin']['id_usuario']) ||
                    !empty($_SESSION['instructor']['id_usuario']) ||
                    !empty($_SESSION['aprendiz']['id_usuario']));

    if (!$quedanRoles) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? null) == 443;
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => $esHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
        session_destroy();
        return false;
    }

    return true;
}

/**
 * obtiene el usuario actual desde el namespace correspondiente en la sesion
 * @param int|string|null $rol Opcional: rol especifico a consultar (1/'admin', 2/'instructor', 3/'aprendiz')
 * @return array|null array con datos del usuario o null si no hay sesion valida
 */
function usuarioActual(int|string|null $rol = null): ?array
{
    asegurarSesionIniciada();

    $clave = null;
    if ($rol !== null) {
        $clave = rolClave($rol);
    } else {
        $contexto = detectarRolContexto();
        if ($contexto !== null) {
            $clave = rolClave($contexto);
        }
    }

    // Si aun no hay clave, buscar si hay una unica sesion activa en los tres roles
    if ($clave === null) {
        $rolesActivos = [];
        foreach (['admin', 'instructor', 'aprendiz'] as $r) {
            if (isset($_SESSION[$r]) && is_array($_SESSION[$r]) && !empty($_SESSION[$r]['id_usuario'])) {
                $rolesActivos[] = $r;
            }
        }
        if (count($rolesActivos) === 1) {
            $clave = $rolesActivos[0];
        }
    }

    // Si no hay datos para la clave encontrada, verificar compatibilidad transicional con sesion plana
    if ($clave === null || empty($_SESSION[$clave]) || !is_array($_SESSION[$clave]) || empty($_SESSION[$clave]['id_usuario'])) {
        if (isset($_SESSION['id_usuario'], $_SESSION['fk_rol']) && $_SESSION['id_usuario'] !== '') {
            $nombre = $_SESSION['nombre'] ?? '';
            $apellido = $_SESSION['apellido'] ?? '';
            $nombreCompleto = trim($nombre . ' ' . $apellido);
            if ($nombreCompleto === '') {
                $nombreCompleto = $_SESSION['nombre_usuario'] ?? 'Usuario';
            }
            return [
                'id_usuario'     => (int)$_SESSION['id_usuario'],
                'nombre_usuario' => $_SESSION['nombre_usuario'] ?? '',
                'nombre'         => $nombre,
                'apellido'       => $apellido,
                'nombre_completo'=> $nombreCompleto,
                'identificacion' => $_SESSION['identificacion'] ?? '',
                'fk_rol'         => (int)$_SESSION['fk_rol'],
                'nombre_rol'     => $_SESSION['nombre_rol'] ?? '',
                'telefono'       => $_SESSION['telefono'] ?? null,
            ];
        }
        return null;
    }

    $datos = $_SESSION[$clave];

    // Cabeceras anti-cache estrictas justo despues de validar la sesion
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    $nombre = $datos['nombre'] ?? '';
    $apellido = $datos['apellido'] ?? '';
    $nombreCompleto = $datos['nombre_completo'] ?? trim($nombre . ' ' . $apellido);
    if ($nombreCompleto === '') {
        $nombreCompleto = $datos['nombre_usuario'] ?? 'Usuario';
    }

    return [
        'id_usuario'     => (int)$datos['id_usuario'],
        'nombre_usuario' => $datos['nombre_usuario'] ?? '',
        'nombre'         => $nombre,
        'apellido'       => $apellido,
        'nombre_completo'=> $nombreCompleto,
        'identificacion' => $datos['identificacion'] ?? '',
        'fk_rol'         => (int)($datos['fk_rol'] ?? rolId($clave)),
        'nombre_rol'     => $datos['nombre_rol'] ?? ucfirst($clave),
        'telefono'       => $datos['telefono'] ?? null,
    ];
}

/**
 * Neutraliza valores susceptibles de inyección de fórmulas CSV (CWE-1236 / Formula Injection).
 * Si la celda comienza con caracteres disparadores (=, +, -, @, \t, \r, |, %) tras ignorar
 * espacios y caracteres de control iniciales, antepone un apóstrofo ASCII (') para forzar
 * su interpretación como texto literal en Excel, LibreOffice y otras aplicaciones de hoja de cálculo.
 * Los números legítimos (como 123456 o -5) y textos normales (como correos o nombres) se conservan intactos.
 *
 * @param mixed $valor Valor de la celda a evaluar
 * @return mixed Valor seguro para exportación CSV
 */
function neutralizarCeldaCsv($valor)
{
    if ($valor === null || is_int($valor) || is_float($valor) || is_bool($valor)) {
        return $valor;
    }

    $str = (string)$valor;
    if ($str === '') {
        return $str;
    }

    // Caracteres invisibles o de control iniciales que hojas de cálculo pueden ignorar antes de interpretar fórmulas
    $trimmed = ltrim($str, " \t\r\n\0\x0B");
    if ($trimmed === '') {
        return $str;
    }

    $primerChar = $trimmed[0];

    // Caracteres que disparan fórmulas o ejecución de comandos
    $disparadores = ['=', '@', '|', '%', "\t", "\r"];
    if (in_array($primerChar, $disparadores, true)) {
        return "'" . $str;
    }

    // Si empieza por '+' o '-', neutralizar si NO es un número escalar puro
    // (ej. -5 o +5.2 se preservan numéricamente, pero -1+2 o +SUM(...) se neutralizan)
    if ($primerChar === '+' || $primerChar === '-') {
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $trimmed)) {
            return "'" . $str;
        }
    }

    return $str;
}

/**
 * Aplica neutralización de fórmulas a todas las celdas de una fila para exportación CSV.
 *
 * @param array $fila Array de valores que componen la fila del CSV
 * @return array Fila con todas sus celdas neutralizadas de forma segura
 */
function neutralizarFilaCsv(array $fila): array
{
    return array_map('neutralizarCeldaCsv', $fila);
}

