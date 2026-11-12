<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE JORNADAS: JornadaController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida la gestión del ciclo de vida de jornadas presenciales:
 *   - accion=abrir  : Apertura de jornada y cálculo dinámico de puntualidad (10 min)
 *   - accion=cerrar : Cierre de jornada y generación automática de inasistencias
 * =============================================================================
 */

require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Bogota');

// Solo procesar peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    setFlash('error', 'Método no permitido.');
    header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
    exit;
}

// Validar token CSRF unificado
$tokenRecibido = $_POST['token_csrf'] ?? '';
if ($tokenRecibido === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}
if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    setFlash('error', 'Token de seguridad inválido o sesión expirada.');
    header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Fallback por presencia de campos
if ($accion === '') {
    if (isset($_POST['horario_id']) && !isset($_POST['sesion_id'])) {
        $accion = 'abrir';
    } else {
        $accion = 'cerrar';
    }
}

switch ($accion) {
    case 'abrir':
        procesarAbrirJornada();
        break;

    case 'cerrar':
        procesarCerrarJornada();
        break;

    default:
        setFlash('error', 'Acción no válida solicitada.');
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;
}

/**
 * Abre una jornada para una ficha y horario específico.
 */
function procesarAbrirJornada(): void
{
    exigirRol(2);

    $usuarioInstructor = usuarioActual(2);
    $instructorId = (int)($usuarioInstructor['id_usuario'] ?? 0);

    $fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
    $horarioId = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT);

    if (!$fichaId || !$horarioId) {
        setFlash('error', 'Ficha y horario son obligatorios para abrir la jornada.');
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;
    }

    $fechaHoy = date('Y-m-d');
    $diaSemana = date('l');
    $diasMap = [
        'Monday'    => 'Lunes',
        'Tuesday'   => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday'  => 'Jueves',
        'Friday'    => 'Viernes',
        'Saturday'  => 'Sábado',
        'Sunday'    => 'Domingo'
    ];
    $diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;
    $diaSemanaAlt = str_replace(['é', 'á'], ['e', 'a'], $diaSemanaEs);

    $conexion = (new Conexion())->conectar();

    try {
        $conexion->beginTransaction();

        // Validar que el horario pertenezca al instructor, ficha y día
        $q = $conexion->prepare(
            "SELECT id_horario, hora_inicio, hora_fin, dia_semana
             FROM horario
             WHERE id_horario = :horario_id
               AND fk_ficha = :ficha_id
               AND fk_usuario = :instructor_id
               AND (LOWER(dia_semana) = LOWER(:dia_es) OR LOWER(dia_semana) = LOWER(:dia_alt))
               AND (estado = 'Activo' OR estado IS NULL)
             LIMIT 1"
        );
        $q->execute([
            ':horario_id'     => $horarioId,
            ':ficha_id'       => $fichaId,
            ':instructor_id'  => $instructorId,
            ':dia_es'         => $diaSemanaEs,
            ':dia_alt'        => $diaSemanaAlt
        ]);
        $horario = $q->fetch(PDO::FETCH_ASSOC);

        if (!$horario) {
            throw new Exception('El horario seleccionado no es válido o no corresponde a este instructor/ficha hoy.');
        }

        // Validar que no exista ya una sesión abierta o cerrada para este horario hoy
        $q = $conexion->prepare(
            "SELECT id_sesion, estado
             FROM sesiones_jornada
             WHERE ficha_id = :ficha_id
               AND horario_id = :horario_id
               AND fecha = :fecha
             LIMIT 1"
        );
        $q->execute([
            ':ficha_id'    => $fichaId,
            ':horario_id'  => $horarioId,
            ':fecha'       => $fechaHoy
        ]);
        $sesionExistente = $q->fetch(PDO::FETCH_ASSOC);

        if ($sesionExistente) {
            throw new Exception('Ya existe una jornada registrada (abierta o cerrada) para este horario hoy.');
        }

        $horaAperturaReal = date('Y-m-d H:i:s');
        $limitePuntualidadObj = new DateTime($horaAperturaReal);
        $limitePuntualidadObj->modify('+10 minutes');
        $horaLimitePuntualidad = $limitePuntualidadObj->format('Y-m-d H:i:s');

        $q = $conexion->prepare(
            "INSERT INTO sesiones_jornada (instructor_id, ficha_id, horario_id, fecha, hora_apertura_real, hora_limite_puntualidad, estado)
             VALUES (:instructor_id, :ficha_id, :horario_id, :fecha, :hora_apertura, :hora_limite, 'abierta')"
        );
        $q->execute([
            ':instructor_id' => $instructorId,
            ':ficha_id'      => $fichaId,
            ':horario_id'    => $horarioId,
            ':fecha'         => $fechaHoy,
            ':hora_apertura' => $horaAperturaReal,
            ':hora_limite'   => $horaLimitePuntualidad
        ]);

        $conexion->commit();

        $horaAperturaFmt = date('H:i', strtotime($horaAperturaReal));
        $horaLimiteFmt = date('H:i', strtotime($horaLimitePuntualidad));

        setFlash('success', "Jornada abierta correctamente a las {$horaAperturaFmt}. Límite sin retardo: {$horaLimiteFmt}.");
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;

    } catch (Exception $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        setFlash('error', $e->getMessage());
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;
    }
}

