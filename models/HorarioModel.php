<?php
/**
 * Modelo para la gestión y consulta de horarios de formación.
 * Utiliza PDO y sentencias preparadas.
 * Proporciona consultas seguras por rol (Instructor, Aprendiz, Administrador)
 * y el cálculo matemático de posiciones temporales para la cuadrícula semanal.
 */

require_once __DIR__ . '/../config/conexion.php';

class HorarioModel
{
    /**
     * Días estándar de la semana en español
     */
    public const DIAS_SEMANA = [
        1 => ['nombre' => 'Lunes',     'abrev' => 'LUN', 'alt' => 'Lunes'],
        2 => ['nombre' => 'Martes',    'abrev' => 'MAR', 'alt' => 'Martes'],
        3 => ['nombre' => 'Miércoles', 'abrev' => 'MIÉ', 'alt' => 'Miercoles'],
        4 => ['nombre' => 'Jueves',    'abrev' => 'JUE', 'alt' => 'Jueves'],
        5 => ['nombre' => 'Viernes',   'abrev' => 'VIE', 'alt' => 'Viernes'],
        6 => ['nombre' => 'Sábado',    'abrev' => 'SÁB', 'alt' => 'Sabado'],
        7 => ['nombre' => 'Domingo',   'abrev' => 'DOM', 'alt' => 'Domingo']
    ];

    /**
     * Meses en español
     */
    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    /**
     * Normaliza un día a su formato con acento estándar ('Miércoles', 'Sábado')
     */
    public static function normalizarDia(string $dia): string
    {
        $diaLimpio = mb_strtolower(trim($dia), 'UTF-8');
        $mapa = [
            'lunes'     => 'Lunes',
            'martes'    => 'Martes',
            'miercoles' => 'Miércoles',
            'miércoles' => 'Miércoles',
            'jueves'    => 'Jueves',
            'viernes'   => 'Viernes',
            'sabado'    => 'Sábado',
            'sábado'    => 'Sábado',
            'domingo'   => 'Domingo'
        ];
        return $mapa[$diaLimpio] ?? ucfirst($dia);
    }

