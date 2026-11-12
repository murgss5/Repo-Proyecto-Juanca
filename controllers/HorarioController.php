<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE HORARIOS: HorarioController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida todas las operaciones del dominio de horarios de formación:
 *   - accion=importar  : Carga, previsualización y matriz oficial Excel (.xlsx)
 *   - accion=confirmar : Inserción atómica y validación cruzada de bloques
 *   - accion=eliminar  : Eliminación segura y limpieza en cascada de sesiones
 * =============================================================================
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(1);

require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/HorarioModel.php';
require_once __DIR__ . '/../includes/HorarioValidator.php';
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

date_default_timezone_set('America/Bogota');

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática por compatibilidad de formularios existentes
if ($accion === '') {
    if (isset($_POST['crear_ficha_rapida'])) {
        $accion = 'crear_ficha_rapida';
    } elseif (isset($_POST['filas_seleccionadas']) || isset($_POST['accion_confirmar'])) {
        $accion = 'confirmar';
    } elseif (isset($_POST['id_horario']) && !isset($_FILES['archivo_excel'])) {
        $accion = 'eliminar';
    } else {
        $accion = 'importar';
    }
}

switch ($accion) {
    case 'eliminar':
        procesarEliminarHorario();
        break;

    case 'confirmar':
        procesarConfirmarImportarHorarios();
        break;

    case 'importar':
    case 'crear_ficha_rapida':
    case 'guardar_instructores_faltantes':
    default:
        procesarImportarHorariosExcel();
        break;
}

/**
 * =============================================================================
 * ACCIÓN 1: ELIMINACIÓN DE BLOQUES HORARIOS
 * =============================================================================
 */
function procesarEliminarHorario(): void
{
/**
 * Controlador administrativo: Eliminación segura de bloques horarios
 * Valida rol de administrador (rol 1), método HTTP POST y token CSRF.
 * Elimina el horario y realiza limpieza en cascada de sus sesiones asociadas.
 */

date_default_timezone_set('America/Bogota');

// 1. Validar método HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/admin/horarios/index.php');
    exit;
}

// 2. Determinar URL de retorno segura
$returnUrl = trim($_POST['return_url'] ?? '');
if (empty($returnUrl) || strpos($returnUrl, RUTA_BASE) !== 0) {
    $vista = in_array($_POST['vista'] ?? '', ['tabla', 'calendario'], true) ? $_POST['vista'] : 'calendario';
    $fecha = trim($_POST['fecha'] ?? '');
    $ficha = filter_input(INPUT_POST, 'ficha', FILTER_VALIDATE_INT) ?: '';
    $instructor = filter_input(INPUT_POST, 'instructor', FILTER_VALIDATE_INT) ?: '';
    
    $params = ['vista' => $vista];
    if ($fecha) $params['fecha'] = $fecha;
    if ($ficha) $params['ficha'] = $ficha;
    if ($instructor) $params['instructor'] = $instructor;
    
    $returnUrl = RUTA_BASE . 'views/admin/horarios/index.php?' . http_build_query($params);
}

function responderEliminacion(string $urlBase, string $tipo, string $mensaje, bool $isAjax = false): void
{
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => ($tipo === 'mensaje'),
            'message' => $mensaje,
            'redirect' => $urlBase
        ]);
        exit;
    }

    $separador = (strpos($urlBase, '?') !== false) ? '&' : '?';
    header('Location: ' . $urlBase . $separador . urlencode($tipo) . '=' . urlencode($mensaje));
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// 3. Validar token CSRF
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    responderEliminacion($returnUrl, 'error', 'Token de seguridad inválido o sesión expirada.', $isAjax);
}

// 4. Validar ID del horario
$idHorario = filter_input(INPUT_POST, 'id_horario', FILTER_VALIDATE_INT);
if (!$idHorario || $idHorario <= 0) {
    responderEliminacion($returnUrl, 'error', 'Identificador de horario no válido.', $isAjax);
}

// 5. Ejecutar eliminación en cascada
$resultado = HorarioModel::eliminarHorario($idHorario);

if ($resultado['success']) {
    $msg = $resultado['mensaje'] ?? 'Horario eliminado correctamente.';
    responderEliminacion($returnUrl, 'mensaje', $msg, $isAjax);
} else {
    $msg = $resultado['mensaje'] ?? 'No fue posible eliminar el horario.';
    responderEliminacion($returnUrl, 'error', $msg, $isAjax);
}


}

/**
 * =============================================================================
 * ACCIÓN 2: CONFIRMACIÓN E INSERCIÓN EN TRANSACCIÓN DE HORARIOS
 * =============================================================================
 */
function procesarConfirmarImportarHorarios(): void
{
/**
 * controlador: confirmar importacion de horarios desde previsualizacion
 * fase 3: inserta horarios validos en transaccion
 */

// validar post y rol admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

// validar csrf
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Token CSRF invalido'];
    header('Location: ../views/admin/horarios/importar_preview.php');
    exit;
}

// recuperar preview de sesion
if (empty($_SESSION['import_preview'])) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No hay previsualizacion para confirmar'];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

$preview = $_SESSION['import_preview'];
$filas = $preview['filas'] ?? [];

$filasSeleccionadas = $_POST['filas_seleccionadas'] ?? [];
$fichasManual = $_POST['ficha_manual'] ?? [];

if (empty($filasSeleccionadas)) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No se seleccionaron filas para importar'];
    header('Location: ../views/admin/horarios/importar_preview.php');
    exit;
}

// conectar a bd
$conexion = (new Conexion())->conectar();

