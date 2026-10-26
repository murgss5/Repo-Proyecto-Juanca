<?php
require_once __DIR__ . '/../config/conexion.php';
$c = (new Conexion())->conectar();

echo "Testing index creation and drop...\n";
// Create new unique key first
$indexes = array_column($c->query("SHOW INDEX FROM asistencia")->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
if (!in_array('uq_asistencia_aprendiz_horario_fecha', $indexes, true)) {
    $c->exec("ALTER TABLE asistencia ADD UNIQUE KEY uq_asistencia_aprendiz_horario_fecha (fk_aprendiz, fk_horario, fecha_asistencia)");
    echo "uq_asistencia_aprendiz_horario_fecha created.\n";
}

// Create dedicated index on fk_aprendiz to support the foreign key
if (!in_array('idx_asistencia_aprendiz', $indexes, true)) {
    $c->exec("ALTER TABLE asistencia ADD INDEX idx_asistencia_aprendiz (fk_aprendiz)");
    echo "idx_asistencia_aprendiz created.\n";
}

// Drop old index uq_asistencia_aprendiz_fecha
$indexes = array_column($c->query("SHOW INDEX FROM asistencia")->fetchAll(PDO::FETCH_ASSOC), 'Key_name');
if (in_array('uq_asistencia_aprendiz_fecha', $indexes, true)) {
    $c->exec("ALTER TABLE asistencia DROP INDEX uq_asistencia_aprendiz_fecha");
    echo "uq_asistencia_aprendiz_fecha dropped successfully.\n";
}

echo "Indexes on asistencia:\n";
print_r(array_column($c->query("SHOW INDEX FROM asistencia")->fetchAll(PDO::FETCH_ASSOC), 'Key_name'));
