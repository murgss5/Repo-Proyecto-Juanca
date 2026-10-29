<?php
/**
 * Controlador para descargar la plantilla oficial de aprendices (Excel XLSX)
 * Accesible únicamente para usuarios con rol Instructor (rol 2).
 */

require_once __DIR__ . '/../includes/autenticacion.php';
exigirRol(1, 2);

require_once __DIR__ . '/../includes/AprendizExcelReader.php';

// Generar y descargar la plantilla de 5 columnas
AprendizExcelReader::generarPlantilla();