try {
    $conexion->beginTransaction();

    // cache para validaciones
    $cacheInstructores = [];
    $cacheFichas = [];
    $cacheHorariosExistentes = [];
    $cacheHorariosPorAmbienteDia = [];

    // obtener horarios existentes con ambiente_principal
    $q = $conexion->query("
        SELECT h.fk_ficha, h.dia_semana, h.entrada, h.salida, f.ambiente_principal
        FROM horario h
        INNER JOIN ficha f ON f.id_ficha = h.fk_ficha
    ");
    $horariosBD = $q->fetchAll();
    foreach ($horariosBD as $h) {
        $clave = $h['fk_ficha'] . '|' . $h['dia_semana'] . '|' . substr($h['entrada'], 0, 5) . '|' . substr($h['salida'], 0, 5);
        $cacheHorariosExistentes[$clave] = true;
        // para cruces de ambiente
        $amb = trim((string)($h['ambiente_principal'] ?? ''));
        if ($amb !== '') {
            $ambDia = HorarioValidator::normalizarAmbiente($amb) . '|' . $h['dia_semana'];
            $cacheHorariosPorAmbienteDia[$ambDia][] = $h;
        }
    }

    $resultados = [];
    $creados = 0;
    $omitidos = 0;
    $rechazados = 0;

    foreach ($filasSeleccionadas as $idxStr) {
        $idx = (int)$idxStr;
        if (!isset($filas[$idx])) {
            continue; // ignorar indices invalidos
        }

        $f = $filas[$idx];

        // solo filas VALIDA, ADVERTENCIA, PENDIENTE_FICHA
        if (!in_array($f['estado'], ['VALIDA', 'ADVERTENCIA', 'PENDIENTE_FICHA'], true)) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => $f['ficha_id'] ?? ($fichasManual[$idx] ?? '—'),
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Estado no importable: ' . $f['estado']
            ];
            $rechazados++;
            continue;
        }

        // validar instructor
        $identInstr = $f['identificacion_instructor'];
        if (!isset($cacheInstructores[$identInstr])) {
            $q = $conexion->prepare("SELECT id_usuario, nombre, apellido FROM usuario WHERE identificacion = ? AND fk_rol = 2 LIMIT 1");
            $q->execute([$identInstr]);
            $cacheInstructores[$identInstr] = $q->fetch();
        }
        $instructor = $cacheInstructores[$identInstr];
        if (!$instructor) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '—',
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Instructor no encontrado o sin rol Instructor'
            ];
            $rechazados++;
            continue;
        }

        // resolver ficha
        $fichaId = $f['ficha_id'];
        if ($f['pendiente_ficha']) {
            $fichaId = isset($fichasManual[$idx]) ? (int)$fichasManual[$idx] : 0;
        }

        if (!$fichaId) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '—',
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Ficha no seleccionada'
            ];
            $rechazados++;
            continue;
        }

        // validar ficha
        if (!isset($cacheFichas[$fichaId])) {
            $q = $conexion->prepare("SELECT id_ficha, nombre_programa, fk_usuario, ambiente_principal FROM ficha WHERE id_ficha = ? LIMIT 1");
            $q->execute([$fichaId]);
            $cacheFichas[$fichaId] = $q->fetch();
        }
        $ficha = $cacheFichas[$fichaId];
        if (!$ficha) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $fichaId,
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Ficha no existe'
            ];
            $rechazados++;
            continue;
        }

        // validar pertenencia ficha-instructor
        if ((int)$ficha['fk_usuario'] !== (int)$instructor['id_usuario']) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'],
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'La ficha no pertenece al instructor indicado'
            ];
            $rechazados++;
            continue;
        }

        // validar ambiente: Excel vs ficha.ambiente_principal
        $ambienteExcel = trim((string)($f['ambiente'] ?? ''));
        $ambienteFicha = trim((string)($ficha['ambiente_principal'] ?? ''));
        if ($ambienteExcel !== '') {
            if ($ambienteFicha !== '') {
                if (HorarioValidator::normalizarAmbiente($ambienteExcel) !== HorarioValidator::normalizarAmbiente($ambienteFicha)) {
                    $resultados[] = [
                        'fila' => $f['fila'],
                        'instructor' => $f['instructor_nombre'],
                        'ficha' => '#' . $ficha['id_ficha'],
                        'dia' => $f['dia'],
                        'inicio' => $f['hora_inicio'],
                        'fin' => $f['hora_fin'],
                        'resultado' => 'RECHAZADA',
                        'detalle' => "Ambiente no coincide: Excel='$ambienteExcel', ficha principal='$ambienteFicha'"
                    ];
                    $rechazados++;
                    continue;
                }
            }
            // si ficha no tiene ambiente_principal, solo advertencia (no bloquea)
        }

        // normalizar dia y horas
        $dia = HorarioValidator::normalizarDia($f['dia']);
        $horaIni = HorarioValidator::normalizarHora($f['hora_inicio']);
        $horaFin = HorarioValidator::normalizarHora($f['hora_fin']);

        if (!$dia || !$horaIni || !$horaFin || !HorarioValidator::validarOrdenHoras($horaIni, $horaFin)) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'],
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Dia u horas invalidas'
            ];
            $rechazados++;
            continue;
        }

        // comprobar duplicado exacto en bd
        $claveBD = (int)$ficha['id_ficha'] . '|' . $dia . '|' . $horaIni . '|' . $horaFin;
        if (isset($cacheHorariosExistentes[$claveBD])) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'resultado' => 'OMITIDA_DUPLICADO',
                'detalle' => 'Horario identico ya existe en BD'
            ];
            $omitidos++;
            // marcar en cache para que siguientes filas no intenten insertar lo mismo
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'ambiente' => $ambienteFicha,
                'resultado' => 'OMITIDA_DUPLICADO',
                'detalle' => 'Horario identico ya existe en BD'
            ];
            continue;
        }

        // cruces de ambiente contra BD (fichas distintas con mismo ambiente, mismo dia, horas solapadas)
        if ($ambienteFicha !== '') {
            $ambDia = HorarioValidator::normalizarAmbiente($ambienteFicha) . '|' . $dia;
            if (isset($cacheHorariosPorAmbienteDia[$ambDia])) {
                foreach ($cacheHorariosPorAmbienteDia[$ambDia] as $hExist) {
                    if ((int)$hExist['fk_ficha'] === $ficha['id_ficha']) continue;
                    $hIni = substr($hExist['entrada'], 0, 5);
                    $hFin = substr($hExist['salida'], 0, 5);
                    if (HorarioValidator::haySolapamientoSeguro($horaIni, $horaFin, $hIni, $hFin)) {
                        $resultados[] = [
                            'fila' => $f['fila'],
                            'instructor' => $f['instructor_nombre'],
                            'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                            'dia' => $dia,
                            'inicio' => $horaIni,
                            'fin' => $horaFin,
                            'resultado' => 'RECHAZADA',
                            'detalle' => "Cruce de ambiente: ficha #{$hExist['fk_ficha']} usa mismo ambiente '$ambienteFicha' el $dia ($hIni-$hFin)"
                        ];
                        $rechazados++;
                        continue 2; // salta al siguiente foreach principal
                    }
                }
            }
        }

        // cruces de ambiente dentro de la misma transaccion (filas previas de esta importacion)
        if ($ambienteFicha !== '') {
            $ambienteNorm = HorarioValidator::normalizarAmbiente($ambienteFicha);
            foreach ($resultados as $r) {
                if ($r['resultado'] === 'RECHAZADA') continue;
                if ($r['ficha'] === '#' . $ficha['id_ficha']) continue;
                if ($r['dia'] !== $dia) continue;
                $rAmbNorm = HorarioValidator::normalizarAmbiente($r['ambiente'] ?? '');
                if ($rAmbNorm !== $ambienteNorm) continue;
                if (HorarioValidator::haySolapamientoSeguro($horaIni, $horaFin, $r['inicio'], $r['fin'])) {
                    $resultados[] = [
                        'fila' => $f['fila'],
                        'instructor' => $f['instructor_nombre'],
                        'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                        'dia' => $dia,
                        'inicio' => $horaIni,
                        'fin' => $horaFin,
                        'resultado' => 'RECHAZADA',
                        'detalle' => "Cruce de ambiente dentro de la importacion: ficha {$r['ficha']} usa mismo ambiente '$ambienteFicha' el $dia ({$r['inicio']}-{$r['fin']})"
                    ];
                    $rechazados++;
                    continue 2;
                }
            }
        }

        // insertar horario
        $stmt = $conexion->prepare("INSERT INTO horario (dia_semana, entrada, salida, fk_ficha, instructor_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$dia, $horaIni . ':00', $horaFin . ':00', $ficha['id_ficha'], $instructor['id_usuario']]);

        if ($stmt->rowCount() > 0) {
            $nuevoIdHorario = (int)$conexion->lastInsertId();
            $resultados[] = [
                'fila' => $f['fila'],
                'id_horario' => $nuevoIdHorario,
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'ambiente' => $ambienteFicha,
                'resultado' => 'CREADA',
                'detalle' => 'Horario insertado correctamente'
            ];
            $creados++;
            // actualizar cache para evitar duplicados en misma transaccion
            $cacheHorariosExistentes[$claveBD] = true;
        } else {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'resultado' => 'RECHAZADA',
                'detalle' => 'No se pudo insertar (rowCount=0)'
            ];
            $rechazados++;
        }
    }

    $conexion->commit();

    // limpiar preview de sesion
    unset($_SESSION['import_preview']);

    // guardar resultado en flash para la vista de resultado
    $_SESSION['import_resultado'] = [
        'total_seleccionadas' => count($filasSeleccionadas),
        'creadas' => $creados,
        'omitidas' => $omitidos,
        'rechazadas' => $rechazados,
        'detalles' => $resultados,
        'timestamp' => time()
    ];

    header('Location: ../views/admin/horarios/importar_resultado.php');
    exit;

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error en confirmar_importar_horarios: " . $e->getMessage());
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No fue posible completar la importación de horarios. Inténtalo nuevamente.'];
    header('Location: ../views/admin/horarios/importar_preview.php');
    exit;
}
}

