<?php
/**
 * =============================================================================
 * CONTROLADOR AJAX UNIFICADO: RfidAjaxController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida las operaciones AJAX vinculadas al hardware y tarjetas RFID:
 *   - accion=listar_pendientes : Listar aprendices con RFID pendiente por ficha
 *   - accion=registrar_tarjeta  : Registrar / asociar tarjeta física a un aprendiz
 *   - accion=simular_ingreso    : Simular lectura de tarjeta para pruebas de jornada
 * =============================================================================
 * NOTA DE SEGURIDAD Y ARQUITECTURA:
 * El endpoint físico del lector RFID (registrar_asistencia_rfid.php) permanece
 * separado e intacto para no interferir con las peticiones del hardware.
 * =============================================================================
 */

ob_start();

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';
require_once dirname(__DIR__, 2) . '/models/AsistenciaModel.php';

// Todas las acciones de este controlador corresponden al rol Instructor (rol 2)
exigirRol(2);

// Cabeceras obligatorias para respuestas JSON y prevención de caché
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

date_default_timezone_set('America/Bogota');

// Solo peticiones POST permitidas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar token CSRF (soporta POST y Header X-CSRF-Token)
$tokenRecibido = $_POST['token_csrf'] ?? '';
if ($tokenRecibido === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}

