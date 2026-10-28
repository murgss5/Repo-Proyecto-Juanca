-- =============================================================================
-- MIGRACIÓN SQL VERSIONADA: CONTROL DE INTENTOS DE LOGIN Y RATE LIMITING
-- Archivo: database/migrations/20261028_control_intentos_login.sql
-- Base de Datos: control_asistencia
-- Fecha: 2026-10-28
-- Descripción:
--   1. Crea la tabla `control_intentos_login` para contadores atómicos y bloqueos temporales por cuenta e IP (InnoDB).
--   2. Crea la tabla `intentos_login_log` para auditoría forense de accesos fallidos sin almacenar contraseñas.
-- =============================================================================

-- 1. TABLA PARA CONTADORES Y BLOQUEOS ATÓMICOS
CREATE TABLE IF NOT EXISTS `control_intentos_login` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tipo` ENUM('cuenta', 'ip') NOT NULL COMMENT 'Tipo de limitación: por identificador de cuenta o por dirección IP',
    `clave` VARCHAR(100) NOT NULL COMMENT 'Identificador normalizado (usuario/cédula) o dirección IP',
    `intentos_fallidos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número acumulado de fallos dentro de la ventana activa',
    `primer_intento` DATETIME NOT NULL COMMENT 'Fecha del primer fallo dentro de la ventana actual',
    `ultimo_intento` DATETIME NOT NULL COMMENT 'Fecha del intento más reciente',
    `bloqueado_hasta` DATETIME NULL DEFAULT NULL COMMENT 'Fecha hasta la cual rige el bloqueo temporal si se alcanzó el límite',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tipo_clave` (`tipo`, `clave`),
    KEY `idx_bloqueado_hasta` (`bloqueado_hasta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABLA PARA AUDITORÍA FORENSE DE SEGURIDAD
CREATE TABLE IF NOT EXISTS `intentos_login_log` (
    `id_log` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identificador` VARCHAR(100) NOT NULL COMMENT 'Identificador ingresado por el cliente',
    `ip_origen` VARCHAR(45) NOT NULL COMMENT 'Dirección IP de origen validada',
    `fecha_registro` DATETIME NOT NULL COMMENT 'Marca de tiempo del intento',
    `motivo` VARCHAR(50) NOT NULL COMMENT 'Motivo del fallo (ej. CREDENCIALES_INVALIDAS, USUARIO_INEXISTENTE, BLOQUEADO)',
    PRIMARY KEY (`id_log`),
    KEY `idx_identificador_fecha` (`identificador`, `fecha_registro`),
    KEY `idx_ip_fecha` (`ip_origen`, `fecha_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
