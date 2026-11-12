<?php
/**
 * Modelo para la gestión y consulta de Fichas de formación.
 * Sistema de Control de Ingreso de Aprendices - SENA
 */

require_once __DIR__ . '/../config/conexion.php';

class FichaModel
{
    /**
     * Obtiene todas las fichas activas ordenadas por número de ficha.
     *
     * @return array Listado de fichas con id_ficha, nombre_programa, jornada, etc.
     */
    public static function obtenerFichasActivas(): array
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->query(
            "SELECT id_ficha, nombre_programa, jornada, ambiente_principal
             FROM ficha
             ORDER BY id_ficha ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene una ficha por su ID
     *
     * @param int $idFicha
     * @return array|null
     */
    public static function obtenerPorId(int $idFicha): ?array
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare("SELECT * FROM ficha WHERE id_ficha = ? LIMIT 1");
        $stmt->execute([$idFicha]);
        $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
        return $ficha ?: null;
    }

    /**
     * Obtiene las fichas asignadas a un instructor específico.
     *
     * @param int $idInstructor
     * @return array
     */
    public static function obtenerPorInstructor(int $idInstructor): array
    {
        try {
            $conexion = (new Conexion())->conectar();
            $stmt = $conexion->prepare(
                "SELECT id_ficha, nombre_programa, jornada, ambiente_principal
                 FROM ficha
                 WHERE fk_usuario = :id_instructor
                 ORDER BY id_ficha ASC"
            );
            $stmt->execute([':id_instructor' => $idInstructor]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log("Error en FichaModel::obtenerPorInstructor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todas las fichas con nombre de instructor y conteo de aprendices.
     *
     * @return array
     */
    public static function obtenerFichasConDetalle(): array
    {
        try {
            $conexion = (new Conexion())->conectar();
            $stmt = $conexion->query(
                "SELECT f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal,
                        CONCAT(u.nombre, ' ', u.apellido) AS instructor,
                        COUNT(ap.id_aprendiz) AS aprendices
                 FROM ficha f
                 INNER JOIN usuario u ON u.id_usuario = f.fk_usuario
                 LEFT JOIN aprendiz ap ON ap.fk_ficha = f.id_ficha
                 GROUP BY f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal, u.nombre, u.apellido
                 ORDER BY f.id_ficha DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log("Error en FichaModel::obtenerFichasConDetalle: " . $e->getMessage());
            return [];
        }
    }
}

