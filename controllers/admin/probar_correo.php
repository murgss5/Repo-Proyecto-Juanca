<?php
/**
 * ============================================================
 * CONTROLADOR: PRUEBA DE ENVÍO DE CORREO SMTP
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ============================================================
 * Recibe por POST un documento y un correo para probar la configuración
 * SMTP de PHPMailer sin alterar la base de datos.
 */

// Buffer de salida para evitar que advertencias o espacios corrompan la respuesta JSON
ob_start();

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../includes/constantes.php';
require_once __DIR__ . '/../../includes/autenticacion.php';

if (file_exists(__DIR__ . '/../../config/mailer.php')) {
    require_once __DIR__ . '/../../config/mailer.php';
}

if (!function_exists('enviarCredencialesSena')) {
    function enviarCredencialesSena($destinatario, $nombre, $usuario, $clave) {
        $asunto = 'Credenciales de Acceso - Sistema SENA';
        $mensaje = "Cordial saludo, {$nombre}.\n\nSus credenciales de acceso son:\nUsuario: {$usuario}\nContraseña: {$clave}\n\nSistema de Control de Ingreso SENA";
        $headers = 'From: no-reply@adsocta.com' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
        return @mail($destinatario, $asunto, $mensaje, $headers);
    }
}

// 1. Validar autenticación de Administrador (rol 1)
if (empty($_SESSION['admin']['id_usuario'])) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Sesión no válida o expirada. Inicie sesión como Administrador.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Método de solicitud no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Capturar parámetros de entrada
$documento = trim($_POST['documento'] ?? '');
$correo    = trim($_POST['correo'] ?? '');

if (empty($documento) || empty($correo)) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'El Documento y el Correo Electrónico son obligatorios.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'La dirección de correo electrónico ingresada no es válida.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Intentar envío de prueba
try {
    enviarCredencialesSena($correo, 'Usuario de Prueba', $documento, $documento);

    ob_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Correo enviado exitosamente a ' . $correo
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;

