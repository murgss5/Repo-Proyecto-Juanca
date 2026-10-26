-- =============================================================================
-- MIGRACIÓN SQL VERSIONADA: ASISTENCIA MULTI-HORARIO
-- Archivo: database/migrations/20261023_asistencia_multi_horario.sql
-- Base de Datos: control_asistencia
-- Fecha: 2026-10-23
-- Descripción:
--   1. Permite múltiples sesiones de jornada por ficha en una misma fecha (una por horario).
--   2. Permite múltiples asistencias por aprendiz en una misma fecha (una por horario).
--   3. Previene duplicados para el mismo aprendiz, horario y fecha.
--   4. Refuerza la integridad referencial y coherencia relacional:
--      aprendiz.fk_ficha = sesiones_jornada.ficha_id = horario.fk_ficha.
--   5. Implementa generar_inasistencias_v2 conservando generar_inasistencias.
-- =============================================================================

-- FASE 1: REFUERZO DE UNICIDAD Y RELACIONES EN HORARIO Y SESIONES
ALTER TABLE `horario` 
    ADD UNIQUE KEY `uq_horario_id_ficha` (`id_horario`, `fk_ficha`);

ALTER TABLE `sesiones_jornada` 
    DROP INDEX `uk_ficha_fecha`,
    ADD UNIQUE KEY `uk_ficha_horario_fecha` (`ficha_id`, `horario_id`, `fecha`);

ALTER TABLE `sesiones_jornada`
    ADD CONSTRAINT `fk_sesion_horario_ficha` 
    FOREIGN KEY (`horario_id`, `ficha_id`) REFERENCES `horario` (`id_horario`, `fk_ficha`)
    ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `sesiones_jornada` 
    ADD UNIQUE KEY `uq_sesion_id_horario` (`id_sesion`, `horario_id`);

-- FASE 2: PREPARACIÓN Y POBLACIÓN DE fk_horario EN ASISTENCIA
ALTER TABLE `asistencia` 
    ADD COLUMN `fk_horario` INT(11) NULL AFTER `fk_sesion_jornada`;

-- 2.1 Población directa desde sesiones_jornada
UPDATE `asistencia` a
INNER JOIN `sesiones_jornada` s ON a.fk_sesion_jornada = s.id_sesion
SET a.fk_horario = s.horario_id
WHERE a.fk_horario IS NULL;

-- 2.2 Población por inferencia estricta para registros sin sesión
-- Solo si existe exactamente 1 horario candidato para esa asistencia específica
UPDATE `asistencia` a
SET a.fk_horario = (
    SELECT h.id_horario
    FROM `horario` h
    INNER JOIN `aprendiz` ap ON ap.id_aprendiz = a.fk_aprendiz
    WHERE h.fk_ficha = ap.fk_ficha
      AND (
          h.dia_semana = CASE DAYOFWEEK(a.fecha_asistencia)
              WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes'
              WHEN 4 THEN 'Miércoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sábado'
          END
          OR h.dia_semana = CASE DAYOFWEEK(a.fecha_asistencia)
              WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes'
              WHEN 4 THEN 'Miercoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sabado'
          END
      )
      AND (h.fecha_inicio IS NULL OR a.fecha_asistencia >= h.fecha_inicio)
      AND (h.fecha_fin IS NULL OR a.fecha_asistencia <= h.fecha_fin)
)
WHERE a.fk_horario IS NULL
  AND a.fk_sesion_jornada IS NULL
  AND (
      SELECT COUNT(*)
      FROM `horario` h2
      INNER JOIN `aprendiz` ap2 ON ap2.id_aprendiz = a.fk_aprendiz
      WHERE h2.fk_ficha = ap2.fk_ficha
        AND (
            h2.dia_semana = CASE DAYOFWEEK(a.fecha_asistencia)
                WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes'
                WHEN 4 THEN 'Miércoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sábado'
            END
            OR h2.dia_semana = CASE DAYOFWEEK(a.fecha_asistencia)
                WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes'
                WHEN 4 THEN 'Miercoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sabado'
            END
        )
        AND (h2.fecha_inicio IS NULL OR a.fecha_asistencia >= h2.fecha_inicio)
        AND (h2.fecha_fin IS NULL OR a.fecha_asistencia <= h2.fecha_fin)
  ) = 1;

