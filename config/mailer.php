<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';

/**
 * ============================================================
 * HELPER DE CORREO ELECTRÓNICO (PHPMailer)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ============================================================
 * Gestión centralizada de envíos de correo mediante SMTP.
 */

/**
 * Envía las credenciales de acceso al correo institucional o personal del usuario.
 *
 * @param string $correoDestino Dirección de correo electrónico del destinatario.
 * @param string $nombres Nombre completo del destinatario.
 * @param string $usuario Nombre de usuario o documento para el acceso.
 * @param string $password Contraseña de acceso asignada.
 * @return bool Retorna true si el correo se envía correctamente.
 * @throws \Exception Si ocurre un error durante la configuración o el envío SMTP.
 */
function enviarCredencialesSena($correoDestino, $nombres, $usuario, $password)
{
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        throw new \Exception('No se pudo inicializar la librería PHPMailer en libs/PHPMailer.');
    }

    $mail = new PHPMailer(true);

    try {
        // --- 1. Configuración del Servidor SMTP ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'camilomurgueitio928@gmail.com';
        $mail->Password   = 'TU_APP_PASSWORD_AQUI';
        $mail->SMTPSecure = 'ssl';
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // --- 2. Configuración de Remitente y Destinatario ---
        $mail->setFrom('TU_CORREO_AQUI', 'SENA - Control de Asistencia');
        $mail->addAddress($correoDestino, $nombres);

        // --- 3. Contenido del Correo ---
        $mail->isHTML(true);
        $mail->Subject = 'Tus Credenciales de Acceso - SENA';

        $cuerpoHtml = '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Credenciales de Acceso - SENA</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 25px 15px; color: #333333; }
                .email-wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.07); border: 1px solid #e9ecef; }
                .email-header { background-color: #28a745; padding: 28px 24px; text-align: center; color: #ffffff; }
                .email-header h1 { margin: 0; font-size: 21px; font-weight: 700; letter-spacing: 0.3px; }
                .email-header p { margin: 6px 0 0 0; font-size: 13px; opacity: 0.92; }
                .email-content { padding: 32px 28px; }
                .greeting { font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #212529; }
                .text-intro { font-size: 14px; line-height: 1.6; color: #495057; margin-bottom: 24px; }
                .credentials-card { background-color: #f8f9fa; border: 1px solid #dee2e6; border-left: 4px solid #28a745; border-radius: 6px; padding: 20px 22px; margin-bottom: 24px; }
                .credential-row { margin-bottom: 12px; font-size: 14px; }
                .credential-row:last-child { margin-bottom: 0; }
                .credential-label { font-weight: 600; color: #343a40; display: inline-block; width: 110px; }
                .credential-value { font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; background-color: #e9ecef; padding: 3px 10px; border-radius: 4px; color: #1e7e34; font-weight: 700; font-size: 13.5px; border: 1px solid #ced4da; }
                .text-security { font-size: 12.5px; color: #6c757d; line-height: 1.5; margin-bottom: 0; }
                .email-footer { background-color: #f8f9fa; padding: 20px 24px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #e9ecef; line-height: 1.5; }
            </style>
        </head>
        <body>
            <div class="email-wrapper">
                <div class="email-header">
                    <h1>Servicio Nacional de Aprendizaje - SENA</h1>
                    <p>Sistema de Control de Ingreso y Seguimiento de Asistencia</p>
                </div>
                <div class="email-content">
                    <div class="greeting">Apreciado(a) ' . htmlspecialchars($nombres) . ',</div>
                    <div class="text-intro">
                        Se ha generado tu cuenta institucional en la plataforma de control de ingreso. A continuación se presentan tus credenciales para acceder al sistema:
                    </div>
                    <div class="credentials-card">
                        <div class="credential-row">
                            <span class="credential-label">Usuario:</span>
                            <span class="credential-value">' . htmlspecialchars($usuario) . '</span>
                        </div>
                        <div class="credential-row">
                            <span class="credential-label">Contraseña:</span>
                            <span class="credential-value">' . htmlspecialchars($password) . '</span>
                        </div>
                    </div>
                    <p class="text-security">
                        <strong style="color: #495057;">Importante:</strong> Te recomendamos cambiar tu contraseña una vez hayas iniciado sesión por primera vez en la plataforma.
                    </p>
                </div>
                <div class="email-footer">
                    Este es un mensaje automático generado por el sistema. Por favor no responda a este remitente.<br>
                    &copy; ' . date('Y') . ' SENA - Todos los derechos reservados.
                </div>
            </div>
        </body>
        </html>
        ';

        $mail->Body    = $cuerpoHtml;
        $mail->AltBody = "SENA - Credenciales de Acceso\n\nEstimado(a) {$nombres},\n\nTus credenciales para ingresar a la plataforma son:\nUsuario: {$usuario}\nContraseña: {$password}\n\nPor favor no responder a este correo.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        throw new \Exception("Error al enviar el correo: " . $mail->ErrorInfo);
    } catch (\Throwable $e) {
        throw new \Exception("Error general al procesar el correo: " . $e->getMessage());
    }
}
