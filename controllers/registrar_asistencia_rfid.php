<?php
/**
 * controlador: Registrar asistencia por RFID
 * Registra entrada/salida de aprendiz validando sesion de jornada abierta
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';

// Configurar zona horaria
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

$conexion = (new Conexion())->conectar();

try {
    // Buscar aprendiz por RFID
    $q = $conexion->prepare(
        "SELECT a.id_aprendiz, a.fk_ficha, a.fk_usuario, u.nombre, u.apellido
         FROM aprendiz a
         INNER JOIN usuario u ON u.id_usuario = a.fk_usuario
         WHERE a.codigo_rfid = :rfid
         LIMIT 1"
    );
    $q->execute([':rfid' => $codigoRfid]);
    $aprendiz = $q->fetch();

    if (!$aprendiz) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Aprendiz no encontrado con ese RFID']);
        exit;
    }

    $aprendizId = (int)$aprendiz['id_aprendiz'];
    $fichaId = (int)$aprendiz['fk_ficha'];
    $fechaHoy = date('Y-m-d');
    $horaActual = date('Y-m-d H:i:s');

    // Buscar sesion de jornada abierta para esta ficha y fecha
    $q = $conexion->prepare(
        "SELECT id_sesion, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
         FROM sesiones_jornada
         WHERE ficha_id = :ficha_id
           AND fecha = :fecha
           AND estado = 'abierta'
         ORDER BY hora_apertura_real DESC
         LIMIT 1"
    );
    $q->execute([
        ':ficha_id' => $fichaId,
        ':fecha' => $fechaHoy
    ]);
    $sesion = $q->fetch();

    if (!$sesion) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No hay una jornada abierta para esta ficha. El instructor debe abrir la jornada primero.'
        ]);
        exit;
    }

    $sesionId = (int)$sesion['id_sesion'];
    $horaLimitePuntualidad = $sesion['hora_limite_puntualidad'];
    $horaCierreReal = $sesion['hora_cierre_real'];

    // Verificar si la jornada ya fue cerrada
    if ($sesion['estado'] === 'cerrada_manual' || $horaCierreReal !== null) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'La jornada ya ha sido cerrada. No se pueden registrar mas asistencias.'
        ]);
        exit;
    }

    // Verificar si ya existe registro de entrada para este aprendiz en esta sesion
    $q = $conexion->prepare(
        "SELECT id_asistencia, entrada FROM asistencia
         WHERE fk_aprendiz = :aprendiz_id
           AND fk_sesion_jornada = :sesion_id
         LIMIT 1"
    );
    $q->execute([
        ':aprendiz_id' => $aprendizId,
        ':sesion_id' => $sesionId
    ]);
    $asistenciaExistente = $q->fetch();

    if ($tipoRegistro === 'entrada') {
        if ($asistenciaExistente) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Este aprendiz ya registro su entrada en esta jornada'
            ]);
            exit;
        }

        // Determinar estado de entrada comparando con hora limite
        $esPuntual = ($horaActual <= $horaLimitePuntualidad);
        $estadoEntrada = $esPuntual ? 'PUNTUAL' : 'RETARDO';
        $minutosRetardo = 0;

        if (!$esPuntual) {
            $diff = strtotime($horaActual) - strtotime($horaLimitePuntualidad);
            $minutosRetardo = (int)ceil($diff / 60);
        }

        // Insertar nueva asistencia
        $q = $conexion->prepare(
            "INSERT INTO asistencia (fecha_asistencia, entrada, estado_entrada, minutos_retardo, fk_aprendiz, fk_sesion_jornada)
             VALUES (:fecha, :entrada, :estado_entrada, :minutos_retardo, :aprendiz_id, :sesion_id)"
        );
        $q->execute([
            ':fecha' => $fechaHoy,
            ':entrada' => $horaActual,
            ':estado_entrada' => $estadoEntrada,
            ':minutos_retardo' => $minutosRetardo,
            ':aprendiz_id' => $aprendizId,
            ':sesion_id' => $sesionId
        ]);

        $asistenciaId = (int)$conexion->lastInsertId();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => $esPuntual ? 'Entrada registrada: PUNTUAL' : 'Entrada registrada: RETARDO (' . $minutosRetardo . ' min)',
            'data' => [
                'asistencia_id' => $asistenciaId,
                'aprendiz' => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'rfid' => $codigoRfid,
                'hora' => date('H:i', strtotime($horaActual)),
                'estado' => $estadoEntrada,
                'minutos_retardo' => $minutosRetardo,
                'tipo' => 'entrada'
            ]
        ]);

    } elseif ($tipoRegistro === 'salida') {
        if (!$asistenciaExistente) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'El aprendiz no tiene entrada registrada en esta jornada'
            ]);
            exit;
        }

        if ($asistenciaExistente['salida'] !== null) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Este aprendiz ya registro su salida'
            ]);
            exit;
        }

        // Actualizar salida
        $q = $conexion->prepare(
            "UPDATE asistencia
             SET salida = :salida, estado_salida = 'SALIDA_NORMAL'
             WHERE id_asistencia = :id_asistencia"
        );
        $q->execute([
            ':salida' => $horaActual,
            ':id_asistencia' => $asistenciaExistente['id_asistencia']
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Salida registrada correctamente',
            'data' => [
                'asistencia_id' => (int)$asistenciaExistente['id_asistencia'],
                'aprendiz' => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'rfid' => $codigoRfid,
                'hora' => date('H:i', strtotime($horaActual)),
                'tipo' => 'salida'
            ]
        ]);

    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tipo de registro invalido']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}