USE control_asistencia;
ALTER TABLE `horario` 
  ADD COLUMN `competencia` VARCHAR(100) NULL AFTER `salida`,
  ADD COLUMN `tipo_bloque` ENUM('completo', 'partido') NOT NULL DEFAULT 'completo' AFTER `competencia`,
  ADD COLUMN `ambiente` VARCHAR(100) NULL AFTER `tipo_bloque`,
  ADD COLUMN `instructor_id` INT(11) NULL AFTER `fk_ficha`;