/**
 * =============================================================================
 * ACCIÓN 3: IMPORTACIÓN MATRICIAL Y LECTURA DE EXCEL
 * =============================================================================
 */
function procesarImportarHorariosExcel(): void
{
/**
 * ============================================================
 * CONTROLADOR: IMPORTADOR MATRICIAL DE HORARIOS SENA
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ============================================================
 * Lee el formato oficial matricial del SENA (.xlsx), extrayendo:
 * - Ficha y Programa (celdas E2/D2 y H2 o cabeceras) con creación dinámica
 * - Instructores únicos y validación flexible contra BD
 * - Mapeo de días (Lunes: D, Martes: F, Miércoles: H, Jueves: J, Viernes: L, Sábado: N)
 * - Franjas horarias (Inicio: Col B, Fin: Col C)
 * - Agrupación de bloques continuos con el mismo instructor y ambiente
 * - Inserción atómica en BD mediante transacción PDO
 */

/**
 * Responder en formato JSON
 */
function responderJson(array $data): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// 1. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        responderJson(['success' => false, 'error' => 'Método no permitido. Se requiere POST.']);
    }
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

// 2. Validar token CSRF
$tokenPost = $_POST['token_csrf'] ?? '';
if (empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $tokenPost)) {
    if ($isAjax) {
        responderJson(['success' => false, 'error' => 'Token CSRF inválido o sesión caducada. Recargue la página.']);
    }
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Token CSRF inválido.'];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

// Conexión a la base de datos
$conexion = (new Conexion())->conectar();

// 2.1 Acción: Creación Rápida de Ficha Faltante
if (isset($_POST['crear_ficha_rapida'])) {
    $numeroFicha = filter_var($_POST['numero_ficha'] ?? null, FILTER_VALIDATE_INT);
    $programa = trim($_POST['programa'] ?? '');
    $jornada = trim($_POST['jornada'] ?? '');

    if (!$numeroFicha || $programa === '' || $jornada === '') {
        responderJson([
            'success' => false,
            'error' => 'Todos los campos de la ficha son obligatorios (número, programa y jornada).'
        ]);
    }

    if (mb_strlen($programa) > 100) {
        $programa = mb_substr($programa, 0, 100);
    }

    // Jornadas válidas
    $jornadasPermitidas = ['Mañana', 'Tarde', 'Noche', 'Mixta', 'Fines de semana', 'Diurna', 'Nocturna'];
    if (!in_array($jornada, $jornadasPermitidas, true)) {
        $jornada = 'Mañana';
    }

    // Obtener un instructor por defecto para fk_usuario (obligatorio en el esquema)
    $stmtInst = $conexion->query("SELECT id_usuario FROM usuario WHERE fk_rol = 2 ORDER BY id_usuario ASC LIMIT 1");
    $idInstructor = $stmtInst->fetchColumn();
    if (!$idInstructor) {
        $idInstructor = $_SESSION['admin']['id_usuario'];
    }

    try {
        $stmtCheck = $conexion->prepare("SELECT id_ficha FROM ficha WHERE id_ficha = ?");
        $stmtCheck->execute([$numeroFicha]);
        if ($stmtCheck->fetch()) {
            $stmtUp = $conexion->prepare("UPDATE ficha SET nombre_programa = ?, jornada = ? WHERE id_ficha = ?");
            $stmtUp->execute([$programa, $jornada, $numeroFicha]);
        } else {
            $stmtIns = $conexion->prepare("
                INSERT INTO ficha (id_ficha, nombre_programa, jornada, ambiente_principal, fk_usuario)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([
                $numeroFicha,
                $programa,
                $jornada,
                'Por asignar',
                $idInstructor
            ]);
        }

        echo json_encode(['success' => true]);
        exit;
    } catch (\Throwable $e) {
        error_log("Error al crear ficha en importar_horarios_excel: " . $e->getMessage());
        responderJson([
            'success' => false,
            'error' => 'No fue posible crear la ficha. Inténtalo nuevamente.'
        ]);
    }
}

// 3. Funciones auxiliares de normalización y extracción

/**
 * Normaliza cadenas eliminando tildes y espacios excesivos
 */
function normalizarTexto(string $texto): string {
    $texto = mb_strtoupper(trim($texto), 'UTF-8');
    $reemplazos = [
        'Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U',
        'À'=>'A', 'È'=>'E', 'Ì'=>'I', 'Ò'=>'O', 'Ù'=>'U',
        'Ä'=>'A', 'Ë'=>'E', 'Ï'=>'I', 'Ö'=>'O', 'Ü'=>'U'
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = preg_replace('/[^A-Z0-9\s]/', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    return trim($texto);
}

/**
 * Normaliza valores de hora de Excel a formato HH:MM:SS
 */
function normalizarHora($valor, $cell = null): ?string {
    if ($cell instanceof \PhpOffice\PhpSpreadsheet\Cell\Cell) {
        if (ExcelDate::isDateTime($cell)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject($cell->getValue());
                return $dt->format('H:i:00');
            } catch (\Exception $e) {}
        }
        $formatted = trim((string)$cell->getFormattedValue());
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $formatted, $m)) {
            return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
        }
    }
    $str = trim((string)$valor);
    if ($str === '') return null;

    if (is_numeric($str)) {
        $num = (float)$str;
        // Fracción decimal de día de Excel (ej. 0.25 = 06:00:00)
        if ($num > 0 && $num < 1) {
            $segundos = round($num * 86400);
            $h = floor($segundos / 3600);
            $m = floor(($segundos % 3600) / 60);
            return sprintf('%02d:%02d:00', $h, $m);
        }
        // Hora militar entera o decimal (ej. 6, 7, 12, 12.5, 18, 22)
        if ($num >= 0 && $num <= 24) {
            $h = floor($num);
            $m = round(($num - $h) * 60);
            if ($h == 24 && $m == 0) {
                return '23:59:59';
            }
            return sprintf('%02d:%02d:00', $h, $m);
        }
    }

    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $str, $m)) {
        return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
    }
    return null;
}

