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

// Ruta fisica absoluta de la raiz del proyecto
define('DIRECTORIO_PROYECTO', dirname(__DIR__));

/**
 * Calculo de RUTA_BASE como ruta web absoluta (ej. '/' o '/control_asistencia/').
 *
 * Funciona de manera universal:
 *  - En hosting donde el dominio apunta a la raiz (public_html/): '/'
 *  - En XAMPP local (htdocs/control_asistencia/): '/control_asistencia/'
 *  - En hosting dentro de una subcarpeta (public_html/sistema/): '/sistema/'
 *
 * Permite anulacion explicita por entorno mediante constante o variable
 * de entorno APP_BASE_URL (ej. en .htaccess o php.ini: SetEnv APP_BASE_URL /mi_ruta/).
 */
$basePersonalizada = getenv('APP_BASE_URL') ?: (defined('APP_BASE_URL') ? APP_BASE_URL : null);
if ($basePersonalizada !== null && $basePersonalizada !== false && trim((string)$basePersonalizada) !== '') {
    $rutaCalculada = '/' . trim((string)$basePersonalizada, '/') . '/';
    define('RUTA_BASE', $rutaCalculada === '//' ? '/' : $rutaCalculada);
} else {
    $scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $projectDir = str_replace('\\', '/', DIRECTORIO_PROYECTO);

    $rutaCalculada = '/';
    if ($scriptFilename !== '' && $scriptName !== '') {
        $pos = stripos($scriptFilename, $projectDir);
        if ($pos !== false) {
            $relativePath = substr($scriptFilename, $pos + strlen($projectDir));
            if ($relativePath === '' || $relativePath === '/') {
                $base = dirname($scriptName);
                $rutaCalculada = rtrim($base, '/\\') . '/';
            } else {
                $len = strlen($relativePath);
                if (substr_compare($scriptName, $relativePath, -$len, $len, true) === 0) {
                    $base = substr($scriptName, 0, strlen($scriptName) - $len);
                    $rutaCalculada = rtrim($base, '/\\') . '/';
                }
            }
        }
    }
    $rutaCalculada = '/' . trim($rutaCalculada, '/') . '/';
    define('RUTA_BASE', $rutaCalculada === '//' ? '/' : $rutaCalculada);
}

/**
 * MODO DE PRUEBAS PARA SIMULACION DE INGRESOS RFID
 * true  = Habilitado (muestra la herramienta en el panel del instructor en jornadas abiertas y permite procesar ingresos simulados).
 * false = Deshabilitado (bloquea la interfaz y rechaza cualquier solicitud en el servidor).
 */
define('MODO_PRUEBAS_RFID', true);