<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO: AprendizAdminController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida la administración de aprendices en el panel de administrador:
 *   - accion=asignar_correo : Asignar o actualizar correo de contacto para credenciales
 *   - accion=importar_excel  : Importación masiva de aprendices desde archivo Excel
 * =============================================================================
 */

// Buffer de salida para evitar que advertencias o espacios corrompan respuestas JSON
ob_start();

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';

$vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

// Detectar si la petición es AJAX
$esAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Solo permitir peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($esAjax) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php');
    exit;
}

// Validar token CSRF (POST o Header)
$tokenRecibido = $_POST['token_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $tokenRecibido)) {
    if ($esAjax) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido o sesión caducada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Token de seguridad inválido.'));
    exit;
}

// Determinar la acción solicitada
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática por presencia de campos
if ($accion === '') {
    if (isset($_FILES['archivo_excel']) || isset($_FILES['archivo']) || isset($_POST['fk_ficha'])) {
        $accion = 'importar_excel';
    } elseif (isset($_POST['id_usuario']) || isset($_POST['correo'])) {
        $accion = 'asignar_correo';
    }
}

switch ($accion) {
    case 'asignar_correo':
        procesarAsignarCorreo($esAjax);
        break;

    case 'importar_excel':
        procesarImportarExcel();
        break;

    default:
        if ($esAjax) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Acción no válida o no especificada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php');
        exit;
}

/**
 * =============================================================================
 * ACCIÓN 1: ASIGNAR / ACTUALIZAR CORREO ELECTRÓNICO DE UN APRENDIZ
 * =============================================================================
 */
