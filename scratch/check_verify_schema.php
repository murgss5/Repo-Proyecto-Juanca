<?php
$c = new PDO("mysql:host=localhost;dbname=control_asistencia_backup_verify;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
echo "Tables in control_asistencia_backup_verify:\n";
$tables = $c->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
$idx = $c->query("SHOW INDEX FROM asistencia")->fetchAll(PDO::FETCH_ASSOC);
echo "Indexes in control_asistencia_backup_verify.asistencia:\n";
print_r(array_unique(array_column($idx, 'Key_name')));
