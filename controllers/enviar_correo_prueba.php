<?php
/**
 * ============================================================
 * CONTROLADOR: ENVIAR CORREO DE PRUEBA DE CREDENCIALES
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Procesa la prueba de despacho de correo para verificar el
 * funcionamiento de mail() en entornos de producción (Hostinger).
 * ============================================================
 */

require_once dirname(__DIR__) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/includes/constantes.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php');
    exit;
}

// Validar Token CSRF
if (empty($_POST['token_csrf']) || empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $_POST['token_csrf'])) {
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=csrf');
    exit;
}

$cc = trim($_POST['cc'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$nombre = trim($_POST['nombre'] ?? 'Aprendiz Demo');

// Validar campos obligatorios
if ($cc === '' || $correo === '') {
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=campos_requeridos');
    exit;
}

// Validar formato de correo electrónico
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=correo_invalido&cc=' . urlencode($cc));
    exit;
}

// URL base de acceso al sistema
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$urlLogin = $protocolo . $host . RUTA_BASE . 'index.php';

// Asunto del correo
$asunto = 'Credenciales de Acceso - Sistema de Control de Ingreso SENA';

// Remitente optimizado para servidores Hostinger y dominios en producción
$dominioActual = $_SERVER['SERVER_NAME'] ?? 'adsocta.com';
if ($dominioActual === 'localhost' || $dominioActual === '127.0.0.1') {
    $fromEmail = 'no-reply@adsocta.com';
} else {
    $dominioLimpio = preg_replace('/^www\./i', '', $dominioActual);
    $fromEmail = 'no-reply@' . $dominioLimpio;
}

// Plantilla HTML profesional con diseño institucional SENA
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
                    <span class="cred-value">' . htmlspecialchars($cc, ENT_QUOTES, 'UTF-8') . '</span>
                </div>
                <div class="cred-item">
                    <span class="cred-label">Contraseña:</span>
                    <span class="cred-value">' . htmlspecialchars($cc, ENT_QUOTES, 'UTF-8') . '</span>
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

// Cabeceras MIME para formato HTML en Hostinger y clientes de correo modernos
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'From: Sistema SENA <' . $fromEmail . '>';
$headers[] = 'Reply-To: ' . $fromEmail;
$headers[] = 'X-Mailer: PHP/' . phpversion();

$cabecerasStr = implode("\r\n", $headers);

// Despacho del correo
$enviado = false;
try {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // En Windows / XAMPP
        $enviado = @mail($correo, $asunto, $cuerpoHtml, $cabecerasStr);
    } else {
        // En Linux / Servidor Hostinger con parámetro de remitente envelope
        $enviado = @mail($correo, $asunto, $cuerpoHtml, $cabecerasStr, '-f' . $fromEmail);
    }
} catch (Throwable $e) {
    $enviado = false;
}

if ($enviado) {
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?mensaje=enviado&correo=' . urlencode($correo) . '&cc=' . urlencode($cc));
    exit;
}

// Si falla mail() nativo (común en XAMPP local sin servidor de correos configurado)
$esEntornoLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';

if ($esEntornoLocal) {
    // Notificar al admin que la estructura y el script se ejecutaron con éxito en modo simulación local
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?mensaje=simulado_local&correo=' . urlencode($correo) . '&cc=' . urlencode($cc));
    exit;
} else {
    header('Location: ' . RUTA_BASE . 'views/admin/prueba_correo.php?error=fallo_envio&correo=' . urlencode($correo) . '&cc=' . urlencode($cc));
    exit;
}

