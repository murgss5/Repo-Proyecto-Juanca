<?php
/**
 * Tarea automatica: Generar inasistencias
 * Ejecuta el procedimiento almacenado generar_inasistencias_v2()
 */

require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../config/conexion.php';

// Token de seguridad para ejecucion desde cron o navegador externo
if (!defined('GENERAR_INASISTENCIAS_TOKEN')) {
    define('GENERAR_INASISTENCIAS_TOKEN', 'cambiar_esta_clave_2026');
}

// Verifica permisos si se ejecuta desde navegador o HTTP (no CLI)
if (php_sapi_name() !== 'cli') {
    asegurarSesionIniciada();
    $esAdmin = !empty($_SESSION['admin']['id_usuario']);

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenHeader = $headers['X-Cron-Token'] ?? $headers['X-CRON-TOKEN'] ?? '';
    $tokenRecibido = (string)($_GET['token'] ?? $tokenHeader);

    $tokenValido = ($tokenRecibido !== '' && hash_equals(GENERAR_INASISTENCIAS_TOKEN, $tokenRecibido));

    if (!$esAdmin && !$tokenValido) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Acceso denegado: se requiere sesión de Administrador o token de autorización válido.');
    }
}

try {
    $conexion = (new Conexion())->conectar();

    // Ejecuta el procedimiento almacenado v2 adaptado para multi-horario
    $consulta = $conexion->prepare('CALL generar_inasistencias_v2()');
    $consulta->execute();

    // Consume todos los result sets para evitar "pending result sets"
    do {
        $consulta->fetchAll();
    } while ($consulta->nextRowset());

    $consulta->closeCursor();

    echo 'Proceso de inasistencias ejecutado correctamente.';
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo 'No fue posible ejecutar el proceso.';
    exit;
}
