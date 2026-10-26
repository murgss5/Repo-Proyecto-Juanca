<?php
require_once __DIR__ . '/../config/conexion.php';
$c = (new Conexion())->conectar();

echo "=== SHOW CREATE TABLE asistencia ===\n";
$stmt = $c->query("SHOW CREATE TABLE asistencia");
echo $stmt->fetchColumn(1) . "\n\n";

echo "=== TABLE CONSTRAINTS ===\n";
$stmt2 = $c->query("SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asistencia'");
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
