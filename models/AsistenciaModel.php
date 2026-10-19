<?php
/**
 * Modelo y servicio centralizado de asistencia
 * Gestiona el registro de asistencias por RFID (entrada y salida)
 * Reutilizado tanto por el lector fisico como por la herramienta de simulacion
 */

require_once __DIR__ . '/../config/conexion.php';

class AsistenciaModel
{
    /**
     * Procesa un registro de asistencia por RFID (entrada o salida)
     *
     * @param string $codigoRfid Codigo de la tarjeta RFID
     * @param string $tipoRegistro 'entrada' o 'salida'
     * @param PDO|null $conexion Conexion opcional a la base de datos
     * @return array Resultado estructurado con 'http_code', 'success', 'message' y 'data'
     */
    public static function registrarPorRfid(string $codigoRfid, string $tipoRegistro = 'entrada', ?PDO $conexion = null): array
    {
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
            // Buscar aprendiz por RFID
            $q = $conexion->prepare(
                "SELECT a.id_aprendiz, a.fk_ficha, a.fk_usuario, u.nombre, u.apellido
                 FROM aprendiz a
                 INNER JOIN usuario u ON u.id_usuario = a.fk_usuario
                 WHERE a.codigo_rfid = :rfid
                 LIMIT 1"
            );
            $q->execute([':rfid' => $codigoRfid]);
            $aprendiz = $q->fetch();

            if (!$aprendiz) {
                return [
                    'http_code' => 404,
                    'success'   => false,
                    'message'   => 'Aprendiz no encontrado con ese RFID'
                ];
            }

            $aprendizId = (int)$aprendiz['id_aprendiz'];
            $fichaId = (int)$aprendiz['fk_ficha'];
            $fechaHoy = date('Y-m-d');
            $horaActual = date('Y-m-d H:i:s');

            // Buscar sesion de jornada abierta para esta ficha y fecha
            $q = $conexion->prepare(
                "SELECT id_sesion, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
                 FROM sesiones_jornada
                 WHERE ficha_id = :ficha_id
                   AND fecha = :fecha
                   AND estado = 'abierta'
                 ORDER BY hora_apertura_real DESC
                 LIMIT 1"
            );
            $q->execute([
                ':ficha_id' => $fichaId,
                ':fecha'    => $fechaHoy
            ]);
            $sesion = $q->fetch();

            if (!$sesion) {
                return [
                    'http_code' => 400,
                    'success'   => false,
                    'message'   => 'No hay una jornada abierta para esta ficha. El instructor debe abrir la jornada primero.'
                ];
            }

            $sesionId = (int)$sesion['id_sesion'];
            $horaLimitePuntualidad = $sesion['hora_limite_puntualidad'];
            $horaCierreReal = $sesion['hora_cierre_real'];

            // Verificar si la jornada ya fue cerrada
            if ($sesion['estado'] === 'cerrada_manual' || $horaCierreReal !== null) {
                return [
                    'http_code' => 400,
                    'success'   => false,
                    'message'   => 'La jornada ya ha sido cerrada. No se pueden registrar mas asistencias.'
                ];
            }

            // Verificar si ya existe registro de entrada para este aprendiz en esta sesion
            $q = $conexion->prepare(
                "SELECT id_asistencia, entrada, salida FROM asistencia
                 WHERE fk_aprendiz = :aprendiz_id
                   AND fk_sesion_jornada = :sesion_id
                 LIMIT 1"
            );
            $q->execute([
                ':aprendiz_id' => $aprendizId,
                ':sesion_id'   => $sesionId
            ]);
            $asistenciaExistente = $q->fetch();

            if ($tipoRegistro === 'entrada') {
                if ($asistenciaExistente) {
                    return [
                        'http_code' => 400,
                        'success'   => false,
                        'message'   => 'Este aprendiz ya registro su entrada en esta jornada'
                    ];
                }

                // Calculo dinamico de retardo: hora_apertura_real + 10 minutos
                $horaApertura = $sesion['hora_apertura_real'] ?? $horaActual;
                $limiteSinRetardo = strtotime($horaApertura) + (10 * 60);
                $horaLlegada = strtotime($horaActual);

                $minutosRetardo = 0;
                if ($horaLlegada > $limiteSinRetardo) {
                    $minutosRetardo = (int)round(($horaLlegada - $limiteSinRetardo) / 60);
                    if ($minutosRetardo < 1) {
                        $minutosRetardo = 1;
                    }
                    $estadoEntrada = 'RETARDO';
                } else {
                    $estadoEntrada = 'PUNTUAL';
                }
                $esPuntual = ($estadoEntrada === 'PUNTUAL');

                // Insertar nueva asistencia
                $q = $conexion->prepare(
                    "INSERT INTO asistencia (fecha_asistencia, entrada, estado_entrada, minutos_retardo, fk_aprendiz, fk_sesion_jornada)
                     VALUES (:fecha, :entrada, :estado_entrada, :minutos_retardo, :aprendiz_id, :sesion_id)"
                );
                $q->execute([
                    ':fecha'           => $fechaHoy,
                    ':entrada'         => $horaActual,
                    ':estado_entrada'  => $estadoEntrada,
                    ':minutos_retardo' => $minutosRetardo,
                    ':aprendiz_id'     => $aprendizId,
                    ':sesion_id'       => $sesionId
                ]);

                $asistenciaId = (int)$conexion->lastInsertId();

                return [
                    'http_code' => 200,
                    'success'   => true,
                    'message'   => $esPuntual ? 'Entrada registrada: PUNTUAL' : 'Entrada registrada: RETARDO (' . $minutosRetardo . ' min)',
                    'data'      => [
                        'asistencia_id'   => $asistenciaId,
                        'aprendiz'        => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                        'rfid'            => $codigoRfid,
                        'hora'            => date('H:i', strtotime($horaActual)),
                        'estado'          => $estadoEntrada,
                        'minutos_retardo' => $minutosRetardo,
                        'tipo'            => 'entrada'
                    ]
                ];

            } else { // salida
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

                // Actualizar salida
                $q = $conexion->prepare(
                    "UPDATE asistencia
                     SET salida = :salida, estado_salida = 'SALIDA_NORMAL'
                     WHERE id_asistencia = :id_asistencia"
                );
                $q->execute([
                    ':salida'        => $horaActual,
                    ':id_asistencia' => $asistenciaExistente['id_asistencia']
                ]);

                return [
                    'http_code' => 200,
                    'success'   => true,
                    'message'   => 'Salida registrada correctamente',
                    'data'      => [
                        'asistencia_id' => (int)$asistenciaExistente['id_asistencia'],
                        'aprendiz'      => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                        'rfid'          => $codigoRfid,
                        'hora'          => date('H:i', strtotime($horaActual)),
                        'tipo'          => 'salida'
                    ]
                ];
            }

        } catch (Exception $e) {
            return [
                'http_code' => 500,
                'success'   => false,
                'message'   => 'Error en el servidor: ' . $e->getMessage()
            ];
        }
    }
}

