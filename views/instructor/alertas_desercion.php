<?php
/**
 * =============================================================================
 * VISTA DEDICADA: GESTIÓN DE ALERTAS DE DESERCIÓN (INSTRUCTOR)
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * -----------------------------------------------------------------------------
 * Permite al instructor monitorear a escala todos los aprendices en
 * Advertencia Preventiva o Riesgo Crítico en sus fichas asignadas.
 * Reutiliza el componente centralizado views/components/tabla_alertas_desercion.php.
 * =============================================================================
 */

require_once dirname(__DIR__, 2) . '/includes/autenticacion.php';
exigirRol(2);

require_once dirname(__DIR__, 2) . '/includes/constantes.php';
require_once dirname(__DIR__, 2) . '/config/conexion.php';
require_once dirname(__DIR__, 2) . '/models/AsistenciaModel.php';

$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
$conexion = (new Conexion())->conectar();

// Carga de aprendices en riesgo contextualizados a las fichas del instructor
$aprendicesEnRiesgo = AsistenciaModel::obtenerAprendicesEnRiesgo($idInstructor, 'instructor', $conexion);
$origen = 'instructor';

// Variables del layout AdminLTE
$paginaTitulo = 'Alertas de Deserción';
$menuActivo   = 'alertas_desercion';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Alertas de Deserción'],
];

ob_start();
include __DIR__ . '/../components/tabla_alertas_desercion.php';
$contenido = ob_get_clean();

// Render del layout de instructor
include __DIR__ . '/../layouts/layout_instructor.php';
