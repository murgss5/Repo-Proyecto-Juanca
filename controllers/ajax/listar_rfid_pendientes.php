<?php
/**
 * AJAX: Listar aprendices pendientes de RFID para flujo global
 * Retorna exclusivamente JSON.
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

asegurarSesionIniciada();

// Cabeceras obligatorias
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Validar mtodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Mtodo no permitido.']);
    exit;
}

// Exigir rol Instructor
$usuarioActual = usuarioActual();
if (!$usuarioActual || $usuarioActual['fk_rol'] !== 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Se requiere rol Instructor.']);
    exit;
}

// Validar CSRF
$tokenRecibido = $_POST['token_csrf'] ?? '';
if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invlido o sesin expirada.']);
    exit;
}

// Validar parmetros
$idFicha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
if (!$idFicha) {
    echo json_encode(['success' => false, 'error' => 'Ficha invlida o no proporcionada.']);
    exit;
}

try {
    $conexion = (new Conexion())->conectar();

    // Validar que la ficha pertenece al instructor autenticado
    $qFicha = $conexion->prepare('SELECT nombre_programa FROM ficha WHERE id_ficha = :id_ficha AND fk_usuario = :id_instructor');
    $qFicha->execute([
        ':id_ficha' => $idFicha,
        ':id_instructor' => $usuarioActual['id_usuario']
    ]);
    
    $ficha = $qFicha->fetch();
    if (!$ficha) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'La ficha no existe o no le pertenece a este instructor.']);
        exit;
    }
    
    $nombrePrograma = $ficha['nombre_programa'];

    // Consultar aprendices pendientes de esta ficha
    // "Pendiente" significa que el codigo_rfid comienza por "RFID"
    $qAprendices = $conexion->prepare('
        SELECT 
            a.id_aprendiz,
            u.nombre,
            u.apellido,
            u.tipo_documento,
            u.identificacion,
            a.codigo_rfid AS codigo_rfid_actual
        FROM aprendiz a
        INNER JOIN usuario u ON a.fk_usuario = u.id_usuario
        WHERE a.fk_ficha = :id_ficha
          AND a.codigo_rfid LIKE "RFID%"
        ORDER BY u.apellido ASC, u.nombre ASC, u.identificacion ASC, a.id_aprendiz ASC
    ');
    
    $qAprendices->execute([':id_ficha' => $idFicha]);
    $resultados = $qAprendices->fetchAll();

    $pendientes = [];
    foreach ($resultados as $row) {
        $pendientes[] = [
            'id_aprendiz' => (int)$row['id_aprendiz'],
            'nombre_completo' => trim($row['nombre'] . ' ' . $row['apellido']),
            'tipo_documento' => $row['tipo_documento'] ?: 'No especificado',
            'identificacion' => $row['identificacion'] ?: 'Sin ID',
            'id_ficha' => $idFicha,
            'programa' => $nombrePrograma,
            'codigo_rfid_actual' => $row['codigo_rfid_actual']
        ];
    }

    echo json_encode([
        'success' => true,
        'pendientes' => $pendientes
    ]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    // No exponer SQL ni trazas
    echo json_encode(['success' => false, 'error' => 'Ocurri un error en el servidor al listar pendientes.']);
    exit;
}

