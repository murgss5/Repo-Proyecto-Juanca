<?php
/**
 * controlador: Registrar asistencia por RFID
 * Registra entrada/salida de aprendiz validando sesion de jornada abierta
 * Mantiene compatibilidad total con lectores fisicos RFID via POST
 */

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
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$codigoRfid = trim($input['codigo_rfid'] ?? '');
$tipoRegistro = $input['tipo'] ?? 'entrada'; // 'entrada' o 'salida'

if ($codigoRfid === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Codigo RFID requerido']);
    exit;
}

// Procesar con el servicio centralizado
$resultado = AsistenciaModel::registrarPorRfid($codigoRfid, $tipoRegistro);

$codigoHttp = $resultado['http_code'] ?? 200;
unset($resultado['http_code']);

http_response_code($codigoHttp);
header('Content-Type: application/json');
echo json_encode($resultado);
exit;