<?php
/**
 * controlador: Abrir jornada manual por ficha
 * Permite al instructor abrir una jornada para una ficha especifica
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';

// Validar sesion y rol instructor
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$usuarioInstructor = usuarioActual(2);
if (!$usuarioInstructor) {
    http_response_code(403);
    setFlash('error', 'Acceso denegado: se requiere rol Instructor');
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? '../views/instructor/dashboard.php');
    exit;
}

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Metodo no permitido');
    header('Location: ../views/instructor/dashboard.php');
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
    setFlash('error', 'Token CSRF invalido');
    header('Location: ../views/instructor/dashboard.php');
    exit;
}

// Recibir ficha_id y horario_id desde POST
$fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
$horarioId = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT);

if (!$fichaId || !$horarioId) {
    setFlash('error', 'ficha_id y horario_id son requeridos');
    header('Location: ../views/instructor/dashboard.php');
    exit;
}

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

$instructorId = $usuarioInstructor['id_usuario'];
$fechaHoy = date('Y-m-d');
$diaSemana = date('l'); // Lunes, Martes, etc en ingles
$diasMap = [
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sábado',
    'Sunday'    => 'Domingo'
];
$diasMapAlt = [
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miercoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sabado',
    'Sunday'    => 'Domingo'
];
$diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;
$diaSemanaAlt = $diasMapAlt[$diaSemana] ?? $diaSemana;

$conexion = (new Conexion())->conectar();

try {
    // Iniciar transaccion
    $conexion->beginTransaction();

    // Validar que la ficha pertenezca al instructor
    $q = $conexion->prepare(
        "SELECT id_ficha FROM ficha WHERE id_ficha = :ficha_id AND fk_usuario = :instructor_id"
    );
    $q->execute([
        ':ficha_id' => $fichaId,
        ':instructor_id' => $instructorId
    ]);
    if (!$q->fetch()) {
        throw new Exception('La ficha no pertenece al instructor');
    }

    // Validar que el horario pertenezca a la ficha y sea para el dia actual (con o sin tilde)
    $q = $conexion->prepare(
        "SELECT id_horario, entrada, salida FROM horario 
         WHERE id_horario = :horario_id AND fk_ficha = :ficha_id 
           AND (dia_semana = :dia_semana OR dia_semana = :dia_semana_alt)"
    );
    $q->execute([
        ':horario_id' => $horarioId,
        ':ficha_id' => $fichaId,
        ':dia_semana' => $diaSemanaEs,
        ':dia_semana_alt' => $diaSemanaAlt
    ]);
    $horario = $q->fetch();
    if (!$horario) {
        throw new Exception('El horario no pertenece a la ficha o no es para hoy');
    }

    // Apertura flexible sin restriccion de hora maxima:
    // Se eliminan las restricciones de ventana para permitir aperturas tardias.
    // La jornada podra abrirse en cualquier momento del dia programado mientras este 'sin_iniciar'.

    // Verificar si ya existe una sesion abierta o cerrada para esta ficha/fecha
    $q = $conexion->prepare(
        "SELECT id_sesion FROM sesiones_jornada
         WHERE instructor_id = :instructor_id
           AND ficha_id = :ficha_id
           AND fecha = :fecha
           AND estado IN ('abierta', 'cerrada_manual')
         FOR UPDATE"
    );
    $q->execute([
        ':instructor_id' => $instructorId,
        ':ficha_id' => $fichaId,
        ':fecha' => $fechaHoy
    ]);
    $sesionExistente = $q->fetch();

    if ($sesionExistente) {
        throw new Exception('Ya existe una jornada (abierta o cerrada) para esta ficha hoy');
    }

    // Obtener hora actual exacta del servidor (hora real de apertura)
    $horaAperturaReal = date('Y-m-d H:i:s');
    
    // Calcular limite de puntualidad dinamico: exactamente hora de apertura real + 10 minutos
    $limitePuntualidadObj = new DateTime($horaAperturaReal);
    $limitePuntualidadObj->modify('+10 minutes');
    $horaLimitePuntualidad = $limitePuntualidadObj->format('Y-m-d H:i:s');

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

    // Formatear horas para mensaje
    $horaAperturaFmt = date('H:i', strtotime($horaAperturaReal));
    $horaLimiteFmt = date('H:i', strtotime($horaLimitePuntualidad));

    setFlash('success', "Jornada abierta correctamente a las {$horaAperturaFmt}. Límite sin retardo: {$horaLimiteFmt}.");
    header('Location: ../views/instructor/dashboard.php');
    exit;

} catch (Exception $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    setFlash('error', $e->getMessage());
    header('Location: ../views/instructor/dashboard.php');
    exit;
}