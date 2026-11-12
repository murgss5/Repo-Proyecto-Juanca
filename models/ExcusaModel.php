<?php
/**
 * =============================================================================
 * MODELO: ExcusaModel.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Centraliza las consultas, filtros y validaciones de acceso al expediente
 * de justificaciones médicas para Administradores, Instructores y Aprendices.
 * =============================================================================
 */

require_once __DIR__ . '/../config/conexion.php';

class ExcusaModel
{
    /**
     * Obtiene el listado de excusas médicas según el rol y filtros.
     *
     * @param int|null $idInstructor Contexto de instructor
     * @param int|null $idUsuarioAprendiz Contexto de aprendiz
     * @param array $filtros Filtros opcionales (estado, fecha_inicio, fecha_fin, ficha, aprendiz)
     * @return array
     */
    public static function obtenerExcusas(?int $idInstructor = null, ?int $idUsuarioAprendiz = null, array $filtros = []): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            $where = [];
            $params = [];

            if ($idInstructor !== null && $idInstructor > 0) {
                $where[] = 'f.fk_usuario = :id_instructor';
                $params[':id_instructor'] = $idInstructor;
            }

            if ($idUsuarioAprendiz !== null && $idUsuarioAprendiz > 0) {
                $where[] = 'u.id_usuario = :id_usuario_aprendiz';
                $params[':id_usuario_aprendiz'] = $idUsuarioAprendiz;
            }

            if (!empty($filtros['estado'])) {
                $where[] = 'e.estado = :estado';
                $params[':estado'] = strtoupper(trim($filtros['estado']));
            }

            if (!empty($filtros['fecha_inicio'])) {
                $where[] = 'DATE(COALESCE(e.fecha_radicacion, e.fecha_subida)) >= :fecha_ini';
                $params[':fecha_ini'] = $filtros['fecha_inicio'];
            }

            if (!empty($filtros['fecha_fin'])) {
                $where[] = 'DATE(COALESCE(e.fecha_radicacion, e.fecha_subida)) <= :fecha_fin';
                $params[':fecha_fin'] = $filtros['fecha_fin'];
            }

            if (!empty($filtros['ficha'])) {
                $where[] = 'f.id_ficha = :ficha';
                $params[':ficha'] = (int)$filtros['ficha'];
            }