/**
 * Cierra la jornada y genera inasistencias automáticas para los aprendices sin registro.
 */
function procesarCerrarJornada(): void
{
    exigirRol(1, 2);

    $usuarioSesion = usuarioActual(2) ?? usuarioActual(1);
    $usuarioId = (int)($usuarioSesion['id_usuario'] ?? 0);
    $rolUsuario = (int)($usuarioSesion['fk_rol'] ?? 0);

    $fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT);
    $horarioIdFiltro = filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT) ?: null;
    $sesionIdFiltro = filter_input(INPUT_POST, 'sesion_id', FILTER_VALIDATE_INT) ?: null;

    if (!$fichaId) {
        setFlash('error', 'Ficha no especificada para cerrar jornada.');
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;
    }

    $fechaHoy = date('Y-m-d');
    $conexion = (new Conexion())->conectar();

    try {
        $conexion->beginTransaction();

        $sqlSesion = "SELECT id_sesion, ficha_id, horario_id, instructor_id, hora_apertura_real
                      FROM sesiones_jornada
                      WHERE ficha_id = :ficha_id
                        AND fecha = :fecha
                        AND estado = 'abierta'";
        $paramsSesion = [
            ':ficha_id' => $fichaId,
            ':fecha'    => $fechaHoy
        ];

        if ($horarioIdFiltro) {
            $sqlSesion .= " AND horario_id = :horario_id";
            $paramsSesion[':horario_id'] = $horarioIdFiltro;
        }
        if ($sesionIdFiltro) {
            $sqlSesion .= " AND id_sesion = :sesion_id";
            $paramsSesion[':sesion_id'] = $sesionIdFiltro;
        }

        $sqlSesion .= " ORDER BY hora_apertura_real DESC LIMIT 1";
        $q = $conexion->prepare($sqlSesion);
        $q->execute($paramsSesion);
        $sesion = $q->fetch(PDO::FETCH_ASSOC);

        if (!$sesion) {
            throw new Exception('No hay jornada abierta hoy para cerrar en esta ficha.');
        }

        if ($rolUsuario !== 1 && (int)$sesion['instructor_id'] !== $usuarioId) {
            throw new Exception('No tienes permisos para cerrar una jornada que no abriste.');
        }

        $sesionId = (int)$sesion['id_sesion'];
        $horarioId = !empty($sesion['horario_id']) ? (int)$sesion['horario_id'] : $horarioIdFiltro;
        $horaCierreReal = date('Y-m-d H:i:s');

        // Cerrar sesión
        $q = $conexion->prepare(
            "UPDATE sesiones_jornada
             SET estado = 'cerrada_manual',
                 hora_cierre_real = :hora_cierre
             WHERE id_sesion = :sesion_id"
        );
        $q->execute([
            ':hora_cierre' => $horaCierreReal,
            ':sesion_id'   => $sesionId
        ]);

        // Generar inasistencias para los aprendices de la ficha sin asistencia registrada
        $q = $conexion->prepare(
            "SELECT ap.id_aprendiz
             FROM aprendiz ap
             WHERE ap.fk_ficha = :ficha_id
               AND ap.id_aprendiz NOT IN (
                   SELECT a.fk_aprendiz
                   FROM asistencia a
                   WHERE a.fecha_asistencia = :fecha
                     AND (
                         a.fk_sesion_jornada = :sesion_id
                         OR (:horario_id IS NOT NULL AND a.fk_horario = :horario_id)
                     )
               )"
        );
        $q->execute([
            ':ficha_id'   => $fichaId,
            ':fecha'      => $fechaHoy,
            ':sesion_id'  => $sesionId,
            ':horario_id' => $horarioId
        ]);
        $aprendicesSinAsistencia = $q->fetchAll(PDO::FETCH_COLUMN);

        $inasistenciasCreadas = 0;
        if (!empty($aprendicesSinAsistencia)) {
            $qInsert = $conexion->prepare(
                "INSERT INTO asistencia (fk_aprendiz, fk_horario, fk_sesion_jornada, fecha_asistencia, estado_entrada)
                 VALUES (:fk_aprendiz, :fk_horario, :fk_sesion_jornada, :fecha, 'INASISTENCIA')"
            );
            foreach ($aprendicesSinAsistencia as $aprendizId) {
                $qInsert->execute([
                    ':fk_aprendiz'       => $aprendizId,
                    ':fk_horario'        => $horarioId,
                    ':fk_sesion_jornada' => $sesionId,
                    ':fecha'             => $fechaHoy
                ]);
                $inasistenciasCreadas++;
            }
        }

        $conexion->commit();

        $horaCierreFmt = date('H:i', strtotime($horaCierreReal));
        $msg = "Jornada cerrada correctamente a las {$horaCierreFmt}.";
        if ($inasistenciasCreadas > 0) {
            $msg .= " Se registraron {$inasistenciasCreadas} inasistencia(s).";
        }

        setFlash('success', $msg);
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;

    } catch (Exception $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        setFlash('error', $e->getMessage());
        header('Location: ' . RUTA_BASE . 'views/instructor/dashboard.php');
        exit;
    }
}
