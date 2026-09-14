<?php
/**
 * controlador: Abrir jornada manual
 * Permite al instructor abrir una jornada para su ficha
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

    // Buscar fichas del instructor que tengan horario para hoy
    $diaSemana = date('l'); // Lunes, Martes, etc en ingles
    $diasMap = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sabado',
        'Sunday' => 'Domingo'
    ];
    $diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;

    // Obtener ficha y horario del instructor para hoy
    $q = $conexion->prepare(
        "SELECT f.id_ficha, h.id_horario, h.entrada, h.salida
         FROM ficha f
         INNER JOIN horario h ON h.fk_ficha = f.id_ficha
         WHERE f.fk_usuario = :instructor_id
           AND h.dia_semana = :dia_semana
         ORDER BY h.entrada ASC
         LIMIT 1"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':dia_semana' => $diaSemanaEs
    ]);
    $horarioFicha = $q->fetch();

    if (!$horarioFicha) {
        throw new Exception('No hay horario configurado para hoy para sus fichas');
    }

    $fichaId = (int)$horarioFicha['id_ficha'];
    $horarioId = (int)$horarioFicha['id_horario'];

    // Verificar si ya existe una sesion abierta para esta ficha/fecha/instructor
    $q = $conexion->prepare(
        "SELECT id_sesion FROM sesiones_jornada
         WHERE instructor_id = :instructor_id
           AND ficha_id = :ficha_id
           AND fecha = :fecha
           AND estado = 'abierta'
         FOR UPDATE"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':ficha_id' => $fichaId,
        ':fecha' => $fechaHoy
    ]);
    $sesionExistente = $q->fetch();

    if ($sesionExistente) {
        throw new Exception('Ya existe una jornada abierta para esta ficha hoy');
    }

    // Obtener hora actual del servidor
    $horaAperturaReal = date('Y-m-d H:i:s');
    $horaLimitePuntualidad = date('Y-m-d H:i:s', strtotime($horaAperturaReal . ' + 10 minutes'));

    // Crear nueva sesion
    $q = $conexion->prepare(
        "INSERT INTO sesiones_jornada (instructor_id, ficha_id, horario_id, fecha, hora_apertura_real, hora_limite_puntualidad, estado)
         VALUES (:instructor_id, :ficha_id, :horario_id, :fecha, :hora_apertura, :hora_limite, 'abierta')"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':ficha_id' => $fichaId,
        ':horario_id' => $horarioId,
        ':fecha' => $fechaHoy,
        ':hora_apertura' => $horaAperturaReal,
        ':hora_limite' => $horaLimitePuntualidad
    ]);

    $sesionId = (int)$conexion->lastInsertId();

    // Confirmar transaccion
    $conexion->commit();

    // Formatear horas para respuesta
    $horaAperturaFmt = date('H:i', strtotime($horaAperturaReal));
    $horaLimiteFmt = date('H:i', strtotime($horaLimitePuntualidad));

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Jornada abierta correctamente. Los aprendices podran registrar asistencia hasta las {$horaLimiteFmt} como puntuales.",
        'data' => [
            'sesion_id' => $sesionId,
            'hora_apertura' => $horaAperturaFmt,
            'hora_limite_puntualidad' => $horaLimiteFmt,
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