<?php
/**
 * Vista: Horarios del Aprendiz
 * Muestra el calendario semanal visual con las clases programadas para la ficha del aprendiz autenticado.
 * La ficha se resuelve estrictamente desde la sesión del aprendiz (sin permitir manipulación por URL).
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(3);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/models/HorarioModel.php';

asegurarSesionIniciada();
$idUsuarioAprendiz = (int)$_SESSION['id_usuario'];

// Parámetros de consulta seguros (sólo fecha para navegación de semana)
$fechaConsulta = filter_input(INPUT_GET, 'fecha', FILTER_DEFAULT);
if (!$fechaConsulta || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaConsulta)) {
    $fechaConsulta = date('Y-m-d');
}

// Estructura de fechas para la semana consultada
$estructuraSemana = HorarioModel::calcularEstructuraSemana($fechaConsulta, false);

// Consulta segura basada estrictamente en la identidad del usuario autenticado
$consultaAprendiz = HorarioModel::obtenerHorariosAprendiz(
    $idUsuarioAprendiz,
    $estructuraSemana['fecha_inicio_semana'],
    $estructuraSemana['fecha_fin_semana']
);

$fichaAprendiz = $consultaAprendiz['ficha'];
$horarios = $consultaAprendiz['horarios'];

// Posicionamiento geométrico en la cuadrícula temporal
$cuadricula = HorarioModel::procesarEventosParaCuadricula($horarios, $estructuraSemana['dias']);

// Variables para el layout
$paginaTitulo = 'Horarios de mi Ficha';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Horarios']
];

$urlBaseModulo = RUTA_BASE . 'views/aprendiz/horarios/index.php';
$rolActualId   = 3;

ob_start();
?>
<link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/calendario.css">

<div class="container-fluid p-0">
    <?php if (!$fichaAprendiz): ?>
        <!-- Caso: El aprendiz no tiene ficha asignada -->
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body text-center py-5">
                <i class="fas fa-exclamation-circle text-warning fa-3x mb-3"></i>
                <h4 class="font-weight-bold">No tienes una ficha de formación asignada</h4>
                <p class="text-muted max-w-500 mx-auto">
                    Tu usuario aún no está vinculado a una ficha de formación activa en el sistema.
                    Por favor comunícate con la administración académica o con tu instructor para completar tu asignación.
                </p>
                <a href="<?= RUTA_BASE ?>views/aprendiz/dashboard.php" class="btn btn-outline-primary btn-sm mt-2">
                    <i class="fas fa-arrow-left"></i> Volver a mi dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Componente del Calendario Semanal Visual -->
        <?php include dirname(__DIR__, 2) . '/components/calendario_semanal.php'; ?>
    <?php endif; ?>
</div>

<?php
$contenido = ob_get_clean();
include dirname(__DIR__, 2) . '/layouts/layout_aprendiz.php';
