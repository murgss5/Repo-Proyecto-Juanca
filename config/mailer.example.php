<?php
/**
 * =============================================================================
 * PLANTILLA DE CONFIGURACIÓN DEL MOTOR DE CORREO (PHPMailer Manual)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * =============================================================================
 * Copie este archivo como 'config/mailer.php' y asigne sus credenciales reales.
 * =============================================================================
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 1. Carga estandarizada de dependencias manuales de PHPMailer
$rutaLibs = __DIR__ . '/../libs/PHPMailer/';

if (file_exists($rutaLibs . 'Exception.php') && file_exists($rutaLibs . 'PHPMailer.php') && file_exists($rutaLibs . 'SMTP.php')) {
    require_once $rutaLibs . 'Exception.php';
    if (file_exists($rutaLibs . 'OAuthTokenProvider.php')) {
        require_once $rutaLibs . 'OAuthTokenProvider.php';
    }
    if (file_exists($rutaLibs . 'LoggerInterface.php')) {
        require_once $rutaLibs . 'LoggerInterface.php';
    }
    require_once $rutaLibs . 'PHPMailer.php';
    require_once $rutaLibs . 'SMTP.php';
} elseif (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    throw new \RuntimeException("No se encontró la librería PHPMailer en 'libs/PHPMailer/' ni en 'vendor/'.");
}

// 2. Cargar claves adicionales si existen
if (file_exists(__DIR__ . '/claves.php')) {
    require_once __DIR__ . '/claves.php';
}

// 3. Parámetros de Configuración SMTP
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 465); // 465 para SSL, 587 para TLS
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', 'ssl'); // 'ssl' o 'tls'
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', 'TU_CORREO_AQUI@gmail.com');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', 'TU_APP_PASSWORD_AQUI'); // Contraseña de aplicación de 16 caracteres de Google
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'Sistema de Asistencia SENA');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', SMTP_USER);
}

/**
 * Crea y preconfigura una nueva instancia de PHPMailer con soporte SSL para XAMPP
 */
function obtenerPHPMailer(bool $debug = false): PHPMailer
{
    $mail = new PHPMailer(true);

    if ($debug) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    } else {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
    }

    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->Port       = (int)SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    if (strtolower(SMTP_SECURE) === 'tls' || (int)SMTP_PORT === 587) {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];

    $fromEmail = filter_var(SMTP_FROM_EMAIL, FILTER_VALIDATE_EMAIL) ? SMTP_FROM_EMAIL : SMTP_USER;
    $mail->setFrom($fromEmail, SMTP_FROM_NAME);

    return $mail;
}

/**
 * Envía el correo institucional con credenciales de acceso al aprendiz o usuario
 */
function enviarCredencialesSena(string $destinatario, string $nombre, string $usuario, string $clave): bool
{
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException("La dirección de correo destino '{$destinatario}' no es válida.");
    }

    if (SMTP_USER === 'TU_CORREO_AQUI@gmail.com' || SMTP_PASS === 'TU_APP_PASSWORD_AQUI') {
        throw new \RuntimeException("Configure su correo y contraseña de aplicación SMTP en 'config/mailer.php' antes de realizar envíos reales.");
    }

    $mail = obtenerPHPMailer();
    $mail->addAddress($destinatario, $nombre);

    $asunto = 'Credenciales de Acceso - Sistema de Control de Ingreso SENA';
    $mail->Subject = $asunto;

    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $rutaBase = defined('RUTA_BASE') ? RUTA_BASE : '/proyecto_juanca/';
    $urlLogin = $protocolo . $host . $rutaBase . 'index.php';

    $cuerpoHtml = '<!DOCTYPE html>
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
                    <span class="cred-value">' . htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Contraseña:</span>
                    <span class="cred-value">' . htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
            </div>

            <p class="text" style="font-size: 13px; color: #718096;">
                <em>Por política institucional, su usuario y contraseña corresponden inicialmente a su número de documento de identidad. Puede acceder a la plataforma haciendo clic en el siguiente botón:</em>
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

    $cuerpoTexto = "Cordial saludo, {$nombre}.\n\n"
        . "Se han generado sus credenciales de acceso para el Sistema de Control de Ingreso SENA:\n\n"
        . "Usuario: {$usuario}\n"
        . "Contraseña: {$clave}\n\n"
        . "Acceda al sistema en: {$urlLogin}\n\n"
        . "Atentamente,\nSistema de Asistencia SENA";

    $mail->isHTML(true);
    $mail->Body    = $cuerpoHtml;
    $mail->AltBody = $cuerpoTexto;

    return $mail->send();
}
