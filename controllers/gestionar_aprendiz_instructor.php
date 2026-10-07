<?php
/**
 * Controlador para la gestión de aprendices por el instructor:
 * - Registro manual de aprendiz
 * - Importación masiva por archivo Excel (.xlsx) de Sofia Plus:
 *     1. Validación y previsualización (sin insertar en BD)
 *     2. Confirmación de importación (inserción real con control de duplicados)
 *     3. Cancelación de la operación
 *
 * Arquitectura MVC estricta con PDO, validación de permisos de instructor (rol 2)
 * y protección CSRF.
 */

session_start();
require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(2);

require_once __DIR__ . '/../includes/constantes.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/AprendizModel.php';
require_once __DIR__ . '/../includes/AprendizExcelReader.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php');
    exit;
}

// Validar token CSRF
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode('Token de seguridad inválido o sesión expirada.'));
    exit;
}

$idInstructor = (int)$_SESSION['id_usuario'];
$accion = $_POST['accion'] ?? '';

// ============================================================
// 1. REGISTRO MANUAL
// ============================================================
if ($accion === 'registrar_manual') {
    $idFicha   = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
    $tipoDoc   = trim($_POST['tipo_documento'] ?? '');
    $numDoc    = trim($_POST['numero_documento'] ?? '');
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');

    if (!$idFicha) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?modo=manual&error=' . urlencode('Debe seleccionar una ficha válida.'));
        exit;
    }

    $datos = [
        'tipo_documento'   => $tipoDoc,
        'numero_documento' => $numDoc,
        'nombre'           => $nombre,
        'apellidos'        => $apellidos,
        'correo'           => $correo
    ];

    $resultado = AprendizModel::crearAprendiz($datos, $idFicha, $idInstructor);

    if ($resultado['success']) {
        $msg = 'Aprendiz registrado exitosamente en la Ficha #' . $idFicha . '. Puede consultarlo en el menú "Aprendices de mis fichas".';
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?mensaje=' . urlencode($msg));
        exit;
    } else {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?modo=manual&error=' . urlencode($resultado['error']));
        exit;
    }
}

// ============================================================
// 2. FASE 1: VALIDACIÓN Y PREVISUALIZACIÓN DE EXCEL
// ============================================================
if ($accion === 'validar_previsualizar_excel' || $accion === 'importar_excel') {
    $idFicha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);

    if (!$idFicha) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode('Debe seleccionar la ficha de destino para los aprendices.'));
        exit;
    }

    // Verificar que la ficha pertenezca al instructor
    $conexion = (new Conexion())->conectar();
    $stmtFicha = $conexion->prepare('SELECT id_ficha, nombre_programa FROM ficha WHERE id_ficha = ? AND fk_usuario = ?');
    $stmtFicha->execute([$idFicha, $idInstructor]);
    $fichaBD = $stmtFicha->fetch(PDO::FETCH_ASSOC);

    if (!$fichaBD) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode('La ficha seleccionada no existe o no está asignada a su usuario.'));
        exit;
    }

    if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] === UPLOAD_ERR_NO_FILE) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode('Por favor seleccione un archivo Excel (.xlsx).'));
        exit;
    }

    // Leer y validar archivo con AprendizExcelReader (valida .xlsx y 7 encabezados exactos)
    $lectura = AprendizExcelReader::leerArchivo($_FILES['archivo_excel']);

    if (!$lectura['success']) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode($lectura['message']));
        exit;
    }

    $filas = $lectura['filas'];
    $filasPreview = [];
    $nuevosCount = 0;
    $duplicadosCount = 0;
    $documentosEnArchivo = [];

    // Validar duplicados para mostrar en la previsualización
    foreach ($filas as $fila) {
        $numDoc = trim((string)($fila['numero_documento'] ?? ''));
        $esDuplicado = false;
        $observacion = 'Listo para importación.';

        if ($numDoc !== '') {
            if (isset($documentosEnArchivo[$numDoc])) {
                $esDuplicado = true;
                $observacion = 'Documento repetido en el mismo archivo (será omitido).';
            } else {
                $documentosEnArchivo[$numDoc] = true;
                if (AprendizModel::existeDocumento($numDoc)) {
                    $esDuplicado = true;
                    $observacion = 'El aprendiz ya existe en el sistema (será omitido).';
                }
            }
        }

        if ($esDuplicado) {
            $duplicadosCount++;
        } else {
            $nuevosCount++;
        }

        $filasPreview[] = array_merge($fila, [
            'es_duplicado' => $esDuplicado,
            'observacion'  => $observacion
        ]);
    }

    // Guardar los datos en sesión para la fase de previsualización
    $_SESSION['import_preview_instructor'] = [
        'id_ficha'        => $idFicha,
        'nombre_programa' => $fichaBD['nombre_programa'],
        'filas'           => $filasPreview,
        'total'           => count($filasPreview),
        'nuevos'          => $nuevosCount,
        'duplicados'      => $duplicadosCount,
        'timestamp'       => time()
    ];

    $mensaje = 'Archivo válido. Se encontraron ' . count($filasPreview) . ' aprendices para revisar.';
    header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?mensaje=' . urlencode($mensaje));
    exit;
}

