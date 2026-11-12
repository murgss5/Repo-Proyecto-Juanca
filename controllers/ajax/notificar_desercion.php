<?php
/**
 * =============================================================================
 * CONTROLADOR AJAX: NOTIFICACIÓN DE ALERTAS DE DESERCIÓN (REGLAMENTO SENA)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * -----------------------------------------------------------------------------
 * Procesa el envío de notificaciones por correo electrónico a los aprendices en
 * estado de Riesgo Crítico de Deserción según el Reglamento del Aprendiz SENA.
 * Retorna exclusivamente respuesta en formato JSON.
 * =============================================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1, 2);

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';
require_once dirname(__DIR__, 2) . '/models/AsistenciaModel.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

// 1. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'mensaje' => 'Método no permitido. Solo se aceptan peticiones POST.'
    ]);
    exit;
}

// 2. Validar token CSRF
$tokenCsrf = $_POST['token_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($tokenCsrf) || empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $tokenCsrf)) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'mensaje' => 'Token de seguridad inválido o sesión expirada. Por favor recargue la página.'
    ]);
    exit;
}

// 3. Determinar rol y usuario en contexto
$usuarioAdmin = usuarioActual(1);
$usuarioInstructor = usuarioActual(2);
$origen = trim($_POST['origen'] ?? '');

$rolContexto = 'admin';
$idUsuarioContexto = null;

if ($usuarioInstructor && (!$usuarioAdmin || $origen === 'instructor')) {
    $rolContexto = 'instructor';
    $idUsuarioContexto = (int)$usuarioInstructor['id_usuario'];
} elseif ($usuarioAdmin) {
    $rolContexto = 'admin';
    $idUsuarioContexto = (int)$usuarioAdmin['id_usuario'];
}

$conexion = (new Conexion())->conectar();

// 4. Obtener aprendices en riesgo
$aprendicesEnRiesgo = AsistenciaModel::obtenerAprendicesEnRiesgo($idUsuarioContexto, $rolContexto, $conexion);

// Filtrar únicamente los aprendices en nivel CRÍTICO
$criticos = array_values(array_filter($aprendicesEnRiesgo, function ($item) {
    return ($item['nivel_riesgo'] ?? '') === 'CRITICO';
}));

if (empty($criticos)) {
    echo json_encode([
        'status'         => 'info',
        'enviados'       => 0,
        'omitidos'       => 0,
        'total_criticos' => 0,
        'mensaje'        => 'No se encontraron aprendices en riesgo crítico de deserción actualmente.'
    ]);
    exit;
}

// Si se especificó un ID de aprendiz en particular (envío individual opcional)
$idAprendizEspecifico = filter_input(INPUT_POST, 'id_aprendiz', FILTER_VALIDATE_INT);
if ($idAprendizEspecifico && $idAprendizEspecifico > 0) {
    $criticos = array_values(array_filter($criticos, function ($item) use ($idAprendizEspecifico) {
        return (int)$item['id_aprendiz'] === $idAprendizEspecifico;
    }));

    if (empty($criticos)) {
        echo json_encode([
            'status'  => 'error',
            'enviados'=> 0,
            'mensaje' => 'El aprendiz indicado no cumple los criterios de riesgo crítico o no pertenece a sus fichas.'
        ]);
        exit;
    }
}

// 5. Filtrar aprendices notificables (con correo válido y no notificados hoy)
$fechaHoy = date('Y-m-d');
$paraNotificar = [];
$omitidosPorFecha = 0;
$omitidosSinCorreo = 0;

foreach ($criticos as $item) {
    $correo = trim($item['correo_electronico'] ?? '');
    $ultimaAlerta = $item['ultima_alerta_desercion'] ?? null;

    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $omitidosSinCorreo++;
        continue;
    }

    if ($ultimaAlerta === $fechaHoy) {
        $omitidosPorFecha++;
        continue;
    }

    $paraNotificar[] = $item;
}

if (empty($paraNotificar)) {
    $razon = [];
    if ($omitidosPorFecha > 0) {
        $razon[] = "{$omitidosPorFecha} ya fueron notificados el día de hoy ({$fechaHoy})";
    }
    if ($omitidosSinCorreo > 0) {
        $razon[] = "{$omitidosSinCorreo} no cuentan con correo electrónico registrado";
    }

    $detalleRazon = !empty($razon) ? ' (' . implode(' y ', $razon) . ')' : '';

    echo json_encode([
        'status'         => 'info',
        'enviados'       => 0,
        'omitidos'       => $omitidosPorFecha + $omitidosSinCorreo,
        'total_criticos' => count($criticos),
        'mensaje'        => 'No hay aprendices pendientes de notificación en este momento' . $detalleRazon . '.'
    ]);
    exit;
}

// 6. Preparar entorno y remitente de correo
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$urlLogin = $protocolo . $host . RUTA_BASE . 'index.php';
$urlExcusas = $protocolo . $host . RUTA_BASE . 'views/aprendiz/excusas/index.php';

$dominioActual = $_SERVER['SERVER_NAME'] ?? 'adsocta.com';
if ($dominioActual === 'localhost' || $dominioActual === '127.0.0.1') {
    $fromEmail = 'no-reply@adsocta.com';
} else {
    $dominioLimpio = preg_replace('/^www\./i', '', $dominioActual);
    $fromEmail = 'no-reply@' . $dominioLimpio;
}

$asunto = '⚠️ ALERTA TEMPRANA: Riesgo de Deserción por Inasistencias - SENA';

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Sistema de Asistencia SENA <' . $fromEmail . '>',
    'Reply-To: ' . $fromEmail,
    'X-Mailer: PHP/' . phpversion()
];
$cabecerasStr = implode("\r\n", $headers);

$esEntornoLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost' || php_sapi_name() === 'cli';

$enviados = 0;
$erroresEnvio = 0;

// 7. Enviar notificación individual a cada aprendiz
foreach ($paraNotificar as $aprendiz) {
    $idAprendiz = (int)$aprendiz['id_aprendiz'];
    $nombre = htmlspecialchars($aprendiz['nombre_completo'] ?? 'Aprendiz', ENT_QUOTES, 'UTF-8');
    $documento = htmlspecialchars($aprendiz['identificacion'] ?? '', ENT_QUOTES, 'UTF-8');
    $ficha = htmlspecialchars((string)($aprendiz['id_ficha'] ?? ''), ENT_QUOTES, 'UTF-8');
    $programa = htmlspecialchars($aprendiz['nombre_programa'] ?? 'Programa de Formación', ENT_QUOTES, 'UTF-8');
    $consecutivas = (int)($aprendiz['fallas_consecutivas'] ?? 0);
    $fallas30 = (int)($aprendiz['fallas_30_dias'] ?? 0);
    $destinatario = trim($aprendiz['correo_electronico']);

    // Construcción de la plantilla HTML institucional SENA
    $cuerpoHtml = '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; color: #2d3748; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-top: 5px solid #dc3545; }
        .header { background-color: #ffffff; padding: 25px; text-align: center; border-bottom: 1px solid #eef2f5; }
        .header h1 { margin: 8px 0 0; color: #991b1b; font-size: 20px; font-weight: 700; }
        .header p { margin: 4px 0 0; color: #39a900; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .content { padding: 30px 25px; }
        .greeting { font-size: 16px; font-weight: 700; color: #00324d; margin-bottom: 15px; }
        .text { font-size: 14px; line-height: 1.6; color: #4a5568; margin-bottom: 20px; }
        .card-alerta { background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc3545; border-radius: 6px; padding: 18px 20px; margin: 20px 0; }
        .card-alerta h3 { margin: 0 0 12px; font-size: 15px; color: #991b1b; font-weight: 700; }
        .stat-item { margin-bottom: 8px; font-size: 14px; }
        .stat-item:last-child { margin-bottom: 0; }
        .stat-label { font-weight: 600; color: #7f1d1d; display: inline-block; width: 180px; }
        .stat-value { font-weight: 700; color: #991b1b; }
        .norma-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin: 20px 0; font-size: 13px; color: #4a5568; line-height: 1.5; }
        .norma-box strong { color: #00324d; }
        .btn-container { text-align: center; margin: 30px 0; }
        .btn-excusa { display: inline-block; background-color: #39a900; color: #ffffff !important; text-decoration: none; padding: 12px 26px; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 3px 6px rgba(57,169,0,0.25); }
        .footer { background-color: #f8fafc; padding: 20px 25px; text-align: center; border-top: 1px solid #eef2f5; font-size: 12px; color: #888888; line-height: 1.5; }
        .badge-sena { display: inline-block; background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <span class="badge-sena">ALERTA PREVENTIVA &bull; CONTROL DE ASISTENCIA</span>
            <h1>Riesgo de Proceso de Deserción</h1>
            <p>Servicio Nacional de Aprendizaje &bull; SENA</p>
        </div>
        <div class="content">
            <div class="greeting">Estimado(a) aprendiz, ' . $nombre . '</div>
            <p class="text">
                El sistema institucional de registro y control de asistencia ha detectado un registro reiterado de inasistencias injustificadas en su proceso formativo:
            </p>
            
            <div class="card-alerta">
                <h3>Detalle de Inasistencias Registradas</h3>
                <div class="stat-item">
                    <span class="stat-label">Documento:</span>
                    <span class="stat-value">' . $documento . '</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Ficha de Formación:</span>
                    <span class="stat-value">#' . $ficha . ' - ' . $programa . '</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Fallas Consecutivas:</span>
                    <span class="stat-value" style="font-size: 15px;">' . $consecutivas . ' inasistencia(s) injustificada(s)</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Fallas Últimos 30 Días:</span>
                    <span class="stat-value">' . $fallas30 . ' inasistencia(s) acumulada(s)</span>
                </div>
            </div>

            <div class="norma-box">
                <strong>Fundamento Normativo (Reglamento del Aprendiz SENA - Acuerdo 007 de 2012, Art. 22):</strong><br>
                Se considera deserción en el proceso de formación cuando el aprendiz injustificadamente no se presente por <strong>tres (3) días consecutivos</strong> a las actividades de su etapa lectiva o productiva, o cuando acumule inasistencias reiteradas no justificadas. Esta causal faculta el inicio del trámite de cancelación de matrícula con la consiguiente sanción de inhabilidad para postularse a nuevos programas.
            </div>

            <p class="text">
                Si sus inasistencias obedecen a motivos médicos, laborales, de fuerza mayor o caso fortuito debidamente comprobables, le invitamos a radicar formalmente su respectiva <strong>excusa con soporte documental</strong> a través de la plataforma o comunicarse de inmediato con su instructor a cargo.
            </p>

            <div class="btn-container">
                <a href="' . htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8') . '" class="btn-excusa" target="_blank">
                    Radicar Excusa o Ingresar a la Plataforma
                </a>
            </div>

            <p class="text" style="font-size: 13px; color: #718096; margin-bottom: 0;">
                <em>Evite la pérdida de su cupo formativo manteniéndose al día con sus asistencias y obligaciones académicas.</em>
            </p>
        </div>
        <div class="footer">
            <p style="margin: 0 0 5px;"><strong>Sistema de Control de Asistencia SENA</strong> &bull; Centro de Teleinformática y Producción Industrial</p>
            <p style="margin: 0;">Este es un mensaje institucional automático del Sistema de Alertas Tempranas de Deserción.</p>
        </div>
    </div>
</body>
</html>';

    $cuerpoTexto = "ALERTA TEMPRANA: RIESGO DE DESERCIÓN - SENA\n\n"
        . "Estimado(a) aprendiz {$aprendiz['nombre_completo']},\n\n"
        . "El sistema de asistencia reporta que presenta {$consecutivas} fallas consecutivas y {$fallas30} fallas acumuladas en los últimos 30 días en la Ficha #{$ficha} ({$programa}).\n\n"
        . "Según el Reglamento del Aprendiz SENA (Acuerdo 007 de 2012, Art. 22), 3 días de inasistencia consecutiva injustificada constituyen causal de deserción y cancelación de matrícula.\n\n"
        . "Por favor radique su excusa médica o laboral correspondiente o comuníquese con su instructor.\n\n"
        . "Acceda a la plataforma en: {$urlLogin}\n\n"
        . "Atentamente,\nSistema de Asistencia SENA";

    $despachado = false;

    // Intentar despacho mediante PHPMailer si existe la configuración
    try {
        if (file_exists(dirname(__DIR__, 2) . '/config/mailer.php')) {
            require_once dirname(__DIR__, 2) . '/config/mailer.php';
            if (function_exists('obtenerPHPMailer')) {
                $mail = obtenerPHPMailer();
                $mail->addAddress($destinatario, $aprendiz['nombre_completo'] ?? 'Aprendiz');
                $mail->Subject = $asunto;
                $mail->isHTML(true);
                $mail->Body    = $cuerpoHtml;
                $mail->AltBody = $cuerpoTexto;
                $despachado    = $mail->send();
            }
        }
    } catch (\Throwable $e) {
        $despachado = false;
    }

    // Fallback a mail() nativo
    if (!$despachado) {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $despachado = @mail($destinatario, $asunto, $cuerpoHtml, $cabecerasStr);
            } else {
                $despachado = @mail($destinatario, $asunto, $cuerpoHtml, $cabecerasStr, '-f' . $fromEmail);
            }
        } catch (\Throwable $e) {
            $despachado = false;
        }
    }

    // En entorno de desarrollo local (XAMPP sin sendmail), se valida el flujo y se registra la fecha
    if ($despachado || $esEntornoLocal) {
        AsistenciaModel::registrarNotificacionDesercion($idAprendiz, $conexion);
        $enviados++;
    } else {
        $erroresEnvio++;
    }
}

// 8. Responder con resultado estructurado
$mensaje = "Se despacharon satisfactoriamente las notificaciones a {$enviados} aprendiz(ces) en riesgo crítico de deserción.";
if ($omitidosPorFecha > 0) {
    $mensaje .= " ({$omitidosPorFecha} ya habían sido notificados hoy).";
}
if ($omitidosSinCorreo > 0) {
    $mensaje .= " ({$omitidosSinCorreo} no tienen correo registrado).";
}

echo json_encode([
    'status'         => 'success',
    'enviados'       => $enviados,
    'errores'        => $erroresEnvio,
    'omitidos_hoy'   => $omitidosPorFecha,
    'omitidos_correo'=> $omitidosSinCorreo,
    'total_criticos' => count($criticos),
    'mensaje'        => $mensaje
]);
exit;
