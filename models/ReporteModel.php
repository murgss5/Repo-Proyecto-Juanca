<?php
/**
 * =============================================================================
 * MODELO: ReporteModel.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Centraliza las consultas analíticas, resúmenes agrupados, métricas KPI y
 * exportaciones de asistencia tanto a nivel institucional (Admin) como
 * contextualizado a las fichas a cargo (Instructor).
 * =============================================================================
 */

require_once __DIR__ . '/../config/conexion.php';

class ReporteModel
{
    /**
     * Obtiene las fichas disponibles según el rol.
     *
     * @param int|null $idInstructor Si es null, retorna todas las fichas (Admin)
     * @return array
     */
    public static function obtenerFichas(?int $idInstructor = null): array
    {
        try {
            $conexion = (new Conexion())->conectar();
            if ($idInstructor !== null && $idInstructor > 0) {
                $stmt = $conexion->prepare(
                    'SELECT id_ficha, nombre_programa, jornada, ambiente_principal 
                     FROM ficha 
                     WHERE fk_usuario = :id_instructor 
                     ORDER BY id_ficha ASC'
                );
                $stmt->execute([':id_instructor' => $idInstructor]);
            } else {
                $stmt = $conexion->query(
                    'SELECT id_ficha, nombre_programa, jornada, ambiente_principal 
                     FROM ficha 
                     ORDER BY id_ficha ASC'
                );
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('Error en ReporteModel::obtenerFichas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el resumen consolidado de asistencia agrupado por aprendiz.
     *
     * @param int|null $idInstructor Contexto de instructor opcional
     * @param array $filtros Filtros de fecha, ficha, aprendiz, etc.
     * @return array
     */
    public static function obtenerResumenAsistencias(?int $idInstructor, array $filtros): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            $fechaIni = !empty($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] : (!empty($filtros['desde']) ? $filtros['desde'] : date('Y-m-01'));
            $fechaFin = !empty($filtros['fecha_fin']) ? $filtros['fecha_fin'] : (!empty($filtros['hasta']) ? $filtros['hasta'] : date('Y-m-t'));

            $where = ['a.fecha_asistencia BETWEEN :fecha_ini AND :fecha_fin'];
            $params = [
                ':fecha_ini' => $fechaIni,
                ':fecha_fin' => $fechaFin,
            ];

            if ($idInstructor !== null && $idInstructor > 0) {
                $where[] = 'f.fk_usuario = :id_instructor';
                $params[':id_instructor'] = $idInstructor;
            }

            if (!empty($filtros['ficha'])) {
                $where[] = 'ap.fk_ficha = :ficha';
                $params[':ficha'] = (int)$filtros['ficha'];
            }

            if (!empty($filtros['aprendiz'])) {
                $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :aprendiz OR u.identificacion LIKE :aprendiz)";
                $params[':aprendiz'] = '%' . trim($filtros['aprendiz']) . '%';
            }

            if (!empty($filtros['estado_entrada'])) {
                $where[] = 'a.estado_entrada = :estado_entrada';
                $params[':estado_entrada'] = $filtros['estado_entrada'];
            }

            if (!empty($filtros['estado_salida'])) {
                $where[] = 'a.estado_salida = :estado_salida';
                $params[':estado_salida'] = $filtros['estado_salida'];
            }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $sql = "
                SELECT
                    ap.id_aprendiz,
                    CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
                    u.identificacion,
                    f.id_ficha,
                    f.nombre_programa,
                    f.jornada,
                    SUM(CASE WHEN a.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
                    SUM(CASE WHEN a.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
                    SUM(CASE WHEN a.estado_entrada = 'RETARDO' THEN COALESCE(a.minutos_retardo, 0) ELSE 0 END) AS total_min_retardo,
                    SUM(CASE WHEN a.estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
                    SUM(CASE WHEN a.estado_entrada = 'JUSTIFICADA' THEN 1 ELSE 0 END) AS justificadas,
                    SUM(CASE WHEN a.estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_anticipadas,
                    SUM(CASE WHEN a.estado_salida = 'SALIDA_ANTICIPADA' THEN COALESCE(a.minutos_salida_anticipada, 0) ELSE 0 END) AS total_min_salida_anticipada,
                    SUM(CASE WHEN e.estado = 'APROBADA' THEN 1 ELSE 0 END) AS excusas_aprobadas,
                    SUM(CASE WHEN e.estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS excusas_pendientes,
                    SUM(CASE WHEN e.estado = 'RECHAZADA' THEN 1 ELSE 0 END) AS excusas_rechazadas,
                    COUNT(a.id_asistencia) AS total_registros
                FROM asistencia a
                INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
                INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
                INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
                $whereSql
                GROUP BY ap.id_aprendiz, u.identificacion, u.nombre, u.apellido, f.id_ficha, f.nombre_programa, f.jornada
                ORDER BY f.id_ficha ASC, u.apellido ASC, u.nombre ASC
            ";

            $stmt = $conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            error_log('Error en ReporteModel::obtenerResumenAsistencias: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el listado detallado registro a registro de asistencia.
     *
     * @param int|null $idInstructor Contexto de instructor opcional
     * @param array $filtros Filtros de fecha, ficha, aprendiz, etc.
     * @return array
     */
    public static function obtenerDetalleAsistencias(?int $idInstructor, array $filtros): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            $fechaIni = !empty($filtros['fecha_inicio']) ? $filtros['fecha_inicio'] : (!empty($filtros['desde']) ? $filtros['desde'] : date('Y-m-01'));
            $fechaFin = !empty($filtros['fecha_fin']) ? $filtros['fecha_fin'] : (!empty($filtros['hasta']) ? $filtros['hasta'] : date('Y-m-t'));

            $where = ['a.fecha_asistencia BETWEEN :fecha_ini AND :fecha_fin'];
            $params = [
                ':fecha_ini' => $fechaIni,
                ':fecha_fin' => $fechaFin,
            ];

            if ($idInstructor !== null && $idInstructor > 0) {
                $where[] = 'f.fk_usuario = :id_instructor';
                $params[':id_instructor'] = $idInstructor;
            }

            if (!empty($filtros['ficha'])) {
                $where[] = 'ap.fk_ficha = :ficha';
                $params[':ficha'] = (int)$filtros['ficha'];
            }

            if (!empty($filtros['aprendiz'])) {
                $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :aprendiz OR u.identificacion LIKE :aprendiz)";
                $params[':aprendiz'] = '%' . trim($filtros['aprendiz']) . '%';
            }

            if (!empty($filtros['estado_entrada'])) {
                $where[] = 'a.estado_entrada = :estado_entrada';
                $params[':estado_entrada'] = $filtros['estado_entrada'];
            }

            if (!empty($filtros['estado_salida'])) {
                $where[] = 'a.estado_salida = :estado_salida';
                $params[':estado_salida'] = $filtros['estado_salida'];
            }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $sql = "
                SELECT
                    a.id_asistencia,
                    a.fecha_asistencia,
                    CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
                    u.identificacion,
                    f.id_ficha,
                    f.nombre_programa,
                    DATE_FORMAT(a.entrada, '%H:%i') AS hora_entrada,
                    DATE_FORMAT(a.salida, '%H:%i') AS hora_salida,
                    a.estado_entrada,
                    a.estado_salida,
                    COALESCE(a.minutos_retardo, 0) AS minutos_retardo,
                    COALESCE(a.minutos_salida_anticipada, 0) AS minutos_salida_anticipada,
                    e.estado AS estado_excusa,
                    e.id_excusa,
                    e.url_documento
                FROM asistencia a
                INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
                INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
                INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
                $whereSql
                ORDER BY a.fecha_asistencia DESC, a.entrada DESC, a.id_asistencia DESC
            ";

            $stmt = $conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            error_log('Error en ReporteModel::obtenerDetalleAsistencias: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calcula los totales consolidados para las tarjetas superiores de KPI.
     *
     * @param array $resumen Filas devueltas por obtenerResumenAsistencias
     * @return array Totales de asistencias, retardos, inasistencias y excusas
     */
    public static function calcularTotalesKPIs(array $resumen): array
    {
        $kpis = [
            'total_aprendices'   => count($resumen),
            'total_puntuales'    => 0,
            'total_retardos'     => 0,
            'total_inasistencias'=> 0,
            'total_justificadas' => 0,
            'total_salidas_ant'  => 0,
            'total_min_retardo'  => 0,
            'excusas_pendientes' => 0,
            'excusas_aprobadas'  => 0,
        ];

        foreach ($resumen as $fila) {
            $kpis['total_puntuales']     += (int)($fila['puntuales'] ?? 0);
            $kpis['total_retardos']      += (int)($fila['retardos'] ?? 0);
            $kpis['total_inasistencias'] += (int)($fila['inasistencias'] ?? 0);
            $kpis['total_justificadas']  += (int)($fila['justificadas'] ?? 0);
            $kpis['total_salidas_ant']   += (int)($fila['salidas_anticipadas'] ?? 0);
            $kpis['total_min_retardo']   += (int)($fila['total_min_retardo'] ?? 0);
            $kpis['excusas_pendientes']  += (int)($fila['excusas_pendientes'] ?? 0);
            $kpis['excusas_aprobadas']   += (int)($fila['excusas_aprobadas'] ?? 0);
        }

        return $kpis;
    }
}
