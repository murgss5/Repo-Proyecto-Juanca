<?php
/**
 * Modelo y servicio centralizado de asistencia
 * Gestiona el registro de asistencias por RFID (entrada y salida)
 * Valida automáticamente pertenencia a ficha, horario vigente, puntualidad y prevención de duplicados.
 * Reutilizado tanto por el lector físico como por la herramienta de simulación.
 */

require_once __DIR__ . '/../config/conexion.php';

class AsistenciaModel
{
    /**
     * Días estándar de la semana en español (con y sin tilde para máxima compatibilidad)
     */
    private const DIAS_MAP = [
        'Monday'    => 'Lunes',
        'Tuesday'   => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday'  => 'Jueves',
        'Friday'    => 'Viernes',
        'Saturday'  => 'Sábado',
        'Sunday'    => 'Domingo'
    ];

    private const DIAS_MAP_ALT = [
        'Monday'    => 'Lunes',
        'Tuesday'   => 'Martes',
        'Wednesday' => 'Miercoles',
        'Thursday'  => 'Jueves',
        'Friday'    => 'Viernes',
        'Saturday'  => 'Sabado',
        'Sunday'    => 'Domingo'
    ];

    /**
     * Procesa un registro de asistencia por RFID (entrada o salida) con validación automática
     * de pertenencia a ficha, horario vigente, ventana de puntualidad y duplicados.
     *
     * @param string $codigoRfid Codigo de la tarjeta RFID
     * @param string $tipoRegistro 'entrada' o 'salida'
     * @param PDO|null $conexion Conexion opcional a la base de datos
     * @param int|null $fichaContextoId Ficha opcional enviada en el contexto del escaneo
     * @param string|null $fechaReferencia Fecha opcional (YYYY-MM-DD) para pruebas o simulaciones
     * @param string|null $horaReferencia Hora opcional (HH:MM:SS) para pruebas o simulaciones
     * @return array Resultado estructurado con 'http_code', 'success', 'message' y 'data'
     */
    public static function registrarPorRfid(
        string $codigoRfid,
        string $tipoRegistro = 'entrada',
        ?PDO $conexion = null,
        ?int $fichaContextoId = null,
        ?string $fechaReferencia = null,
        ?string $horaReferencia = null
    ): array {
        date_default_timezone_set('America/Bogota');

        $codigoRfid = trim($codigoRfid);
        if ($codigoRfid === '') {
            return [
                'http_code' => 400,
                'success'   => false,
                'message'   => 'Codigo RFID requerido'
            ];
        }

        if (!in_array($tipoRegistro, ['entrada', 'salida'], true)) {
            return [
                'http_code' => 400,
                'success'   => false,
                'message'   => 'Tipo de registro invalido'
            ];
        }

        if ($conexion === null) {
            $conexion = (new Conexion())->conectar();
        }

        try {
            // 1. Verificar existencia del código RFID y obtener datos del aprendiz
            $q = $conexion->prepare(
                "SELECT a.id_aprendiz, a.codigo_rfid, a.fk_ficha, a.fk_usuario,
                        u.nombre, u.apellido, u.fk_rol, u.identificacion
                 FROM aprendiz a
                 LEFT JOIN usuario u ON u.id_usuario = a.fk_usuario
                 WHERE a.codigo_rfid = :rfid
                 LIMIT 1"
            );
            $q->execute([':rfid' => $codigoRfid]);
            $aprendiz = $q->fetch(PDO::FETCH_ASSOC);

            if (!$aprendiz) {
                return [
                    'http_code' => 404,
                    'success'   => false,
                    'message'   => 'Código RFID no registrado.'
                ];
            }

            // 2. Verificar que el aprendiz esté registrado y habilitado
            if (
                empty($aprendiz['fk_usuario']) ||
                empty($aprendiz['fk_ficha']) ||
                (int)($aprendiz['fk_rol'] ?? 0) !== 3
            ) {
                return [
                    'http_code' => 403,
                    'success'   => false,
                    'message'   => 'Aprendiz no habilitado para registrar asistencia.'
                ];
            }

            $aprendizId = (int)$aprendiz['id_aprendiz'];
            $fichaId = (int)$aprendiz['fk_ficha'];

            // 3. Regla 1: Pertenencia a la ficha
            // Si el escaneo se produce en el contexto de una ficha específica y no coincide con la suya, se rechaza
            if ($fichaContextoId !== null && $fichaId !== (int)$fichaContextoId) {
                return [
                    'http_code' => 403,
                    'success'   => false,
                    'message'   => 'El horario encontrado no corresponde a tu ficha.'
                ];
            }

            // 4. Obtener fecha, hora y día actual con la zona horaria del proyecto ('America/Bogota')
            $fechaHoy = $fechaReferencia ?? date('Y-m-d');
            $horaActual = $horaReferencia ?? date('H:i:s');
            $horaActualDatetime = "$fechaHoy $horaActual";
            $horaActualSec = strtotime($horaActualDatetime);

            $diaIngles = date('l', strtotime($fechaHoy));
            $diaSemanaEs = self::DIAS_MAP[$diaIngles] ?? $diaIngles;
            $diaSemanaAlt = self::DIAS_MAP_ALT[$diaIngles] ?? $diaIngles;

            // 5. Consultar los horarios programados para la ficha del aprendiz en este día
            $qHorarios = $conexion->prepare(
                "SELECT h.id_horario, h.dia_semana, h.entrada, h.salida, h.competencia,
                        h.tipo_bloque, h.ambiente, h.estado, h.fecha_inicio, h.fecha_fin,
                        h.fk_ficha, h.instructor_id
                 FROM horario h
                 WHERE h.fk_ficha = :ficha_id
                   AND (h.dia_semana = :dia_semana OR h.dia_semana = :dia_semana_alt)
                   AND (h.estado = 'Activo' OR h.estado IS NULL)
                   AND (
                       (h.fecha_inicio IS NULL AND h.fecha_fin IS NULL)
                       OR (h.fecha_inicio = :fecha_hoy)
                       OR (:fecha_hoy_range BETWEEN h.fecha_inicio AND h.fecha_fin)
                   )
                 ORDER BY h.entrada ASC"
            );
            $qHorarios->execute([
                ':ficha_id'         => $fichaId,
                ':dia_semana'       => $diaSemanaEs,
                ':dia_semana_alt'   => $diaSemanaAlt,
                ':fecha_hoy'        => $fechaHoy,
                ':fecha_hoy_range'  => $fechaHoy
            ]);
            $horarios = $qHorarios->fetchAll(PDO::FETCH_ASSOC);

            if (empty($horarios)) {
                return [
                    'http_code' => 400,
                    'success'   => false,
                    'message'   => 'No tienes una clase programada para tu ficha en este momento.'
                ];
            }

            // 5.1 Verificar si el instructor tiene una sesión de jornada ABIERTA hoy para esta ficha
            $qSesionAbierta = $conexion->prepare(
                "SELECT id_sesion, horario_id, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
                 FROM sesiones_jornada
                 WHERE ficha_id = :ficha_id
                   AND fecha = :fecha
                   AND estado = 'abierta'
                   AND hora_cierre_real IS NULL
                 ORDER BY id_sesion DESC
                 LIMIT 1"
            );
            $qSesionAbierta->execute([
                ':ficha_id' => $fichaId,
                ':fecha'    => $fechaHoy
            ]);
            $sesionAbierta = $qSesionAbierta->fetch(PDO::FETCH_ASSOC);

            // 6. Clasificar horarios según el momento del escaneo y sesión de jornada activa
            $clasesHabilitadas = [];
            $clasesExpiradas = [];
            $clasesSalida = [];

            // Si hay una sesión de jornada abierta por el instructor, esa clase tiene prioridad absoluta
            if ($sesionAbierta && !empty($sesionAbierta['horario_id'])) {
                foreach ($horarios as $h) {
                    if ((int)$h['id_horario'] === (int)$sesionAbierta['horario_id']) {
                        if ($tipoRegistro === 'entrada') {
                            $clasesHabilitadas[] = $h;
                        } else {
                            $clasesSalida[] = $h;
                        }
                    }
                }
            }

            // Si no se asignó por sesión abierta específica, evaluar por franja horaria estándar
            if (empty($clasesHabilitadas) && empty($clasesSalida)) {
                foreach ($horarios as $h) {
                    $entradaSec = strtotime("$fechaHoy {$h['entrada']}");
                    $salidaSec = strtotime("$fechaHoy {$h['salida']}");

                    // Permitir entrada desde 15 minutos antes hasta la hora de salida de la clase
                    $ventanaInicioEntradaSec = $entradaSec - (15 * 60);

                    if ($tipoRegistro === 'entrada') {
                        if ($horaActualSec >= $ventanaInicioEntradaSec && $horaActualSec <= $salidaSec) {
                            $clasesHabilitadas[] = $h;
                        } elseif ($horaActualSec > $salidaSec) {
                            $clasesExpiradas[] = $h;
                        }
                    } else {
                        // Para salida: dentro del horario de clase o hasta 60 min posteriores
                        if ($horaActualSec >= $entradaSec && $horaActualSec <= ($salidaSec + 3600)) {
                            $clasesSalida[] = $h;
                        }
                    }
                }
            }

            // Procesamiento de salida
            if ($tipoRegistro === 'salida') {
                if (empty($clasesSalida)) {
                    return [
                        'http_code' => 400,
                        'success'   => false,
                        'message'   => 'No tienes una clase programada para tu ficha en este momento.'
                    ];
                }

                // Si hay más de una clase candidata a salida, priorizar la más cercana al momento actual
                if (count($clasesSalida) > 1) {
                    usort($clasesSalida, function ($a, $b) use ($horaActualSec, $fechaHoy) {
                        $diffA = abs($horaActualSec - strtotime("$fechaHoy {$a['salida']}"));
                        $diffB = abs($horaActualSec - strtotime("$fechaHoy {$b['salida']}"));
                        return $diffA <=> $diffB;
                    });
                }

                $claseSeleccionada = $clasesSalida[0];
                $horarioId = (int)$claseSeleccionada['id_horario'];
                $horaEntradaProgSec = strtotime("$fechaHoy {$claseSeleccionada['entrada']}");
                $horaSalidaProgSec = strtotime("$fechaHoy {$claseSeleccionada['salida']}");
                $ventanaInicioClase = date('Y-m-d H:i:s', $horaEntradaProgSec - (15 * 60));
                $finClase = date('Y-m-d H:i:s', $horaSalidaProgSec + 3600);

                // Buscar asistencia correspondiente a esta clase específica
                $q = $conexion->prepare(
                    "SELECT a.id_asistencia, a.entrada, a.salida
                     FROM asistencia a
                     LEFT JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion
                     WHERE a.fk_aprendiz = :aprendiz_id
                       AND a.fecha_asistencia = :fecha
                       AND a.entrada IS NOT NULL
                       AND (
                           a.fk_horario = :horario_id
                           OR (s.horario_id IS NOT NULL AND s.horario_id = :horario_id)
                           OR (a.entrada >= :ventana_inicio AND a.entrada <= :fin_clase)
                       )
                     ORDER BY (CASE WHEN a.salida IS NULL THEN 0 ELSE 1 END) ASC, a.id_asistencia DESC
                     LIMIT 1"
                );
                $q->execute([
                    ':aprendiz_id'    => $aprendizId,
                    ':fecha'          => $fechaHoy,
                    ':horario_id'     => $horarioId,
                    ':ventana_inicio' => $ventanaInicioClase,
                    ':fin_clase'      => $finClase
                ]);
                $asistenciaExistente = $q->fetch(PDO::FETCH_ASSOC);

                // Si no se encuentra vinculada al horario específico, buscar la última entrada de hoy
                if (!$asistenciaExistente) {
                    $qFallback = $conexion->prepare(
                        "SELECT id_asistencia, entrada, salida
                         FROM asistencia
                         WHERE fk_aprendiz = :aprendiz_id
                           AND fecha_asistencia = :fecha
                           AND entrada IS NOT NULL
                         ORDER BY (CASE WHEN salida IS NULL THEN 0 ELSE 1 END) ASC, id_asistencia DESC
                         LIMIT 1"
                    );
                    $qFallback->execute([
                        ':aprendiz_id' => $aprendizId,
                        ':fecha'       => $fechaHoy
                    ]);
                    $asistenciaExistente = $qFallback->fetch(PDO::FETCH_ASSOC);
                }

                if (!$asistenciaExistente) {
                    return [
                        'http_code' => 400,
                        'success'   => false,
                        'message'   => 'El aprendiz no tiene entrada registrada en esta jornada'
                    ];
                }

                if ($asistenciaExistente['salida'] !== null) {
                    return [
                        'http_code' => 400,
                        'success'   => false,
                        'message'   => 'Este aprendiz ya registro su salida'
                    ];
                }

                $horaSalidaProgramadaSec = strtotime("$fechaHoy {$claseSeleccionada['salida']}");
                $minutosSalidaAnticipada = 0;
                if ($horaActualSec < $horaSalidaProgramadaSec) {
                    $minutosSalidaAnticipada = (int)round(($horaSalidaProgramadaSec - $horaActualSec) / 60);
                    if ($minutosSalidaAnticipada < 1) {
                        $minutosSalidaAnticipada = 1;
                    }
                    $estadoSalida = 'SALIDA_ANTICIPADA';
                } else {
                    $estadoSalida = 'SALIDA_NORMAL';
                }

                $horaActualTime = date('H:i:s', strtotime($horaActualDatetime));
                $q = $conexion->prepare(
                    "UPDATE asistencia
                     SET salida = :salida, hora_salida = :hora_salida, estado_salida = :estado_salida, minutos_salida_anticipada = :minutos_salida_anticipada
                     WHERE id_asistencia = :id_asistencia"
                );
                $q->execute([
                    ':salida'                    => $horaActualDatetime,
                    ':hora_salida'               => $horaActualTime,
                    ':estado_salida'             => $estadoSalida,
                    ':minutos_salida_anticipada' => $minutosSalidaAnticipada,
                    ':id_asistencia'             => $asistenciaExistente['id_asistencia']
                ]);

                return [
                    'http_code' => 200,
                    'success'   => true,
                    'message'   => 'Salida registrada correctamente',
                    'data'      => [
                        'asistencia_id'             => (int)$asistenciaExistente['id_asistencia'],
                        'aprendiz'                  => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                        'rfid'                      => $codigoRfid,
                        'hora'                      => date('H:i', strtotime($horaActualDatetime)),
                        'tipo'                      => 'salida',
                        'estado_salida'             => $estadoSalida,
                        'minutos_salida_anticipada' => $minutosSalidaAnticipada,
                        'ficha_id'                  => $fichaId,
                        'horario_id'                => $horarioId
                    ]
                ];
            }

            // Procesamiento de entrada
            // Regla 2: Horarios simultáneos o en conflicto (no elegir arbitrariamente)
            if (count($clasesHabilitadas) > 1) {
                return [
                    'http_code' => 409,
                    'success'   => false,
                    'message'   => 'Existe un conflicto entre los horarios configurados y no se puede determinar una clase válida.'
                ];
            }

            // Regla 5 y Regla 3: Si no hay clase habilitada en este instante
            if (empty($clasesHabilitadas)) {
                if (!empty($clasesExpiradas)) {
                    return [
                        'http_code' => 400,
                        'success'   => false,
                        'message'   => 'La ventana de registro de entrada ha finalizado.'
                    ];
                }

                return [
                    'http_code' => 400,
                    'success'   => false,
                    'message'   => 'No tienes una clase programada para tu ficha en este momento.'
                ];
            }

            // Clase válida identificada automáticamente
            $clase = $clasesHabilitadas[0];
            $horarioId = (int)$clase['id_horario'];
            $horaEntradaProgramadaSec = strtotime("$fechaHoy {$clase['entrada']}");
            $horaSalidaProgramadaSec = strtotime("$fechaHoy {$clase['salida']}");
            $ventanaInicioClase = date('Y-m-d H:i:s', $horaEntradaProgramadaSec - (15 * 60));
            $finClase = date('Y-m-d H:i:s', $horaSalidaProgramadaSec + 3600);

            // Reutilizar $sesionAbierta si ya la consultamos, o buscar la sesión correspondiente
            $sesion = $sesionAbierta;
            if (!$sesion) {
                $qSesion = $conexion->prepare(
                    "SELECT id_sesion, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
                     FROM sesiones_jornada
                     WHERE ficha_id = :ficha_id
                       AND fecha = :fecha
                       AND (horario_id = :horario_id OR horario_id IS NULL)
                     ORDER BY (CASE WHEN horario_id = :horario_id THEN 0 ELSE 1 END) ASC, hora_apertura_real DESC
                     LIMIT 1"
                );
                $qSesion->execute([
                    ':ficha_id'   => $fichaId,
                    ':fecha'      => $fechaHoy,
                    ':horario_id' => $horarioId
                ]);
                $sesion = $qSesion->fetch(PDO::FETCH_ASSOC);
            }

            $sesionId = null;
            if ($sesion) {
                if ($sesion['estado'] === 'cerrada_manual' || $sesion['hora_cierre_real'] !== null) {
                    return [
                        'http_code' => 400,
                        'success'   => false,
                        'message'   => 'La jornada ya ha sido cerrada. No se pueden registrar mas asistencias.'
                    ];
                }
                $sesionId = (int)$sesion['id_sesion'];
            }

            // Regla 4: Evitar registros duplicados para la misma clase y fecha
            $qDuplicado = $conexion->prepare(
                "SELECT a.id_asistencia, a.entrada, a.salida, a.estado_entrada
                 FROM asistencia a
                 LEFT JOIN sesiones_jornada s ON a.fk_sesion_jornada = s.id_sesion
                 WHERE a.fk_aprendiz = :aprendiz_id
                   AND a.fecha_asistencia = :fecha
                   AND (
                       a.fk_horario = :horario_id
                       OR (s.horario_id IS NOT NULL AND s.horario_id = :horario_id)
                       OR (:sesion_id IS NOT NULL AND a.fk_sesion_jornada = :sesion_id)
                       OR (a.entrada IS NOT NULL AND a.entrada >= :ventana_inicio AND a.entrada <= :fin_clase)
                       OR (a.estado_entrada = 'INASISTENCIA' AND (a.fk_horario = :horario_id OR s.horario_id = :horario_id OR a.fk_sesion_jornada = :sesion_id))
                   )
                 LIMIT 1"
            );
            $qDuplicado->execute([
                ':aprendiz_id'    => $aprendizId,
                ':fecha'          => $fechaHoy,
                ':horario_id'     => $horarioId,
                ':sesion_id'      => $sesionId,
                ':ventana_inicio' => $ventanaInicioClase,
                ':fin_clase'      => $finClase
            ]);
            $asistenciaExistente = $qDuplicado->fetch(PDO::FETCH_ASSOC);

            if ($asistenciaExistente) {
                return [
                    'http_code' => 400,
                    'success'   => false,
                    'message'   => 'La asistencia de esta clase ya fue registrada.'
                ];
            }

            // Regla 3: Clasificación de puntualidad vs retardo
            $esPuntual = false;
            if ($sesion && !empty($sesion['hora_limite_puntualidad'])) {
                // Si hay una sesión con hora límite de puntualidad explícita
                $horaLimiteSec = strtotime($sesion['hora_limite_puntualidad']);
                $esPuntual = ($horaActualSec <= $horaLimiteSec);
            } else {
                // Tolerancia estándar de 15 minutos tras la hora programada de inicio
                $esPuntual = ($horaActualSec <= ($horaEntradaProgramadaSec + (15 * 60)));
            }

            if ($esPuntual) {
                $estadoEntrada = 'PUNTUAL';
                $minutosRetardo = 0;
                $mensaje = 'Entrada registrada: PUNTUAL';
            } else {
                $estadoEntrada = 'RETARDO';
                $baseCalculo = ($sesion && !empty($sesion['hora_apertura_real']))
                    ? strtotime($sesion['hora_apertura_real'])
                    : $horaEntradaProgramadaSec;
                $minutosRetardo = (int)round(($horaActualSec - $baseCalculo) / 60);
                if ($minutosRetardo < 1) {
                    $minutosRetardo = 1;
                }
                $mensaje = 'Entrada registrada: RETARDO (' . $minutosRetardo . ' min)';
            }

            $horaActualTime = date('H:i:s', strtotime($horaActualDatetime));
            // Insertar asistencia válida con hora_entrada y hora_salida correctamente mapeadas
            $qInsert = $conexion->prepare(
                "INSERT INTO asistencia (
                    fecha_asistencia, entrada, hora_entrada, estado_entrada, minutos_retardo,
                    fk_aprendiz, fk_sesion_jornada, fk_horario
                ) VALUES (
                    :fecha, :entrada, :hora_entrada, :estado_entrada, :minutos_retardo,
                    :aprendiz_id, :sesion_id, :horario_id
                )"
            );
            $qInsert->execute([
                ':fecha'           => $fechaHoy,
                ':entrada'         => $horaActualDatetime,
                ':hora_entrada'    => $horaActualTime,
                ':estado_entrada'  => $estadoEntrada,
                ':minutos_retardo' => $minutosRetardo,
                ':aprendiz_id'     => $aprendizId,
                ':sesion_id'       => $sesionId,
                ':horario_id'      => $horarioId
            ]);

            $asistenciaId = (int)$conexion->lastInsertId();

            return [
                'http_code' => 200,
                'success'   => true,
                'message'   => $mensaje,
                'data'      => [
                    'asistencia_id'   => $asistenciaId,
                    'aprendiz'        => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                    'rfid'            => $codigoRfid,
                    'hora'            => date('H:i', strtotime($horaActualDatetime)),
                    'estado'          => $estadoEntrada,
                    'minutos_retardo' => $minutosRetardo,
                    'tipo'            => 'entrada',
                    'ficha_id'        => $fichaId,
                    'horario_id'      => $horarioId
                ]
            ];

        } catch (Throwable $e) {
            error_log("Error en AsistenciaModel::registrarPorRfid: " . $e->getMessage());
            return [
                'http_code' => 500,
                'success'   => false,
                'message'   => 'Error en el servidor: ' . $e->getMessage()
            ];
        }
    }
}