            if (!empty($filtros['aprendiz'])) {
                $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :aprendiz OR u.identificacion LIKE :aprendiz)";
                $params[':aprendiz'] = '%' . trim($filtros['aprendiz']) . '%';
            }

            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT
                    e.id_excusa,
                    COALESCE(e.url_documento, e.archivo) AS archivo,
                    COALESCE(e.motivo, e.observacion) AS motivo,
                    COALESCE(e.motivo, e.observacion) AS observacion,
                    e.estado,
                    COALESCE(e.fecha_radicacion, e.fecha_subida) AS fecha_subida,
                    e.fecha_revision,
                    a.fecha_asistencia,
                    CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
                    u.identificacion,
                    u.identificacion AS identificacion_aprendiz,
                    f.id_ficha,
                    f.nombre_programa,
                    CONCAT(ui.nombre, CHAR(32), ui.apellido) AS instructor_revisor
                FROM excusa e
                INNER JOIN asistencia a ON e.fk_asistencia = a.id_asistencia
                INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
                INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
                INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                LEFT JOIN usuario ui ON ui.id_usuario = e.fk_usuario_instructor
                $whereSql
                ORDER BY COALESCE(e.fecha_radicacion, e.fecha_subida) DESC, e.id_excusa DESC
            ";

            $stmt = $conexion->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            error_log('Error en ExcusaModel::obtenerExcusas: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene las inasistencias de un aprendiz que aún no tienen excusa radicada.
     *
     * @param int $idUsuarioAprendiz ID de usuario del aprendiz
     * @return array
     */
    public static function obtenerInasistenciasPendientes(int $idUsuarioAprendiz): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            $sql = "
                SELECT
                    a.id_asistencia,
                    a.fecha_asistencia,
                    COALESCE(h.dia_semana, '') AS dia_semana,
                    COALESCE(DATE_FORMAT(h.entrada, '%H:%i'), DATE_FORMAT(h.hora_inicio, '%H:%i'), '--') AS hora_inicio,
                    COALESCE(DATE_FORMAT(h.salida, '%H:%i'), DATE_FORMAT(h.hora_fin, '%H:%i'), '--') AS hora_fin,
                    f.id_ficha,
                    f.nombre_programa
                FROM asistencia a
                INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
                INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
                LEFT JOIN horario h ON h.id_horario = a.fk_horario
                LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
                WHERE ap.fk_usuario = :id_usuario
                  AND a.estado_entrada = 'INASISTENCIA'
                  AND e.id_excusa IS NULL
                ORDER BY a.fecha_asistencia DESC, a.id_asistencia DESC
            ";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':id_usuario' => $idUsuarioAprendiz]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Throwable $e) {
            error_log('Error en ExcusaModel::obtenerInasistenciasPendientes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Valida permisos y obtiene la ruta del archivo adjunto de una excusa médica.
     *
     * @param int $idExcusa
     * @param int|null $idInstructor Contexto opcional de instructor
     * @param int|null $idUsuarioAprendiz Contexto opcional de aprendiz
     * @return string|null Ruta relativa del archivo o null si no existe o no tiene permisos
     */
    public static function obtenerRutaDocumento(int $idExcusa, ?int $idInstructor = null, ?int $idUsuarioAprendiz = null): ?string
    {
        try {
            $conexion = (new Conexion())->conectar();

            $where = ['e.id_excusa = :id_excusa'];
            $params = [':id_excusa' => $idExcusa];

            if ($idInstructor !== null && $idInstructor > 0) {
                $where[] = 'f.fk_usuario = :id_instructor';
                $params[':id_instructor'] = $idInstructor;
            }

            if ($idUsuarioAprendiz !== null && $idUsuarioAprendiz > 0) {
                $where[] = 'u.id_usuario = :id_usuario';
                $params[':id_usuario'] = $idUsuarioAprendiz;
            }

            $whereSql = 'WHERE ' . implode(' AND ', $where);

            $sql = "
                SELECT COALESCE(e.url_documento, e.archivo) AS archivo
                FROM excusa e
                INNER JOIN asistencia a ON e.fk_asistencia = a.id_asistencia
                INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
                INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
                INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                $whereSql
                LIMIT 1
            ";

            $stmt = $conexion->prepare($sql);
            $stmt->execute($params);
            $archivo = $stmt->fetchColumn();

            return $archivo ?: null;

        } catch (\Throwable $e) {
            error_log('Error en ExcusaModel::obtenerRutaDocumento: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Devuelve la clase de insignia de Bootstrap para el estado de la excusa.
     *
     * @param string $estado
     * @return string
     */
    public static function claseInsigniaEstado(string $estado): string
    {
        return match (strtoupper(trim($estado))) {
            'APROBADA'  => 'badge-success',
            'PENDIENTE' => 'badge-warning',
            'RECHAZADA' => 'badge-danger',
            default     => 'badge-secondary',
        };
    }

    /**
     * Sirve un archivo adjunto de manera segura con control de cabeceras y prevención de path traversal.
     *
     * @param string $archivoBd
     * @param bool $inline
     * @return void
     */
    public static function servirArchivo(string $archivoBd, bool $inline = false): void
    {
        $nombreArchivo = basename($archivoBd);
        $carpetaExcusas = realpath(__DIR__ . '/../uploads/excusas');
        $rutaArchivo = $carpetaExcusas ? realpath($carpetaExcusas . DIRECTORY_SEPARATOR . $nombreArchivo) : false;

        if (!$carpetaExcusas || !$rutaArchivo || !is_file($rutaArchivo)
            || strpos($rutaArchivo, $carpetaExcusas . DIRECTORY_SEPARATOR) !== 0) {
            http_response_code(404);
            exit('Archivo no encontrado en el servidor.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $tipoMime = finfo_file($finfo, $rutaArchivo);
        finfo_close($finfo);

        header('Content-Type: ' . $tipoMime);
        header('Content-Length: ' . filesize($rutaArchivo));
        $disposicion = $inline ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposicion . '; filename="' . $nombreArchivo . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($rutaArchivo);
        exit;
    }
}