    /**
     * Obtiene la ficha y programa asignado a un aprendiz mediante su usuario
     */
    public static function obtenerFichaAprendiz(int $idUsuarioAprendiz): ?array
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare(
            "SELECT a.id_aprendiz, a.codigo_rfid, a.fk_ficha,
                    f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal,
                    f.fk_usuario AS id_instructor_lider,
                    CONCAT(u_lider.nombre, ' ', u_lider.apellido) AS instructor_lider_nombre
             FROM aprendiz a
             INNER JOIN ficha f ON a.fk_ficha = f.id_ficha
             LEFT JOIN usuario u_lider ON f.fk_usuario = u_lider.id_usuario
             WHERE a.fk_usuario = :id_usuario
             LIMIT 1"
        );
        $stmt->bindValue(':id_usuario', $idUsuarioAprendiz, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    /**
     * Obtiene las fichas asignadas a un instructor (como líder o con clases asignadas)
     */
    public static function obtenerFichasInstructor(int $idInstructor): array
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare(
            "SELECT DISTINCT f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal
             FROM ficha f
             LEFT JOIN horario h ON h.fk_ficha = f.id_ficha
             WHERE f.fk_usuario = :id_instructor OR h.instructor_id = :id_instructor
             ORDER BY f.id_ficha ASC"
        );
        $stmt->bindValue(':id_instructor', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Consulta las clases asignadas al Instructor autenticado
     * Respeta la regla: si h.instructor_id está definido, le pertenece a ese instructor;
     * si es NULL, le pertenece al instructor líder de la ficha (f.fk_usuario).
     */
    public static function obtenerHorariosInstructor(
        int $idInstructor,
        ?string $fechaInicioSemana = null,
        ?string $fechaFinSemana = null,
        ?int $idFicha = null
    ): array {
        $conexion = (new Conexion())->conectar();

        $sql = "SELECT h.id_horario, h.dia_semana, h.entrada, h.salida,
                       h.competencia, h.tipo_bloque,
                       COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal, 'Por definir') AS ambiente,
                       h.estado, h.observaciones, h.fecha_inicio, h.fecha_fin,
                       f.id_ficha, f.nombre_programa, f.jornada,
                       CONCAT(u_instr.nombre, ' ', u_instr.apellido) AS instructor_nombre,
                       u_instr.id_usuario AS instructor_id
                FROM horario h
                INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
                LEFT JOIN usuario u_instr ON COALESCE(h.instructor_id, f.fk_usuario) = u_instr.id_usuario
                WHERE (h.instructor_id = :id_instructor OR (h.instructor_id IS NULL AND f.fk_usuario = :id_instructor))
                  AND (h.estado = 'Activo' OR h.estado IS NULL)";

        $params = [':id_instructor' => $idInstructor];

        if ($idFicha !== null && $idFicha > 0) {
            $sql .= " AND f.id_ficha = :id_ficha";
            $params[':id_ficha'] = $idFicha;
        }

        if ($fechaInicioSemana && $fechaFinSemana) {
            $sql .= " AND (h.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin)";
            $params[':fecha_inicio'] = $fechaInicioSemana;
            $params[':fecha_fin'] = $fechaFinSemana;
        }

        $sql .= " ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Miercoles', 'Jueves', 'Viernes', 'Sábado', 'Sabado', 'Domingo'), h.entrada ASC";

        $stmt = $conexion->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Consulta las clases programadas para la ficha del Aprendiz autenticado
     * Se resuelve exclusivamente a partir de la identidad del usuario en sesión
     */
    public static function obtenerHorariosAprendiz(
        int $idUsuarioAprendiz,
        ?string $fechaInicioSemana = null,
        ?string $fechaFinSemana = null
    ): array {
        $fichaInfo = self::obtenerFichaAprendiz($idUsuarioAprendiz);
        if (!$fichaInfo || empty($fichaInfo['id_ficha'])) {
            return [
                'ficha' => null,
                'horarios' => []
            ];
        }

        $idFicha = (int)$fichaInfo['id_ficha'];
        $conexion = (new Conexion())->conectar();

        $sql = "SELECT h.id_horario, h.dia_semana, h.entrada, h.salida,
                       h.competencia, h.tipo_bloque,
                       COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal, 'Por definir') AS ambiente,
                       h.estado, h.observaciones, h.fecha_inicio, h.fecha_fin,
                       f.id_ficha, f.nombre_programa, f.jornada,
                       COALESCE(
                           CONCAT(u_esp.nombre, ' ', u_esp.apellido),
                           CONCAT(u_lider.nombre, ' ', u_lider.apellido),
                           'Instructor no asignado'
                       ) AS instructor_nombre,
                       COALESCE(u_esp.id_usuario, u_lider.id_usuario) AS instructor_id
                FROM horario h
                INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
                LEFT JOIN usuario u_esp ON h.instructor_id = u_esp.id_usuario
                LEFT JOIN usuario u_lider ON f.fk_usuario = u_lider.id_usuario
                WHERE h.fk_ficha = :id_ficha
                  AND (h.estado = 'Activo' OR h.estado IS NULL)";

        $params = [':id_ficha' => $idFicha];

        if ($fechaInicioSemana && $fechaFinSemana) {
            $sql .= " AND (h.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin)";
            $params[':fecha_inicio'] = $fechaInicioSemana;
            $params[':fecha_fin'] = $fechaFinSemana;
        }

        $sql .= " ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Miercoles', 'Jueves', 'Viernes', 'Sábado', 'Sabado', 'Domingo'), h.entrada ASC";

        $stmt = $conexion->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();
        $horarios = $stmt->fetchAll();

        return [
            'ficha' => $fichaInfo,
            'horarios' => $horarios
        ];
    }

    /**
     * Consulta horarios generales para el Administrador con filtros opcionales
     */
    public static function obtenerHorariosAdmin(
        ?int $idFicha = null,
        ?int $idInstructor = null,
        ?string $fechaInicioSemana = null,
        ?string $fechaFinSemana = null
    ): array {
        $conexion = (new Conexion())->conectar();

        $sql = "SELECT h.id_horario, h.dia_semana, h.entrada, h.salida,
                       h.competencia, h.tipo_bloque,
                       COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal, 'Por definir') AS ambiente,
                       h.estado, h.observaciones, h.fecha_inicio, h.fecha_fin,
                       f.id_ficha, f.nombre_programa, f.jornada,
                       COALESCE(
                           CONCAT(u_esp.nombre, ' ', u_esp.apellido),
                           CONCAT(u_lider.nombre, ' ', u_lider.apellido),
                           'Instructor no asignado'
                       ) AS instructor_nombre,
                       COALESCE(u_esp.id_usuario, u_lider.id_usuario) AS instructor_id
                FROM horario h
                INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
                LEFT JOIN usuario u_esp ON h.instructor_id = u_esp.id_usuario
                LEFT JOIN usuario u_lider ON f.fk_usuario = u_lider.id_usuario
                WHERE (h.estado = 'Activo' OR h.estado IS NULL)";

        $params = [];

        if ($idFicha !== null && $idFicha > 0) {
            $sql .= " AND f.id_ficha = :id_ficha";
            $params[':id_ficha'] = $idFicha;
        }

        if ($idInstructor !== null && $idInstructor > 0) {
            $sql .= " AND (h.instructor_id = :id_instructor OR (h.instructor_id IS NULL AND f.fk_usuario = :id_instructor))";
            $params[':id_instructor'] = $idInstructor;
        }

        if ($fechaInicioSemana && $fechaFinSemana) {
            $sql .= " AND (h.fecha_inicio BETWEEN :fecha_inicio AND :fecha_fin)";
            $params[':fecha_inicio'] = $fechaInicioSemana;
            $params[':fecha_fin'] = $fechaFinSemana;
        }

        $sql .= " ORDER BY FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Miercoles', 'Jueves', 'Viernes', 'Sábado', 'Sabado', 'Domingo'), h.entrada ASC";

        $stmt = $conexion->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un registro de horario por su ID con todos sus datos asociados
     */
    /**
     * Consulta todos los horarios para la vista de tabla administrativa.
     * Si se selecciona una ficha, no restringe por semana para permitir ver todo
     * el histórico y programación importada, ordenado cronológicamente.
     */
    public static function obtenerHorariosTablaAdmin(
        ?int $idFicha = null,
        ?int $idInstructor = null
    ): array {
        $conexion = (new Conexion())->conectar();

        $sql = "SELECT h.id_horario, h.dia_semana, h.entrada, h.salida,
                       h.competencia, h.tipo_bloque,
                       COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal, 'Por definir') AS ambiente,
                       h.estado, h.observaciones, h.fecha_inicio, h.fecha_fin,
                       f.id_ficha, f.nombre_programa, f.jornada,
                       COALESCE(
                           CONCAT(u_esp.nombre, ' ', u_esp.apellido),
                           CONCAT(u_lider.nombre, ' ', u_lider.apellido),
                           'Instructor no asignado'
                       ) AS instructor_nombre,
                       COALESCE(u_esp.id_usuario, u_lider.id_usuario) AS instructor_id
                FROM horario h
                INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
                LEFT JOIN usuario u_esp ON h.instructor_id = u_esp.id_usuario
                LEFT JOIN usuario u_lider ON f.fk_usuario = u_lider.id_usuario
                WHERE 1=1";

        $params = [];

        if ($idFicha !== null && $idFicha > 0) {
            $sql .= " AND f.id_ficha = :id_ficha";
            $params[':id_ficha'] = $idFicha;
        }

        if ($idInstructor !== null && $idInstructor > 0) {
            $sql .= " AND (h.instructor_id = :id_instructor OR (h.instructor_id IS NULL AND f.fk_usuario = :id_instructor))";
            $params[':id_instructor'] = $idInstructor;
        }

        $sql .= " ORDER BY COALESCE(h.fecha_inicio, '9999-12-31') ASC,
                           FIELD(h.dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Miercoles', 'Jueves', 'Viernes', 'Sábado', 'Sabado', 'Domingo'),
                           h.entrada ASC";

        $stmt = $conexion->prepare($sql);
        foreach ($params as $clave => $valor) {
            $stmt->bindValue($clave, $valor);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los horarios/jornadas asignados a un instructor estrictamente para el día de hoy.
     * Filtra por la fecha exacta de hoy para evitar la duplicación de tarjetas por históricos importados.
     */
    public static function obtenerHorariosHoyInstructor(int $idInstructor, ?string $fechaHoy = null): array
    {
        $conexion = (new Conexion())->conectar();
        if (!$fechaHoy) {
            $fechaHoy = date('Y-m-d');
        }

        $diaSemana = date('l', strtotime($fechaHoy));
        $diasMap = [
            'Monday'    => 'Lunes',
            'Tuesday'   => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday'  => 'Jueves',
            'Friday'    => 'Viernes',
            'Saturday'  => 'Sábado',
            'Sunday'    => 'Domingo'
        ];
        $diasMapAlt = [
            'Monday'    => 'Lunes',
            'Tuesday'   => 'Martes',
            'Wednesday' => 'Miercoles',
            'Thursday'  => 'Jueves',
            'Friday'    => 'Viernes',
            'Saturday'  => 'Sabado',
            'Sunday'    => 'Domingo'
        ];
        $diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;
        $diaSemanaAlt = $diasMapAlt[$diaSemana] ?? $diaSemana;

        $sql = "SELECT f.id_ficha, f.nombre_programa, f.jornada, 
                       h.id_horario, h.entrada, h.salida, h.fecha_inicio, h.fecha_fin,
                       COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal, 'Por definir') AS ambiente,
                       COUNT(a.id_aprendiz) AS total_aprendices 
                FROM ficha f 
                INNER JOIN horario h ON h.fk_ficha = f.id_ficha 
                LEFT JOIN aprendiz a ON a.fk_ficha = f.id_ficha 
                WHERE (h.instructor_id = :id_instructor OR (h.instructor_id IS NULL AND f.fk_usuario = :id_instructor))
                  AND (h.dia_semana = :dia_semana OR h.dia_semana = :dia_semana_alt) 
                  AND (h.estado = 'Activo' OR h.estado IS NULL)
                  AND (h.fecha_inicio = :fecha_hoy OR (:fecha_hoy_range BETWEEN h.fecha_inicio AND h.fecha_fin))
                GROUP BY f.id_ficha, f.nombre_programa, f.jornada, h.id_horario, h.entrada, h.salida, h.fecha_inicio, h.fecha_fin, h.ambiente
                ORDER BY h.entrada ASC";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':id_instructor'   => $idInstructor,
            ':dia_semana'      => $diaSemanaEs,
            ':dia_semana_alt'  => $diaSemanaAlt,
            ':fecha_hoy'       => $fechaHoy,
            ':fecha_hoy_range' => $fechaHoy
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los horarios/jornadas de hoy para todas las fichas (vista Administrador / Métricas rápidas).
     * Filtra estrictamente por la fecha exacta de hoy.
     */
    public static function obtenerHorariosHoyAdmin(?string $fechaHoy = null): array
    {
        $conexion = (new Conexion())->conectar();
        if (!$fechaHoy) {
            $fechaHoy = date('Y-m-d');
        }

        $diaSemana = date('l', strtotime($fechaHoy));
        $diasMap = [
            'Monday'    => 'Lunes',
            'Tuesday'   => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday'  => 'Jueves',
            'Friday'    => 'Viernes',
            'Saturday'  => 'Sábado',
            'Sunday'    => 'Domingo'
        ];
        $diasMapAlt = [
            'Monday'    => 'Lunes',
            'Tuesday'   => 'Martes',
            'Wednesday' => 'Miercoles',
            'Thursday'  => 'Jueves',
            'Friday'    => 'Viernes',
            'Saturday'  => 'Sabado',
            'Sunday'    => 'Domingo'
        ];
        $diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;
        $diaSemanaAlt = $diasMapAlt[$diaSemana] ?? $diaSemana;

        $sql = "SELECT f.id_ficha, f.nombre_programa, f.jornada, 
                       h.id_horario, h.entrada, h.salida, h.fecha_inicio, h.fecha_fin,
                       COALESCE(NULLIF(h.ambiente, ''), f.ambiente_principal, 'Por definir') AS ambiente,
                       COALESCE(CONCAT(u.nombre, ' ', u.apellido), 'Por asignar') AS instructor_nombre,
                       COUNT(a.id_aprendiz) AS total_aprendices 
                FROM ficha f 
                INNER JOIN horario h ON h.fk_ficha = f.id_ficha 
                LEFT JOIN usuario u ON COALESCE(h.instructor_id, f.fk_usuario) = u.id_usuario
                LEFT JOIN aprendiz a ON a.fk_ficha = f.id_ficha 
                WHERE (h.dia_semana = :dia_semana OR h.dia_semana = :dia_semana_alt) 
                  AND (h.estado = 'Activo' OR h.estado IS NULL)
                  AND (h.fecha_inicio = :fecha_hoy OR (:fecha_hoy_range BETWEEN h.fecha_inicio AND h.fecha_fin))
                GROUP BY f.id_ficha, f.nombre_programa, f.jornada, h.id_horario, h.entrada, h.salida, h.fecha_inicio, h.fecha_fin, h.ambiente, u.nombre, u.apellido
                ORDER BY h.entrada ASC";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':dia_semana'      => $diaSemanaEs,
            ':dia_semana_alt'  => $diaSemanaAlt,
            ':fecha_hoy'       => $fechaHoy,
            ':fecha_hoy_range' => $fechaHoy
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function obtenerHorarioPorId(int $idHorario): ?array
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare(
            "SELECT h.*, f.nombre_programa, f.jornada, f.ambiente_principal,
                    COALESCE(
                        CONCAT(u_esp.nombre, ' ', u_esp.apellido),
                        CONCAT(u_lider.nombre, ' ', u_lider.apellido)
                    ) AS instructor_nombre,
                    COALESCE(u_esp.id_usuario, u_lider.id_usuario) AS instructor_id_efectivo
             FROM horario h
             INNER JOIN ficha f ON h.fk_ficha = f.id_ficha
             LEFT JOIN usuario u_esp ON h.instructor_id = u_esp.id_usuario
             LEFT JOIN usuario u_lider ON f.fk_usuario = u_lider.id_usuario
             WHERE h.id_horario = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $idHorario, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = $stmt->fetch();
        return $resultado ?: null;
    }

    /**
     * Calcula la estructura completa de fechas para la semana consultada.
     * Retorna los días (Lunes a Sábado o Domingo), fecha de inicio, fin,
     * controles de navegación y banderas de día actual.
     */
    public static function calcularEstructuraSemana(?string $fechaReferencia = null, bool $incluirDomingo = false): array
    {
        date_default_timezone_set('America/Bogota');
        $hoy = date('Y-m-d');

        // Validar formato Y-m-d de fechaReferencia
        if (!$fechaReferencia || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaReferencia)) {
            $fechaReferencia = $hoy;
        }

        $dt = new DateTime($fechaReferencia, new DateTimeZone('America/Bogota'));
        // N: 1 (Lunes) a 7 (Domingo)
        $diaSemanaN = (int)$dt->format('N');

        // Retroceder al Lunes de esa semana
        $lunes = clone $dt;
        if ($diaSemanaN > 1) {
            $lunes->modify('-' . ($diaSemanaN - 1) . ' days');
        }

        // Construir los días de la semana
        $dias = [];
        $totalDias = $incluirDomingo ? 7 : 6; // Por defecto LUN-SÁB como en la referencia visual

        for ($i = 0; $i < $totalDias; $i++) {
            $diaCurrent = clone $lunes;
            if ($i > 0) {
                $diaCurrent->modify('+' . $i . ' days');
            }
            $fechaYmd = $diaCurrent->format('Y-m-d');
            $numDia = (int)$diaCurrent->format('d');
            $indiceSemana = $i + 1;

            $metaDia = self::DIAS_SEMANA[$indiceSemana];

            $dias[] = [
                'indice'        => $indiceSemana,
                'nombre'        => $metaDia['nombre'],
                'abrev'         => $metaDia['abrev'],
                'alt'           => $metaDia['alt'],
                'numero'        => $numDia,
                'fecha'         => $fechaYmd,
                'es_hoy'        => ($fechaYmd === $hoy),
                'mes_numero'    => (int)$diaCurrent->format('m'),
                'mes_nombre'    => self::MESES[(int)$diaCurrent->format('n')],
                'anio'          => (int)$diaCurrent->format('Y')
            ];
        }

        // Fechas límites de la semana consultada
        $fechaInicioSemana = $dias[0]['fecha'];
        $fechaFinSemana = $dias[count($dias) - 1]['fecha'];

        // Navegación: semana anterior y siguiente
        $semanaAnterior = clone $lunes;
        $semanaAnterior->modify('-7 days');

        $semanaSiguiente = clone $lunes;
        $semanaSiguiente->modify('+7 days');

        // Etiqueta legible del rango: Ej "12 de Octubre - 17 de Octubre, 2026"
        $primerDia = $dias[0];
        $ultimoDia = $dias[count($dias) - 1];
        if ($primerDia['mes_numero'] === $ultimoDia['mes_numero']) {
            $rangoTexto = "{$primerDia['numero']} al {$ultimoDia['numero']} de {$primerDia['mes_nombre']}, {$primerDia['anio']}";
        } else {
            $rangoTexto = "{$primerDia['numero']} de {$primerDia['mes_nombre']} al {$ultimoDia['numero']} de {$ultimoDia['mes_nombre']}, {$ultimoDia['anio']}";
        }

        return [
            'dias'                  => $dias,
            'fecha_referencia'      => $fechaReferencia,
            'fecha_inicio_semana'   => $fechaInicioSemana,
            'fecha_fin_semana'      => $fechaFinSemana,
            'fecha_semana_anterior' => $semanaAnterior->format('Y-m-d'),
            'fecha_semana_actual'   => $hoy,
            'fecha_semana_siguiente'=> $semanaSiguiente->format('Y-m-d'),
            'rango_texto'           => $rangoTexto,
            'es_semana_actual'      => ($hoy >= $fechaInicioSemana && $hoy <= $fechaFinSemana)
        ];
    }

    /**
     * Convierte cadena HH:MM o HH:MM:SS a minutos desde las 00:00
     */
    public static function timeToMinutes(string $timeStr): int
    {
        $parts = explode(':', $timeStr);
        $h = isset($parts[0]) ? (int)$parts[0] : 0;
        $m = isset($parts[1]) ? (int)$parts[1] : 0;
        return ($h * 60) + $m;
    }

    /**
     * Formatea minutos a string legible de 12 horas: ej "06:00 am", "01:30 pm"
     */
    public static function minutesToReadable(int $minutes): string
    {
        $h = (int)floor($minutes / 60);
        $m = $minutes % 60;
        $ampm = $h >= 12 ? 'pm' : 'am';
        $h12 = $h % 12;
        if ($h12 === 0) $h12 = 12;
        return sprintf('%02d:%02d %s', $h12, $m, $ampm);
    }

    /**
     * Procesa los eventos para ubicarlos geométricamente en la cuadrícula temporal.
     * Calcula la altura proporcional según duración real y resuelve superposiciones
     * dividiendo equitativamente el ancho de columna.
     *
     * @param array $horarios Lista de registros de horarios desde la base de datos
     * @param array $diasSemana Array retornado por calcularEstructuraSemana()['dias']
     * @param int $rowHeightPx Altura de cada celda de 1 hora en píxeles (default 64px)
     * @return array [ 'hora_minima' => int, 'hora_maxima' => int, 'horas_grid' => array, 'dias_con_eventos' => array ]
     */
    public static function procesarEventosParaCuadricula(
        array $horarios,
        array $diasSemana,
        int $rowHeightPx = 64
    ): array {
        // Determinamos el rango horario de la cuadrícula.
        // Base institucional SENA: 06:00 a 22:00
        $horaMinima = 6;
        $horaMaxima = 22;

        // Comprobamos si hay clases fuera del rango para expandir automáticamente la cuadrícula
        foreach ($horarios as $h) {
            $minEntrada = self::timeToMinutes($h['entrada']);
            $minSalida = self::timeToMinutes($h['salida']);
            $hEntrada = (int)floor($minEntrada / 60);
            $hSalida = (int)ceil($minSalida / 60);

            if ($hEntrada < $horaMinima) {
                $horaMinima = max(0, $hEntrada);
            }
            if ($hSalida > $horaMaxima) {
                $horaMaxima = min(24, $hSalida);
            }
        }

        // Construir la lista de horas para la columna izquierda
        $horasGrid = [];
        for ($h = $horaMinima; $h <= $horaMaxima; $h++) {
            $ampm = ($h >= 12 && $h < 24) ? 'pm' : 'am';
            $h12 = $h % 12;
            if ($h12 === 0) $h12 = 12;

            $horasGrid[] = [
                'hora_24' => sprintf('%02d:00', $h),
                'hora_12' => sprintf('%02d:00 (%d %s)', $h, $h12, $ampm),
                'numero'  => $h
            ];
        }

        // Agrupar horarios por día de la semana
        $mapaDias = [];
        foreach ($diasSemana as $dia) {
            $mapaDias[$dia['nombre']] = [
                'info' => $dia,
                'eventos_brutos' => []
            ];
            // Mapear también variaciones sin tilde
            if ($dia['alt'] !== $dia['nombre']) {
                $mapaDias[$dia['alt']] = &$mapaDias[$dia['nombre']];
            }
        }

        foreach ($horarios as $idx => $h) {
            $diaNorm = self::normalizarDia($h['dia_semana']);
            if (isset($mapaDias[$diaNorm])) {
                $mapaDias[$diaNorm]['eventos_brutos'][] = $h;
            }
        }

        // Procesar posicionamiento y superposiciones por cada día
        $diasConEventos = [];
        $contadorSesionGlobal = 1;

        foreach ($diasSemana as $dia) {
            $nombreDia = $dia['nombre'];
            $fechaDia = $dia['fecha'];
            $eventosBrutos = $mapaDias[$nombreDia]['eventos_brutos'] ?? [];

            // Filtrar eventos por vigencia de fecha (si el horario tiene fecha_inicio/fecha_fin)
            $eventosValidos = [];
            foreach ($eventosBrutos as $ev) {
                if (!empty($ev['fecha_inicio']) && $fechaDia < $ev['fecha_inicio']) {
                    continue;
                }
                if (!empty($ev['fecha_fin']) && $fechaDia > $ev['fecha_fin']) {
                    continue;
                }
                $eventosValidos[] = $ev;
            }

            // Ordenar por hora de entrada ascendente
            usort($eventosValidos, function ($a, $b) {
                return strcmp($a['entrada'], $b['entrada']);
            });

            // Modelar cada evento con minutos y posiciones
            $eventosModelados = [];
            $contadorBloqueDia = 1;
            foreach ($eventosValidos as $ev) {
                $startMin = self::timeToMinutes($ev['entrada']);
                $endMin = self::timeToMinutes($ev['salida']);

                if ($endMin <= $startMin) {
                    $endMin = $startMin + 60; // Salvaguarda
                }

                $duracionMin = $endMin - $startMin;
                $duracionHoras = round($duracionMin / 60, 1);

                $calStartMin = $horaMinima * 60;
                $topPx = (($startMin - $calStartMin) / 60) * $rowHeightPx;
                $heightPx = ($duracionMin / 60) * $rowHeightPx;

                // Generar etiqueta de bloque
                $numBloque = $contadorBloqueDia++;
                $labelBloque = "Bloque {$numBloque} • " . self::minutesToReadable($startMin) . " - " . self::minutesToReadable($endMin);

                // Título principal: formación / programa / competencia
                $tituloFormacion = !empty($ev['competencia'])
                    ? $ev['competencia']
                    : ($ev['nombre_programa'] ?? 'Formación SENA');

                $eventosModelados[] = [
                    'id_horario'      => (int)$ev['id_horario'],
                    'id_ficha'        => (int)$ev['id_ficha'],
                    'nombre_programa' => $ev['nombre_programa'],
                    'jornada'         => $ev['jornada'] ?? '',
                    'competencia'     => $ev['competencia'] ?? '',
                    'tipo_bloque'     => $ev['tipo_bloque'] ?? 'completo',
                    'ambiente'        => $ev['ambiente'],
                    'instructor'      => $ev['instructor_nombre'] ?? 'Por definir',
                    'instructor_id'   => $ev['instructor_id'] ?? null,
                    'observaciones'   => $ev['observaciones'] ?? '',
                    'dia_semana'      => $nombreDia,
                    'fecha_dia'       => $fechaDia,
                    'start_min'       => $startMin,
                    'end_min'         => $endMin,
                    'duracion_min'    => $duracionMin,
                    'duracion_horas'  => $duracionHoras,
                    'hora_inicio_str' => substr($ev['entrada'], 0, 5),
                    'hora_fin_str'    => substr($ev['salida'], 0, 5),
                    'hora_inicio_ampm'=> self::minutesToReadable($startMin),
                    'hora_fin_ampm'   => self::minutesToReadable($endMin),
                    'top_px'          => $topPx,
                    'height_px'       => max(28, $heightPx),
                    'label_bloque'    => $labelBloque,
                    'titulo_bloque'   => mb_strtoupper($tituloFormacion, 'UTF-8'),
                    // Inicializar columnas para resolución de solapamientos
                    'col_index'       => 0,
                    'total_cols'      => 1
                ];
            }

            // Resolver superposiciones (overlap clustering)
            $eventosResueltos = self::resolverSuperposiciones($eventosModelados);

            $diasConEventos[] = [
                'info'    => $dia,
                'eventos' => $eventosResueltos
            ];
        }

        return [
            'hora_minima'      => $horaMinima,
            'hora_maxima'      => $horaMaxima,
            'row_height_px'    => $rowHeightPx,
            'total_horas'      => ($horaMaxima - $horaMinima),
            'horas_grid'       => $horasGrid,
            'dias_con_eventos' => $diasConEventos
        ];
    }

    /**
     * Algoritmo de clustering para clases simultáneas:
     * Si dos o más clases coinciden en el mismo día y hora,
     * las divide horizontalmente en sub-columnas proporcionales.
     */
    private static function resolverSuperposiciones(array $eventos): array
    {
        if (empty($eventos)) {
            return [];
        }

        $n = count($eventos);
        // Construir grupos conectados (clusters)
        $clusters = [];
        $currentCluster = [];
        $clusterEnd = -1;

        foreach ($eventos as $ev) {
            if (empty($currentCluster)) {
                $currentCluster[] = $ev;
                $clusterEnd = $ev['end_min'];
            } else {
                if ($ev['start_min'] < $clusterEnd) {
                    // Hay solapamiento con el cluster actual
                    $currentCluster[] = $ev;
                    $clusterEnd = max($clusterEnd, $ev['end_min']);
                } else {
                    // Se inicia un nuevo cluster independiente
                    $clusters[] = $currentCluster;
                    $currentCluster = [$ev];
                    $clusterEnd = $ev['end_min'];
                }
            }
        }
        if (!empty($currentCluster)) {
            $clusters[] = $currentCluster;
        }

        $resultado = [];

        // Asignar columnas dentro de cada cluster
        foreach ($clusters as $cluster) {
            $columnas = []; // Cada entrada es el end_min de la última clase en esa sub-columna

            foreach ($cluster as &$ev) {
                $colAsignada = -1;
                for ($c = 0; $c < count($columnas); $c++) {
                    if ($ev['start_min'] >= $columnas[$c]) {
                        $colAsignada = $c;
                        $columnas[$c] = $ev['end_min'];
                        break;
                    }
                }
                if ($colAsignada === -1) {
                    $colAsignada = count($columnas);
                    $columnas[] = $ev['end_min'];
                }
                $ev['col_index'] = $colAsignada;
            }
            unset($ev);

            $totalCols = max(1, count($columnas));
            foreach ($cluster as &$ev) {
                $ev['total_cols'] = $totalCols;
                $ev['width_percent'] = 100 / $totalCols;
                $ev['left_percent'] = $ev['col_index'] * (100 / $totalCols);
                $resultado[] = $ev;
            }
            unset($ev);
        }

        return $resultado;
    }
}
