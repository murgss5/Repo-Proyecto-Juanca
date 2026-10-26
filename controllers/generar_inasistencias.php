<?php
/**
 * Tarea automatica: Generar inasistencias
 * Ejecuta el procedimiento almacenado generar_inasistencias()
 */

// Token de seguridad para ejecucion desde navegador
define('GENERAR_INASISTENCIAS_TOKEN', 'cambiar_esta_clave_2026');

// Carga la conexion PDO
require_once __DIR__ . '/../config/conexion.php';

// Verifica token si se ejecuta desde navegador (no CLI)
if (php_sapi_name() !== 'cli') {
    $tokenRecibido = $_GET['token'] ?? '';
    if (!hash_equals(GENERAR_INASISTENCIAS_TOKEN, $tokenRecibido)) {
        http_response_code(403);
        exit('Acceso denegado');
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
    echo 'No fue posible ejecutar el proceso.';
    exit;
}