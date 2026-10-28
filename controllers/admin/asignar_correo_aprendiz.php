<?php
/**
 * ============================================================
 * CONTROLADOR: ASIGNAR / ACTUALIZAR CORREO DE APRENDIZ
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Permite al administrador registrar o actualizar rápidamente
 * el correo electrónico de un aprendiz para que pase a estado
 * "Pendiente" y pueda recibir sus credenciales de acceso.
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

// Detectar si la petición es AJAX
$esAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php');
    exit;
}

// Validar CSRF
if (empty($_POST['token_csrf']) || empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $_POST['token_csrf'])) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido o sesión caducada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Token de seguridad inválido.'));
    exit;
}

$idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$correo    = trim($_POST['correo'] ?? '');

if (!$idUsuario || $idUsuario <= 0) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Identificador de usuario no válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Identificador de usuario no válido.'));
    exit;
}

if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'El correo electrónico ingresado no tiene un formato válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('El correo electrónico no es válido.'));
    exit;
}

$conexion = (new Conexion())->conectar();

try {
    // 1. Actualizar correo en la tabla usuario asegurando que sea rol aprendiz (rol 3)
    $stmtUser = $conexion->prepare('
        UPDATE usuario 
        SET correo_electronico = :correo 
        WHERE id_usuario = :id_usuario AND fk_rol = 3
    ');
    $stmtUser->execute([
        ':correo' => $correo,
        ':id_usuario' => $idUsuario,
    ]);

    // 2. Restablecer correo_enviado = 0 en aprendiz para que quede listo en el lote de pendientes
    try {
        $conexion->exec("ALTER TABLE aprendiz ADD COLUMN correo_enviado TINYINT(1) NOT NULL DEFAULT 0");
    } catch (\Throwable $e) {}

    $stmtAprendiz = $conexion->prepare('
        UPDATE aprendiz 
        SET correo_enviado = 0 
        WHERE fk_usuario = :id_usuario
    ');
    $stmtAprendiz->execute([':id_usuario' => $idUsuario]);

    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'message' => 'Correo electrónico asignado exitosamente.',
            'correo' => $correo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?mensaje=' . urlencode('Correo electrónico asignado exitosamente. El aprendiz ahora está pendiente para envío de credenciales.'));
    exit;

} catch (PDOException $e) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos al guardar el correo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Error en la base de datos al actualizar el correo.'));
    exit;
}

