<?php
/**
 * =============================================================================
 * CLASE DE SERVICIO: CONTROL DE ACCESO Y MITIGACIÓN DE FUERZA BRUTA (RATE LIMITER)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * =============================================================================
 * Implementa control de intentos fallidos por cuenta y por IP con:
 * - Operaciones atómicas bajo transacciones InnoDB con bloqueo de fila (FOR UPDATE)
 *   para prevenir condiciones de carrera.
 * - Bloqueo temporal configurable (5 intentos / 15 minutos).
 * - Espera progresiva entre reintentos.
 * - Mitigación de enumeración de usuarios (tiempo constante y mensajes genéricos).
 * - Resolución segura de dirección IP sin confiar ciegamente en cabeceras falsificables.
 * - Registro de auditoría forense sin almacenar contraseñas ni datos sensibles.
 */

class RateLimiter
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, ?array $config = null)
    {
        $this->db = $db;
        $this->config = $config ?? self::cargarConfiguracion();
    }

    /**
     * Carga la configuración de seguridad con valores predeterminados seguros
     */
    public static function cargarConfiguracion(): array
    {
        $rutaConfig = dirname(__DIR__) . '/config/seguridad.php';
        $defecto = [
            'max_intentos_cuenta' => 5,
            'max_intentos_ip'     => 15,
            'ventana_tiempo_seg'  => 900,
            'bloqueo_base_seg'    => 900,
            'espera_progresiva'   => true,
            'esperas_progresivas' => [
                3 => 5,
                4 => 15,
            ],
            'proxies_confiables'  => [],
        ];

        if (file_exists($rutaConfig)) {
            $cargada = require $rutaConfig;
            if (is_array($cargada)) {
                return array_merge($defecto, $cargada);
            }
        }

        return $defecto;
    }

    /**
     * Obtiene la dirección IP del cliente de forma segura.
     * Solo confía en cabeceras HTTP si la solicitud proviene de un proxy explícitamente autorizado.
     */
    public function obtenerIpCliente(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $proxiesConfiables = $this->config['proxies_confiables'] ?? [];

        if (in_array($remoteAddr, $proxiesConfiables, true)) {
            // Evalúa cabeceras de proxy reverso autorizado
            $cabeceras = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'];
            foreach ($cabeceras as $cabecera) {
                if (!empty($_SERVER[$cabecera])) {
                    // X-Forwarded-For puede contener lista separada por comas (client, proxy1, proxy2)
                    $partes = explode(',', (string)$_SERVER[$cabecera]);
                    $ipCandidata = trim($partes[0]);
                    if (filter_var($ipCandidata, FILTER_VALIDATE_IP)) {
                        return $ipCandidata;
                    }
                }
            }
        }

        // Si no está detrás de un proxy de confianza o la IP no es válida, usar estrictamente REMOTE_ADDR
        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }

    /**
     * Normaliza el identificador de cuenta para evitar variaciones por mayúsculas/minúsculas o espacios
     */
    public function normalizarIdentificador(string $identificador): string
    {
        return mb_strtolower(trim($identificador), 'UTF-8');
    }

    /**
     * Comprueba atómicamente si una cuenta o IP se encuentra bloqueada o bajo espera progresiva.
     * Utiliza SELECT ... FOR UPDATE sobre InnoDB para evitar condiciones de carrera.
     *
     * @return array [
     *   'permitido' => bool,
     *   'motivo'    => 'ok'|'bloqueado_cuenta'|'bloqueado_ip'|'espera_progresiva',
     *   'segundos_restantes' => int
     * ]
     */
    public function verificarIntento(string $identificador, string $ip): array
    {
        $idNorm = $this->normalizarIdentificador($identificador);
        $maxCuenta = (int)$this->config['max_intentos_cuenta'];
        $maxIp = (int)$this->config['max_intentos_ip'];
        $ventana = (int)$this->config['ventana_tiempo_seg'];
        $bloqueoBase = (int)$this->config['bloqueo_base_seg'];
        $esperaProg = !empty($this->config['espera_progresiva']);
        $esperasProg = $this->config['esperas_progresivas'] ?? [];

        try {
            $this->db->beginTransaction();

            // 1. Asegurar existencia de registros para permitir bloqueo de fila exclusivo (FOR UPDATE)
            $stmtUpsert = $this->db->prepare("
                INSERT INTO control_intentos_login (tipo, clave, intentos_fallidos, primer_intento, ultimo_intento, bloqueado_hasta)
                VALUES (?, ?, 0, NOW(), NOW(), NULL)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
            ");
            $stmtUpsert->execute(['cuenta', $idNorm]);
            $stmtUpsert->execute(['ip', $ip]);

            // 2. Consultar y bloquear atómicamente los dos registros (cuenta e ip)
            $stmtSelect = $this->db->prepare("
                SELECT tipo, clave, intentos_fallidos,
                       UNIX_TIMESTAMP(NOW()) AS now_ts,
                       UNIX_TIMESTAMP(primer_intento) AS primer_ts,
                       UNIX_TIMESTAMP(ultimo_intento) AS ultimo_ts,
                       UNIX_TIMESTAMP(bloqueado_hasta) AS bloqueado_ts
                FROM control_intentos_login
                WHERE (tipo = 'cuenta' AND clave = ?) OR (tipo = 'ip' AND clave = ?)
                FOR UPDATE
            ");
            $stmtSelect->execute([$idNorm, $ip]);
            $registros = $stmtSelect->fetchAll(PDO::FETCH_ASSOC);

            $infoCuenta = null;
            $infoIp = null;
            foreach ($registros as $reg) {
                if ($reg['tipo'] === 'cuenta') $infoCuenta = $reg;
                if ($reg['tipo'] === 'ip') $infoIp = $reg;
            }

            // Evaluar IP primero (freno ante ataques distribuidos de origen común)
            if ($infoIp) {
                $evalIp = $this->evaluarEstadoRegistro($infoIp, $maxIp, $ventana, $bloqueoBase, false, []);
                if (!$evalIp['permitido']) {
                    $this->db->commit();
                    return [
                        'permitido' => false,
                        'motivo'    => 'bloqueado_ip',
                        'segundos_restantes' => $evalIp['segundos_restantes']
                    ];
                }
            }

            // Evaluar Cuenta
            if ($infoCuenta) {
                $evalCuenta = $this->evaluarEstadoRegistro($infoCuenta, $maxCuenta, $ventana, $bloqueoBase, $esperaProg, $esperasProg);
                if (!$evalCuenta['permitido']) {
                    $this->db->commit();
                    return [
                        'permitido' => false,
                        'motivo'    => $evalCuenta['motivo'],
                        'segundos_restantes' => $evalCuenta['segundos_restantes']
                    ];
                }
            }

            $this->db->commit();
            return [
                'permitido' => true,
                'motivo'    => 'ok',
                'segundos_restantes' => 0
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en RateLimiter::verificarIntento: " . $e->getMessage());
            // En caso de fallo interno, permitir continuar la autenticación para no degradar el servicio
            return [
                'permitido' => true,
                'motivo'    => 'error_degradado',
                'segundos_restantes' => 0
            ];
        }
    }

    /**
     * Evalúa el estado de bloqueo o ventana de un registro específico
     */
    private function evaluarEstadoRegistro(array $reg, int $maxIntentos, int $ventanaSeg, int $bloqueoBaseSeg, bool $evaluarEspera, array $esperasProg): array
    {
        $now = (int)$reg['now_ts'];
        $bloqueadoTs = $reg['bloqueado_ts'] ? (int)$reg['bloqueado_ts'] : null;
        $primerTs = (int)$reg['primer_ts'];
        $ultimoTs = (int)$reg['ultimo_ts'];
        $intentos = (int)$reg['intentos_fallidos'];

        // 1. ¿Existe un bloqueo activo vigente?
        if ($bloqueadoTs !== null) {
            if ($bloqueadoTs > $now) {
                return [
                    'permitido' => false,
                    'motivo'    => ($reg['tipo'] === 'ip') ? 'bloqueado_ip' : 'bloqueado_cuenta',
                    'segundos_restantes' => max(1, $bloqueadoTs - $now)
                ];
            } else {
                // El bloqueo ya expiró: restablecer contadores (recuperación automática)
                $stmtReset = $this->db->prepare("UPDATE control_intentos_login SET intentos_fallidos = 0, bloqueado_hasta = NULL, primer_intento = NOW(), ultimo_intento = NOW() WHERE tipo = ? AND clave = ?");
                $stmtReset->execute([$reg['tipo'], $reg['clave']]);
                $intentos = 0;
            }
        }

        // 2. ¿La ventana de tiempo expiró? (Recuperación automática)
        if (($now - $primerTs) > $ventanaSeg) {
            $stmtReset = $this->db->prepare("UPDATE control_intentos_login SET intentos_fallidos = 0, bloqueado_hasta = NULL, primer_intento = NOW(), ultimo_intento = NOW() WHERE tipo = ? AND clave = ?");
            $stmtReset->execute([$reg['tipo'], $reg['clave']]);
            $intentos = 0;
        }

        // 3. ¿Alcanzó o superó el umbral de intentos fallidos?
        if ($intentos >= $maxIntentos) {
            $stmt = $this->db->prepare("UPDATE control_intentos_login SET bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE tipo = ? AND clave = ?");
            $stmt->execute([$bloqueoBaseSeg, $reg['tipo'], $reg['clave']]);

            return [
                'permitido' => false,
                'motivo'    => ($reg['tipo'] === 'ip') ? 'bloqueado_ip' : 'bloqueado_cuenta',
                'segundos_restantes' => $bloqueoBaseSeg
            ];
        }

        // 4. ¿Aplica espera progresiva entre reintentos?
        if ($evaluarEspera && isset($esperasProg[$intentos])) {
            $esperaRequerida = (int)$esperasProg[$intentos];
            $transcurrido = $now - $ultimoTs;
            if ($transcurrido < $esperaRequerida) {
                return [
                    'permitido' => false,
                    'motivo'    => 'espera_progresiva',
                    'segundos_restantes' => max(1, $esperaRequerida - $transcurrido)
                ];
            }
        }

        return ['permitido' => true, 'motivo' => 'ok', 'segundos_restantes' => 0];
    }

    /**
     * Registra un fallo de autenticación de forma atómica.
     * Incrementa contadores tanto para la cuenta como para la IP.
     */
    public function registrarFallo(string $identificador, string $ip, string $motivo = 'CREDENCIALES_INVALIDAS'): void
    {
        $idNorm = $this->normalizarIdentificador($identificador);
        $maxCuenta = (int)$this->config['max_intentos_cuenta'];
        $maxIp = (int)$this->config['max_intentos_ip'];
        $ventana = (int)$this->config['ventana_tiempo_seg'];
        $bloqueoBase = (int)$this->config['bloqueo_base_seg'];

        try {
            $this->db->beginTransaction();

            $this->incrementarContador('cuenta', $idNorm, $maxCuenta, $ventana, $bloqueoBase);
            $this->incrementarContador('ip', $ip, $maxIp, $ventana, $bloqueoBase);

            // Registro forense sin datos sensibles
            $stmtLog = $this->db->prepare("
                INSERT INTO intentos_login_log (identificador, ip_origen, fecha_registro, motivo)
                VALUES (?, ?, NOW(), ?)
            ");
            $stmtLog->execute([$idNorm, $ip, $motivo]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en RateLimiter::registrarFallo: " . $e->getMessage());
        }
    }

    /**
     * Incrementa atómicamente el contador para una clave específica (cuenta o ip)
     */
    private function incrementarContador(string $tipo, string $clave, int $maxIntentos, int $ventanaSeg, int $bloqueoBaseSeg): void
    {
        // 1. Asegurar existencia de fila en control_intentos_login
        $stmtEnsure = $this->db->prepare("
            INSERT INTO control_intentos_login (tipo, clave, intentos_fallidos, primer_intento, ultimo_intento, bloqueado_hasta)
            VALUES (?, ?, 0, NOW(), NOW(), NULL)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmtEnsure->execute([$tipo, $clave]);

        // 2. Bloquear fila para actualización atómica
        $stmt = $this->db->prepare("
            SELECT intentos_fallidos,
                   UNIX_TIMESTAMP(NOW()) AS now_ts,
                   UNIX_TIMESTAMP(primer_intento) AS primer_ts,
                   UNIX_TIMESTAMP(bloqueado_hasta) AS bloqueado_ts
            FROM control_intentos_login
            WHERE tipo = ? AND clave = ?
            FOR UPDATE
        ");
        $stmt->execute([$tipo, $clave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = (int)$row['now_ts'];
        $primerTs = (int)$row['primer_ts'];
        $intentosActuales = (int)$row['intentos_fallidos'];

        // Si la ventana previa expiró, reiniciar ventana
        if (($now - $primerTs) > $ventanaSeg) {
            $nuevosIntentos = 1;
            $resetPrimer = true;
        } else {
            $nuevosIntentos = $intentosActuales + 1;
            $resetPrimer = false;
        }

        if ($nuevosIntentos >= $maxIntentos) {
            if ($resetPrimer) {
                $stmtUpd = $this->db->prepare("UPDATE control_intentos_login SET intentos_fallidos = ?, primer_intento = NOW(), ultimo_intento = NOW(), bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE tipo = ? AND clave = ?");
                $stmtUpd->execute([$nuevosIntentos, $bloqueoBaseSeg, $tipo, $clave]);
            } else {
                $stmtUpd = $this->db->prepare("UPDATE control_intentos_login SET intentos_fallidos = ?, ultimo_intento = NOW(), bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE tipo = ? AND clave = ?");
                $stmtUpd->execute([$nuevosIntentos, $bloqueoBaseSeg, $tipo, $clave]);
            }
        } else {
            if ($resetPrimer) {
                $stmtUpd = $this->db->prepare("UPDATE control_intentos_login SET intentos_fallidos = ?, primer_intento = NOW(), ultimo_intento = NOW(), bloqueado_hasta = NULL WHERE tipo = ? AND clave = ?");
                $stmtUpd->execute([$nuevosIntentos, $tipo, $clave]);
            } else {
                $stmtUpd = $this->db->prepare("UPDATE control_intentos_login SET intentos_fallidos = ?, ultimo_intento = NOW(), bloqueado_hasta = NULL WHERE tipo = ? AND clave = ?");
                $stmtUpd->execute([$nuevosIntentos, $tipo, $clave]);
            }
        }
    }

    /**
     * Restablece los contadores tras un inicio de sesión exitoso.
     */
    public function registrarExito(string $identificador, string $ip): void
    {
        $idNorm = $this->normalizarIdentificador($identificador);

        try {
            $this->db->beginTransaction();

            // Restablecer contadores de la cuenta autenticada
            $stmt = $this->db->prepare("
                UPDATE control_intentos_login
                SET intentos_fallidos = 0, bloqueado_hasta = NULL, primer_intento = NOW(), ultimo_intento = NOW()
                WHERE tipo = 'cuenta' AND clave = ?
            ");
            $stmt->execute([$idNorm]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Error en RateLimiter::registrarExito: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta una verificación simulada en tiempo constante cuando el usuario no existe en la base de datos.
     * Esto evita que un atacante determine la existencia de usuarios mediante diferencias de tiempo (Timing Attacks).
     */
    public static function mitigarEnumeracionTiempo(string $contrasena): void
    {
        $hashFalso = '$2y$10$e0MYzXyjpJS7Pd0RVvPGceE5X9e9sXn3bTkmk99U0r8Y8x29mF7wK';
        password_verify($contrasena, $hashFalso);
    }
}
