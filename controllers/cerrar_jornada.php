<?php
/**
 * controlador: Cerrar jornada manual
 * Permite al instructor cerrar una jornada abierta
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';

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

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

$instructorId = $_SESSION['id_usuario'];
$fechaHoy = date('Y-m-d');

$conexion = (new Conexion())->conectar();

try {
    // Iniciar transaccion
    $conexion->beginTransaction();

    // Buscar sesion abierta del instructor para hoy
    $q = $conexion->prepare(
        "SELECT id_sesion, ficha_id, hora_apertura_real
         FROM sesiones_jornada
         WHERE instructor_id = :instructor_id
           AND fecha = :fecha
           AND estado = 'abierta'
         FOR UPDATE"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':fecha' => $fechaHoy
    ]);
    $sesion = $q->fetch();

    if (!$sesion) {
        throw new Exception('No hay una jornada abierta para cerrar');
    }

    $sesionId = (int)$sesion['id_sesion'];
    $fichaId = (int)$sesion['ficha_id'];

    // Obtener hora actual del servidor
    $horaCierreReal = date('Y-m-d H:i:s');

    // Actualizar sesion a cerrada
    $q = $conexion->prepare(
        "UPDATE sesiones_jornada
         SET hora_cierre_real = :hora_cierre, estado = 'cerrada_manual', actualizado_at = :actualizado
         WHERE id_sesion = :id_sesion"
    );
    $q->execute([
        ':hora_cierre' => $horaCierreReal,
        ':actualizado' => $horaCierreReal,
        ':id_sesion' => $sesionId
    ]);

    if (!$q->rowCount()) {
        throw new Exception('No se pudo cerrar la jornada');
    }

    // Confirmar transaccion
    $conexion->commit();

    $horaCierreFmt = date('H:i', strtotime($horaCierreReal));

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "La jornada fue cerrada correctamente a las {$horaCierreFmt}. Ya no se aceptaran nuevos registros de asistencia.",
        'data' => [
            'sesion_id' => $sesionId,
            'hora_cierre' => $horaCierreFmt,
            'ficha_id' => $fichaId
        ]
    ]);

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}