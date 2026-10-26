<?php
/**
 * Script de ejecución segura de migración sobre la base de datos real: control_asistencia
 */
date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/../config/conexion.php';

$conexion = (new Conexion())->conectar();

echo "=== INICIANDO MIGRACIÓN SEGURA SOBRE 'control_asistencia' ===\n";

// Verificar estado de mantenimiento
$mantenimientoFile = __DIR__ . '/../.mantenimiento';
file_put_contents($mantenimientoFile, "Mantenimiento activo - Migración en curso " . date('Y-m-d H:i:s'));
echo "[1] Modo mantenimiento activado: $mantenimientoFile creado.\n";

// Confirmar conexiones activas
$resTrx = $conexion->query("SELECT COUNT(*) FROM information_schema.innodb_trx WHERE trx_mysql_thread_id != CONNECTION_ID()")->fetchColumn();
echo "[2] Transacciones concurrentes activas: $resTrx\n";

// ----------------------------------------------------
// ETAPA 1: DIAGNÓSTICOS PREVIOS (D0-A hasta D5)
// ----------------------------------------------------
echo "\n--- ETAPA 1: DIAGNÓSTICOS PREVIOS ---\n";

$d0a = $conexion->query("SELECT COUNT(*) FROM sesiones_jornada WHERE horario_id IS NULL")->fetchColumn();
echo "D0-A (sesiones_jornada sin horario_id): $d0a\n";
if ($d0a > 0) die("BLOQUEO D0-A: Existen sesiones sin horario_id.\n");

$d0b = $conexion->query("SELECT COUNT(*) FROM sesiones_jornada s LEFT JOIN horario h ON s.horario_id = h.id_horario WHERE s.horario_id IS NOT NULL AND h.id_horario IS NULL")->fetchColumn();
echo "D0-B (sesiones_jornada con horario_id huérfano): $d0b\n";
if ($d0b > 0) die("BLOQUEO D0-B: Existen sesiones con horario_id huérfano.\n");

$d0c = $conexion->query("SELECT COUNT(*) FROM sesiones_jornada s INNER JOIN horario h ON s.horario_id = h.id_horario WHERE s.ficha_id != h.fk_ficha")->fetchColumn();
echo "D0-C (sesiones_jornada con ficha incompatible): $d0c\n";
if ($d0c > 0) die("BLOQUEO D0-C: Sesiones con ficha incompatible.\n");

$d0d = $conexion->query("SELECT COUNT(*) FROM sesiones_jornada s INNER JOIN horario h ON s.horario_id = h.id_horario WHERE s.instructor_id != h.instructor_id")->fetchColumn();
echo "D0-D (sesiones_jornada con instructor incompatible): $d0d\n";
if ($d0d > 0) die("BLOQUEO D0-D: Sesiones con instructor incompatible.\n");

$d1 = $conexion->query("SELECT COUNT(*) FROM asistencia WHERE fk_aprendiz IS NULL")->fetchColumn();
echo "D1 (asistencias sin aprendiz): $d1\n";
if ($d1 > 0) die("BLOQUEO D1: Asistencias sin aprendiz.\n");

$d2 = $conexion->query("SELECT COUNT(*) FROM asistencia a LEFT JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz WHERE ap.id_aprendiz IS NULL")->fetchColumn();
echo "D2 (asistencias con aprendiz huérfano): $d2\n";
if ($d2 > 0) die("BLOQUEO D2: Asistencias con aprendiz huérfano.\n");

$d3 = $conexion->query("SELECT COUNT(*) FROM asistencia WHERE fk_sesion_jornada IS NULL")->fetchColumn();
echo "D3 (asistencias sin sesión): $d3\n";

$d4 = $conexion->query("SELECT COUNT(*) FROM asistencia a LEFT JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion WHERE a.fk_sesion_jornada IS NOT NULL AND s.id_sesion IS NULL")->fetchColumn();
echo "D4 (asistencias con sesión huérfana): $d4\n";
if ($d4 > 0) die("BLOQUEO D4: Asistencias con sesión huérfana.\n");

