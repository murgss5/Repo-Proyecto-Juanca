<?php
/**
 * ============================================================
 * CONFIGURACION DEL PROYECTO
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Define rutas base y configuracion global.
 * NO contiene logica de negocio, autenticacion ni conexion
 * a base de datos.
 * ============================================================
 */

// Evita redefiniciones si el archivo se incluye varias veces
if (defined('RUTA_BASE')) {
    return;
}

/**
 * Ruta base relativa desde la ubicacion actual del script
 * hasta la raiz del proyecto. Se calcula automaticamente
 * segun la profundidad de la carpeta para que las vistas
 * funcionen desde XAMPP sin importar en que subcarpeta esten.
 *
 * Ejemplo:
 *   /proyecto_juanca/views/admin/dashboard.php        -> '../../'
 *   /proyecto_juanca/views/admin/aprendices/index.php -> '../../../'
 */
$nivelProfundidad = substr_count(trim(dirname($_SERVER['SCRIPT_NAME']), '/'), '/');
define('RUTA_BASE', str_repeat('../', max(0, $nivelProfundidad)));

// Ruta fisica absoluta de la raiz del proyecto
define('DIRECTORIO_PROYECTO', dirname(__DIR__));

/**
 * MODO DE PRUEBAS PARA SIMULACION DE INGRESOS RFID
 * true  = Habilitado (muestra la herramienta en el panel del instructor en jornadas abiertas y permite procesar ingresos simulados).
 * false = Deshabilitado (bloquea la interfaz y rechaza cualquier solicitud en el servidor).
 */
define('MODO_PRUEBAS_RFID', true);