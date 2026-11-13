<?php
/**
 * =============================================================================
 * MODELO: GESTIÓN DE INTENTOS DE LOGIN Y RATE LIMITING (LoginAttemptModel.php)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Responsable de mitigar ataques de fuerza bruta mediante bloqueo temporal
 * por exceso de intentos fallidos (5 intentos máximos / 10 minutos de bloqueo).
 * Rastrea simultáneamente por identificador de cuenta y por dirección IP.
 * =============================================================================
 */

require_once dirname(__DIR__) . '/config/conexion.php';

class LoginAttemptModel
{
    public const MAX_INTENTOS = 5;
    public const MINUTOS_BLOQUEO = 10;
    public const SEGUNDOS_BLOQUEO = 600;

    /**
     * Resuelve la dirección IP del cliente de forma segura y normalizada.
     */
    public static function obtenerIpCliente(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $partes = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidata = trim($partes[0]);
            if (filter_var($candidata, FILTER_VALIDATE_IP)) {
                $ip = $candidata;
            }
        }

        if ($ip === '::1') {
            return '127.0.0.1';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    /**
     * Normaliza el identificador de usuario para evitar variaciones por mayúsculas o espacios.
     */
    public static function normalizarUsuario(string $usuario): string
    {
        return mb_strtolower(trim($usuario), 'UTF-8');
    }

    /**
     * Verifica si el usuario o la dirección IP se encuentran actualmente bajo bloqueo temporal.
     *
     * @param string $usuario Identificador o documento ingresado
     * @param string $ip Dirección IP del cliente
     * @return array|null Datos del bloqueo activo si existe (segundos y minutos restantes), o null si está habilitado.
     */
    public static function verificarBloqueo(string $usuario, string $ip): ?array
    {
        $usuarioNorm = self::normalizarUsuario($usuario);

        try {
            $conexion = (new Conexion())->conectar();
            $sql = "SELECT id, usuario_intento, ip_address, intentos, ultimo_intento, bloqueado_hasta,
                           TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) AS segundos_restantes
                    FROM intentos_login
                    WHERE (usuario_intento = :usuario OR ip_address = :ip)
                      AND bloqueado_hasta IS NOT NULL
                      AND bloqueado_hasta > NOW()
                    ORDER BY bloqueado_hasta DESC
                    LIMIT 1";

            $stmt = $conexion->prepare($sql);
            $stmt->bindValue(':usuario', $usuarioNorm, PDO::PARAM_STR);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->execute();

            $bloqueo = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($bloqueo && (int)$bloqueo['segundos_restantes'] > 0) {
                $segundos = (int)$bloqueo['segundos_restantes'];
                return [
                    'bloqueado'          => true,
                    'segundos_restantes' => $segundos,
                    'minutos_restantes'  => (int)ceil($segundos / 60),
                    'usuario_bloqueado'  => $bloqueo['usuario_intento'],
                    'ip_bloqueada'       => $bloqueo['ip_address'],
                    'bloqueado_hasta'    => $bloqueo['bloqueado_hasta'],
                    'motivo'             => ($bloqueo['usuario_intento'] === $usuarioNorm && $bloqueo['ip_address'] === $ip)
                        ? 'usuario_ip'
                        : ($bloqueo['usuario_intento'] === $usuarioNorm ? 'usuario' : 'ip')
                ];
            }

            return null;

        } catch (Throwable $e) {
            error_log("Error en LoginAttemptModel::verificarBloqueo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra un intento fallido de inicio de sesión.
     * Incrementa el contador y, al alcanzar el 5.° fallo, aplica bloqueo de 10 minutos exactos.
     *
     * @param string $usuario Identificador o documento ingresado
     * @param string $ip Dirección IP del cliente
     * @return array Estado del intento: intentos acumulados, intentos restantes y estado de bloqueo.
     */
    public static function registrarFallo(string $usuario, string $ip): array
    {
        $usuarioNorm = self::normalizarUsuario($usuario);

        try {
            $conexion = (new Conexion())->conectar();

            // 1. Consultar registro existente para este par usuario/IP
            $sqlConsulta = "SELECT id, intentos, ultimo_intento, bloqueado_hasta,
                                   TIMESTAMPDIFF(SECOND, ultimo_intento, NOW()) AS segundos_desde_ultimo,
                                   TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta) AS segundos_bloqueo_activo
                            FROM intentos_login
                            WHERE usuario_intento = :usuario AND ip_address = :ip
                            LIMIT 1";

            $stmtConsulta = $conexion->prepare($sqlConsulta);
            $stmtConsulta->execute([':usuario' => $usuarioNorm, ':ip' => $ip]);
            $registro = $stmtConsulta->fetch(PDO::FETCH_ASSOC);
            $stmtConsulta->closeCursor();

            $nuevosIntentos = 1;

            if ($registro) {
                $segundosDesdeUltimo = (int)($registro['segundos_desde_ultimo'] ?? 0);
                $bloqueadoActivo = ((int)($registro['segundos_bloqueo_activo'] ?? 0) > 0);

                // Si pasaron más de 10 minutos desde el último intento o el bloqueo expiró, reiniciar contador
                if (!$bloqueadoActivo && $segundosDesdeUltimo > self::SEGUNDOS_BLOQUEO) {
                    $nuevosIntentos = 1;
                } else {
                    $nuevosIntentos = (int)$registro['intentos'] + 1;
                }

                $esBloqueado = ($nuevosIntentos >= self::MAX_INTENTOS);

                if ($esBloqueado) {
                    $sqlUpdate = "UPDATE intentos_login
                                  SET intentos = :intentos,
                                      ultimo_intento = NOW(),
                                      bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                                  WHERE id = :id";
                } else {
                    $sqlUpdate = "UPDATE intentos_login
                                  SET intentos = :intentos,
                                      ultimo_intento = NOW(),
                                      bloqueado_hasta = NULL
                                  WHERE id = :id";
                }

                $stmtUpdate = $conexion->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':intentos' => $nuevosIntentos,
                    ':id' => (int)$registro['id']
                ]);
                $stmtUpdate->closeCursor();

            } else {
                $esBloqueado = false;
                $sqlInsert = "INSERT INTO intentos_login (usuario_intento, ip_address, intentos, ultimo_intento, bloqueado_hasta)
                              VALUES (:usuario, :ip, 1, NOW(), NULL)";
                $stmtInsert = $conexion->prepare($sqlInsert);
                $stmtInsert->execute([':usuario' => $usuarioNorm, ':ip' => $ip]);
                $stmtInsert->closeCursor();
            }

            // 2. Control de mitigación de fuerza bruta distribuida por IP (múltiples usuarios distintos)
            if (!$esBloqueado) {
                $sqlIpCheck = "SELECT SUM(intentos) AS total_ip
                               FROM intentos_login
                               WHERE ip_address = :ip
                                 AND ultimo_intento >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)";
                $stmtIpCheck = $conexion->prepare($sqlIpCheck);
                $stmtIpCheck->execute([':ip' => $ip]);
                $totalIp = (int)($stmtIpCheck->fetchColumn() ?: 0);
                $stmtIpCheck->closeCursor();

                if ($totalIp >= self::MAX_INTENTOS) {
                    $esBloqueado = true;
                    $sqlLockIp = "UPDATE intentos_login
                                  SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL 10 MINUTE)
                                  WHERE ip_address = :ip";
                    $stmtLockIp = $conexion->prepare($sqlLockIp);
                    $stmtLockIp->execute([':ip' => $ip]);
                    $stmtLockIp->closeCursor();
                }
            }

