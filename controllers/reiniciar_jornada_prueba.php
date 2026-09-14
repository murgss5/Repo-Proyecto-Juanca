<?php
/**
 * controlador: Reiniciar jornada de prueba (solo desarrollo)
 * Elimina la sesión de jornada de hoy del instructor actual para permitir volver a abrir
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';

// Validar entorno de desarrollo (localhost)
$host = $_SERVER['HTTP_HOST'] ?? '';
$esDesarrollo = ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost') !== false);

if (!$esDesarrollo) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Solo disponible en entorno de desarrollo']);
    exit;
}

// Validar sesion y rol instructor
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (($_SESSION['fk_rol'] ?? null) != 2) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado: se requiere rol Instructor']);
    exit;
}

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

// Validar token CSRF (desde POST o header)
$tokenRecibido = $_POST['token_csrf'] ?? '';
if ($tokenRecibido === '') {
    $headers = getallheaders();
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}
if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

$instructorId = $_SESSION['id_usuario'];
$fechaHoy = date('Y-m-d');

$conexion = (new Conexion())->conectar();

try {
    $conexion->beginTransaction();

    // Buscar sesión de jornada de hoy del instructor (abierta o cerrada)
    $q = $conexion->prepare(
        "SELECT id_sesion, estado FROM sesiones_jornada
         WHERE instructor_id = :instructor_id
           AND fecha = :fecha
           AND estado IN ('abierta', 'cerrada_manual')
         FOR UPDATE"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':fecha' => $fechaHoy
    ]);
    $sesion = $q->fetch();

    if (!$sesion) {
        $conexion->commit();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'No hay jornada de prueba para reiniciar (ya está limpio)'
        ]);
        exit;
    }

    $sesionId = (int)$sesion['id_sesion'];
    $estadoSesion = $sesion['estado'];

    // Marcar asistencias de prueba si existen (usando observación si está disponible)
    // No borramos asistencias reales - solo eliminamos la sesión
    // Si en el futuro hay un campo para marcar pruebas, se podría filtrar aquí

    // Eliminar la sesión de jornada (esto permite volver a abrir)
    // Nota: las asistencias reales quedan intactas con fk_sesion_jornada apuntando a ID que ya no existe
    // pero eso es aceptable para datos de prueba
    $q = $conexion->prepare("DELETE FROM sesiones_jornada WHERE id_sesion = :id_sesion");
    $q->execute([':id_sesion' => $sesionId]);

    $conexion->commit();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Jornada de prueba reiniciada',
        'data' => [
            'sesion_eliminada' => $sesionId,
            'estado_anterior' => $estadoSesion
        ]
    ]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}