/**
 * Busca de forma flexible un instructor de Excel en la BD
 */
function buscarInstructorEnBD(string $nombreExcel, array $instructoresBD): ?int {
    $normExcel = normalizarTexto($nombreExcel);
    if ($normExcel === '') return null;

    $palabrasExcel = array_filter(explode(' ', $normExcel), fn($p) => strlen($p) >= 3);

    foreach ($instructoresBD as $inst) {
        $nom = normalizarTexto($inst['nombre'] ?? '');
        $ape = normalizarTexto($inst['apellido'] ?? '');
        $nomCompleto1 = trim($nom . ' ' . $ape);
        $nomCompleto2 = trim($ape . ' ' . $nom);

        // Coincidencia exacta
        if ($normExcel === $nomCompleto1 || $normExcel === $nomCompleto2) {
            return (int)$inst['id_usuario'];
        }

        // Subcadena directa
        if ($nomCompleto1 !== '' && (strpos($normExcel, $nomCompleto1) !== false || strpos($nomCompleto1, $normExcel) !== false)) {
            return (int)$inst['id_usuario'];
        }
        if ($nomCompleto2 !== '' && (strpos($normExcel, $nomCompleto2) !== false || strpos($nomCompleto2, $normExcel) !== false)) {
            return (int)$inst['id_usuario'];
        }

        // Coincidencia de palabras clave (nombres y apellidos)
        $palabrasNom = array_filter(explode(' ', $nom), fn($p) => strlen($p) >= 3);
        $palabrasApe = array_filter(explode(' ', $ape), fn($p) => strlen($p) >= 3);

        $tieneNom = false;
        foreach ($palabrasNom as $pn) {
            if (in_array($pn, $palabrasExcel, true)) {
                $tieneNom = true;
                break;
            }
        }

        $tieneApe = false;
        foreach ($palabrasApe as $pa) {
            if (in_array($pa, $palabrasExcel, true)) {
                $tieneApe = true;
                break;
            }
        }

        if ($tieneNom && $tieneApe) {
            return (int)$inst['id_usuario'];
        }
    }

    return null;
}

/**
 * Extrae número de ficha y nombre del programa sugerido desde el Excel
 */
