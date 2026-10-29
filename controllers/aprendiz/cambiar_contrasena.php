<?php
/**
 * Controlador: Cambio seguro de contraseña del Aprendiz
 * Permite cambiar la contraseña tanto desde la invitación inicial como desde "Mi perfil".
 * Valida la contraseña actual, aplica política de seguridad, previene truncamiento silencioso
 * y registra la decisión en la base de datos de manera atómica.
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

// 4. Obtener campos enviados sin recortar espacios que puedan ser parte de una frase de contraseña
$claveActual    = $_POST['clave_actual'] ?? null;
$nuevaClave     = $_POST['nueva_clave'] ?? null;
$confirmarClave = $_POST['confirmar_clave'] ?? null;

if (!is_string($claveActual) || !is_string($nuevaClave) || !is_string($confirmarClave)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Los campos de contraseña deben ser cadenas de texto válidas.'
    ]);
    exit;
}

if ($claveActual === '' || $nuevaClave === '' || $confirmarClave === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Todos los campos de contraseña son obligatorios.'
    ]);
    exit;
}

// 5. Validar longitud mínima y máxima en bytes (límite Bcrypt = 72 bytes)
if (mb_strlen($nuevaClave) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'La nueva contraseña debe tener al menos 8 caracteres.'
    ]);
    exit;
}

// Validar longitud en bytes para evitar truncamiento silencioso del algoritmo de hash (Bcrypt limita a 72 bytes)
if (strlen($nuevaClave) > 72) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'La nueva contraseña excede el límite seguro de 72 bytes para el algoritmo de cifrado.'
    ]);
    exit;
}

// 6. Validar coincidencia de nueva contraseña y su confirmación
if ($nuevaClave !== $confirmarClave) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'La nueva contraseña y su confirmación no coinciden.'
    ]);
    exit;
}

$idUsuario = (int)$usuario['id_usuario'];

try {
    $conexion = (new Conexion())->conectar();

    // 7. Consultar datos actuales del usuario
    $stmtUser = $conexion->prepare(
        'SELECT id_usuario, contrasena, identificacion, nombre_usuario, estado_invitacion_clave, fecha_resolucion_invitacion 
         FROM usuario 
         WHERE id_usuario = :id AND fk_rol = 3 
         LIMIT 1'
    );
    $stmtUser->execute([':id' => $idUsuario]);
    $usuarioBD = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$usuarioBD) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => 'Usuario no encontrado en el sistema.'
        ]);
        exit;
    }

    // 8. Verificar que la contraseña actual sea correcta mediante password_verify
    if (!password_verify($claveActual, $usuarioBD['contrasena'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'La contraseña actual ingresada es incorrecta.'
        ]);
        exit;
    }

    // 9. Rechazar nueva contraseña si es idéntica a la actual
    if (password_verify($nuevaClave, $usuarioBD['contrasena'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'La nueva contraseña no puede ser igual a la contraseña actual.'
        ]);
        exit;
    }

    // 10. Rechazar nueva contraseña si es igual al número de documento o nombre de usuario
    $docIdentidad = trim((string)($usuarioBD['identificacion'] ?? ''));
    $nombreUsuario = trim((string)($usuarioBD['nombre_usuario'] ?? ''));

    if ($docIdentidad !== '' && strtolower($nuevaClave) === strtolower($docIdentidad)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'La nueva contraseña no puede ser igual a su número de documento.'
        ]);
        exit;
    }

    if ($nombreUsuario !== '' && strtolower($nuevaClave) === strtolower($nombreUsuario)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'La nueva contraseña no puede ser igual a su nombre de usuario.'
        ]);
        exit;
    }

    // 11. Generar nuevo hash con algoritmo seguro por defecto (Bcrypt / PASSWORD_DEFAULT)
    $nuevoHash = password_hash($nuevaClave, PASSWORD_DEFAULT);

    // 12. Actualizar en base de datos en una sola transacción atómica
    $conexion->beginTransaction();

    // fecha_resolucion_invitacion: se establece si era NULL (primera resolución), se conserva si ya existía.
    // fecha_ultimo_cambio_clave: se actualiza siempre a NOW().
    $stmtUpdate = $conexion->prepare(
        "UPDATE usuario 
         SET contrasena = :nuevo_hash,
             estado_invitacion_clave = 'CAMBIADA',
             fecha_resolucion_invitacion = COALESCE(fecha_resolucion_invitacion, NOW()),
             fecha_ultimo_cambio_clave = NOW()
         WHERE id_usuario = :id 
           AND fk_rol = 3"
    );
    $stmtUpdate->execute([
        ':nuevo_hash' => $nuevoHash,
        ':id'         => $idUsuario
    ]);

    $conexion->commit();

    // 13. Renovar identificador de sesión después del cambio exitoso
    session_regenerate_id(true);

    echo json_encode([
        'success' => true,
        'message' => 'Contraseña actualizada correctamente.',
        'fecha'   => date('d/m/Y h:i a')
    ]);
    exit;

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error en el servidor al actualizar la contraseña. Inténtelo de nuevo.'
    ]);
    exit;
}