// ============================================================
// 3. FASE 2: CONFIRMAR IMPORTACIÓN (INSERCIÓN REAL EN BD)
// ============================================================
if ($accion === 'confirmar_importar_excel') {
    $previewData = $_SESSION['import_preview_instructor'] ?? null;

    if (!$previewData || empty($previewData['filas'])) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode('No se encontró una previsualización activa o la sesión expiró. Cargue el archivo nuevamente.'));
        exit;
    }

    $idFicha = (int)$previewData['id_ficha'];
    $filas   = $previewData['filas'];

    // Ejecutar importación en base de datos con control de duplicados y transacción PDO
    $resultado = AprendizModel::importarAprendicesLote($filas, $idFicha, $idInstructor);

    // Limpiar datos de previsualización de la sesión
    unset($_SESSION['import_preview_instructor']);

    if (!$resultado['success']) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?error=' . urlencode($resultado['error'] ?? 'Ocurrió un error al procesar la importación.'));
        exit;
    }

    $registrados = (int)$resultado['registrados'];

    if ($registrados > 0 && !empty($resultado['aprendices_creados'])) {
        $_SESSION['rfid_importacion_pendiente'] = [
            'id_importacion' => bin2hex(random_bytes(16)),
            'id_ficha'       => $idFicha,
            'id_instructor'  => $idInstructor,
            'aprendices'     => $resultado['aprendices_creados'],
            'created_at'     => time(),
            'expires_at'     => time() + 1800 // 30 minutos
        ];
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/importar_rfid.php');
        exit;
    } else {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?mensaje=' . urlencode('La importación terminó, pero no hubo aprendices nuevos que requieran registro RFID.'));
        exit;
    }
}

// ============================================================
// 4. CANCELAR PREVISUALIZACIÓN DE EXCEL
// ============================================================
if ($accion === 'cancelar_importacion_excel') {
    unset($_SESSION['import_preview_instructor']);
    header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php?mensaje=' . urlencode('Operación de importación cancelada.'));
    exit;
}

// ============================================================
// 5. EDITAR APRENDIZ (INSTRUCTOR)
// ============================================================
if ($accion === 'editar_aprendiz') {
    $idAprendiz = filter_input(INPUT_POST, 'id_aprendiz', FILTER_VALIDATE_INT);
    if (!$idAprendiz) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?error=' . urlencode('ID de aprendiz inválido.'));
        exit;
    }

    $datos = [
        'tipo_documento'   => trim($_POST['tipo_documento'] ?? ''),
        'numero_documento' => trim($_POST['numero_documento'] ?? ''),
        'nombre'           => trim($_POST['nombre'] ?? ''),
        'apellidos'        => trim($_POST['apellidos'] ?? ''),
        'telefono'         => trim($_POST['telefono'] ?? ''),
        'correo'           => trim($_POST['correo'] ?? '')
    ];

    $resultado = AprendizModel::editarAprendizDeInstructor($idAprendiz, $idInstructor, $datos);

    if ($resultado['success']) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?mensaje=' . urlencode('Aprendiz actualizado correctamente.'));
        exit;
    } else {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?error=' . urlencode($resultado['error']));
        exit;
    }
}

// ============================================================
// 6. ELIMINAR APRENDIZ (INSTRUCTOR)
// ============================================================
if ($accion === 'eliminar_aprendiz') {
    $idAprendiz = filter_input(INPUT_POST, 'id_aprendiz', FILTER_VALIDATE_INT);
    if (!$idAprendiz) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?error=' . urlencode('ID de aprendiz inválido.'));
        exit;
    }

    $resultado = AprendizModel::eliminarAprendizDeInstructor($idAprendiz, $idInstructor);

    if ($resultado['success']) {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?mensaje=' . urlencode('Aprendiz eliminado correctamente.'));
        exit;
    } else {
        header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/index.php?error=' . urlencode($resultado['error']));
        exit;
    }
}

// Acción no reconocida
header('Location: ' . RUTA_BASE . 'views/instructor/aprendices/create.php');
exit;
