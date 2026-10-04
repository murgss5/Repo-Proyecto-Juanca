<?php
/**
 * Controlador: Cancelar Importación de Aprendices
 */
require_once dirname(__DIR__) . '/includes/autenticacion.php';
exigirRol(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php');
    exit;
}

// Validación CSRF obligatoria
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    exit('Solicitud no válida.');
}

// Limpiar únicamente la previsualización de aprendices
if (isset($_SESSION['import_preview_aprendices'])) {
    unset($_SESSION['import_preview_aprendices']);
}

// Redirigir según la acción
if (isset($_POST['recargar']) && $_POST['recargar'] === '1') {
    // El usuario quiere cargar otro Excel
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
} else {
    // El usuario quiere cancelar por completo
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php');
}
exit;
