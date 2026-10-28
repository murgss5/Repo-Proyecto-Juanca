<?php
/**
 * controlador: Registrar asistencia por RFID
 * Registra entrada/salida de aprendiz validando sesion de jornada abierta.
 * Aplica ventana dinamica de 10 minutos sin retardo desde la apertura real del instructor.
 * Mantiene compatibilidad total con lectores fisicos RFID via POST.
 */

require_once __DIR__ . '/../config/claves.php';

// Si la petición viene en formato JSON, poblar $_POST para compatibilidad uniforme
$rawInputJson = json_decode(file_get_contents('php://input'), true);
if (is_array($rawInputJson) && !isset($_POST['api_key']) && isset($rawInputJson['api_key'])) {
    $_POST['api_key'] = $rawInputJson['api_key'];
}

// Validación estricta de API Key antes de procesar cualquier dato o conexión a BD
if (!isset($_POST['api_key']) || $_POST['api_key'] !== API_KEY_RFID) {
    header('HTTP/1.1 403 Forbidden');
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'mensaje' => 'Acceso denegado',
        'message' => 'Acceso denegado'
    ]);
    exit;
}

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/AsistenciaModel.php';

date_default_timezone_set('America/Bogota');

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Metodo no permitido']);
    exit;
}

// Obtener datos del request (JSON o form-data)
$input = is_array($rawInputJson) ? $rawInputJson : $_POST;

$codigoRfid = trim($input['codigo_rfid'] ?? '');
$tipoRegistro = $input['tipo'] ?? 'entrada'; // 'entrada' o 'salida'

if ($codigoRfid === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Codigo RFID requerido']);
    exit;
}

// Extraer ficha_id opcional si la peticion especifica un contexto de ficha/lector
$fichaContexto = !empty($input['ficha_id']) ? (int)$input['ficha_id'] : null;

// Procesar con el servicio centralizado
$resultado = AsistenciaModel::registrarPorRfid($codigoRfid, $tipoRegistro, null, $fichaContexto);

$codigoHttp = $resultado['http_code'] ?? 200;
unset($resultado['http_code']);

http_response_code($codigoHttp);
header('Content-Type: application/json');
echo json_encode($resultado);
exit;
