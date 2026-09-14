<?php
/**
 * controlador: Obtener aprendices para registro demo (solo desarrollo)
 * Devuelve lista de aprendices de la ficha de la jornada abierta del instructor actual
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

// Solo procesar peticiones GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

// Validar token CSRF (desde query param o header)
$tokenRecibido = $_GET['token_csrf'] ?? '';
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
    // Buscar sesión de jornada ABIERTA de hoy del instructor
    $q = $conexion->prepare(
        "SELECT id_sesion, ficha_id, hora_apertura_real, hora_limite_puntualidad
         FROM sesiones_jornada
         WHERE instructor_id = :instructor_id
           AND fecha = :fecha
           AND estado = 'abierta'
         ORDER BY hora_apertura_real DESC
         LIMIT 1"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':fecha' => $fechaHoy
    ]);
    $sesion = $q->fetch();

    if (!$sesion) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No hay una jornada abierta para registrar entradas demo'
        ]);
        exit;
    }

    $sesionId = (int)$sesion['id_sesion'];
    $fichaId = (int)$sesion['ficha_id'];

    // Obtener aprendices de esa ficha
    $q = $conexion->prepare(
        "SELECT a.id_aprendiz, a.codigo_rfid, u.nombre, u.apellido, u.identificacion
         FROM aprendiz a
         INNER JOIN usuario u ON u.id_usuario = a.fk_usuario
         WHERE a.fk_ficha = :ficha_id
         ORDER BY u.apellido, u.nombre"
    );
    $q->execute([':ficha_id' => $fichaId]);
    $aprendices = $q->fetchAll();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'sesion_id' => $sesionId,
            'ficha_id' => $fichaId,
            'hora_apertura_real' => $sesion['hora_apertura_real'],
            'hora_limite_puntualidad' => $sesion['hora_limite_puntualidad'],
            'aprendices' => $aprendices
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}