function extraerFichaYPrograma($sheet): array {
    $numeroFicha = null;
    $nombrePrograma = '';

    // 1. Revisar celda E2 y D2 primero
    $valE2 = trim((string)$sheet->getCell('E2')->getValue());
    $valD2 = trim((string)$sheet->getCell('D2')->getValue());

    if ($valE2 !== '' && preg_match('/(?:ficha[:\s#]*)?(\d+)/i', $valE2, $m)) {
        $numeroFicha = (int)$m[1];
    } elseif ($valD2 !== '' && preg_match('/(?:ficha[:\s#]*)?(\d+)/i', $valD2, $m)) {
        $numeroFicha = (int)$m[1];
    }

    // 2. Si no se encontró en E2 ni D2, escanear primeras 10 filas buscando "ficha"
    if (!$numeroFicha) {
        $highestCol = $sheet->getHighestColumn();
        $colLimit = min(Coordinate::columnIndexFromString($highestCol), 20);
        for ($r = 1; $r <= 10; $r++) {
            for ($c = 1; $c <= $colLimit; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $val = trim((string)$sheet->getCell($coord)->getValue());
                if (stripos($val, 'ficha') !== false) {
                    if (preg_match('/(?:ficha[:\s#]*)(\d+)/i', $val, $m)) {
                        $numeroFicha = (int)$m[1];
                        break 2;
                    }
                    for ($offset = 1; $offset <= 3; $offset++) {
                        $coordDer = Coordinate::stringFromColumnIndex($c + $offset) . $r;
                        $valDer = trim((string)$sheet->getCell($coordDer)->getValue());
                        if (preg_match('/(\d+)/', $valDer, $m)) {
                            $numeroFicha = (int)$m[1];
                            break 3;
                        }
                    }
                }
            }
        }
    }

    // 3. Extraer Nombre del Programa sugerido (celda H2 o cercanas)
    $valH2 = trim((string)$sheet->getCell('H2')->getValue());
    if ($valH2 !== '' && strlen($valH2) > 3) {
        $nombrePrograma = $valH2;
    } else {
        foreach (['G2', 'F2', 'I2', 'J2', 'H1', 'H3', 'D1', 'E1', 'F1'] as $coordTest) {
            $valTest = trim((string)$sheet->getCell($coordTest)->getValue());
            if ($valTest !== '' && strlen($valTest) > 5 && !preg_match('/^\d+$/', $valTest) && stripos($valTest, 'ficha') === false) {
                $nombrePrograma = $valTest;
                break;
            }
        }
    }

    if ($nombrePrograma === '') {
        $highestCol = $sheet->getHighestColumn();
        $colLimit = min(Coordinate::columnIndexFromString($highestCol), 20);
        for ($r = 1; $r <= 10; $r++) {
            for ($c = 1; $c <= $colLimit; $c++) {
                $coord = Coordinate::stringFromColumnIndex($c) . $r;
                $val = trim((string)$sheet->getCell($coord)->getValue());
                if ($val === '') continue;
                if (preg_match('/(?:programa[:\s]*|tecn[oó]logo\s+en\s+|t[eé]cnico\s+en\s+)(.+)/i', $val)) {
                    $nombrePrograma = trim($val);
                    break 2;
                }
            }
        }
    }

    if (preg_match('/^programa[:\s]*(.+)$/i', $nombrePrograma, $mClean)) {
        $nombrePrograma = trim($mClean[1]);
    }
    if (mb_strlen($nombrePrograma) > 100) {
        $nombrePrograma = mb_substr($nombrePrograma, 0, 100);
    }

    return [
        'numero_ficha'      => $numeroFicha,
        'programa_sugerido' => $nombrePrograma
    ];
}

/**
 * Detecta inteligentemente la jornada sugerida (Mañana, Tarde, Noche)
 * escaneando la columna B (hora_inicio) buscando la primera hora válida.
 */
function detectarJornadaSugerida($sheet): string {
    $highestRow = method_exists($sheet, 'getHighestDataRow') ? $sheet->getHighestDataRow() : $sheet->getHighestRow();
    $colsDias = ['D', 'F', 'H', 'J', 'L', 'N'];
    $primeraHoraConClase = null;
    $primeraHoraCualquiera = null;

    for ($row = 1; $row <= $highestRow; $row++) {
        $cellB = $sheet->getCell("B{$row}");
        $hora = normalizarHora($cellB->getValue(), $cellB);
        if ($hora !== null) {
            if ($primeraHoraCualquiera === null) {
                $primeraHoraCualquiera = $hora;
            }
            foreach ($colsDias as $colD) {
                $valD = trim((string)$sheet->getCell("{$colD}{$row}")->getValue());
                if ($valD !== '' && strpos($valD, '-') !== false) {
                    $primeraHoraConClase = $hora;
                    break 2;
                }
            }
        }
    }

    $horaElegida = $primeraHoraConClase ?: $primeraHoraCualquiera;
    if ($horaElegida !== null) {
        $partes = explode(':', $horaElegida);
        $h = (int)$partes[0];
        if ($h >= 6 && $h < 12) {
            return 'Mañana';
        } elseif ($h >= 12 && $h < 18) {
            return 'Tarde';
        } else {
            return 'Noche';
        }
    }
    return 'Mañana';
}

/**
 * Parsea el contenido de una celda de clase del SENA
 */
function parsearCeldaSena(string $texto, array $mapeoInstructores): array {
    $lineas = preg_split('/\r\n|\r|\n/', trim($texto));
    $lineas = array_values(array_filter(array_map('trim', $lineas), fn($l) => $l !== ''));

    $nombreInstructor = !empty($lineas[0]) ? $lineas[0] : null;

    // Ambiente: último segmento tras el último '-'
    $ambiente = null;
    if (strpos($texto, '-') !== false) {
        $partes = explode('-', $texto);
        $ambiente = trim(end($partes));
        $ambiente = preg_replace('/\s+/', ' ', $ambiente);
        if (strlen($ambiente) > 100) {
            $ambiente = substr($ambiente, 0, 100);
        }
    }

    // Competencia: líneas intermedias
    $competencia = null;
    if (count($lineas) >= 2) {
        $lineaMedia = $lineas[1];
        if (strpos($lineaMedia, '-') !== false) {
            $partesComp = explode('-', $lineaMedia);
            $c = trim($partesComp[0]);
            $competencia = $c !== '' ? $c : null;
        } else {
            $competencia = $lineaMedia;
        }
        if ($competencia !== null && strlen($competencia) > 100) {
            $competencia = substr($competencia, 0, 100);
        }
    }

    $instructorId = null;
    if ($nombreInstructor !== null && isset($mapeoInstructores[$nombreInstructor])) {
        $instructorId = $mapeoInstructores[$nombreInstructor];
    }

    return [
        'instructor_nombre' => $nombreInstructor,
        'instructor_id'     => $instructorId,
        'ambiente'          => $ambiente,
        'competencia'       => $competencia
    ];
}