$d5 = $conexion->query("SELECT COUNT(*) FROM asistencia a INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz INNER JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion WHERE ap.fk_ficha != s.ficha_id")->fetchColumn();
echo "D5 (asistencias donde aprendiz.ficha != sesion.ficha): $d5\n";
if ($d5 > 0) die("BLOQUEO D5: Inconsistencia aprendiz-sesión.\n");

echo "-> ETAPA 1 SUPERADA EXITOSAMENTE.\n";

// ----------------------------------------------------
// ETAPA 2: CREACIÓN TEMPORAL Y POBLACIÓN DE fk_horario
// ----------------------------------------------------
echo "\n--- ETAPA 2: CREAR Y POBLAR fk_horario ---\n";

$colExists = $conexion->query("SHOW COLUMNS FROM asistencia LIKE 'fk_horario'")->fetch();
if (!$colExists) {
    echo "Agregando columna fk_horario INT NULL a asistencia...\n";
    $conexion->exec("ALTER TABLE asistencia ADD COLUMN fk_horario INT NULL AFTER fk_sesion_jornada");
} else {
    echo "Columna fk_horario ya existe en asistencia.\n";
}

$updatedSes = $conexion->exec("
    UPDATE asistencia a
    INNER JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion
    SET a.fk_horario = s.horario_id
    WHERE a.fk_sesion_jornada IS NOT NULL
      AND s.horario_id IS NOT NULL
      AND (a.fk_horario IS NULL OR a.fk_horario != s.horario_id)
");
echo "Filas actualizadas desde sesiones_jornada: " . ($updatedSes !== false ? $updatedSes : 0) . "\n";

// ----------------------------------------------------
// ETAPA 3: GATEKEEPERS OPERACIONALES (D6 hasta D8-B)
// ----------------------------------------------------
echo "\n--- ETAPA 3: GATEKEEPERS OPERACIONALES ---\n";

$d6 = $conexion->query("SELECT COUNT(*) FROM asistencia WHERE fk_horario IS NULL")->fetchColumn();
echo "D6 (asistencias con fk_horario NULL): $d6\n";
if ($d6 > 0) die("BLOQUEO D6: Existen asistencias sin horario asignado.\n");

$d7 = $conexion->query("SELECT COUNT(*) FROM (SELECT fk_aprendiz, fk_horario, fecha_asistencia FROM asistencia GROUP BY fk_aprendiz, fk_horario, fecha_asistencia HAVING COUNT(*) > 1) t")->fetchColumn();
echo "D7 (duplicados de aprendiz + horario + fecha): $d7\n";
if ($d7 > 0) die("BLOQUEO D7: Existen registros duplicados.\n");

$d8 = $conexion->query("SELECT COUNT(*) FROM asistencia a INNER JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion WHERE a.fk_horario != s.horario_id")->fetchColumn();
echo "D8 (discrepancias entre a.fk_horario y s.horario_id): $d8\n";
if ($d8 > 0) die("BLOQUEO D8: Discrepancia entre horario de asistencia y de sesión.\n");

$d8b = $conexion->query("SELECT COUNT(*) FROM asistencia a INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz INNER JOIN horario h ON a.fk_horario = h.id_horario WHERE ap.fk_ficha != h.fk_ficha")->fetchColumn();
echo "D8-B (discrepancias entre aprendiz.fk_ficha y horario.fk_ficha): $d8b\n";
if ($d8b > 0) die("BLOQUEO D8-B: Discrepancia entre ficha de aprendiz y horario.\n");

echo "-> ETAPA 3 SUPERADA EXITOSAMENTE.\n";

// ----------------------------------------------------
// ETAPA 4: APLICAR NOT NULL, RESTRICCIONES Y V2
// ----------------------------------------------------
echo "\n--- ETAPA 4: APLICAR NOT NULL, ÍNDICES Y CONSTRAINTS ---\n";

// 1. Modificar columna fk_horario a NOT NULL
$conexion->exec("ALTER TABLE asistencia MODIFY COLUMN fk_horario INT NOT NULL");
echo "1. asistencia.fk_horario modificado a NOT NULL.\n";

// 2. Foreign keys y restricciones en asistencia
$fks = $conexion->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asistencia' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('fk_asistencia_horario', $fks, true)) {
    $conexion->exec("ALTER TABLE asistencia ADD CONSTRAINT fk_asistencia_horario FOREIGN KEY (fk_horario) REFERENCES horario (id_horario) ON UPDATE CASCADE ON DELETE RESTRICT");
    echo "2. Constraint fk_asistencia_horario agregado.\n";
}

