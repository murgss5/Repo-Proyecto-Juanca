<?php
/**
 * =============================================================================
 * CONTROLADOR: IMPORTACIÓN MASIVA DE APRENDICES DESDE ARCHIVO EXCEL
 * Sistema de Control de Ingreso de Aprendices - SENA
 * =============================================================================
 * Procesa la carga de aprendices asociados a una ficha técnica específica.
 * Implementa lectura calculada de celdas (getCalculatedValue), sanitización
 * de campos numéricos (evitando notación científica o floats), exclusión
 * estricta de la columna "Estado" y transaccionalidad atómica completa con PDO.
 * =============================================================================
 */

// 1. Desactivar salida previa para garantizar respuesta JSON limpia
ob_start();

require_once __DIR__ . '/../../includes/autenticacion.php';
exigirRol(1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../includes/constantes.php';

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Obtiene el valor evaluado de una celda evitando fórmulas literales (REGLA 2)
 *
 * @param \PhpOffice\PhpSpreadsheet\Cell\Cell|null $cell
 * @return string
 */
function obtenerValorCalculadoCelda($cell): string
{
    if (!$cell) {
        return '';
    }

    try {
        // getCalculatedValue resuelve fórmulas tipo =VLOOKUP(...) o =CONCATENAR(...)
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
 * Sanitiza números de documento evitando notaciones científicas y decimales (REGLA 3)
 *
 * @param mixed $valor
 * @return string
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

    // Convertir notación científica (ej. 1.00456789E+9 o 1.05e10) a número plano
    if (preg_match('/^[+-]?[0-9]+(\.[0-9]+)?[eE][+-]?[0-9]+$/', $str)) {
        $str = sprintf('%.0f', (float)$str);
    }

    // Eliminar terminación decimal .0 o .00 producida por formatos numéricos
    if (preg_match('/^([0-9A-Za-z]+)\.0+$/', $str, $m)) {
        $str = $m[1];
    }

    // Remover puntos de miles, comas, espacios y guiones
    $str = preg_replace('/[.\s,\-_]/', '', $str);

    // Preservar solo caracteres alfanuméricos válidos
    return preg_replace('/[^0-9A-Za-z]/', '', $str);
}

/**
 * Sanitiza números de celular/teléfono evitando notaciones científicas y flotantes (REGLA 3)
 *
 * @param mixed $valor
 * @return string|null
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

// 2. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Método de solicitud no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Validar token CSRF
$tokenCsrf = $_POST['token_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $tokenCsrf)) {
    ob_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Token CSRF inválido o sesión caducada. Recargue la página e intente nuevamente.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Validar Ficha de destino
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

// 5. Validar archivo Excel subido
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

// 6. Cargar y leer el archivo con PhpSpreadsheet
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
    $highestColIdx = Coordinate::columnIndexFromString($highestCol);
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

// 7. Mapeo inteligente de encabezados en fila 1 (REGLA 1: Ignorar explícitamente "Estado")
$colMap = [
    'tipo_doc' => null,
    'num_doc'  => null,
    'nombre'   => null,
    'apellido' => null,
    'celular'  => null,
    'correo'   => null,
    'estado'   => null // Se rastrea para excluirlo expresamente
];

for ($c = 1; $c <= $highestColIdx; $c++) {
    $letraCol = Coordinate::stringFromColumnIndex($c);
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
        // REGLA 1: Se identifica la columna Estado para omitirla e ignorarla de forma absoluta
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
    $colMap['estado']   = 'G'; // Omitida en inserción
}

// 8. Preparación de consultas preparadas
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

// Inserción en aprendiz vinculando fk_ficha y fk_usuario con codigo_rfid NULL (Pendiente para asignación manual)
$stmtInsertAprendiz = $conexion->prepare('
    INSERT INTO aprendiz (codigo_rfid, fk_ficha, fk_usuario) VALUES (NULL, ?, ?)
');

$contador = 0;
$duplicados = 0;
$nombres_omitidos = [];
$documentosVistosEnLote = [];

// 9. REGLA 4: Transacción segura con PDO envolviendo la inserción masiva
$conexion->beginTransaction();

try {
    for ($row = 2; $row <= $highestRow; $row++) {
        // REGLA 2: Lectura con getCalculatedValue() para evitar importar fórmulas
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

        // NOTA REGLA 1: La celda de la columna "Estado" no se consulta ni se guarda en ninguna variable.

        // REGLA 3: Sanitización estricta de documento (evita notaciones científicas y floats)
        $numDoc = sanitizarDocumento($numDocRaw);
        if ($numDoc === '') {
            continue; // Omitir filas sin documento
        }

        $nombre   = mb_substr($nombreRaw, 0, 45, 'UTF-8');
        $apellido = mb_substr($apeRaw, 0, 45, 'UTF-8');
        $tipoDoc  = mb_substr($tipoDocRaw, 0, 10, 'UTF-8');

        // Si tanto nombre como apellido están vacíos, omitir fila espuria
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

        // Control de duplicados en la base de datos (se protege la asignación previa)
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
    // REGLA 4: Revertir todas las operaciones si ocurre una excepción
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

// 10. Devolver respuesta JSON limpia para SweetAlert2
ob_clean();
echo json_encode([
    'status'           => 'success',
    'importados'       => $contador,
    'duplicados'       => $duplicados,
    'detalle_omitidos' => $nombres_omitidos
], JSON_UNESCAPED_UNICODE);
exit;