// 5. Gestión del archivo Excel (temporal o subido)
$tempFilePath = null;
$tempSessionKey = 'sena_horario_temp_' . ($_SESSION['admin']['id_usuario'] ?? '0');

if (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['archivo_excel']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        responderJson(['success' => false, 'error' => 'Formato no válido. Debe ser un archivo .xlsx']);
    }
    $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sena_' . uniqid() . '.xlsx';
    if (!move_uploaded_file($_FILES['archivo_excel']['tmp_name'], $tempPath)) {
        copy($_FILES['archivo_excel']['tmp_name'], $tempPath);
    }
    $_SESSION[$tempSessionKey] = $tempPath;
    $tempFilePath = $tempPath;
} elseif (!empty($_SESSION[$tempSessionKey]) && file_exists($_SESSION[$tempSessionKey])) {
    $tempFilePath = $_SESSION[$tempSessionKey];
} else {
    responderJson(['success' => false, 'error' => 'No se recibió un archivo Excel válido. Por favor seleccione un archivo .xlsx']);
}

// 6. Cargar archivo con PhpSpreadsheet
try {
    $reader = IOFactory::createReaderForFile($tempFilePath);
    $reader->setReadDataOnly(false);
    $spreadsheet = $reader->load($tempFilePath);
} catch (\Exception $e) {
    responderJson(['success' => false, 'error' => 'Error al leer el archivo Excel: ' . $e->getMessage()]);
}

// Hoja1 o activa
$sheet = $spreadsheet->getSheetByName('Hoja1');
if (!$sheet) {
    $sheet = $spreadsheet->getActiveSheet();
}

// 7. Extraer y validar Ficha
$datosFicha = extraerFichaYPrograma($sheet);
$numeroFicha = $datosFicha['numero_ficha'];
$nombrePrograma = $datosFicha['programa_sugerido'];

if (!$numeroFicha) {
    responderJson(['success' => false, 'error' => 'No se encontró el número de Ficha en el archivo (se buscó en las celdas E2, D2 y cabeceras).']);
}

// Consultar si la ficha existe en la base de datos
$stmtFicha = $conexion->prepare("SELECT id_ficha, nombre_programa FROM ficha WHERE id_ficha = ?");
$stmtFicha->execute([$numeroFicha]);
$fichaEncontrada = $stmtFicha->fetch(PDO::FETCH_ASSOC);

if (!$fichaEncontrada) {
    // Auto-detectar jornada sugerida a partir de la primera hora de inicio válida
    $jornadaSugerida = detectarJornadaSugerida($sheet);

    // ELIMINAR ERROR DURO: Devolver requerimiento de ficha para creación dinámica
    echo json_encode([
        'requiere_ficha'    => true,
        'numero_ficha'      => $numeroFicha,
        'programa_sugerido' => $nombrePrograma,
        'jornada_sugerida'  => $jornadaSugerida
    ]);
    exit;
}

$idFicha = (int)$fichaEncontrada['id_ficha'];

// 8. Fase 1: Extracción de Instructores Únicos
$highestRow = $sheet->getHighestDataRow();
$highestColumn = $sheet->getHighestColumn();
$highestColIndex = Coordinate::columnIndexFromString($highestColumn);

$colsDiasIndices = [4, 6, 8, 10, 12, 14]; // D, F, H, J, L, N
$nombresInstructoresEncontrados = [];

for ($r = 1; $r <= $highestRow; $r++) {
    foreach ($colsDiasIndices as $colIdx) {
        if ($colIdx > $highestColIndex) continue;
        $coord = Coordinate::stringFromColumnIndex($colIdx) . $r;
        $val = (string)$sheet->getCell($coord)->getValue();
        if ($val === '') continue;

        // Si contiene \n y guiones '-', la primera línea es el nombre del instructor
        if ((strpos($val, "\n") !== false || strpos($val, "\r") !== false) && strpos($val, '-') !== false) {
            $lineas = preg_split('/\r\n|\r|\n/', $val);
            $lineas = array_values(array_filter(array_map('trim', $lineas), fn($l) => $l !== ''));
            if (!empty($lineas)) {
                $nombreInst = trim($lineas[0]);
                if (strlen($nombreInst) >= 3 && !preg_match('/^[0-9\-\s\.\:]+$/', $nombreInst)) {
                    $nombresInstructoresEncontrados[$nombreInst] = true;
                }
            }
        }
    }
}

// Consultar instructores existentes en BD
$stmtInst = $conexion->query("SELECT id_usuario, identificacion, nombre, apellido, correo_electronico FROM usuario WHERE fk_rol = 2");
$instructoresBD = $stmtInst->fetchAll(PDO::FETCH_ASSOC);

$mapeoInstructores = [];
$instructoresFaltantes = [];

