<?php
/**
 * Verificacion rapida de sesion activa para proteccion contra bfcache / restauracion de historial.
 */
require_once __DIR__ . '/../../includes/autenticacion.php';

asegurarSesionIniciada();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$usuario = usuarioActual();

if ($usuario === null) {
    http_response_code(401);
    echo json_encode([
        'autenticado' => false,
        'mensaje'     => 'Sesion no activa o expirada.'
    ]);
    exit;
}

http_response_code(200);
echo json_encode([
    'autenticado' => true,
    'id_usuario'  => $usuario['id_usuario'],
    'rol'         => $usuario['nombre_rol']
]);
exit;