if (!hash_equals($_SESSION['token_csrf'] ?? '', $tokenRecibido)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token CSRF inválido o sesión expirada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuarioActual = usuarioActual(2);

// Determinar la acción solicitada
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática de acción por parámetros enviados (tolerancia a llamados legados)
if ($accion === '') {
    if (isset($_POST['codigo_rfid'])) {
        $accion = 'registrar_tarjeta';
    } elseif (isset($_POST['ficha_id'])) {
        $accion = 'simular_ingreso';
    } elseif (isset($_POST['id_ficha'])) {
        $accion = 'listar_pendientes';
    }
}

switch ($accion) {
    case 'listar_pendientes':
        procesarListarPendientes($usuarioActual);
        break;

    case 'registrar_tarjeta':
        procesarRegistrarTarjeta($usuarioActual);
        break;

    case 'simular_ingreso':
        procesarSimularIngreso($usuarioActual);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no válida o no especificada.'], JSON_UNESCAPED_UNICODE);
        exit;
}

/**
 * =============================================================================
 * ACCIÓN 1: LISTAR APRENDICES PENDIENTES DE RFID
 * =============================================================================
 */
function procesarListarPendientes(array $usuarioActual): void
{
    $idFicha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
    if (!$idFicha) {
        echo json_encode(['success' => false, 'error' => 'Ficha inválida o no proporcionada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conexion = (new Conexion())->conectar();

        // Validar que la ficha pertenece al instructor autenticado
        $qFicha = $conexion->prepare('SELECT nombre_programa FROM ficha WHERE id_ficha = :id_ficha AND fk_usuario = :id_instructor');
        $qFicha->execute([
            ':id_ficha'      => $idFicha,
            ':id_instructor' => $usuarioActual['id_usuario']
        ]);

        $ficha = $qFicha->fetch();
        if (!$ficha) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'La ficha no existe o no le pertenece a este instructor.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $nombrePrograma = $ficha['nombre_programa'];

        // Consultar aprendices pendientes de esta ficha (codigo_rfid NULL, vacío o temporal RFID%)
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
              AND (a.codigo_rfid IS NULL OR a.codigo_rfid = "" OR a.codigo_rfid LIKE "RFID%")
            ORDER BY u.apellido ASC, u.nombre ASC, u.identificacion ASC, a.id_aprendiz ASC
        ');

        $qAprendices->execute([':id_ficha' => $idFicha]);
        $resultados = $qAprendices->fetchAll();

        $pendientes = [];
        foreach ($resultados as $row) {
            $pendientes[] = [
                'id_aprendiz'        => (int)$row['id_aprendiz'],
                'nombre_completo'    => trim($row['nombre'] . ' ' . $row['apellido']),
                'tipo_documento'     => $row['tipo_documento'] ?: 'No especificado',
                'identificacion'     => $row['identificacion'] ?: 'Sin ID',
                'id_ficha'           => $idFicha,
                'programa'           => $nombrePrograma,
                'codigo_rfid_actual' => $row['codigo_rfid_actual']
            ];
        }

        echo json_encode([
            'success'    => true,
            'pendientes' => $pendientes
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error en el servidor al consultar los aprendices.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 2: REGISTRAR CÓDIGO RFID PARA UN APRENDIZ
 * =============================================================================
 */
function procesarRegistrarTarjeta(array $usuarioActual): void
{
    $idAprendiz   = filter_input(INPUT_POST, 'id_aprendiz', FILTER_VALIDATE_INT);
    $idFicha      = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
    $rfidOriginal = $_POST['codigo_rfid'] ?? '';

    if (!$idAprendiz || !$idFicha) {
        echo json_encode(['success' => false, 'error' => 'Datos de aprendiz o ficha inválidos.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nuevoRfid = trim((string)$rfidOriginal);

    if ($nuevoRfid === '') {
        echo json_encode(['success' => false, 'error' => 'El código RFID no puede estar vacío.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (mb_strlen($nuevoRfid) > 50) {
        echo json_encode(['success' => false, 'error' => 'El código RFID excede el límite de 50 caracteres.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (strpos($nuevoRfid, 'RFID') === 0) {
        echo json_encode(['success' => false, 'error' => 'El prefijo "RFID" está reservado por el sistema. Por favor, lea un código físico válido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conexion = (new Conexion())->conectar();

        // Confirmar existencia y pertenencia
        $qVerificar = $conexion->prepare('
            SELECT a.id_aprendiz, a.codigo_rfid 
            FROM aprendiz a
            INNER JOIN ficha f ON a.fk_ficha = f.id_ficha
            WHERE a.id_aprendiz = :id_aprendiz 
              AND a.fk_ficha = :id_ficha
              AND f.fk_usuario = :id_instructor
        ');
        $qVerificar->execute([
            ':id_aprendiz'   => $idAprendiz,
            ':id_ficha'      => $idFicha,
            ':id_instructor' => $usuarioActual['id_usuario']
        ]);

        $aprendiz = $qVerificar->fetch();

        if (!$aprendiz) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Aprendiz no encontrado o no pertenece a una de sus fichas autorizadas.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!empty($aprendiz['codigo_rfid']) && strpos((string)$aprendiz['codigo_rfid'], 'RFID') !== 0) {
            echo json_encode(['success' => false, 'error' => 'El aprendiz ya posee un código RFID físico registrado y no puede ser reemplazado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Actualizar atómicamente
        $qUpdate = $conexion->prepare('
            UPDATE aprendiz 
            SET codigo_rfid = :nuevo_rfid 
            WHERE id_aprendiz = :id_aprendiz 
              AND fk_ficha = :id_ficha 
              AND fk_ficha IN (SELECT id_ficha FROM ficha WHERE fk_usuario = :id_instructor)
        ');

        $qUpdate->execute([
            ':nuevo_rfid'    => $nuevoRfid,
            ':id_aprendiz'   => $idAprendiz,
            ':id_ficha'      => $idFicha,
            ':id_instructor' => $usuarioActual['id_usuario']
        ]);

        if ($qUpdate->rowCount() === 1) {
            $es_flujo_importacion = false;
            $pendientes_restantes = 0;

            if (isset($_SESSION['rfid_importacion_pendiente']) && is_array($_SESSION['rfid_importacion_pendiente'])) {
                $es_flujo_importacion = true;
                $aprendices_pendientes = $_SESSION['rfid_importacion_pendiente']['aprendices'] ?? [];

                $nueva_cola = array_filter($aprendices_pendientes, function ($a) use ($idAprendiz) {
                    return (int)$a['id_aprendiz'] !== (int)$idAprendiz;
                });

                $_SESSION['rfid_importacion_pendiente']['aprendices'] = array_values($nueva_cola);
                $pendientes_restantes = count($_SESSION['rfid_importacion_pendiente']['aprendices']);

                if ($pendientes_restantes === 0) {
                    unset($_SESSION['rfid_importacion_pendiente']);
                }
            }

            echo json_encode([
                'success'              => true,
                'message'              => 'Código RFID registrado correctamente.',
                'id_aprendiz'          => $idAprendiz,
                'codigo_rfid'          => $nuevoRfid,
                'es_flujo_importacion' => $es_flujo_importacion,
                'pendientes_restantes' => $pendientes_restantes
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'No se actualizó ningún registro (verifique el estado actual).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'error' => 'El código leído ya está asignado a otro aprendiz. Intente con una tarjeta distinta.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error de servidor al guardar el código RFID.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 3: SIMULAR INGRESO RFID DEL SIGUIENTE APRENDIZ PENDIENTE
 * =============================================================================
 */
function procesarSimularIngreso(array $usuarioActual): void
{
    // Validar que el modo de pruebas esté habilitado en el servidor
    if (!defined('MODO_PRUEBAS_RFID') || !MODO_PRUEBAS_RFID) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'El modo de pruebas para simular ingresos RFID está deshabilitado en el servidor'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fichaId = filter_input(INPUT_POST, 'ficha_id', FILTER_VALIDATE_INT) ?: (isset($_POST['ficha_id']) ? (int)$_POST['ficha_id'] : null);
    if (!$fichaId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetro ficha_id es requerido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $instructorId = (int)$usuarioActual['id_usuario'];
    $fechaHoy = date('Y-m-d');

    $conexion = (new Conexion())->conectar();

    try {
        // Validar que la ficha pertenezca al instructor autenticado
        $q = $conexion->prepare("SELECT id_ficha FROM ficha WHERE id_ficha = :ficha_id AND fk_usuario = :instructor_id");
        $q->execute([
            ':ficha_id'      => $fichaId,
            ':instructor_id' => $instructorId
        ]);
        if (!$q->fetch()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tiene autorización para gestionar esta ficha'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validar que la jornada esté actualmente abierta hoy
        $horarioIdFiltro = (filter_input(INPUT_POST, 'horario_id', FILTER_VALIDATE_INT) ?: (isset($_POST['horario_id']) ? (int)$_POST['horario_id'] : null)) ?: null;
        $sqlSesion = "SELECT id_sesion, horario_id, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
                      FROM sesiones_jornada
                      WHERE ficha_id = :ficha_id
                        AND fecha = :fecha
                        AND estado = 'abierta'";
        $paramsSesion = [
            ':ficha_id' => $fichaId,
            ':fecha'    => $fechaHoy
        ];
        if ($horarioIdFiltro) {
            $sqlSesion .= " AND (horario_id = :horario_id OR horario_id IS NULL)
                            ORDER BY (CASE WHEN horario_id = :horario_id THEN 0 ELSE 1 END) ASC, id_sesion DESC";
            $paramsSesion[':horario_id'] = $horarioIdFiltro;
        } else {
            $sqlSesion .= " ORDER BY id_sesion DESC";
        }
        $sqlSesion .= " LIMIT 1";

        $q = $conexion->prepare($sqlSesion);
        $q->execute($paramsSesion);
        $sesion = $q->fetch(PDO::FETCH_ASSOC);

        if (!$sesion || $sesion['hora_cierre_real'] !== null) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'La jornada no está abierta para esta ficha. El instructor debe abrirla primero.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sesionId = (int)$sesion['id_sesion'];
        $horarioIdSesion = !empty($sesion['horario_id']) ? (int)$sesion['horario_id'] : $horarioIdFiltro;

        // Verificar si la ficha tiene aprendices con RFID registrado
        $q = $conexion->prepare(
            "SELECT COUNT(*) FROM aprendiz 
             WHERE fk_ficha = :ficha_id 
               AND codigo_rfid IS NOT NULL 
               AND TRIM(codigo_rfid) <> ''"
        );
        $q->execute([':ficha_id' => $fichaId]);
        $totalConRfid = (int)$q->fetchColumn();

        if ($totalConRfid === 0) {
            http_response_code(200);
            echo json_encode([
                'success'              => false,
                'message'              => 'No hay aprendices con tarjeta RFID asignada en esta ficha',
                'pendientes_restantes' => 0
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Buscar aprendices de la ficha con RFID que NO hayan registrado ingreso en esta sesión/horario
        $q = $conexion->prepare(
            "SELECT a.id_aprendiz, a.codigo_rfid, u.nombre, u.apellido
             FROM aprendiz a
             INNER JOIN usuario u ON u.id_usuario = a.fk_usuario
             WHERE a.fk_ficha = :ficha_id
               AND a.codigo_rfid IS NOT NULL 
               AND TRIM(a.codigo_rfid) <> ''
               AND NOT EXISTS (
                   SELECT 1 FROM asistencia ast 
                   WHERE ast.fk_aprendiz = a.id_aprendiz 
                     AND (
                         ast.fk_sesion_jornada = :sesion_id
                         OR (ast.fecha_asistencia = :fecha AND :horario_id IS NOT NULL AND ast.fk_horario = :horario_id)
                     )
               )
             ORDER BY a.id_aprendiz ASC"
        );
        $q->execute([
            ':ficha_id'   => $fichaId,
            ':sesion_id'  => $sesionId,
            ':fecha'      => $fechaHoy,
            ':horario_id' => $horarioIdSesion
        ]);
        $pendientes = $q->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendientes)) {
            http_response_code(200);
            echo json_encode([
                'success'              => false,
                'message'              => 'No quedan aprendices con RFID pendientes de ingreso',
                'pendientes_restantes' => 0
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Seleccionar el siguiente pendiente
        $siguiente = $pendientes[0];
        $pendientesRestantes = count($pendientes) - 1;

        // Procesar entrada utilizando la lógica centralizada en AsistenciaModel
        $resultado = AsistenciaModel::registrarPorRfid($siguiente['codigo_rfid'], 'entrada', $conexion, $fichaId);

        if (!$resultado['success']) {
            http_response_code($resultado['http_code'] ?? 400);
            echo json_encode([
                'success' => false,
                'message' => $resultado['message'] ?? 'No fue posible registrar el ingreso'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Ingreso registrado correctamente',
            'data'    => [
                'aprendiz'             => $siguiente['nombre'] . ' ' . $siguiente['apellido'],
                'hora'                 => $resultado['data']['hora'] ?? date('H:i'),
                'estado'               => $resultado['data']['estado'] ?? 'PUNTUAL',
                'minutos_retardo'      => $resultado['data']['minutos_retardo'] ?? 0,
                'pendientes_restantes' => $pendientesRestantes
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        error_log("Error al simular ingreso RFID: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'No fue posible completar la simulación de ingreso. Inténtalo nuevamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
