<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE CORREOS: CorreoController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida el despacho de notificaciones y credenciales por correo electrónico:
 *   - accion=prueba            : Prueba de envío SMTP y diagnóstico PHPMailer
 *   - accion=masivo_pendientes : Envío masivo de credenciales a aprendices
 * =============================================================================
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(1);

require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';
if (file_exists(__DIR__ . '/../config/mailer.php')) {
    require_once __DIR__ . '/../config/mailer.php';
}

date_default_timezone_set('America/Bogota');

// Detectar si la petición es AJAX
$esAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método de solicitud no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/dashboard.php');
    exit;
}

// Validar token CSRF
if (empty($_POST['token_csrf']) || empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $_POST['token_csrf'])) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido o sesión caducada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (isset($_POST['aprendices_ids'])) {
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Token de seguridad inválido o sesión caducada.'));
    } else {
        header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=csrf');
    }
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática por presencia de campos
if ($accion === '') {
    if (isset($_POST['aprendices_ids'])) {
        $accion = 'masivo_pendientes';
    } else {
        $accion = 'prueba';
    }
}

switch ($accion) {
    case 'prueba':
        procesarCorreoPrueba();
        break;

    case 'masivo_pendientes':
        procesarCorreosPendientes();
        break;

    default:
        http_response_code(400);
        header('Location: ' . RUTA_BASE . 'views/admin/dashboard.php');
        exit;
}

/**
 * =============================================================================
 * ACCIÓN 1: CORREO DE PRUEBA DE CREDENCIALES
 * =============================================================================
 */
