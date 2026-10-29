<?php
/**
 * Controlador: Importación masiva de aprendices desde archivo Excel
 * Sistema de Control de Ingreso de Aprendices - SENA
 * 
 * Recibe la ficha de destino y el archivo Excel (.xlsx / .xls).
 * Lee mediante PhpSpreadsheet las columnas:
 *   A: Tipo Documento
 *   B: Documento
 *   C: Nombres
 *   D: Apellidos
 *   E: Correo / Teléfono
 *   F: Teléfono / Correo
 * Controla duplicados por número de documento en la base de datos y en el lote.
 * Inserta de manera atómica en las tablas 'usuario' y 'aprendiz'.
 */

// Desactivar salida previa para garantizar JSON limpio
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

// 2. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Método de solicitud no permitido.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. Validar token CSRF
$tokenCsrf = $_POST['token_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['token_csrf']) || !hash_equals($_SESSION['token_csrf'], $tokenCsrf)) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Token CSRF inválido o sesión caducada. Recargue la página.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Validar Ficha de destino
$idFicha = filter_input(INPUT_POST, 'fk_ficha', FILTER_VALIDATE_INT);
if (!$idFicha || $idFicha <= 0) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Debe seleccionar una ficha de formación válida.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conexion = (new Conexion())->conectar();

// Verificar que la ficha exista en la base de datos
$stmtFicha = $conexion->prepare('SELECT id_ficha, nombre_programa FROM ficha WHERE id_ficha = ?');
$stmtFicha->execute([$idFicha]);
if (!$stmtFicha->fetch()) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'La ficha seleccionada no existe en el sistema.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 5. Validar archivo Excel subido
$file = $_FILES['archivo_excel'] ?? $_FILES['archivo'] ?? null;
if (!$file || empty($file['tmp_name'])) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'No se recibió ningún archivo Excel para procesar.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Error durante la subida del archivo (Código: ' . $file['error'] . ').'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Formato no permitido. El archivo debe ser .xlsx o .xls.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 6. Cargar y leer el archivo con PhpSpreadsheet
if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Librería PhpSpreadsheet no encontrada. Ejecute "composer install" en el servidor para habilitar el procesador Excel.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
try {
    $reader = IOFactory::createReaderForFile($file['tmp_name']);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($file['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = (int)$sheet->getHighestDataRow();
} catch (\Throwable $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'No fue posible procesar el archivo Excel: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($highestRow < 2) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'El archivo Excel no contiene registros de aprendices a partir de la fila 2.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 7. Preparación de consultas para inserción y control de duplicados
try {
    $conexion->exec("ALTER TABLE aprendiz ADD COLUMN correo_enviado TINYINT(1) NOT NULL DEFAULT 0");
} catch (\Throwable $e) {}
$contador = 0;
$duplicados = 0;
$nombres_omitidos = [];
$documentosVistosEnLote = [];

$stmtCheckDoc = $conexion->prepare('SELECT id_usuario, nombre, apellido FROM usuario WHERE identificacion = ? LIMIT 1');
$stmtCheckUser = $conexion->prepare('SELECT COUNT(*) FROM usuario WHERE nombre_usuario = ?');
$stmtInsertUser = $conexion->prepare('
    INSERT INTO usuario (
        nombre_usuario, contrasena, nombre, apellido, 
        tipo_documento, identificacion, telefono, correo_electronico, 
        fk_rol, estado_invitacion_clave
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, "PENDIENTE")
');
$stmtCheckRfid = $conexion->prepare('SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid = ? LIMIT 1');
$stmtInsertAprendiz = $conexion->prepare('
    INSERT INTO aprendiz (codigo_rfid, fk_ficha, fk_usuario) VALUES (?, ?, ?)
');

// 8. Iteración de filas (saltando la cabecera en fila 1)
for ($row = 2; $row <= $highestRow; $row++) {
    $tipoDocRaw = trim((string)$sheet->getCell("A{$row}")->getValue());
    $numDocRaw  = trim((string)$sheet->getCell("B{$row}")->getValue());
    $nombreRaw  = trim((string)$sheet->getCell("C{$row}")->getValue());
    $apeRaw     = trim((string)$sheet->getCell("D{$row}")->getValue());
    $valE       = trim((string)$sheet->getCell("E{$row}")->getValue());
    $valF       = trim((string)$sheet->getCell("F{$row}")->getValue());

    // Limpiar número de documento (preservando ceros a la izquierda y letras si es alfanumérico)
    $numDoc = preg_replace('/[^0-9A-Za-z]/', '', $numDocRaw);
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
        $telefono = $valF !== '' ? mb_substr($valF, 0, 45, 'UTF-8') : null;
    } elseif (filter_var($valF, FILTER_VALIDATE_EMAIL) || strpos($valF, '@') !== false) {
        $correo = mb_substr($valF, 0, 100, 'UTF-8');
        $telefono = $valE !== '' ? mb_substr($valE, 0, 45, 'UTF-8') : null;
    } else {
        $telefono = $valE !== '' ? mb_substr($valE, 0, 45, 'UTF-8') : ($valF !== '' ? mb_substr($valF, 0, 45, 'UTF-8') : null);
    }

    // Control de duplicados en el mismo archivo
    if (isset($documentosVistosEnLote[$numDoc])) {
        $duplicados++;
        $nombres_omitidos[] = $numDoc . ' - ' . trim($nombre . ' ' . $apellido);
        continue;
    }
    $documentosVistosEnLote[$numDoc] = true;

    // Control de duplicados en la base de datos (se omite para proteger su ficha actual)
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

    // Inserción atómica del aprendiz
    $conexion->beginTransaction();
    try {
        // Generar nombre de usuario único
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

        // Insertar en tabla usuario
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

        // Generar código RFID consecutivo único (formato RFID###)
        $qMaxRfid = $conexion->query("
            SELECT MAX(CAST(SUBSTRING(codigo_rfid, 5) AS UNSIGNED)) AS max_num 
            FROM aprendiz 
            WHERE codigo_rfid LIKE 'RFID%' AND codigo_rfid REGEXP '^RFID[0-9]{3,}$'
        ");
        $resMaxRfid = $qMaxRfid->fetch(PDO::FETCH_ASSOC);
        $siguienteNum = (int)($resMaxRfid['max_num'] ?? 0) + 1;
        $rfid = 'RFID' . str_pad((string)$siguienteNum, 3, '0', STR_PAD_LEFT);

        while (true) {
            $stmtCheckRfid->execute([$rfid]);
            if (!$stmtCheckRfid->fetch()) {
                break;
            }
            $siguienteNum++;
            $rfid = 'RFID' . str_pad((string)$siguienteNum, 3, '0', STR_PAD_LEFT);
        }

        // Insertar en tabla aprendiz vinculando con la ficha
        $stmtInsertAprendiz->execute([$rfid, $idFicha, $idUsuario]);

        $conexion->commit();
        $contador++;
    } catch (\Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }
        // Omitir registro fallido y continuar con los demás
    }
}

// 9. Devolver respuesta JSON estricta con detalle de aprendices omitidos
ob_clean();
echo json_encode([
    'status'           => 'success',
    'importados'       => $contador,
    'duplicados'       => $duplicados,
    'detalle_omitidos' => $nombres_omitidos
], JSON_UNESCAPED_UNICODE);
exit;