if (!in_array('fk_asistencia_aprendiz', $fks, true)) {
    $conexion->exec("ALTER TABLE asistencia ADD CONSTRAINT fk_asistencia_aprendiz FOREIGN KEY (fk_aprendiz) REFERENCES aprendiz (id_aprendiz) ON UPDATE CASCADE ON DELETE RESTRICT");
    echo "3. Constraint fk_asistencia_aprendiz agregado.\n";
}

// 3. Sustituir índice único en asistencia asegurando índice de soporte
$indexesAsistencia = $conexion->query("SHOW INDEX FROM asistencia")->fetchAll(PDO::FETCH_ASSOC);
$indexNames = array_column($indexesAsistencia, 'Key_name');

if (!in_array('uq_asistencia_aprendiz_horario_fecha', $indexNames, true)) {
    $conexion->exec("ALTER TABLE asistencia ADD UNIQUE KEY uq_asistencia_aprendiz_horario_fecha (fk_aprendiz, fk_horario, fecha_asistencia)");
    echo "4. Índice uq_asistencia_aprendiz_horario_fecha creado.\n";
}

if (!in_array('idx_asistencia_aprendiz', $indexNames, true)) {
    $conexion->exec("ALTER TABLE asistencia ADD INDEX idx_asistencia_aprendiz (fk_aprendiz)");
    echo "5. Índice idx_asistencia_aprendiz creado.\n";
}

if (in_array('uq_asistencia_aprendiz_fecha', $indexNames, true)) {
    $conexion->exec("ALTER TABLE asistencia DROP INDEX uq_asistencia_aprendiz_fecha");
    echo "6. Índice uq_asistencia_aprendiz_fecha eliminado.\n";
}

// 4. Modificar restricciones en sesiones_jornada
$indexesSesiones = $conexion->query("SHOW INDEX FROM sesiones_jornada")->fetchAll(PDO::FETCH_ASSOC);
$sesIndexNames = array_column($indexesSesiones, 'Key_name');

if (in_array('uk_ficha_fecha', $sesIndexNames, true)) {
    $conexion->exec("ALTER TABLE sesiones_jornada DROP INDEX uk_ficha_fecha");
    echo "7. Índice uk_ficha_fecha eliminado de sesiones_jornada.\n";
}

if (!in_array('uk_ficha_horario_fecha', $sesIndexNames, true)) {
    $conexion->exec("ALTER TABLE sesiones_jornada ADD UNIQUE KEY uk_ficha_horario_fecha (ficha_id, horario_id, fecha)");
    echo "8. Índice uk_ficha_horario_fecha creado en sesiones_jornada.\n";
}

// 5. Claves compuestas para integridad estricta
$indexesHorario = $conexion->query("SHOW INDEX FROM horario")->fetchAll(PDO::FETCH_ASSOC);
$horIndexNames = array_column($indexesHorario, 'Key_name');
if (!in_array('uq_horario_id_ficha', $horIndexNames, true)) {
    $conexion->exec("ALTER TABLE horario ADD UNIQUE KEY uq_horario_id_ficha (id_horario, fk_ficha)");
    echo "9. Índice uq_horario_id_ficha creado en horario.\n";
}

$fksSesiones = $conexion->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesiones_jornada' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('fk_sesion_horario_ficha', $fksSesiones, true)) {
    $conexion->exec("ALTER TABLE sesiones_jornada ADD CONSTRAINT fk_sesion_horario_ficha FOREIGN KEY (horario_id, ficha_id) REFERENCES horario (id_horario, fk_ficha) ON UPDATE CASCADE ON DELETE RESTRICT");
    echo "10. FK fk_sesion_horario_ficha creada en sesiones_jornada.\n";
}

