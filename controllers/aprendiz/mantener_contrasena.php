<?php
/**
 * Controlador: Mantener contraseña actual del Aprendiz
 * Resuelve la invitación inicial de cambio de contraseña registrando la preferencia
 * en la base de datos sin alterar el hash de la contraseña actual.
 */

require_once __DIR__ . '/../../includes/autenticacion.php';
exigirRol(3);

require_once __DIR__ . '/../../includes/constantes.php';
require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 1. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido. Se requiere POST.'
    ]);
    exit;
}

$usuario = usuarioActual(3);

// 3. Validar token CSRF
$tokenRecibido = $_POST['token_csrf'] ?? '';
if ($tokenRecibido === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}

if (!is_string($tokenRecibido) || !hash_equals($_SESSION['token_csrf'] ?? '', trim($tokenRecibido))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Token de seguridad inválido o sesión expirada.'
    ]);
    exit;
}

$idUsuario = (int)$usuario['id_usuario'];

try {
    $conexion = (new Conexion())->conectar();

    // 4. Consultar estado actual del usuario
    $stmtCheck = $conexion->prepare(
        'SELECT estado_invitacion_clave, fecha_resolucion_invitacion 
         FROM usuario 
         WHERE id_usuario = :id AND fk_rol = 3 
         LIMIT 1'
    );
    $stmtCheck->execute([':id' => $idUsuario]);
    $estadoActual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$estadoActual) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => 'Usuario no encontrado en el sistema.'
        ]);
        exit;
    }

    // Si ya fue resuelta previamente (MANTENIDA o CAMBIADA), no sobreescribir
    if ($estadoActual['estado_invitacion_clave'] !== 'PENDIENTE') {
        echo json_encode([
            'success' => true,
            'message' => 'La preferencia ya se encuentra registrada en su cuenta.',
            'estado'  => $estadoActual['estado_invitacion_clave']
        ]);
        exit;
    }

    // 5. Transición condicional estricta: solo actualiza si sigue PENDIENTE
    $stmtUpdate = $conexion->prepare(
        "UPDATE usuario 
         SET estado_invitacion_clave = 'MANTENIDA',
             fecha_resolucion_invitacion = NOW()
         WHERE id_usuario = :id 
           AND fk_rol = 3 
           AND estado_invitacion_clave = 'PENDIENTE'"
    );
    $stmtUpdate->execute([':id' => $idUsuario]);

    if ($stmtUpdate->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Preferencia guardada. Puedes cambiar tu contraseña en cualquier momento desde Mi perfil.',
            'estado'  => 'MANTENIDA'
        ]);
        exit;
    } else {
        // En caso de concurrencia donde otra petición ya la resolvió
        echo json_encode([
            'success' => true,
            'message' => 'La preferencia ya había sido resuelta previamente.',
            'estado'  => 'MANTENIDA'
        ]);
        exit;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error en el servidor al guardar la preferencia. Inténtelo más tarde.'
    ]);
    exit;
}

