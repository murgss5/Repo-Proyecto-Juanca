<?php
/**
 * Lector y generador de plantillas Excel para el módulo de aprendices del instructor.
 * Cumple con el formato estricto de Sofia Plus: exactamente 7 columnas (A-G) en orden:
 * 1. Tipo de Documento
 * 2. Número de Documento
 * 3. Nombre
 * 4. Apellidos
 * 5. Celular
 * 6. Correo Electrónico
 * 7. Estado
 *
 * Preserva el número de documento como texto (incluyendo ceros iniciales).
 * Permite campos opcionales vacíos sin rechazar la fila.
 */

class AprendizExcelReader
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const EXPECTED_COLUMNS_COUNT = 7;

    /**
     * Inicializa el autoloader de Composer sin disparar platform_check.
     */
    public static function inicializarAutoload(): void
    {
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $baseVendor = dirname(__DIR__) . '/vendor';
            if (file_exists($baseVendor . '/composer/ClassLoader.php') && file_exists($baseVendor . '/composer/autoload_static.php')) {
                require_once $baseVendor . '/composer/ClassLoader.php';
                require_once $baseVendor . '/composer/autoload_static.php';
                $loader = new \Composer\Autoload\ClassLoader($baseVendor);
                call_user_func(\Composer\Autoload\ComposerStaticInita3f5fdfb28c645cf6e21017703c474cf::getInitializer($loader));
                $loader->register(true);
            } else {
                require_once dirname(__DIR__) . '/vendor/autoload.php';
            }
        }
    }

    /**
     * Lee y valida un archivo Excel subido (.xlsx) con los 7 encabezados requeridos.
     *
     * @param array $file Entrada de $_FILES['archivo_excel']
     * @return array ['success' => bool, 'filas' => array, 'message' => string]
     */
    public static function leerArchivo(array $file): array
    {
        self::inicializarAutoload();

        // 1. Validar error de subida de PHP
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return self::error(self::obtenerMensajeErrorSubida($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        // 2. Validar tamaño máximo
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return self::error('El archivo supera el tamaño máximo permitido de 5 MB.');
        }

        // 3. Validar que sea estrictamente extensión .xlsx
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            return self::error('Formato de archivo inválido. El archivo debe ser un Excel original con extensión .xlsx.');
        }

        // 4. Mover a archivo temporal seguro
        $tempDir = sys_get_temp_dir();
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . 'import_aprendices_' . bin2hex(random_bytes(16)) . '.xlsx';

        $movido = is_uploaded_file($file['tmp_name'])
            ? @move_uploaded_file($file['tmp_name'], $tempPath)
            : @copy($file['tmp_name'], $tempPath);

        if (!$movido) {
            return self::error('No fue posible almacenar temporalmente el archivo subido.');
        }

        try {
            // 5. Cargar con PhpSpreadsheet
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false); // Mantener formatos para getFormattedValue
            $spreadsheet = $reader->load($tempPath);
            $sheet = $spreadsheet->getActiveSheet();

            // Eliminar temporal inmediatamente tras leer
            @unlink($tempPath);

            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow < 1) {
                return self::error('El archivo Excel está completamente vacío.');
            }

            // 6. Validar cantidad de columnas con encabezados en la fila 1
            // Se leen las columnas de la fila 1
            $encabezadosRequeridos = [
                'A' => 'Tipo de Documento',
                'B' => 'Número de Documento',
                'C' => 'Nombre',
                'D' => 'Apellidos',
                'E' => 'Celular',
                'F' => 'Correo Electrónico',
                'G' => 'Estado'
            ];

            // Validar que no sobren columnas con contenido en fila 1 más allá de G
            if ($highestColumnIndex > self::EXPECTED_COLUMNS_COUNT) {
                for ($colIdx = self::EXPECTED_COLUMNS_COUNT + 1; $colIdx <= $highestColumnIndex; $colIdx++) {
                    $colLetra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                    $valExtra = trim((string)$sheet->getCell($colLetra . '1')->getValue());
                    if ($valExtra !== '') {
                        return self::error('El archivo no cumple con el formato requerido. Contiene más de 7 columnas o columnas adicionales no permitidas.');
                    }
                }
            }

            // Validar que coincidan exactamente los 7 encabezados en ese orden
            foreach ($encabezadosRequeridos as $colLetra => $encabezadoEsperado) {
                $celdaHeader = $sheet->getCell($colLetra . '1');
                $textoEncontrado = trim((string)$celdaHeader->getValue());

                if (self::normalizarHeader($textoEncontrado) !== self::normalizarHeader($encabezadoEsperado)) {
                    return self::error(
                        "El archivo no cumple con el formato requerido. Verifique los encabezados y el orden de las columnas. " .
                        "En la columna {$colLetra} se esperaba '{$encabezadoEsperado}' y se encontró '{$textoEncontrado}'."
                    );
                }
            }

            // 7. Procesar filas de datos a partir de la fila 2
            $filasValidas = [];

            for ($numFila = 2; $numFila <= $highestRow; $numFila++) {
                // Obtener valores como texto preservando ceros iniciales
                $tipoDoc   = self::obtenerTextoCelda($sheet->getCell('A' . $numFila));
                $numDoc    = self::obtenerTextoCelda($sheet->getCell('B' . $numFila));
                $nombre    = self::obtenerTextoCelda($sheet->getCell('C' . $numFila));
                $apellidos = self::obtenerTextoCelda($sheet->getCell('D' . $numFila));
                $celular   = self::obtenerTextoCelda($sheet->getCell('E' . $numFila));
                $correo    = self::obtenerTextoCelda($sheet->getCell('F' . $numFila));
                $estado    = self::obtenerTextoCelda($sheet->getCell('G' . $numFila));

                // Si toda la fila está vacía, se ignora
                if ($tipoDoc === '' && $numDoc === '' && $nombre === '' && $apellidos === '' && $celular === '' && $correo === '' && $estado === '') {
                    continue;
                }

                // Campos vacíos se permiten tal como vienen
                $filasValidas[] = [
                    'fila'             => $numFila,
                    'tipo_documento'   => $tipoDoc,
                    'numero_documento' => $numDoc,
                    'nombre'           => $nombre,
                    'apellidos'        => $apellidos,
                    'celular'          => $celular,
                    'correo'           => $correo,
                    'estado'           => $estado
                ];
            }

            if (empty($filasValidas)) {
                return self::error('El archivo Excel no contiene datos de aprendices para revisar.');
            }

            return [
                'success' => true,
                'filas'   => $filasValidas,
                'message' => 'Archivo válido. Se encontraron ' . count($filasValidas) . ' aprendices para revisar.'
            ];

        } catch (\Throwable $e) {
            @unlink($tempPath);
            return self::error('Error al procesar el archivo Excel: ' . $e->getMessage());
        }
    }

    /**
     * Extrae el contenido de una celda garantizando que se trate como texto.
     * Preserva estrictamente ceros iniciales (ej. "001234567").
     */
    private static function obtenerTextoCelda($celda): string
    {
        if ($celda === null) {
            return '';
        }

        // Primero intentar getFormattedValue() para respetar formato texto de Excel
        try {
            $formatted = $celda->getFormattedValue();
            if ($formatted !== null && $formatted !== '') {
                return trim((string)$formatted);
            }
        } catch (\Throwable $t) {
            // Ignorar y recurrir a getValue()
        }

        $raw = $celda->getValue();
        if ($raw === null) {
            return '';
        }

        return trim((string)$raw);
    }

    /**
     * Normaliza un encabezado para comparación insensible a mayúsculas y acentos.
     */
    private static function normalizarHeader(string $texto): string
    {
        $texto = trim(mb_strtolower($texto, 'UTF-8'));
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $texto
        );
        return preg_replace('/\s+/', ' ', $texto);
    }

    /**
     * Genera y descarga la plantilla Excel oficial (.xlsx) de 7 columnas exactas.
     */
    public static function generarPlantilla(): void
    {
        $headers = [
            'Tipo de Documento',
            'Número de Documento',
            'Nombre',
            'Apellidos',
            'Celular',
            'Correo Electrónico',
            'Estado'
        ];

        $muestras = [
            ['CC', '001234567', 'Carlos', 'Pérez', '3001234567', 'carlos@correo.com', 'En formación'],
            ['TI', '1098765432', 'María', 'Gómez', '', 'maria@correo.com', 'En formación'],
            ['CC', '80123456', 'Juan', 'Rodríguez', '3109876543', '', 'En formación']
        ];

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plantilla_' . bin2hex(random_bytes(8)) . '.xlsx';
        $zip = new \ZipArchive();

        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            die('Error al generar archivo temporal de plantilla.');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Aprendices" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><name val="Calibri"/><sz val="11"/></font>
    <font><b/><name val="Calibri"/><sz val="11"/></font>
  </fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf/></cellStyleXfs>
  <cellXfs count="2">
    <xf fontId="0" fillId="0" borderId="0"/>
    <xf fontId="1" fillId="0" borderId="0" applyFont="1"/>
  </cellXfs>
</styleSheet>';

        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        $sheetData = '<sheetData>';
        $sheetData .= '<row r="1">';
        foreach ($headers as $idx => $h) {
            $c = $cols[$idx] . '1';
            $val = htmlspecialchars($h, ENT_XML1, 'UTF-8');
            $sheetData .= '<c r="' . $c . '" t="inlineStr" s="1"><is><t>' . $val . '</t></is></c>';
        }
        $sheetData .= '</row>';

        foreach ($muestras as $i => $row) {
            $rNum = $i + 2;
            $sheetData .= '<row r="' . $rNum . '">';
            foreach ($row as $idx => $val) {
                $c = $cols[$idx] . $rNum;
                $text = htmlspecialchars((string)$val, ENT_XML1, 'UTF-8');
                $sheetData .= '<c r="' . $c . '" t="inlineStr"><is><t>' . $text . '</t></is></c>';
            }
            $sheetData .= '</row>';
        }
        $sheetData .= '</sheetData>';

        $worksheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <cols>
    <col min="1" max="1" width="22" customWidth="1"/>
    <col min="2" max="2" width="25" customWidth="1"/>
    <col min="3" max="3" width="20" customWidth="1"/>
    <col min="4" max="4" width="25" customWidth="1"/>
    <col min="5" max="5" width="20" customWidth="1"/>
    <col min="6" max="6" width="30" customWidth="1"/>
    <col min="7" max="7" width="18" customWidth="1"/>
  </cols>
  ' . $sheetData . '
</worksheet>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/styles.xml', $styles);
        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheet);
        $zip->close();

        if (ob_get_level()) {
            ob_clean();
        }

        $nombreDescarga = 'plantilla_aprendices_sofia.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
        header('Content-Length: ' . filesize($tempPath));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($tempPath);
        @unlink($tempPath);
        exit;
    }

    private static function error(string $mensaje): array
    {
        return [
            'success' => false,
            'filas'   => [],
            'message' => $mensaje
        ];
    }

    private static function obtenerMensajeErrorSubida(int $codigoError): string
    {
        switch ($codigoError) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo supera el tamaño máximo permitido.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió solo parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se seleccionó ningún archivo Excel.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal en el servidor.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en el disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida del archivo.';
            default:
                return 'Error desconocido al subir el archivo.';
        }
    }
}
