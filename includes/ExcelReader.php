<?php
/**
 * Wrapper seguro para lectura de archivos XLSX con PhpSpreadsheet
 * - Solo lectura, no evalúa fórmulas
 * - Validación de tipo MIME real
 * - Limpieza garantizada de temporales
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelReader
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const ALLOWED_EXTENSION = 'xlsx';

    /**
     * Valida y lee un archivo XLSX subido
     *
     * @param array $_FILES['archivo']
     * @param string $sheetName Nombre de la hoja a leer (default: 'Horarios')
     * @return array ['success' => bool, 'data' => array, 'headers' => array, 'message' => string, 'tempPath' => string|null]
     */
    public static function readUploadedFile(array $file, string $sheetName = 'Horarios'): array
    {
        // 1. Validar error de subida
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return self::error(self::uploadErrorMessage($file['error']));
        }

        // 2. Validar tamaño
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return self::error('El archivo supera el límite de 5 MB.');
        }

        // 3. Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== self::ALLOWED_EXTENSION) {
            return self::error('Solo se permiten archivos .xlsx');
        }

        // 4. Validar MIME real con finfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime !== self::ALLOWED_MIME) {
            return self::error('El archivo no es un XLSX válido (tipo MIME: ' . $mime . ')');
        }

        // 5. Mover a temporal con nombre aleatorio fuera de document_root
        $tempDir = sys_get_temp_dir();
        $tempName = 'import_horarios_' . bin2hex(random_bytes(16)) . '.xlsx';
        $tempPath = $tempDir . DIRECTORY_SEPARATOR . $tempName;

        if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
            return self::error('No se pudo guardar el archivo temporal.');
        }

        try {
            // 6. Cargar con PhpSpreadsheet (solo lectura, no evalúa fórmulas)
            $spreadsheet = IOFactory::load($tempPath);
            
            // 7. Verificar que existe la hoja
            if (!$spreadsheet->sheetNameExists($sheetName)) {
                @unlink($tempPath);
                return self::error("La hoja '$sheetName' no existe en el archivo.");
            }

            $sheet = $spreadsheet->getSheetByName($sheetName);
            
            // 8. Convertir a array (índice por letra de columna)
            $rows = $sheet->toArray(null, true, true, true);
            
            if (empty($rows)) {
                @unlink($tempPath);
                return self::error('La hoja está vacía.');
            }

            // 9. Primera fila = encabezados
            $headers = array_map('strtolower', array_map('trim', $rows[1] ?? []));
            $dataRows = array_slice($rows, 2);

            // 10. Limpiar temporal tras lectura exitosa
            @unlink($tempPath);

            return [
                'success' => true,
                'data' => $dataRows,
                'headers' => $headers,
                'message' => 'Archivo leído correctamente.',
                'tempPath' => null
            ];

        } catch (\Exception $e) {
            @unlink($tempPath);
            return self::error('Error al leer el XLSX: ' . $e->getMessage());
        }
    }

    /**
     * Genera y descarga la plantilla oficial XLSX
     */
    public static function downloadTemplate(): void
    {
        $spreadsheet = new Spreadsheet();
        
        // Hoja 1: Horarios
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Horarios');

        $headers = [
            'A' => 'fecha_inicio_semana',
            'B' => 'fecha_fin_semana',
            'C' => 'dia',
            'D' => 'hora_inicio',
            'E' => 'hora_fin',
            'F' => 'identificacion_instructor',
            'G' => 'nombre_instructor',
            'H' => 'id_ficha',
            'I' => 'numero_ficha',
            'J' => 'programa',
            'K' => 'ambiente',
            'L' => 'area_o_competencia',
            'M' => 'observacion'
        ];

        // Escribir encabezados
        foreach ($headers as $col => $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
        }

        // Filas de ejemplo
        $ejemplos = [
            [
                '2026-09-21', '2026-09-26', 'Lunes', '06:00', '12:00',
                '100000001', 'Carlos Gomez', 1, '3234082',
                'Analisis y Desarrollo de Software', 'Taller 9',
                'Tecnico Especialista', ''
            ],
            [
                '2026-09-21', '2026-09-26', 'Martes', '06:00', '08:45',
                '100000001', 'Carlos Gomez', 1, '3234082',
                'Analisis y Desarrollo de Software', 'Taller 9',
                'Tecnico Especialista', ''
            ],
            [
                '2026-09-21', '2026-09-26', 'Lunes', '13:00', '18:00',
                '1114152000', 'Camilo Murgueitio', 23, '332350',
                'Animacion 3D', 'Taller 9',
                'Tecnico Especialista', ''
            ]
        ];

        foreach ($ejemplos as $i => $row) {
            $rowNum = $i + 2;
            foreach ($headers as $col => $header) {
                $sheet->setCellValue($col . $rowNum, $row[array_search($header, $headers)]);
            }
        }

        // Hoja 2: Instrucciones
        $instructionsSheet = $spreadsheet->createSheet();
        $instructionsSheet->setTitle('Instrucciones');

        $instructions = [
            ['INSTRUCCIONES PARA LA PLANTILLA DE IMPORTACIÓN DE HORARIOS'],
            [''],
            ['1. REGLAS GENERALES'],
            ['   - Complete una fila por cada bloque de horario.'],
            ['   - No modifique los encabezados de la hoja "Horarios".'],
            ['   - No elimine ni reordene columnas.'],
            ['   - Las columnas obligatorias están marcadas con * en la descripción.'],
            [''],
            ['2. COLUMNAS OBLIGATORIAS (*)'],
            ['   * dia: Lunes, Martes, Miércoles, Jueves, Viernes, Sábado o Domingo'],
            ['   * hora_inicio: Formato HH:MM (24h), ej. 06:00'],
            ['   * hora_fin: Formato HH:MM (24h), ej. 12:00. Debe ser posterior a hora_inicio.'],
            ['   * identificacion_instructor: Documento de identidad del instructor (debe existir en el sistema con rol Instructor).'],
            ['   * ambiente: Nombre del ambiente (ej. Taller 9). Debe coincidir con el ambiente principal de la ficha.'],
            ['   * id_ficha O numero_ficha: Al menos uno de los dos. Identifica la ficha de forma unívoca.'],
            [''],
            ['3. COLUMNAS INFORMATIVAS (no bloquean la importación)'],
            ['   fecha_inicio_semana: Lunes de la semana (YYYY-MM-DD). Solo referencia.'],
            ['   fecha_fin_semana: Sábado/Domingo de la semana (YYYY-MM-DD). Solo referencia.'],
            ['   nombre_instructor: Solo visual, no se usa para búsqueda.'],
            ['   programa: Solo visual, debe coincidir con el programa de la ficha.'],
            ['   area_o_competencia: Área, competencia o materia.'],
            ['   observacion: Texto libre.'],
            [''],
            ['4. REGLAS DE NEGOCIO'],
            ['   - Un instructor no puede tener dos fichas en horarios que se superpongan el mismo día.'],
            ['   - Una ficha no puede tener dos bloques que se superpongan el mismo día.'],
            ['   - Dos fichas distintas no pueden usar el mismo ambiente el mismo día en horarios que se superpongan.'],
            ['   - La ficha debe estar asignada al instructor indicado (ficha.fk_usuario = instructor.id_usuario).'],
            ['   - No se permiten filas duplicadas (misma ficha + día + hora_inicio) en el archivo ni contra horarios existentes.'],
            [''],
            ['5. ESTADOS EN PREVISUALIZACIÓN'],
            ['   VALIDA: La fila pasa todas las validaciones y se importará al confirmar.'],
            ['   ADVERTENCIA: La fila es válida pero hay una observación (ej. horario ya existe en BD).'],
            ['   PENDIENTE_FICHA: Falta id_ficha y numero_ficha. Debe seleccionar una ficha del instructor en la previsualización.'],
            ['   ERROR: La fila no se importará. Corrija el archivo o la selección.'],
            [''],
            ['6. AMBIENTE'],
            ['   IMPORTANTE: El campo ambiente_principal aún no está configurado en la base de datos.'],
            ['   La validación de ambiente se muestra como ADVERTENCIA:'],
            ['   "Validación de ambiente pendiente: ambiente_principal aun no esta configurado"'],
            [''],
            ['7. FORMATO DE ARCHIVO'],
            ['   - Solo .xlsx (Excel 2007+)'],
            ['   - Tamaño máximo: 5 MB'],
            ['   - Una sola hoja "Horarios" con datos; hoja "Instrucciones" es solo lectura.'],
            [''],
            ['8. CONTACTO'],
            ['   Para dudas, contacte al administrador del sistema.']
        ];

        foreach ($instructions as $i => $line) {
            $instructionsSheet->setCellValue('A' . ($i + 1), $line[0]);
            $instructionsSheet->getColumnDimension('A')->setWidth(120);
        }

        // Descargar
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'plantilla_horarios_' . date('Ymd_His') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }

    private static function error(string $message): array
    {
        return [
            'success' => false,
            'data' => [],
            'headers' => [],
            'message' => $message,
            'tempPath' => null
        ];
    }

    private static function uploadErrorMessage(int $error): string
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo supera el tamaño permitido.';
            case UPLOAD_ERR_PARTIAL:
                return 'La subida fue incompleta.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se seleccionó ningún archivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta directorio temporal en el servidor.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir en disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Subida detenida por una extensión de PHP.';
            default:
                return 'Error desconocido en la subida (código: ' . $error . ')';
        }
    }
}