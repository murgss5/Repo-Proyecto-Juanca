<?php
/**
 * Limpieza de datos de demostración (excluye admi_prueba)
 * Ejecuta solo por CLI o con token de desarrollo.
 * Elimina únicamente datos demo específicos, conservando admi_prueba.
 */

define('LIMPIEZA_DEMO_TOKEN', 'cambiar_esta_clave_2026');

// Carga la conexión PDO
require_once __DIR__ . '/../config/conexion.php';

// Verifica token si se ejecuta desde navegador (no CLI)
if (php_sapi_name() !== 'cli') {
    $tokenRecibido = $_GET['token'] ?? '';
    if (!hash_equals(LIMPIEZA_DEMO_TOKEN, $tokenRecibido)) {
        http_response_code(403);
        exit('Acceso denegado');
    }
}

$conexion = (new Conexion())->conectar();

try {
    $conexion->beginTransaction();

    $eliminados = [
        'excusa' => 0,
        'asistencia' => 0,
        'horario' => 0,
        'aprendiz' => 0,
        'ficha' => 0,
        'usuario' => 0,
    ];

    // 1. Obtener IDs de usuarios demo (EXCLUYE admi_prueba)
    $stmt = $conexion->prepare("
        SELECT id_usuario FROM usuario 
        WHERE nombre_usuario IN (
            'demo_instructor_1',
            'demo_aprendiz_1', 'demo_aprendiz_2', 'demo_aprendiz_3',
            'demo_aprendiz_4', 'demo_aprendiz_5', 'demo_aprendiz_6'
        )
        AND identificacion != 'DEMO-ADM-001'
        AND nombre_usuario != 'admi_prueba'
    ");
    $stmt->execute();
    $idsUsuariosDemo = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($idsUsuariosDemo)) {
        echo "No hay usuarios demo para eliminar.\n";
        $conexion->rollBack();
        exit;
    }

    echo "IDs de usuarios demo a eliminar: " . implode(', ', $idsUsuariosDemo) . "\n";

    $placeholders = implode(',', array_fill(0, count($idsUsuariosDemo), '?'));

    // 2. Obtener IDs de aprendices demo
    $stmt = $conexion->prepare("SELECT id_aprendiz FROM aprendiz WHERE fk_usuario IN ($placeholders)");
    $stmt->execute($idsUsuariosDemo);
    $idsAprendicesDemo = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "IDs de aprendices demo a eliminar: " . implode(', ', $idsAprendicesDemo) . "\n";

    // 3. Obtener IDs de fichas demo (nombre contiene 'Demo')
    $stmt = $conexion->prepare("SELECT id_ficha FROM ficha WHERE nombre_programa LIKE '%Demo%' AND fk_usuario IN ($placeholders)");
    $stmt->execute($idsUsuariosDemo);
    $idsFichasDemo = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "IDs de fichas demo a eliminar: " . implode(', ', $idsFichasDemo) . "\n";

    // 4. Eliminar excusas demo (asistencias de aprendices demo)
    if (!empty($idsAprendicesDemo)) {
        $placeholdersA = implode(',', array_fill(0, count($idsAprendicesDemo), '?'));
        $stmt = $conexion->prepare("DELETE FROM excusa WHERE fk_asistencia IN (SELECT id_asistencia FROM asistencia WHERE fk_aprendiz IN ($placeholdersA))");
        $stmt->execute($idsAprendicesDemo);
        $eliminados['excusa'] = $stmt->rowCount();
    }

    // 5. Eliminar asistencias demo
    if (!empty($idsAprendicesDemo)) {
        $placeholdersA = implode(',', array_fill(0, count($idsAprendicesDemo), '?'));
        $stmt = $conexion->prepare("DELETE FROM asistencia WHERE fk_aprendiz IN ($placeholdersA)");
        $stmt->execute($idsAprendicesDemo);
        $eliminados['asistencia'] = $stmt->rowCount();
    }

    // 6. Eliminar horarios demo
    if (!empty($idsFichasDemo)) {
        $placeholdersF = implode(',', array_fill(0, count($idsFichasDemo), '?'));
        $stmt = $conexion->prepare("DELETE FROM horario WHERE fk_ficha IN ($placeholdersF)");
        $stmt->execute($idsFichasDemo);
        $eliminados['horario'] = $stmt->rowCount();
    }

    // 7. Eliminar aprendices demo
    if (!empty($idsAprendicesDemo)) {
        $placeholdersA = implode(',', array_fill(0, count($idsAprendicesDemo), '?'));
        $stmt = $conexion->prepare("DELETE FROM aprendiz WHERE id_aprendiz IN ($placeholdersA)");
        $stmt->execute($idsAprendicesDemo);
        $eliminados['aprendiz'] = $stmt->rowCount();
    }

    // 8. Eliminar fichas demo
    if (!empty($idsFichasDemo)) {
        $placeholdersF = implode(',', array_fill(0, count($idsFichasDemo), '?'));
        $stmt = $conexion->prepare("DELETE FROM ficha WHERE id_ficha IN ($placeholdersF)");
        $stmt->execute($idsFichasDemo);
        $eliminados['ficha'] = $stmt->rowCount();
    }

    // 9. Eliminar usuarios demo
    $placeholdersU = implode(',', array_fill(0, count($idsUsuariosDemo), '?'));
    $stmt = $conexion->prepare("DELETE FROM usuario WHERE id_usuario IN ($placeholdersU)");
    $stmt->execute($idsUsuariosDemo);
    $eliminados['usuario'] = $stmt->rowCount();

    $conexion->commit();

    echo "\nLimpieza completada.\n";
    foreach ($eliminados as $tabla => $cantidad) {
        echo "- $tabla: $cantidad registros eliminados\n";
    }
    exit;

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo "No fue posible ejecutar la limpieza: " . $e->getMessage() . "\n";
    exit;
}