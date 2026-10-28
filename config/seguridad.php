<?php
/**
 * =============================================================================
 * CONFIGURACIÓN DE SEGURIDAD Y CONTROL DE INTENTOS DE LOGIN
 * Sistema de Control de Ingreso de Aprendices - SENA
 * =============================================================================
 * Permite ajustar los umbrales de rate limiting y mitigación de fuerza bruta
 * sin necesidad de modificar los controladores ni la lógica de autenticación.
 */

return [
    // Número máximo de intentos fallidos permitidos por cuenta antes de aplicar bloqueo temporal
    'max_intentos_cuenta' => 5,

    // Número máximo de intentos fallidos permitidos por dirección IP (mitiga ataques distribuidos a múltiples cuentas)
    'max_intentos_ip'     => 15,

    // Ventana de tiempo (en segundos) para la acumulación de intentos fallidos (900 seg = 15 minutos)
    'ventana_tiempo_seg'  => 900,

    // Duración (en segundos) del bloqueo temporal cuando se alcanza el límite (900 seg = 15 minutos)
    'bloqueo_base_seg'    => 900,

    // Habilita una espera progresiva en segundos tras reiterados fallos antes del bloqueo total
    'espera_progresiva'   => true,

    // Segundos mínimos obligatorios entre intentos según número de fallos previos (espera progresiva)
    'esperas_progresivas' => [
        3 => 5,   // Tras 3 intentos fallidos, esperar al menos 5 segundos
        4 => 15,  // Tras 4 intentos fallidos, esperar al menos 15 segundos
    ],

    // Lista de IPs o rangos de proxies reversos de confianza (ej. CDN, Cloudflare o reverse proxies de Hostinger)
    // Solo cuando la conexión proviene de un proxy autorizado aquí se evalúan cabeceras HTTP_X_FORWARDED_FOR o HTTP_CF_CONNECTING_IP.
    'proxies_confiables'  => [
        // '127.0.0.1',
        // '::1',
    ],
];