            $intentosRestantes = max(0, self::MAX_INTENTOS - $nuevosIntentos);
            $segundosRestantes = $esBloqueado ? self::SEGUNDOS_BLOQUEO : 0;
            $minutosRestantes = $esBloqueado ? self::MINUTOS_BLOQUEO : 0;

            return [
                'intentos'           => $nuevosIntentos,
                'max_intentos'       => self::MAX_INTENTOS,
                'intentos_restantes' => $intentosRestantes,
                'bloqueado'          => $esBloqueado,
                'segundos_restantes' => $segundosRestantes,
                'minutos_restantes'  => $minutosRestantes
            ];

        } catch (Throwable $e) {
            error_log("Error en LoginAttemptModel::registrarFallo: " . $e->getMessage());
            return [
                'intentos'           => 1,
                'max_intentos'       => self::MAX_INTENTOS,
                'intentos_restantes' => 4,
                'bloqueado'          => false,
                'segundos_restantes' => 0,
                'minutos_restantes'  => 0
            ];
        }
    }

    /**
     * Limpia de forma inmediata el contador de fallos tras un inicio de sesión exitoso.
     *
     * @param string $usuario Identificador o documento autenticado
     * @param string $ip Dirección IP del cliente
     */
    public static function limpiarIntentos(string $usuario, string $ip): void
    {
        $usuarioNorm = self::normalizarUsuario($usuario);

        try {
            $conexion = (new Conexion())->conectar();

            // Eliminar registros del usuario autenticado y del par con la IP
            $sql = "DELETE FROM intentos_login
                    WHERE usuario_intento = :usuario
                       OR (ip_address = :ip AND (bloqueado_hasta IS NULL OR bloqueado_hasta <= NOW()))";

            $stmt = $conexion->prepare($sql);
            $stmt->execute([':usuario' => $usuarioNorm, ':ip' => $ip]);
            $stmt->closeCursor();

        } catch (Throwable $e) {
            error_log("Error en LoginAttemptModel::limpiarIntentos: " . $e->getMessage());
        }
    }

    /**
     * Mitiga ataques de enumeración basados en medición de tiempo cuando el usuario no existe.
     */
    public static function mitigarEnumeracionTiempo(string $contrasena): void
    {
        $hashFalso = '$2y$10$e0MYzXyjpJS7Pd0RVvPGceE5X9e9sXn3bTkmk99U0r8Y8x29mF7wK';
        password_verify($contrasena, $hashFalso);
    }
}