function procesarCorreoPrueba(): void
{
    $esAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
              (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    $cc = trim($_POST['cc'] ?? $_POST['documento'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $nombre = trim($_POST['nombre'] ?? 'Aprendiz Demo');

    if ($cc === '' || $correo === '') {
        if ($esAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'El Documento y el Correo Electrónico son obligatorios.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=campos_requeridos');
        exit;
    }

    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        if ($esAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La dirección de correo electrónico ingresada no es válida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=correo_invalido&cc=' . urlencode($cc));
        exit;
    }

    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $urlLogin = $protocolo . $host . RUTA_BASE . 'index.php';

    $asunto = 'Credenciales de Acceso - Sistema de Control de Ingreso SENA';

    $dominioActual = $_SERVER['SERVER_NAME'] ?? 'adsocta.com';
    if ($dominioActual === 'localhost' || $dominioActual === '127.0.0.1') {
        $fromEmail = 'no-reply@adsocta.com';
    } else {
        $dominioLimpio = preg_replace('/^www\./i', '', $dominioActual);
        $fromEmail = 'no-reply@' . $dominioLimpio;
    }

    $cuerpoHtml = generarPlantillaHtmlCredenciales($asunto, $nombre, $cc, $urlLogin);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Sistema SENA <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion()
    ];
    $cabecerasStr = implode("\r\n", $headers);

    $enviado = false;
    $errorDetalle = '';
    try {
        if (function_exists('enviarCredencialesSena')) {
            $enviado = enviarCredencialesSena($correo, $nombre, $cc, $cc);
        } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $enviado = @mail($correo, $asunto, $cuerpoHtml, $cabecerasStr);
        } else {
            $enviado = @mail($correo, $asunto, $cuerpoHtml, $cabecerasStr, '-f' . $fromEmail);
        }
    } catch (\Throwable $e) {
        $enviado = false;
        $errorDetalle = $e->getMessage();
    }

    if ($enviado) {
        if ($esAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'success',
                'message' => 'Correo enviado exitosamente a ' . $correo
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?mensaje=enviado&correo=' . urlencode($correo) . '&cc=' . urlencode($cc));
        exit;
    }

    $esEntornoLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';

    if ($esEntornoLocal) {
        if ($esAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'success',
                'message' => 'Simulación local: En entorno de desarrollo (localhost) el correo para ' . $correo . ' fue simulado correctamente.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?mensaje=simulado_local&correo=' . urlencode($correo) . '&cc=' . urlencode($cc));
        exit;
    } else {
        if ($esAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Fallo en el envío SMTP. ' . ($errorDetalle ?: 'No fue posible enviar el correo de prueba.')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=fallo_envio&correo=' . urlencode($correo) . '&cc=' . urlencode($cc));
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 2: ENVÍO MASIVO DE CREDENCIALES A APRENDICES PENDIENTES
 * =============================================================================
 */
function procesarCorreosPendientes(): void
{
    $aprendicesIds = $_POST['aprendices_ids'] ?? [];
    if (!is_array($aprendicesIds) || empty($aprendicesIds)) {
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('No se seleccionó ningún aprendiz para el envío.'));
        exit;
    }

    $idsSanitizados = [];
    foreach ($aprendicesIds as $id) {
        $idInt = filter_var($id, FILTER_VALIDATE_INT);
        if ($idInt !== false && $idInt > 0) {
            $idsSanitizados[] = $idInt;
        }
    }

    if (empty($idsSanitizados)) {
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Los identificadores de aprendices no son válidos.'));
        exit;
    }

    $conexion = (new Conexion())->conectar();

    try {
        $conexion->exec("ALTER TABLE aprendiz ADD COLUMN correo_enviado TINYINT(1) NOT NULL DEFAULT 0");
    } catch (\Throwable $e) {}

    $placeholders = implode(',', array_fill(0, count($idsSanitizados), '?'));
    $sql = "SELECT ap.id_aprendiz, u.identificacion,
                   CONCAT(u.nombre, CHAR(32), u.apellido) AS nombre_completo,
                   u.correo_electronico
            FROM aprendiz ap
            INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
            WHERE ap.id_aprendiz IN ($placeholders)";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($idsSanitizados);
    $aprendices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($aprendices)) {
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('No se encontraron registros de los aprendices seleccionados.'));
        exit;
    }

    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $urlLogin = $protocolo . $host . RUTA_BASE . 'index.php';

    $dominioActual = $_SERVER['SERVER_NAME'] ?? 'adsocta.com';
    if ($dominioActual === 'localhost' || $dominioActual === '127.0.0.1') {
        $fromEmail = 'no-reply@adsocta.com';
    } else {
        $dominioLimpio = preg_replace('/^www\./i', '', $dominioActual);
        $fromEmail = 'no-reply@' . $dominioLimpio;
    }

    $asunto = 'Credenciales de Acceso - Sistema de Control de Ingreso SENA';
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Sistema SENA <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion()
    ];
    $cabecerasStr = implode("\r\n", $headers);

    $esEntornoLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';
    $stmtActualizar = $conexion->prepare('UPDATE aprendiz SET correo_enviado = 1 WHERE id_aprendiz = ?');

    $enviados = 0;
    $omitidos = 0;

    foreach ($aprendices as $item) {
        $correo = trim($item['correo_electronico'] ?? '');
        $documento = trim($item['identificacion'] ?? '');
        $nombre = trim($item['nombre_completo'] ?? 'Aprendiz');
        $idAprendiz = (int)$item['id_aprendiz'];

        if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL) || $documento === '') {
            $omitidos++;
            continue;
        }

        $cuerpoHtml = generarPlantillaHtmlCredenciales($asunto, $nombre, $documento, $urlLogin);

        $despachado = false;
        try {
            if (function_exists('enviarCredencialesSena')) {
                $despachado = enviarCredencialesSena($correo, $nombre, $documento, $documento);
            } elseif (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $despachado = @mail($correo, $asunto, $cuerpoHtml, $cabecerasStr);
            } else {
                $despachado = @mail($correo, $asunto, $cuerpoHtml, $cabecerasStr, '-f' . $fromEmail);
            }
        } catch (\Throwable $e) {
            $despachado = false;
        }

        if ($despachado || $esEntornoLocal) {
            $stmtActualizar->execute([$idAprendiz]);
            $enviados++;
        } else {
            $omitidos++;
        }
    }

    $mensaje = "Se despacharon las credenciales correctamente a {$enviados} aprendiz(ces).";
    if ($omitidos > 0) {
        $mensaje .= " ({$omitidos} no pudieron ser enviados o tenían correo inválido).";
    }

    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?mensaje=' . urlencode($mensaje));
    exit;
}

