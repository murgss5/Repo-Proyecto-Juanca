<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE PLANTILLAS: PlantillaController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Consolida la generación y descarga de plantillas oficiales en Excel (.xlsx):
 *   - accion=aprendices : Plantilla estructurada para matrícula de aprendices
 *   - accion=horarios   : Plantilla oficial para programación y matriz de horarios
 * =============================================================================
 */

require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/constantes.php';

$accion = $_GET['accion'] ?? $_GET['tipo'] ?? '';

switch ($accion) {
    case 'aprendices':
        exigirRol(1, 2);
        require_once __DIR__ . '/../includes/AprendizExcelReader.php';
        AprendizExcelReader::generarPlantilla();
        exit;

    case 'horarios':
        exigirRol(1);
        require_once __DIR__ . '/../includes/ExcelReader.php';
        ExcelReader::downloadTemplate();
        exit;

    default:
        http_response_code(400);
        header('Location: ' . RUTA_BASE . 'index.php');
        exit;
}
