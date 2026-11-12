<?php
/**
 * =============================================================================
 * MODELO: DashboardModel.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Centraliza la recolección analítica y métricas para los tableros de control
 * (Dashboards) de Administradores, Instructores y Aprendices.
 * =============================================================================
 */

require_once __DIR__ . '/../config/conexion.php';

class DashboardModel
{
    /**
     * Obtiene métricas, gráficas y listas recientes para el Dashboard de Administrador.
     *
     * @return array
     */
    public static function obtenerDatosAdmin(): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            $totalAprendices   = (int)$conexion->query('SELECT COUNT(*) FROM aprendiz')->fetchColumn();
            $totalFichas       = (int)$conexion->query('SELECT COUNT(*) FROM ficha')->fetchColumn();
            $excusasPendientes = (int)$conexion->query("SELECT COUNT(*) FROM excusa WHERE estado = 'PENDIENTE'")->fetchColumn();

            // Métricas de hoy
            $consultaHoy = $conexion->query(
                "SELECT
                    SUM(CASE WHEN estado_entrada IN ('PUNTUAL', 'RETARDO') THEN 1 ELSE 0 END) AS presentes,
                    SUM(CASE WHEN estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
                    SUM(CASE WHEN estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
                    SUM(CASE WHEN estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_tempranas
                 FROM asistencia 
                 WHERE fecha_asistencia = CURDATE()"
            )->fetch(PDO::FETCH_ASSOC);

            $presentesHoy        = (int)($consultaHoy['presentes'] ?? 0);
            $retardosHoy         = (int)($consultaHoy['retardos'] ?? 0);
            $inasistenciasHoy    = (int)($consultaHoy['inasistencias'] ?? 0);
            $salidasTempranasHoy = (int)($consultaHoy['salidas_tempranas'] ?? 0);

            // Gráfica semanal (Lunes a Sábado)
            $graficaSemana = [
                'labels'        => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                'presentes'     => array_fill(0, 6, 0),
                'retardos'      => array_fill(0, 6, 0),
                'inasistencias' => array_fill(0, 6, 0),
            ];

            $consultaSemana = $conexion->query(
                "SELECT
                    WEEKDAY(fecha_asistencia) AS dia,
                    SUM(CASE WHEN estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS presentes,
                    SUM(CASE WHEN estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
                    SUM(CASE WHEN estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias
                 FROM asistencia
                 WHERE fecha_asistencia >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                   AND fecha_asistencia <= DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 5 DAY)
                 GROUP BY WEEKDAY(fecha_asistencia)"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($consultaSemana as $fila) {
                $dia = (int)$fila['dia'];
                if ($dia >= 0 && $dia <= 5) {
                    $graficaSemana['presentes'][$dia]     = (int)$fila['presentes'];
                    $graficaSemana['retardos'][$dia]      = (int)$fila['retardos'];
                    $graficaSemana['inasistencias'][$dia] = (int)$fila['inasistencias'];
                }
            }

            // Últimos ingresos registrados hoy
            $consultaIngresos = $conexion->query(
                "SELECT
                    asistencia.id_asistencia,
                    DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora,
                    CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
                    ficha.id_ficha,
                    aprendiz.codigo_rfid,
                    asistencia.estado_entrada
                 FROM asistencia
                 INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
                 INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
                 INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
                 WHERE asistencia.fecha_asistencia = CURDATE()
                   AND asistencia.entrada IS NOT NULL
                 ORDER BY asistencia.entrada DESC LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);

            // Últimos retardos registrados
            $consultaRetardos = $conexion->query(
                "SELECT
                    asistencia.fecha_asistencia,
                    DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora,
                    CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
                    ficha.id_ficha,
                    asistencia.minutos_retardo
                 FROM asistencia
                 INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
                 INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
                 INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
                 WHERE asistencia.estado_entrada = 'RETARDO'
                 ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total_aprendices'       => $totalAprendices,
                'total_fichas'           => $totalFichas,
                'excusas_pendientes'     => $excusasPendientes,
                'presentes_hoy'          => $presentesHoy,
                'retardos_hoy'           => $retardosHoy,
                'inasistencias_hoy'      => $inasistenciasHoy,
                'salidas_tempranas_hoy'  => $salidasTempranasHoy,
                'grafica_semana'         => $graficaSemana,
                'ultimos_ingresos'       => $consultaIngresos,
                'ultimos_retardos'       => $consultaRetardos,
            ];

        } catch (\Throwable $e) {
            error_log('Error en DashboardModel::obtenerDatosAdmin: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene métricas, jornadas y datos para el Dashboard de Instructor.
     *
     * @param int $idInstructor
     * @return array
     */
    public static function obtenerDatosInstructor(int $idInstructor): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            // Total fichas asignadas
            $qFichas = $conexion->prepare('SELECT COUNT(*) FROM ficha WHERE fk_usuario = :id_instructor');
            $qFichas->execute([':id_instructor' => $idInstructor]);
            $totalFichas = (int)$qFichas->fetchColumn();

            // Total aprendices en sus fichas
            $qAprendices = $conexion->prepare(
                'SELECT COUNT(*) FROM aprendiz ap 
                 INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha 
                 WHERE f.fk_usuario = :id_instructor'
            );
            $qAprendices->execute([':id_instructor' => $idInstructor]);
            $totalAprendices = (int)$qAprendices->fetchColumn();

            // Asistencias hoy en sus fichas
            $qAsistencias = $conexion->prepare(
                "SELECT COUNT(*) FROM asistencia a
                 INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
                 INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                 WHERE f.fk_usuario = :id_instructor
                   AND a.fecha_asistencia = CURDATE()
                   AND a.estado_entrada IN ('PUNTUAL', 'RETARDO')"
            );
            $qAsistencias->execute([':id_instructor' => $idInstructor]);
            $presentesHoy = (int)$qAsistencias->fetchColumn();

            // Excusas pendientes en sus fichas
            $qExcusas = $conexion->prepare(
                "SELECT COUNT(*) FROM excusa e
                 INNER JOIN asistencia a ON e.fk_asistencia = a.id_asistencia
                 INNER JOIN aprendiz ap ON a.fk_aprendiz = ap.id_aprendiz
                 INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                 WHERE f.fk_usuario = :id_instructor
                   AND e.estado = 'PENDIENTE'"
            );
            $qExcusas->execute([':id_instructor' => $idInstructor]);
            $excusasPendientes = (int)$qExcusas->fetchColumn();

            // Últimos ingresos hoy
            $qIngresos = $conexion->prepare(
                "SELECT
                    DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora,
                    CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
                    ficha.id_ficha AS ficha
                 FROM asistencia
                 INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
                 INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
                 INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
                 WHERE ficha.fk_usuario = :id_instructor
                   AND asistencia.fecha_asistencia = CURDATE()
                   AND asistencia.entrada IS NOT NULL
                 ORDER BY asistencia.entrada DESC LIMIT 5"
            );
            $qIngresos->execute([':id_instructor' => $idInstructor]);
            $ultimosIngresos = $qIngresos->fetchAll(PDO::FETCH_ASSOC);

            // Gráfica semanal del instructor
            $qSemana = $conexion->prepare(
                "SELECT
                    WEEKDAY(asistencia.fecha_asistencia) AS indice_dia,
                    SUM(CASE WHEN asistencia.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
                    SUM(CASE WHEN asistencia.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos
                 FROM asistencia
                 INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
                 INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
                 WHERE ficha.fk_usuario = :id_instructor
                   AND asistencia.fecha_asistencia >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                   AND asistencia.fecha_asistencia < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
                 GROUP BY WEEKDAY(asistencia.fecha_asistencia)"
            );
            $qSemana->execute([':id_instructor' => $idInstructor]);
            $datosSemanaBD = $qSemana->fetchAll(PDO::FETCH_ASSOC);

            $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $puntualesPorDia = array_fill(0, 6, 0);
            $retardosPorDia  = array_fill(0, 6, 0);
            foreach ($datosSemanaBD as $fila) {
                $idx = (int)$fila['indice_dia'];
                if ($idx >= 0 && $idx < 6) {
                    $puntualesPorDia[$idx] = (int)$fila['puntuales'];
                    $retardosPorDia[$idx]  = (int)$fila['retardos'];
                }
            }

            // Sesiones y jornadas de hoy
            $qJornadas = $conexion->prepare(
                "SELECT s.id_sesion, s.ficha_id, s.horario_id, s.hora_apertura_real, s.hora_limite_puntualidad, s.hora_cierre_real, s.estado 
                 FROM sesiones_jornada s 
                 INNER JOIN horario h ON s.horario_id = h.id_horario AND (h.estado = 'Activo' OR h.estado IS NULL) 
                 WHERE s.instructor_id = :id_instructor 
                   AND s.fecha = CURDATE() 
                   AND s.estado IN ('abierta', 'cerrada_manual') 
                 ORDER BY s.hora_apertura_real DESC"
            );
            $qJornadas->execute([':id_instructor' => $idInstructor]);
            $jornadas = $qJornadas->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total_fichas'       => $totalFichas,
                'total_aprendices'   => $totalAprendices,
                'presentes_hoy'      => $presentesHoy,
                'excusas_pendientes' => $excusasPendientes,
                'ultimos_ingresos'   => $ultimosIngresos,
                'grafica_semana'     => [
                    'dias'      => $diasSemana,
                    'puntuales' => $puntualesPorDia,
                    'retardos'  => $retardosPorDia
                ],
                'jornadas'           => $jornadas,
            ];

        } catch (\Throwable $e) {
            error_log('Error en DashboardModel::obtenerDatosInstructor: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene métricas y datos para el Dashboard de Aprendiz.
     *
     * @param int $idUsuarioAprendiz
     * @return array
     */
    public static function obtenerDatosAprendiz(int $idUsuarioAprendiz): array
    {
        try {
            $conexion = (new Conexion())->conectar();

            // Datos de vinculación del aprendiz
            $qDatos = $conexion->prepare(
                "SELECT ap.id_aprendiz, f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal,
                        u.identificacion, u.nombre, u.apellido
                 FROM aprendiz ap
                 INNER JOIN usuario u ON ap.fk_usuario = u.id_usuario
                 INNER JOIN ficha f ON ap.fk_ficha = f.id_ficha
                 WHERE ap.fk_usuario = :id_usuario
                 LIMIT 1"
            );
            $qDatos->execute([':id_usuario' => $idUsuarioAprendiz]);
            $datosAprendiz = $qDatos->fetch(PDO::FETCH_ASSOC);

            if (!$datosAprendiz) {
                return [
                    'datos_aprendiz'    => null,
                    'metricas'          => [],
                    'excusas_pendientes'=> 0,
                    'ultimas_asistencias'=> [],
                ];
            }

            $idAprendiz = (int)$datosAprendiz['id_aprendiz'];

            // Resumen de asistencias histórico
            $qResumen = $conexion->prepare(
                "SELECT
                    COUNT(*) AS total_clases,
                    SUM(CASE WHEN estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
                    SUM(CASE WHEN estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
                    SUM(CASE WHEN estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
                    SUM(CASE WHEN estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_anticipadas,
                    SUM(CASE WHEN estado_entrada = 'JUSTIFICADA' THEN 1 ELSE 0 END) AS justificadas
                 FROM asistencia
                 WHERE fk_aprendiz = :id_aprendiz"
            );
            $qResumen->execute([':id_aprendiz' => $idAprendiz]);
            $metricas = $qResumen->fetch(PDO::FETCH_ASSOC) ?: [];

            // Resumen de asistencia del mes actual
            $qResumenMes = $conexion->prepare(
                "SELECT
                    SUM(CASE WHEN estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
                    SUM(CASE WHEN estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
                    SUM(CASE WHEN estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
                    SUM(CASE WHEN estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_tempranas
                 FROM asistencia
                 WHERE fk_aprendiz = :id_aprendiz
                   AND YEAR(fecha_asistencia) = YEAR(CURDATE())
                   AND MONTH(fecha_asistencia) = MONTH(CURDATE())"
            );
            $qResumenMes->execute([':id_aprendiz' => $idAprendiz]);
            $resumenMes = $qResumenMes->fetch(PDO::FETCH_ASSOC) ?: [];

            // Excusas pendientes
            $qExcusas = $conexion->prepare(
                "SELECT COUNT(*) FROM excusa e
                 INNER JOIN asistencia a ON e.fk_asistencia = a.id_asistencia
                 WHERE a.fk_aprendiz = :id_aprendiz AND e.estado = 'PENDIENTE'"
            );
            $qExcusas->execute([':id_aprendiz' => $idAprendiz]);
            $excusasPendientes = (int)$qExcusas->fetchColumn();

            // Últimas 5 excusas registradas
            $qExcusasRecientes = $conexion->prepare(
                "SELECT a.fecha_asistencia, e.fecha_subida, e.estado
                 FROM excusa e
                 INNER JOIN asistencia a ON e.fk_asistencia = a.id_asistencia
                 WHERE a.fk_aprendiz = :id_aprendiz
                 ORDER BY COALESCE(e.fecha_radicacion, e.fecha_subida) DESC, e.id_excusa DESC
                 LIMIT 5"
            );
            $qExcusasRecientes->execute([':id_aprendiz' => $idAprendiz]);
            $excusasRecientes = $qExcusasRecientes->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Estado de invitación de cambio de contraseña
            $qInvitacion = $conexion->prepare(
                "SELECT estado_invitacion_clave FROM usuario WHERE id_usuario = :id LIMIT 1"
            );
            $qInvitacion->execute([':id' => $idUsuarioAprendiz]);
            $estadoInvitacion = $qInvitacion->fetchColumn() ?: 'MANTENIDA';
            $mostrarInvitacionClave = ($estadoInvitacion === 'PENDIENTE');

            // Últimas 5 asistencias
            $qUltimas = $conexion->prepare(
                "SELECT a.id_asistencia, a.fecha_asistencia,
                        DATE_FORMAT(a.entrada, '%H:%i') AS entrada,
                        DATE_FORMAT(a.salida, '%H:%i') AS salida,
                        a.estado_entrada, a.estado_salida, a.minutos_retardo,
                        e.estado AS estado_excusa
                 FROM asistencia a
                 LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
                 WHERE a.fk_aprendiz = :id_aprendiz
                 ORDER BY a.fecha_asistencia DESC, a.id_asistencia DESC LIMIT 5"
            );
            $qUltimas->execute([':id_aprendiz' => $idAprendiz]);
            $ultimasAsistencias = $qUltimas->fetchAll(PDO::FETCH_ASSOC);

            return [
                'datos_aprendiz'          => $datosAprendiz,
                'metricas'                => $metricas,
                'resumen_mes'             => $resumenMes,
                'excusas_recientes'       => $excusasRecientes,
                'excusas_pendientes'      => $excusasPendientes,
                'mostrar_invitacion_clave'=> $mostrarInvitacionClave,
                'ultimas_asistencias'     => $ultimasAsistencias,
            ];

        } catch (\Throwable $e) {
            error_log('Error en DashboardModel::obtenerDatosAprendiz: ' . $e->getMessage());
            return [];
        }
    }
}