if (!in_array('uq_sesion_id_horario', $sesIndexNames, true)) {
    $conexion->exec("ALTER TABLE sesiones_jornada ADD UNIQUE KEY uq_sesion_id_horario (id_sesion, horario_id)");
    echo "11. Índice uq_sesion_id_horario creado en sesiones_jornada.\n";
}

$fksAsistencia = $conexion->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asistencia' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('fk_asistencia_sesion_horario', $fksAsistencia, true)) {
    $conexion->exec("ALTER TABLE asistencia ADD CONSTRAINT fk_asistencia_sesion_horario FOREIGN KEY (fk_sesion_jornada, fk_horario) REFERENCES sesiones_jornada (id_sesion, horario_id) ON UPDATE CASCADE ON DELETE RESTRICT");
    echo "12. FK fk_asistencia_sesion_horario creada en asistencia.\n";
}

// 6. Crear procedimiento generar_inasistencias_v2
$conexion->exec("DROP PROCEDURE IF EXISTS generar_inasistencias_v2");
$spSql = <<<SQL
CREATE PROCEDURE `generar_inasistencias_v2`()
BEGIN
    DECLARE fecha_actual DATE;
    DECLARE hora_actual TIME;
    DECLARE dia_semana_actual VARCHAR(20);

    SET fecha_actual = CURDATE();
    SET hora_actual = CURTIME();

    SET dia_semana_actual = CASE DAYOFWEEK(fecha_actual)
        WHEN 1 THEN 'Domingo'
        WHEN 2 THEN 'Lunes'
        WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miércoles'
        WHEN 5 THEN 'Jueves'
        WHEN 6 THEN 'Viernes'
        WHEN 7 THEN 'Sábado'
    END;

    INSERT INTO asistencia (
        fk_aprendiz,
        fk_horario,
        fecha_asistencia,
        estado_entrada,
        fk_sesion_jornada
    )
    SELECT
        a.id_aprendiz,
        h.id_horario,
        fecha_actual,
        'INASISTENCIA',
        s.id_sesion
    FROM horario h
    INNER JOIN aprendiz a ON a.fk_ficha = h.fk_ficha
    LEFT JOIN sesiones_jornada s ON s.horario_id = h.id_horario
                                 AND s.fecha = fecha_actual
                                 AND s.estado = 'abierta'
    WHERE (h.dia_semana = dia_semana_actual
           OR (dia_semana_actual = 'Miércoles' AND h.dia_semana = 'Miercoles')
           OR (dia_semana_actual = 'Sábado' AND h.dia_semana = 'Sabado'))
      AND (h.estado = 'Activo' OR h.estado IS NULL)
      AND (h.fecha_inicio IS NULL OR fecha_actual >= h.fecha_inicio)
      AND (h.fecha_fin IS NULL OR fecha_actual <= h.fecha_fin)
      AND hora_actual > ADDTIME(h.entrada, '00:20:00')
      AND NOT EXISTS (
          SELECT 1
          FROM asistencia ast
          WHERE ast.fk_aprendiz = a.id_aprendiz
            AND ast.fk_horario = h.id_horario
            AND ast.fecha_asistencia = fecha_actual
      )
    ON DUPLICATE KEY UPDATE fk_horario = VALUES(fk_horario);

    SELECT ROW_COUNT() AS inasistencias_generadas;
END
SQL;

$conexion->exec($spSql);
echo "13. Procedimiento generar_inasistencias_v2 creado exitosamente.\n";

// Desactivar mantenimiento
if (file_exists($mantenimientoFile)) {
    unlink($mantenimientoFile);
    echo "[!] Modo mantenimiento desactivado: $mantenimientoFile eliminado.\n";
}

echo "\n=== MIGRACIÓN COMPLETADA EXITOSAMENTE EN 'control_asistencia' ===\n";
