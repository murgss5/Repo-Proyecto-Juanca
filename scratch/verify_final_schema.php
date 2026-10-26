<?php
require_once __DIR__ . '/../config/conexion.php';
$c = (new Conexion())->conectar();

echo "======================= ASISTENCIA =======================\n";
echo $c->query("SHOW CREATE TABLE asistencia")->fetchColumn(1) . "\n\n";

echo "=================== SESIONES_JORNADA ====================\n";
echo $c->query("SHOW CREATE TABLE sesiones_jornada")->fetchColumn(1) . "\n\n";

echo "======================= HORARIO =========================\n";
echo $c->query("SHOW CREATE TABLE horario")->fetchColumn(1) . "\n\n";

echo "=================== PROCEDURES ==========================\n";
$stmt = $c->query("SHOW PROCEDURE STATUS WHERE Db = DATABASE()");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $proc) {
    echo "Procedure: " . $proc['Name'] . "\n";
}

echo "\n================ ROW COUNTS =============================\n";
echo "Total asistencias: " . $c->query("SELECT COUNT(*) FROM asistencia")->fetchColumn() . "\n";
echo "Asistencias con fk_horario NULL: " . $c->query("SELECT COUNT(*) FROM asistencia WHERE fk_horario IS NULL")->fetchColumn() . "\n";
echo "Total sesiones: " . $c->query("SELECT COUNT(*) FROM sesiones_jornada")->fetchColumn() . "\n";
echo "Total horarios: " . $c->query("SELECT COUNT(*) FROM horario")->fetchColumn() . "\n";
echo "Total aprendices: " . $c->query("SELECT COUNT(*) FROM aprendiz")->fetchColumn() . "\n";
echo "Total excusas: " . $c->query("SELECT COUNT(*) FROM excusa")->fetchColumn() . "\n";
