<?php
/**
 * controlador: descargar plantilla oficial de importacion de horarios
 * genera XLSX con PhpSpreadsheet, no consulta base de datos
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(1);

require_once __DIR__ . '/../includes/ExcelReader.php';

// generar y descargar plantilla
ExcelReader::downloadTemplate();