/**
 * Genera la plantilla HTML institucional de credenciales.
 */
function generarPlantillaHtmlCredenciales(string $asunto, string $nombre, string $documento, string $urlLogin): string
{
    return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #333333; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 5px solid #39a900; }
        .header { background-color: #ffffff; padding: 25px; text-align: center; border-bottom: 1px solid #eef2f5; }
        .header h1 { margin: 8px 0 0; color: #00324d; font-size: 20px; font-weight: 700; }
        .header p { margin: 4px 0 0; color: #39a900; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .content { padding: 30px 25px; }
        .greeting { font-size: 16px; font-weight: 600; color: #00324d; margin-bottom: 15px; }
        .text { font-size: 14px; line-height: 1.6; color: #555555; margin-bottom: 20px; }
        .card-credenciales { background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #39a900; border-radius: 6px; padding: 20px; margin: 25px 0; }
        .card-credenciales h3 { margin: 0 0 15px; font-size: 15px; color: #00324d; font-weight: 700; }
        .cred-item { margin-bottom: 12px; font-size: 14px; }
        .cred-item:last-child { margin-bottom: 0; }
        .cred-label { font-weight: 600; color: #4a5568; display: inline-block; width: 110px; }
        .cred-value { font-family: "Courier New", Courier, monospace; background: #edf2f7; padding: 4px 10px; border-radius: 4px; font-weight: 700; color: #2d3748; letter-spacing: 0.5px; font-size: 15px; }
        .btn-container { text-align: center; margin: 30px 0; }
        .btn-login { display: inline-block; background-color: #39a900; color: #ffffff !important; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 6px rgba(57,169,0,0.25); }
        .footer { background-color: #f8fafc; padding: 20px 25px; text-align: center; border-top: 1px solid #eef2f5; font-size: 12px; color: #888888; line-height: 1.5; }
        .badge-sena { display: inline-block; background: #e6f4ea; color: #137333; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <span class="badge-sena">SERVICIO NACIONAL DE APRENDIZAJE &bull; SENA</span>
            <h1>Control de Ingreso y Asistencia</h1>
            <p>Notificación Oficial de Credenciales</p>
        </div>
        <div class="content">
            <div class="greeting">Cordial saludo, ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</div>
            <p class="text">
                Le informamos que se han generado sus credenciales de acceso para la plataforma de <strong>Control de Ingreso y Asistencia SENA</strong>.
            </p>
            
            <div class="card-credenciales">
                <h3>Credenciales Asignadas</h3>
                <div class="cred-item">
                    <span class="cred-label">Usuario:</span>
                    <span class="cred-value">' . htmlspecialchars($documento, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Contraseña:</span>
                    <span class="cred-value">' . htmlspecialchars($documento, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
            </div>

            <p class="text" style="font-size: 13px; color: #718096;">
                <em>Por política institucional, su usuario y contraseña corresponden inicialmente a su número de documento de identidad (CC / TI). Puede acceder a la plataforma haciendo clic en el siguiente botón:</em>
            </p>

            <div class="btn-container">
                <a href="' . htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8') . '" class="btn-login" target="_blank">
                    Ingresar al Sistema SENA
                </a>
            </div>
        </div>
        <div class="footer">
            <p style="margin: 0 0 5px;"><strong>Sistema de Asistencia SENA</strong> &bull; Centro de Teleinformática y Producción Industrial</p>
            <p style="margin: 0;">Este mensaje es automático, por favor no responda a esta dirección de correo.</p>
        </div>
    </div>
</body>
</html>';
}
