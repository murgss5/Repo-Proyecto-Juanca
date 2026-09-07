<?php
/**
 * ============================================================
 * CONFIGURACION BASE DEL PROTOTIPO VISUAL
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Este archivo SOLO define rutas y datos de demostracion.
 * NO contiene logica de negocio, autenticacion ni conexion
 * a base de datos. Sera complementado por el desarrollador
 * con los controladores, modelos y sesiones reales.
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

// ============================================================
// ROL DE DEMOSTRACION
// ------------------------------------------------------------
// Como aun no existe autenticacion funcional, se puede cambiar
// manualmente este valor para previsualizar cada panel:
//   'admin', 'instructor' o 'aprendiz'
// (Esta variable NO es una sesion real.)
// ============================================================
$rolDemo = 'admin';

// ============================================================
// DATOS DE DEMOSTRACION (estaticos, sin base de datos)
// ============================================================
require_once __DIR__ . '/demo_data.php';