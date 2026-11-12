<?php
/**
 * =============================================================================
 * MODELO: InstructorModel.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Centraliza las consultas relacionadas con los instructores del centro.
 * =============================================================================
 */

require_once __DIR__ . '/../config/conexion.php';

class InstructorModel
{
    /**
     * Obtiene el listado completo de instructores con el conteo de fichas asignadas.
     *
     * @return array
     */
    public static function obtenerInstructoresConFichas(): array
    {
        try {
            $conexion = (new Conexion())->conectar();
            $stmt = $conexion->query(
                "SELECT u.id_usuario, u.identificacion, CONCAT(u.nombre, CHAR(32), u.apellido) AS nombre, u.telefono, COUNT(f.id_ficha) AS fichas 
                 FROM usuario u 
                 LEFT JOIN ficha f ON f.fk_usuario = u.id_usuario 
                 WHERE u.fk_rol = 2 
                 GROUP BY u.id_usuario, u.identificacion, u.nombre, u.apellido, u.telefono
                 ORDER BY u.apellido, u.nombre"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log("Error en InstructorModel::obtenerInstructoresConFichas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los datos de un instructor por su ID de usuario.
     *
     * @param int $idUsuario
     * @return array|null
     */
    public static function obtenerPorId(int $idUsuario): ?array
    {
        try {
            $conexion = (new Conexion())->conectar();
            $stmt = $conexion->prepare("SELECT * FROM usuario WHERE id_usuario = :id AND fk_rol = 2 LIMIT 1");
            $stmt->execute([':id' => $idUsuario]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            return $res ?: null;
        } catch (\Throwable $e) {
            error_log("Error en InstructorModel::obtenerPorId: " . $e->getMessage());
            return null;
        }
    }
}
