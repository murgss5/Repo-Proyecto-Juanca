<?php
/**
 * Controlador: Procesar Excel de Aprendices (Fase 1 - Previsualización)
 */
require_once dirname(__DIR__) . '/includes/autenticacion.php';
exigirRol(1); // Solo administrador

require_once dirname(__DIR__) . '/includes/constantes.php';
require_once dirname(__DIR__) . '/config/conexion.php';
require_once dirname(__DIR__) . '/includes/ExcelReader.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

// Validar CSRF
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    exit('Solicitud no válida.');
}

$id_ficha = filter_input(INPUT_POST, 'id_ficha', FILTER_VALIDATE_INT);
if (!$id_ficha) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'mensaje' => 'Seleccione una ficha válida.'];
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

// Consultar nombre de la ficha para la sesión (solo para visualizar)
$conexion = (new Conexion())->conectar();
$q = $conexion->prepare('SELECT id_ficha, nombre_programa FROM ficha WHERE id_ficha = ?');
$q->execute([$id_ficha]);
$fichaBD = $q->fetch(PDO::FETCH_ASSOC);

if (!$fichaBD) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'mensaje' => 'La ficha seleccionada no existe.'];
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] === UPLOAD_ERR_NO_FILE) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'mensaje' => 'No se ha seleccionado ningún archivo.'];
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

try {
    // Obtener el nombre de la primera hoja de forma óptima sin cargar todo el archivo
    $reader = IOFactory::createReader('Xlsx');
    $sheetNames = $reader->listWorksheetNames($_FILES['archivo_excel']['tmp_name']);
    $sheetName = $sheetNames[0] ?? 'Hoja1';
} catch (\Exception $e) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'mensaje' => 'El archivo no es un Excel válido o está corrupto.'];
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

// Reutilizar ExcelReader para lectura segura
$resultado = ExcelReader::readUploadedFile($_FILES['archivo_excel'], $sheetName);

if (!$resultado['success']) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'mensaje' => $resultado['message']];
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

function limpiarEncabezado($texto) {
    $texto = trim((string)$texto);
    if (function_exists('mb_strtolower')) {
        $texto = mb_strtolower($texto, 'UTF-8');
    } else {
        $texto = strtolower($texto);
    }
    // Remover tildes para evitar problemas de codificación al comparar
    $texto = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú'], 
        ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u'], 
        $texto
    );
    return $texto;
}

$headersExcel = $resultado['headers'] ?? [];
$dataRows = $resultado['data'] ?? [];

$encabezadosRequeridos = [
    'A' => 'Tipo de Documento',
    'B' => 'Número de Documento',
    'C' => 'Nombre',
    'D' => 'Apellidos',
    'E' => 'Celular',
    'F' => 'Correo Electrónico',
    'G' => 'Estado'
];

foreach ($encabezadosRequeridos as $columna => $encabezadoEsperado) {
    $encontrado = $headersExcel[$columna] ?? '';
    if (limpiarEncabezado($encontrado) !== limpiarEncabezado($encabezadoEsperado)) {
        $_SESSION['flash'] = [
            'tipo' => 'danger', 
            'mensaje' => "El encabezado de la columna $columna no coincide. Esperado: '$encabezadoEsperado'. Encontrado: '" . htmlspecialchars($encontrado) . "'."
        ];
        header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
        exit;
    }
}

// Procesar filas
$filasPreview = [];
$totalFilas = 0;
$creadas = 0;
$rechazadas = 0;
$duplicadas = 0;
$ignoradas = 0;
$conAdvertencia = 0;

$documentosVistos = [];

foreach ($dataRows as $indice => $filaExcel) {
    $filaNum = $indice; // Indice real del excel (ej: 2, 3...)
    
    // Extraer valores tal cual vienen del excel
    $tipoDoc = trim((string)($filaExcel['A'] ?? ''));
    $documento = trim((string)($filaExcel['B'] ?? ''));
    $nombre = trim((string)($filaExcel['C'] ?? ''));
    $apellidos = trim((string)($filaExcel['D'] ?? ''));
    $celular = trim((string)($filaExcel['E'] ?? ''));
    $correo = trim((string)($filaExcel['F'] ?? ''));
    $estado = trim((string)($filaExcel['G'] ?? ''));

    // 1. Ignorar filas completamente vacías
    if ($tipoDoc === '' && $documento === '' && $nombre === '' && $apellidos === '' && $celular === '' && $correo === '' && $estado === '') {
        $ignoradas++;
        continue;
    }

    $totalFilas++;
    $estadoValidacion = 'VALIDA';
    $observacion = 'Lista para importación.';

    // 2. Rechazar si el documento está vacío o tiene error de Excel
    if ($documento === '' || strpos($documento, 'ERROR:#N/A') !== false || strpos($documento, '#N/A') !== false) {
        $estadoValidacion = 'RECHAZADA';
        $observacion = 'El número de documento está vacío o contiene error.';
    } else {
        // 3. Revisar duplicados en el mismo archivo
        if (isset($documentosVistos[$documento])) {
            $estadoValidacion = 'DUPLICADA';
            $observacion = 'Documento repetido en el archivo (fila ' . $documentosVistos[$documento] . ').';
        } else {
            $documentosVistos[$documento] = $filaNum;
        }
    }

    // 4. Advertencias (celular vacío o N/A)
    if ($estadoValidacion === 'VALIDA') {
        if ($celular === '' || strpos($celular, 'ERROR:#N/A') !== false || strpos($celular, '#N/A') !== false) {
            $estadoValidacion = 'ADVERTENCIA';
            $observacion = 'Falta número de celular, se dejará sin registro.';
        }
    }

    // Contabilizar
    if ($estadoValidacion === 'VALIDA') $creadas++;
    if ($estadoValidacion === 'ADVERTENCIA') {
        $creadas++; // Las advertencias también son importables
        $conAdvertencia++;
    }
    if ($estadoValidacion === 'RECHAZADA') $rechazadas++;
    if ($estadoValidacion === 'DUPLICADA') $duplicadas++;

    $filasPreview[] = [
        'fila' => $filaNum,
        'tipo_documento' => $tipoDoc,
        'documento' => $documento,
        'nombre' => $nombre,
        'apellidos' => $apellidos,
        'celular' => $celular,
        'correo' => $correo,
        'estado' => $estado,
        'estado_validacion' => $estadoValidacion,
        'observacion' => $observacion
    ];
}

// Guardar en sesión
$_SESSION['import_preview_aprendices'] = [
    'ficha' => [
        'id' => $fichaBD['id_ficha'],
        'programa' => $fichaBD['nombre_programa']
    ],
    'resumen' => [
        'total' => $totalFilas,
        'validas' => $creadas,
        'rechazadas' => $rechazadas,
        'duplicadas' => $duplicadas,
        'ignoradas' => $ignoradas,
        'advertencias' => $conAdvertencia
    ],
    'filas' => $filasPreview,
    'timestamp' => time() // Para caducidad
];

header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar_preview.php');
exit;