-- FASE 3: APLICACIÓN DE RESTRICCIONES DEFINITIVAS EN ASISTENCIA
ALTER TABLE `asistencia` 
    MODIFY COLUMN `fk_horario` INT(11) NOT NULL;

ALTER TABLE `asistencia` 
    ADD CONSTRAINT `fk_asistencia_horario` 
    FOREIGN KEY (`fk_horario`) REFERENCES `horario` (`id_horario`) 
    ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `asistencia` 
    ADD CONSTRAINT `fk_asistencia_aprendiz` 
    FOREIGN KEY (`fk_aprendiz`) REFERENCES `aprendiz` (`id_aprendiz`) 
    ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `asistencia` 
    ADD CONSTRAINT `fk_asistencia_sesion_horario` 
    FOREIGN KEY (`fk_sesion_jornada`, `fk_horario`) REFERENCES `sesiones_jornada` (`id_sesion`, `horario_id`) 
    ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `asistencia` 
    DROP INDEX `uq_asistencia_aprendiz_fecha`,
    ADD UNIQUE KEY `uq_asistencia_aprendiz_horario_fecha` (`fk_aprendiz`, `fk_horario`, `fecha_asistencia`);

-- FASE 4: PROCEDIMIENTO ALMACENADO NUEVO generar_inasistencias_v2
DROP PROCEDURE IF EXISTS `generar_inasistencias_v2`;
DELIMITER $$
CREATE PROCEDURE `generar_inasistencias_v2`()
BEGIN
    DECLARE fecha_actual DATE;
    DECLARE hora_actual TIME;
    DECLARE dia_semana_actual VARCHAR(15);
    DECLARE dia_semana_alt VARCHAR(15);
    DECLARE cantidad_registros INT DEFAULT 0;

    -- Atrapa exclusivamente duplicados 1062 causados por inserciones concurrentes
    DECLARE CONTINUE HANDLER FOR 1062 
    BEGIN
    END;

    SET fecha_actual = CURDATE();
    SET hora_actual = CURTIME();

    SET dia_semana_actual = CASE DAYOFWEEK(fecha_actual)
        WHEN 1 THEN 'Domingo' WHEN 2 THEN 'Lunes' WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miércoles' WHEN 5 THEN 'Jueves' WHEN 6 THEN 'Viernes' WHEN 7 THEN 'Sábado'
    END;
    SET dia_semana_alt = REPLACE(REPLACE(dia_semana_actual, 'é', 'e'), 'á', 'a');

    INSERT INTO asistencia (
        fecha_asistencia,
        entrada,
        salida,
        estado_entrada,
        estado_salida,
        minutos_retardo,
        minutos_salida_anticipada,
        fk_aprendiz,
        fk_sesion_jornada,
        fk_horario
    )
    SELECT
        fecha_actual,
        NULL,
        NULL,
        'INASISTENCIA',
        NULL,
        0,
        0,
        ap.id_aprendiz,
        (
            SELECT s.id_sesion 
            FROM sesiones_jornada s 
            WHERE s.ficha_id = ap.fk_ficha 
              AND s.horario_id = h.id_horario 
              AND s.fecha = fecha_actual 
            ORDER BY s.id_sesion DESC 
            LIMIT 1
        ),
        h.id_horario
    FROM aprendiz ap
    INNER JOIN horario h ON h.fk_ficha = ap.fk_ficha
    WHERE (h.dia_semana = dia_semana_actual OR h.dia_semana = dia_semana_alt)
      AND (h.estado = 'Activo' OR h.estado IS NULL)
      AND (h.fecha_inicio IS NULL OR fecha_actual >= h.fecha_inicio)
      AND (h.fecha_fin IS NULL OR fecha_actual <= h.fecha_fin)
      AND hora_actual > ADDTIME(h.entrada, '00:20:00')
      AND NOT EXISTS (
          SELECT 1
          FROM asistencia a
          WHERE a.fk_aprendiz = ap.id_aprendiz
            AND a.fecha_asistencia = fecha_actual
            AND a.fk_horario = h.id_horario
      )
      AND NOT EXISTS (
          SELECT 1
          FROM sesiones_jornada s
          WHERE s.ficha_id = ap.fk_ficha
            AND s.horario_id = h.id_horario
            AND s.fecha = fecha_actual
            AND s.estado = 'abierta'
      );

    SET cantidad_registros = ROW_COUNT();
    SELECT cantidad_registros AS inasistencias_generadas;
END$$
DELIMITER ;
