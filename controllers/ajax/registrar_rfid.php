<?php
/**
 * AJAX: Registrar cdigo RFID para un aprendiz
 * Retorna exclusivamente JSON.
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Mtodo no permitido.']);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$usuarioActual = usuarioActual();
if (!$usuarioActual || $usuarioActual['fk_rol'] !== 2) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Se requiere rol Instructor.']);
    exit;
}

$tokenRecibido = $_POST['token_csrf'] ?? '';
if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token CSRF invlido o sesin expirada.']);
    exit;
}

$idAprendiz = filter_input(INPUT_POST, 'id_aprendiz', FILTER_VALIDATE_INT);
$idFicha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
$rfidOriginal = $_POST['codigo_rfid'] ?? '';

if (!$idAprendiz || !$idFicha) {
    echo json_encode(['success' => false, 'error' => 'Datos de aprendiz o ficha invlidos.']);
    exit;
}

// Limpiar espacios externos
$nuevoRfid = trim((string)$rfidOriginal);

if ($nuevoRfid === '') {
    echo json_encode(['success' => false, 'error' => 'El cdigo RFID no puede estar vaco.']);
    exit;
}

// Validar longitud contra VARCHAR(50)
if (mb_strlen($nuevoRfid) > 50) {
    echo json_encode(['success' => false, 'error' => 'El cdigo RFID excede el lmite de 50 caracteres.']);
    exit;
}

// Rechazar si comienza por "RFID" (reservado para temporales)
if (strpos($nuevoRfid, 'RFID') === 0) {
    echo json_encode(['success' => false, 'error' => 'El prefijo "RFID" est reservado por el sistema. Por favor, lea un cdigo fsico vlido.']);
    exit;
}

try {
    $conexion = (new Conexion())->conectar();

    // Confirmar existencia, pertenencia y que su RFID actual inicie con RFID (es temporal)
    $qVerificar = $conexion->prepare('
        SELECT a.id_aprendiz, a.codigo_rfid 
        FROM aprendiz a
        INNER JOIN ficha f ON a.fk_ficha = f.id_ficha
        WHERE a.id_aprendiz = :id_aprendiz 
          AND a.fk_ficha = :id_ficha
          AND f.fk_usuario = :id_instructor
    ');
    $qVerificar->execute([
        ':id_aprendiz' => $idAprendiz,
        ':id_ficha' => $idFicha,
        ':id_instructor' => $usuarioActual['id_usuario']
    ]);
    
    $aprendiz = $qVerificar->fetch();
    
    if (!$aprendiz) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Aprendiz no encontrado o no pertenece a una de sus fichas autorizadas.']);
        exit;
    }
    
    if (strpos((string)$aprendiz['codigo_rfid'], 'RFID') !== 0) {
        echo json_encode(['success' => false, 'error' => 'El aprendiz ya posee un cdigo RFID fsico registrado y no puede ser reemplazado.']);
        exit;
    }

    // Actualizar atomicamente, el UNIQUE constraint capturar duplicados
    $qUpdate = $conexion->prepare('
        UPDATE aprendiz 
        SET codigo_rfid = :nuevo_rfid 
        WHERE id_aprendiz = :id_aprendiz 
          AND fk_ficha = :id_ficha 
          AND fk_ficha IN (SELECT id_ficha FROM ficha WHERE fk_usuario = :id_instructor)
    ');
    
    $qUpdate->execute([
        ':nuevo_rfid' => $nuevoRfid,
        ':id_aprendiz' => $idAprendiz,
        ':id_ficha' => $idFicha,
        ':id_instructor' => $usuarioActual['id_usuario']
    ]);

    if ($qUpdate->rowCount() === 1) {
        $es_flujo_importacion = false;
        $pendientes_restantes = 0;

        // Comprobar si existe la sesion de importacion pendiente
        if (isset($_SESSION['rfid_importacion_pendiente']) && is_array($_SESSION['rfid_importacion_pendiente'])) {
            $es_flujo_importacion = true;
            $aprendices_pendientes = $_SESSION['rfid_importacion_pendiente']['aprendices'] ?? [];
            
            // Remover al aprendiz actual de la cola si esta presente
            $nueva_cola = array_filter($aprendices_pendientes, function($a) use ($idAprendiz) {
                return (int)$a['id_aprendiz'] !== (int)$idAprendiz;
            });
            
            $_SESSION['rfid_importacion_pendiente']['aprendices'] = array_values($nueva_cola);
            $pendientes_restantes = count($_SESSION['rfid_importacion_pendiente']['aprendices']);
            
            // Si ya no quedan, podemos limpiar la variable de sesion
            if ($pendientes_restantes === 0) {
                unset($_SESSION['rfid_importacion_pendiente']);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Cdigo RFID registrado correctamente.',
            'id_aprendiz' => $idAprendiz,
            'codigo_rfid' => $nuevoRfid,
            'es_flujo_importacion' => $es_flujo_importacion,
            'pendientes_restantes' => $pendientes_restantes
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'No se actualiz ningn registro (verifique el estado actual).']);
        exit;
    }

} catch (PDOException $e) {
    // Capturar error de unicidad (código 23000)
    if ($e->getCode() == 23000) {
        echo json_encode(['success' => false, 'error' => 'El cdigo ledo ya est asignado a otro aprendiz. Intente con una tarjeta distinta.']);
        exit;
    }
    
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de servidor al guardar el cdigo RFID.']);
    exit;
}

