<?php
/**
 * Controlador administrativo: Eliminación segura de bloques horarios
 * Valida rol de administrador (rol 1), método HTTP POST y token CSRF.
 * Elimina el horario y realiza limpieza en cascada de sus sesiones asociadas.
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';
require_once dirname(__DIR__, 2) . '/models/HorarioModel.php';

date_default_timezone_set('America/Bogota');

// 1. Validar método HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/admin/horarios/index.php');
    exit;
}

// 2. Determinar URL de retorno segura
$returnUrl = trim($_POST['return_url'] ?? '');
if (empty($returnUrl) || strpos($returnUrl, RUTA_BASE) !== 0) {
    $vista = in_array($_POST['vista'] ?? '', ['tabla', 'calendario'], true) ? $_POST['vista'] : 'calendario';
    $fecha = trim($_POST['fecha'] ?? '');
    $ficha = filter_input(INPUT_POST, 'ficha', FILTER_VALIDATE_INT) ?: '';
    $instructor = filter_input(INPUT_POST, 'instructor', FILTER_VALIDATE_INT) ?: '';
    
    $params = ['vista' => $vista];
    if ($fecha) $params['fecha'] = $fecha;
    if ($ficha) $params['ficha'] = $ficha;
    if ($instructor) $params['instructor'] = $instructor;
    
    $returnUrl = RUTA_BASE . 'views/admin/horarios/index.php?' . http_build_query($params);
}

function responderEliminacion(string $urlBase, string $tipo, string $mensaje, bool $isAjax = false): void
{
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => ($tipo === 'mensaje'),
            'message' => $mensaje,
            'redirect' => $urlBase
        ]);
        exit;
    }

    $separador = (strpos($urlBase, '?') !== false) ? '&' : '?';
    header('Location: ' . $urlBase . $separador . urlencode($tipo) . '=' . urlencode($mensaje));
    exit;
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// 3. Validar token CSRF
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    responderEliminacion($returnUrl, 'error', 'Token de seguridad inválido o sesión expirada.', $isAjax);
}

// 4. Validar ID del horario
$idHorario = filter_input(INPUT_POST, 'id_horario', FILTER_VALIDATE_INT);
if (!$idHorario || $idHorario <= 0) {
    responderEliminacion($returnUrl, 'error', 'Identificador de horario no válido.', $isAjax);
}

// 5. Ejecutar eliminación en cascada
$resultado = HorarioModel::eliminarHorario($idHorario);

if ($resultado['success']) {
    $msg = $resultado['mensaje'] ?? 'Horario eliminado correctamente.';
    responderEliminacion($returnUrl, 'mensaje', $msg, $isAjax);
} else {
    $msg = $resultado['mensaje'] ?? 'No fue posible eliminar el horario.';
    responderEliminacion($returnUrl, 'error', $msg, $isAjax);
}