function procesarAsignarCorreo(bool $esAjax): void
{
    $idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
    $correo    = trim($_POST['correo'] ?? '');

    if (!$idUsuario || $idUsuario <= 0) {
        if ($esAjax) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Identificador de usuario no válido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Identificador de usuario no válido.'));
        exit;
    }

    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        if ($esAjax) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'El correo electrónico ingresado no tiene un formato válido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('El correo electrónico no es válido.'));
        exit;
    }

    $conexion = (new Conexion())->conectar();

    try {
        // 1. Actualizar correo en la tabla usuario asegurando que sea rol aprendiz (rol 3)
        $stmtUser = $conexion->prepare('
            UPDATE usuario 
            SET correo_electronico = :correo 
            WHERE id_usuario = :id_usuario AND fk_rol = 3
        ');
        $stmtUser->execute([
            ':correo'     => $correo,
            ':id_usuario' => $idUsuario,
        ]);

        // 2. Restablecer correo_enviado = 0 en aprendiz para que quede listo en el lote de pendientes
        try {
            $conexion->exec("ALTER TABLE aprendiz ADD COLUMN correo_enviado TINYINT(1) NOT NULL DEFAULT 0");
        } catch (\Throwable $e) {}

        $stmtAprendiz = $conexion->prepare('
            UPDATE aprendiz 
            SET correo_enviado = 0 
            WHERE fk_usuario = :id_usuario
        ');
        $stmtAprendiz->execute([':id_usuario' => $idUsuario]);

        if ($esAjax) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'success',
                'message' => 'Correo electrónico asignado exitosamente.',
                'correo'  => $correo
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?mensaje=' . urlencode('Correo electrónico asignado exitosamente. El aprendiz ahora está pendiente para envío de credenciales.'));
        exit;

    } catch (PDOException $e) {
        if ($esAjax) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error de base de datos al guardar el correo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=' . urlencode('Error en la base de datos al actualizar el correo.'));
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 2: IMPORTACIÓN MASIVA DE APRENDICES DESDE ARCHIVO EXCEL
 * =============================================================================
 */
function procesarImportarExcel(): void
{
    header('Content-Type: application/json; charset=utf-8');

    // Validar Ficha de destino
    $idFicha = filter_input(INPUT_POST, 'fk_ficha', FILTER_VALIDATE_INT);
    if (!$idFicha || $idFicha <= 0) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Debe seleccionar una ficha de formación válida.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conexion = (new Conexion())->conectar();

    // Verificar existencia de la ficha en base de datos
    $stmtFicha = $conexion->prepare('SELECT id_ficha, nombre_programa FROM ficha WHERE id_ficha = ?');
    $stmtFicha->execute([$idFicha]);
    if (!$stmtFicha->fetch()) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'La ficha seleccionada no existe en el sistema.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validar archivo Excel subido
    $file = $_FILES['archivo_excel'] ?? $_FILES['archivo'] ?? null;
    if (!$file || empty($file['tmp_name'])) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'No se recibió ningún archivo Excel para procesar.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Error durante la subida del archivo (Código: ' . $file['error'] . ').'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'xls'], true)) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Formato no permitido. El archivo debe ser una hoja de cálculo (.xlsx o .xls).'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Cargar y leer el archivo con PhpSpreadsheet
    if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Librería PhpSpreadsheet no encontrada. Asegúrese de que las dependencias estén instaladas.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $reader = IOFactory::createReaderForFile($file['tmp_name']);
        $reader->setReadDataOnly(false); // Permite acceso completo a fórmulas y valores evaluados
        $spreadsheet = $reader->load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = (int)$sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIdx = columnaLetraAIndice($highestCol);
    } catch (\Throwable $e) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'No fue posible leer el archivo Excel: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($highestRow < 2) {
        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'El archivo Excel no contiene registros de aprendices a partir de la fila 2.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Mapeo inteligente de encabezados en fila 1 (Ignorar explícitamente "Estado")
    $colMap = [
        'tipo_doc' => null,
        'num_doc'  => null,
        'nombre'   => null,
        'apellido' => null,
        'celular'  => null,
        'correo'   => null,
        'estado'   => null
    ];

    for ($c = 1; $c <= $highestColIdx; $c++) {
        $letraCol = columnaIndiceALetra($c);
        $cellHead = $sheet->getCell($letraCol . '1');
        $valHead  = mb_strtolower(trim(obtenerValorCalculadoCelda($cellHead)), 'UTF-8');

        // Normalizar tildes
        $valHeadNorm = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $valHead
        );

        if (preg_match('/tipo.*doc/i', $valHeadNorm)) {
            $colMap['tipo_doc'] = $letraCol;
        } elseif (preg_match('/(num.*doc|documento|identificaci|cedula|tarjeta)/i', $valHeadNorm)) {
            $colMap['num_doc'] = $letraCol;
        } elseif (preg_match('/apellido/i', $valHeadNorm)) {
            $colMap['apellido'] = $letraCol;
        } elseif (preg_match('/nombre/i', $valHeadNorm)) {
            $colMap['nombre'] = $letraCol;
        } elseif (preg_match('/(celular|telefono|movil)/i', $valHeadNorm)) {
            $colMap['celular'] = $letraCol;
        } elseif (preg_match('/(correo|email|e-mail)/i', $valHeadNorm)) {
            $colMap['correo'] = $letraCol;
        } elseif (preg_match('/estado/i', $valHeadNorm)) {
            $colMap['estado'] = $letraCol;
        }
    }

    // Si no se mapearon por nombres de encabezado, aplicar el orden estándar de plantilla SENA (A-G)
    if (empty($colMap['num_doc'])) {
        $colMap['tipo_doc'] = 'A';
        $colMap['num_doc']  = 'B';
        $colMap['nombre']   = 'C';
        $colMap['apellido'] = 'D';
        $colMap['celular']  = 'E';
        $colMap['correo']   = 'F';
        $colMap['estado']   = 'G';
    }

    // Preparación de consultas preparadas
    try {
        $conexion->exec("ALTER TABLE aprendiz ADD COLUMN correo_enviado TINYINT(1) NOT NULL DEFAULT 0");
    } catch (\Throwable $e) {}

    try {
        $conexion->exec("ALTER TABLE aprendiz MODIFY COLUMN codigo_rfid VARCHAR(50) NULL DEFAULT NULL");
    } catch (\Throwable $e) {}

    $stmtCheckDoc = $conexion->prepare('SELECT id_usuario, nombre, apellido FROM usuario WHERE identificacion = ? LIMIT 1');
    $stmtCheckUser = $conexion->prepare('SELECT COUNT(*) FROM usuario WHERE nombre_usuario = ?');

    // Inserción en usuario base con rol 3 (Aprendiz)
    $stmtInsertUser = $conexion->prepare('
        INSERT INTO usuario (
            nombre_usuario, contrasena, nombre, apellido, 
            tipo_documento, identificacion, telefono, correo_electronico, 
            fk_rol, estado_invitacion_clave
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, "PENDIENTE")
    ');

    // Inserción en aprendiz vinculando fk_ficha y fk_usuario con codigo_rfid NULL
    $stmtInsertAprendiz = $conexion->prepare('
        INSERT INTO aprendiz (codigo_rfid, fk_ficha, fk_usuario) VALUES (NULL, ?, ?)
    ');

    $contador = 0;
    $duplicados = 0;
    $nombres_omitidos = [];
    $documentosVistosEnLote = [];

    // Transacción segura con PDO envolviendo la inserción masiva
    $conexion->beginTransaction();

    try {
        for ($row = 2; $row <= $highestRow; $row++) {
            $tipoDocCell = !empty($colMap['tipo_doc']) ? $sheet->getCell($colMap['tipo_doc'] . $row) : null;
            $numDocCell  = !empty($colMap['num_doc']) ? $sheet->getCell($colMap['num_doc'] . $row) : null;
            $nombreCell  = !empty($colMap['nombre']) ? $sheet->getCell($colMap['nombre'] . $row) : null;
            $apeCell     = !empty($colMap['apellido']) ? $sheet->getCell($colMap['apellido'] . $row) : null;
            $celCell     = !empty($colMap['celular']) ? $sheet->getCell($colMap['celular'] . $row) : null;
            $correoCell  = !empty($colMap['correo']) ? $sheet->getCell($colMap['correo'] . $row) : null;

            $tipoDocRaw = obtenerValorCalculadoCelda($tipoDocCell);
            $numDocRaw  = obtenerValorCalculadoCelda($numDocCell);
            $nombreRaw  = obtenerValorCalculadoCelda($nombreCell);
            $apeRaw     = obtenerValorCalculadoCelda($apeCell);
            $valE       = obtenerValorCalculadoCelda($celCell);
            $valF       = obtenerValorCalculadoCelda($correoCell);

            // Sanitización estricta de documento
            $numDoc = sanitizarDocumento($numDocRaw);
            if ($numDoc === '') {
                continue; // Omitir filas sin documento
            }

            $nombre   = mb_substr($nombreRaw, 0, 45, 'UTF-8');
            $apellido = mb_substr($apeRaw, 0, 45, 'UTF-8');
            $tipoDoc  = mb_substr($tipoDocRaw, 0, 10, 'UTF-8');

            if ($nombre === '' && $apellido === '') {
                continue;
            }

            // Discriminar correo vs teléfono entre Col E y Col F
            $correo = null;
            $telefono = null;
            if (filter_var($valE, FILTER_VALIDATE_EMAIL) || strpos($valE, '@') !== false) {
                $correo = mb_substr($valE, 0, 100, 'UTF-8');
                $telefono = sanitizarCelular($valF);
            } elseif (filter_var($valF, FILTER_VALIDATE_EMAIL) || strpos($valF, '@') !== false) {
                $correo = mb_substr($valF, 0, 100, 'UTF-8');
                $telefono = sanitizarCelular($valE);
            } else {
                $telefono = sanitizarCelular($valE !== '' ? $valE : $valF);
            }

            // Control de duplicados en el lote actual del archivo
            if (isset($documentosVistosEnLote[$numDoc])) {
                $duplicados++;
                $nombres_omitidos[] = $numDoc . ' - ' . trim($nombre . ' ' . $apellido);
                continue;
            }
            $documentosVistosEnLote[$numDoc] = true;

            // Control de duplicados en la base de datos
            $stmtCheckDoc->execute([$numDoc]);
            if ($usrExistente = $stmtCheckDoc->fetch()) {
                $duplicados++;
                $nombreMostrar = trim($nombre . ' ' . $apellido);
                if ($nombreMostrar === '' && !empty($usrExistente['nombre'])) {
                    $nombreMostrar = trim($usrExistente['nombre'] . ' ' . ($usrExistente['apellido'] ?? ''));
                }
                $nombres_omitidos[] = $numDoc . ' - ' . $nombreMostrar;
                continue;
            }

            // Generar nombre de usuario único en tabla usuario
            $baseUsuario = 'ap_' . $numDoc;
            if (mb_strlen($baseUsuario) > 40) {
                $baseUsuario = mb_substr($baseUsuario, 0, 40);
            }
            $nombreUsuario = $baseUsuario;
            $sufijo = 1;
            while (true) {
                $stmtCheckUser->execute([$nombreUsuario]);
                if ((int)$stmtCheckUser->fetchColumn() === 0) {
                    break;
                }
                $nombreUsuario = mb_substr($baseUsuario, 0, 35) . '_' . $sufijo;
                $sufijo++;
            }

            // Contraseña por defecto: documento hasheado con BCRYPT
            $claveHash = password_hash($numDoc, PASSWORD_DEFAULT);

            // 1. Insertar en tabla usuario (Rol 3 = Aprendiz)
            $stmtInsertUser->execute([
                $nombreUsuario,
                $claveHash,
                $nombre !== '' ? $nombre : 'Aprendiz',
                $apellido !== '' ? $apellido : 'SENA',
                $tipoDoc !== '' ? $tipoDoc : 'CC',
                $numDoc,
                $telefono,
                $correo
            ]);

            $idUsuario = (int)$conexion->lastInsertId();
            if ($idUsuario <= 0) {
                throw new \RuntimeException("No fue posible generar el registro de usuario para el documento {$numDoc}.");
            }

            // 2. Insertar en tabla aprendiz con llaves foráneas fk_ficha y fk_usuario (codigo_rfid explícitamente NULL)
            $stmtInsertAprendiz->execute([$idFicha, $idUsuario]);

            $contador++;
        }

        // Confirmar todas las inserciones del lote
        $conexion->commit();

    } catch (\Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        ob_clean();
        echo json_encode([
            'status'  => 'error',
            'message' => 'Error durante la inserción en base de datos: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Devolver respuesta JSON limpia para SweetAlert2
    ob_clean();
    echo json_encode([
        'status'           => 'success',
        'importados'       => $contador,
        'duplicados'       => $duplicados,
        'detalle_omitidos' => $nombres_omitidos
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Obtiene el valor evaluado de una celda evitando fórmulas literales
 */
function obtenerValorCalculadoCelda($cell): string
{
    if (!$cell) {
        return '';
    }

    try {
        $val = $cell->getCalculatedValue();
    } catch (\Throwable $e) {
        try {
            $val = $cell->getFormattedValue();
        } catch (\Throwable $e2) {
            $val = $cell->getValue();
        }
    }

    if ($val === null) {
        return '';
    }

    if (is_object($val)) {
        if (method_exists($val, 'getPlainText')) {
            $val = $val->getPlainText();
        } else {
            $val = (string)$val;
        }
    }

    return trim((string)$val);
}

/**
 * Sanitiza números de documento evitando notaciones científicas y decimales
 */
function sanitizarDocumento($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    if (is_float($valor)) {
        $valor = sprintf('%.0f', $valor);
    }

    $str = trim((string)$valor);
    if ($str === '') {
        return '';
    }

    // Convertir notación científica a número plano
    if (preg_match('/^[+-]?[0-9]+(\.[0-9]+)?[eE][+-]?[0-9]+$/', $str)) {
        $str = sprintf('%.0f', (float)$str);
    }

    // Eliminar terminación decimal .0 o .00
    if (preg_match('/^([0-9A-Za-z]+)\.0+$/', $str, $m)) {
        $str = $m[1];
    }

    // Remover puntos de miles, comas, espacios y guiones
    $str = preg_replace('/[.\s,\-_]/', '', $str);

    // Preservar solo caracteres alfanuméricos válidos
    return preg_replace('/[^0-9A-Za-z]/', '', $str);
}

/**
 * Sanitiza números de celular/teléfono evitando notaciones científicas y flotantes
 */
function sanitizarCelular($valor): ?string
{
    if ($valor === null || $valor === '') {
        return null;
    }

    if (is_float($valor)) {
        $valor = sprintf('%.0f', $valor);
    }

    $str = trim((string)$valor);
    if ($str === '') {
        return null;
    }

    // Convertir notación científica
    if (preg_match('/^[+-]?[0-9]+(\.[0-9]+)?[eE][+-]?[0-9]+$/', $str)) {
        $str = sprintf('%.0f', (float)$str);
    }

    // Eliminar decimales .0
    if (preg_match('/^([0-9+]+)\.0+$/', $str, $m)) {
        $str = $m[1];
    }

    $esInternacional = (strpos($str, '+') === 0);
    $soloDigitos = preg_replace('/[^0-9]/', '', $str);

    if ($soloDigitos === '') {
        return null;
    }

    $resultado = ($esInternacional ? '+' : '') . $soloDigitos;
    return mb_substr($resultado, 0, 45, 'UTF-8');
}

/**
 * Convierte una letra de columna de Excel (A, B, ..., Z, AA) a su índice numérico (1, 2, ..., 26, 27)
 */
function columnaLetraAIndice(string $columna): int
{
    if (class_exists('PhpOffice\PhpSpreadsheet\Cell\Coordinate')) {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columna);
    }

    $columna = strtoupper(trim($columna));
    $longitud = strlen($columna);
    $indice = 0;
    for ($i = 0; $i < $longitud; $i++) {
        $indice = $indice * 26 + (ord($columna[$i]) - 64);
    }
    return $indice;
}

/**
 * Convierte un índice numérico de columna (1, 2, ..., 26, 27) a su letra correspondiente (A, B, ..., Z, AA)
 */
function columnaIndiceALetra(int $indice): string
{
    if (class_exists('PhpOffice\PhpSpreadsheet\Cell\Coordinate')) {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice);
    }

    $letra = '';
    while ($indice > 0) {
        $mod = ($indice - 1) % 26;
        $letra = chr(65 + $mod) . $letra;
        $indice = (int)(($indice - $mod) / 26);
    }
    return $letra;
}
