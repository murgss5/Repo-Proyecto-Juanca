<?php
/**
 * ============================================================
 * CONFIGURACIÓN BASE DEL PROTOTIPO VISUAL
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Este archivo SOLO define rutas y datos de demostración.
 * NO contiene lógica de negocio, autenticación ni conexión
 * a base de datos. Será complementado por el desarrollador
 * con los controladores, modelos y sesiones reales.
 * ============================================================
 */

// Evita redefiniciones si el archivo se incluye varias veces
if (defined('RUTA_BASE')) {
    return;
}

/**
 * Ruta base relativa desde la ubicación actual del script
 * hasta la raíz del proyecto. Se calcula automáticamente
 * según la profundidad de la carpeta para que las vistas
 * funcionen desde XAMPP sin importar en qué subcarpeta estén.
 *
 * Ejemplo:
 *   /proyecto_juanca/views/admin/dashboard.php        -> '../../'
 *   /proyecto_juanca/views/admin/aprendices/index.php -> '../../../'
 */
$nivelProfundidad = substr_count(trim(dirname($_SERVER['SCRIPT_NAME']), '/'), '/');
define('RUTA_BASE', str_repeat('../', max(0, $nivelProfundidad)));

// Ruta física absoluta de la raíz del proyecto
define('DIRECTORIO_PROYECTO', dirname(__DIR__));

// ============================================================
// ROL DE DEMOSTRACIÓN
// ------------------------------------------------------------
// Como aún no existe autenticación funcional, se puede cambiar
// manualmente este valor para previsualizar cada panel:
//   'admin', 'instructor' o 'aprendiz'
// (Esta variable NO es una sesión real.)
// ============================================================
$rolDemo = 'admin';

// ============================================================
// DATOS DE DEMOSTRACIÓN (estáticos, sin base de datos)
// ============================================================
require_once __DIR__ . '/demo_data.php';