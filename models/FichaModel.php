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
}

