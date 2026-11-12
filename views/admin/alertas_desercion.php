<?php
/**
 * =============================================================================
 * VISTA DEDICADA: GESTIÓN DE ALERTAS DE DESERCIÓN (ADMINISTRADOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * -----------------------------------------------------------------------------
 * Permite monitorear a escala todos los aprendices en Advertencia Preventiva
 * o Riesgo Crítico de Deserción según el Reglamento del Aprendiz SENA (Art. 22).
 * Reutiliza el componente centralizado views/components/tabla_alertas_desercion.php.
 * =============================================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';
require_once dirname(__DIR__, 2) . '/models/AsistenciaModel.php';

$conexion = (new Conexion())->conectar();

// Carga de aprendices en riesgo a nivel institucional (Admin)
$aprendicesEnRiesgo = AsistenciaModel::obtenerAprendicesEnRiesgo(null, 'admin', $conexion);
$origen = 'admin';

// Variables del layout AdminLTE
$paginaTitulo = 'Alertas de Deserción';
$menuActivo   = 'alertas_desercion';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Gestión Académica'],
    ['label' => 'Alertas de Deserción'],
];

ob_start();
include __DIR__ . '/../components/tabla_alertas_desercion.php';
$contenido = ob_get_clean();

// Render del layout de administrador
include __DIR__ . '/../layouts/layout_admin.php';