// Si se enviaron datos de nuevos instructores desde el modal (Paso 2)
$nuevosInstructoresInput = $_POST['instructores'] ?? ($_POST['nuevos_instructores'] ?? null);
if ($nuevosInstructoresInput) {
    if (is_string($nuevosInstructoresInput)) {
        $nuevosInstructoresArr = json_decode($nuevosInstructoresInput, true) ?: [];
    } else {
        $nuevosInstructoresArr = (array)$nuevosInstructoresInput;
    }

    foreach ($nuevosInstructoresArr as $item) {
        $nomExcel = trim($item['nombre_excel'] ?? '');
        $ident = trim($item['identificacion'] ?? '');
        $correo = trim($item['correo_electronico'] ?? '');
        $tel = trim($item['telefono'] ?? '');

        if ($nomExcel !== '' && $ident !== '') {
            $stmtCheck = $conexion->prepare("SELECT id_usuario FROM usuario WHERE identificacion = ?");
            $stmtCheck->execute([$ident]);
            $usrExistente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($usrExistente) {
                $nuevoId = (int)$usrExistente['id_usuario'];
                $conexion->prepare("UPDATE usuario SET fk_rol = 2 WHERE id_usuario = ?")->execute([$nuevoId]);
            } else {
                $partes = preg_split('/\s+/', $nomExcel);
                if (count($partes) >= 3) {
                    $nom = $partes[0] . ' ' . $partes[1];
                    $ape = implode(' ', array_slice($partes, 2));
                } elseif (count($partes) === 2) {
                    $nom = $partes[0];
                    $ape = $partes[1];
                } else {
                    $nom = $nomExcel;
                    $ape = 'Instructor';
                }

                $username = 'inst_' . $ident;
                $claveTemp = password_hash('Sena' . substr($ident, -4) . '*', PASSWORD_DEFAULT);

                $stmtNew = $conexion->prepare("
                    INSERT INTO usuario (
                        nombre_usuario, contrasena, nombre, apellido, identificacion,
                        correo_electronico, telefono, fk_rol, estado_invitacion_clave
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 2, 'PENDIENTE')
                ");
                $stmtNew->execute([
                    $username, $claveTemp, $nom, $ape, $ident,
                    $correo !== '' ? $correo : null,
                    $tel !== '' ? $tel : null
                ]);
                $nuevoId = (int)$conexion->lastInsertId();
            }

            $mapeoInstructores[$nomExcel] = $nuevoId;
        }
    }

    // Actualizar instructores en memoria
    $stmtInst = $conexion->query("SELECT id_usuario, identificacion, nombre, apellido, correo_electronico FROM usuario WHERE fk_rol = 2");
    $instructoresBD = $stmtInst->fetchAll(PDO::FETCH_ASSOC);
}

// Comparar instructores de Excel con la BD
foreach (array_keys($nombresInstructoresEncontrados) as $nombreExcel) {
    if (isset($mapeoInstructores[$nombreExcel])) {
        continue;
    }
    $idEncontrado = buscarInstructorEnBD($nombreExcel, $instructoresBD);
    if ($idEncontrado !== null) {
        $mapeoInstructores[$nombreExcel] = $idEncontrado;
    } else {
        $instructoresFaltantes[] = $nombreExcel;
    }
}

// Si hay instructores faltantes, devolver JSON para abrir modal
if (!empty($instructoresFaltantes)) {
    responderJson([
        'success' => false,
        'requiere_instructores' => true,
        'mensaje' => 'Se detectaron instructores en la matriz que no están registrados en el sistema.',
        'ficha' => [
            'id_ficha' => $idFicha,
            'nombre_programa' => $fichaEncontrada['nombre_programa'] ?? ("Ficha #{$idFicha}")
        ],
        'instructores_faltantes' => array_values($instructoresFaltantes)
    ]);
}

// 9. Fase 2: Agrupación y Mapeo Matricial (Guardado en BD)
$columnasDias = [
    'D' => 'Lunes',
    'F' => 'Martes',
    'H' => 'Miércoles',
    'J' => 'Jueves',
    'L' => 'Viernes',
    'N' => 'Sábado'
];

$meses = [
    'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
    'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
    'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10,
    'NOVIEMBRE' => 11, 'DICIEMBRE' => 12
];

$bloquesActivos = [
    'D' => null, 'F' => null, 'H' => null,
    'J' => null, 'L' => null, 'N' => null
];

$fechasPorColumna = [
    'D' => null, 'F' => null, 'H' => null,
    'J' => null, 'L' => null, 'N' => null
];

$mesesSemanaPorColumna = [];
$mesActual = (int)date('n');
$anioActual = (int)date('Y');
$horariosAInsertar = [];

$cerrarBloque = function(string $col) use (&$bloquesActivos, &$horariosAInsertar) {
    if ($bloquesActivos[$col] !== null) {
        $horariosAInsertar[] = $bloquesActivos[$col];
        $bloquesActivos[$col] = null;
    }
};

$highestRow = $sheet->getHighestDataRow();

for ($row = 1; $row <= $highestRow; $row++) {
    // PASO A (Identificar Mes): Extrae texto de la fila. Si encuentra el nombre de un mes, guarda el $mesActual numérico. No hagas continue todavía.
    $mesesEnFila = [];
    for ($c = 1; $c <= min($highestColIndex, 20); $c++) {
        $colLetra = Coordinate::stringFromColumnIndex($c);
        $valCelda = trim((string)$sheet->getCell($colLetra . $row)->getValue());
        if ($valCelda === '') continue;

        foreach ($meses as $nomMes => $nMes) {
            if (preg_match('/\b' . $nomMes . '\b/i', $valCelda)) {
                $mesActual = $nMes;
                $mesesEnFila[$colLetra] = $nMes;
                if (preg_match('/\b(20\d\d)\b/', $valCelda, $mAnio)) {
                    $anioActual = (int)$mAnio[1];
                }
                break;
            }
        }
    }

    if (!empty($mesesEnFila)) {
        $mesCursor = $mesActual;
        foreach ($columnasDias as $colDia => $nomDia) {
            foreach ($mesesEnFila as $colM => $nMes) {
                if ($colDia >= $colM) {
                    $mesCursor = $nMes;
                }
            }
            $mesesSemanaPorColumna[$colDia] = $mesCursor;
        }
    }

    // PASO B (Identificar Días): Si la fila tiene números del 1 al 31 en las columnas de días (D, F, H, J, L, N), arma las fechas exactas uniendo el número con $mesActual y $anioActual.
    $tieneNumerosDia = false;
    foreach ($columnasDias as $colDia => $nomDia) {
        $valDia = trim((string)$sheet->getCell($colDia . $row)->getValue());
        if (is_numeric($valDia) && (int)$valDia >= 1 && (int)$valDia <= 31) {
            $tieneNumerosDia = true;
            break;
        }
    }

    if ($tieneNumerosDia) {
        // Al comenzar una nueva semana de días, cerramos cualquier bloque previo pendiente
        foreach ($columnasDias as $colDia => $nomDia) {
            $cerrarBloque($colDia);
        }

        $diaAnterior = 0;
        $mesCursor = $mesActual;
        $anioCursor = $anioActual;

        foreach ($columnasDias as $colDia => $nomDia) {
            $valDia = trim((string)$sheet->getCell($colDia . $row)->getValue());
            if (is_numeric($valDia) && (int)$valDia >= 1 && (int)$valDia <= 31) {
                $numDia = (int)$valDia;
                if (!empty($mesesSemanaPorColumna[$colDia])) {
                    $mesCol = $mesesSemanaPorColumna[$colDia];
                } else {
                    if ($diaAnterior > 20 && $numDia <= 10) {
                        $mesCursor++;
                        if ($mesCursor > 12) {
                            $mesCursor = 1;
                            $anioCursor++;
                        }
                    }
                    $mesCol = $mesCursor;
                }
                $fechasPorColumna[$colDia] = sprintf('%04d-%02d-%02d', $anioCursor, $mesCol, $numDia);
                $diaAnterior = $numDia;
            } else {
                $fechasPorColumna[$colDia] = null;
            }
        }
    }

    // PASO C (Validar Hora): AHORA SÍ extrae $horaInicio (Col B) y $horaFin (Col C). Si está vacía o inválida, ejecuta el continue;
    $cellB = $sheet->getCell("B{$row}");
    $cellC = $sheet->getCell("C{$row}");
    $horaInicio = normalizarHora($cellB->getValue(), $cellB);
    $horaFin = normalizarHora($cellC->getValue(), $cellC);

    $esFilaHora = ($horaInicio !== null && $horaFin !== null && $horaInicio < $horaFin);

    if (!$esFilaHora) {
        // Cerrar bloques abiertos al finalizar una franja horaria
        foreach ($columnasDias as $colDia => $nomDia) {
            $cerrarBloque($colDia);
        }
        continue;
    }

    // PASO D (Agrupar y Guardar): Extrae instructores y agrupa horas continuas con fecha exacta del Paso B
    foreach ($columnasDias as $colDia => $nombreDia) {
        $cellMatriz = $sheet->getCell("{$colDia}{$row}");
        $textoRaw = trim((string)$cellMatriz->getValue());

        if ($textoRaw === '') {
            $cerrarBloque($colDia);
            continue;
        }

        $textoNormalizado = trim(str_replace(["\r\n", "\r"], "\n", $textoRaw));
        $bloque = $bloquesActivos[$colDia];

        if ($bloque !== null) {
            // Si tiene exactamente el mismo texto, acumular hora_fin
            if ($bloque['texto_original'] === $textoNormalizado) {
                $bloquesActivos[$colDia]['hora_fin'] = $horaFin;
                continue;
            } else {
                $cerrarBloque($colDia);
            }
        }

        // Nuevo bloque si cumple con formato de clase SENA (\n y -)
        if (strpos($textoNormalizado, "\n") !== false && strpos($textoNormalizado, '-') !== false) {
            $parsed = parsearCeldaSena($textoNormalizado, $mapeoInstructores);
            $bloquesActivos[$colDia] = [
                'dia_semana'        => $nombreDia,
                'hora_inicio'       => $horaInicio,
                'hora_fin'          => $horaFin,
                'texto_original'    => $textoNormalizado,
                'instructor_nombre' => $parsed['instructor_nombre'],
                'instructor_id'     => $parsed['instructor_id'],
                'ambiente'          => $parsed['ambiente'],
                'competencia'       => $parsed['competencia'],
                'fecha_inicio'      => $fechasPorColumna[$colDia] ?? null,
                'fecha_fin'         => $fechasPorColumna[$colDia] ?? null,
            ];
        }
    }
}

// Cerrar los bloques restantes al terminar la matriz
foreach ($columnasDias as $colDia => $nombreDia) {
    $cerrarBloque($colDia);
}

// 10. Inserción atómica en base de datos
try {
    $conexion->beginTransaction();

    $insertados = 0;
    $duplicadosOmitidos = 0;

    $stmtCheckDup = $conexion->prepare("
        SELECT id_horario FROM horario 
        WHERE fk_ficha = ? 
          AND dia_semana = ? 
          AND entrada = ? 
          AND salida = ? 
          AND fecha_inicio <=> ?
        LIMIT 1
    ");

    $stmtInsert = $conexion->prepare("
        INSERT INTO horario (
            dia_semana, entrada, salida, competencia, tipo_bloque,
            ambiente, estado, observaciones, fecha_inicio, fecha_fin,
            fk_ficha, instructor_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($horariosAInsertar as $h) {
        $stmtCheckDup->execute([
            $idFicha,
            $h['dia_semana'],
            $h['hora_inicio'],
            $h['hora_fin'],
            $h['fecha_inicio']
        ]);
        if ($stmtCheckDup->fetch()) {
            $duplicadosOmitidos++;
            continue;
        }

        $stmtInsert->execute([
            $h['dia_semana'],
            $h['hora_inicio'],
            $h['hora_fin'],
            $h['competencia'],
            'completo',
            $h['ambiente'],
            'Activo',
            'Importado matriz SENA',
            $h['fecha_inicio'],
            $h['fecha_fin'],
            $idFicha,
            $h['instructor_id']
        ]);
        $insertados++;
    }

    $conexion->commit();

    // Limpiar archivo temporal
    if (file_exists($tempFilePath)) {
        @unlink($tempFilePath);
    }
    unset($_SESSION[$tempSessionKey]);

    responderJson([
        'success' => true,
        'requiere_instructores' => false,
        'mensaje' => "Se importaron exitosamente {$insertados} bloques de horario para la Ficha #{$idFicha}." .
                     ($duplicadosOmitidos > 0 ? " ({$duplicadosOmitidos} bloques ya existentes fueron omitidos)." : ""),
        'insertados' => $insertados,
        'duplicados' => $duplicadosOmitidos,
        'ficha' => $idFicha,
        'bloques' => array_map(function($b) {
            return [
                'dia' => $b['dia_semana'],
                'inicio' => substr($b['hora_inicio'], 0, 5),
                'fin' => substr($b['hora_fin'], 0, 5),
                'instructor' => $b['instructor_nombre'] ?? 'Sin asignar',
                'ambiente' => $b['ambiente'] ?? 'No especificado'
            ];
        }, $horariosAInsertar)
    ]);

} catch (\Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    error_log("Error durante el guardado de horarios en importar_horarios_excel: " . $e->getMessage());
    responderJson([
        'success' => false,
        'error' => 'No fue posible completar el registro de horarios. Inténtalo nuevamente.'
    ]);
}

}