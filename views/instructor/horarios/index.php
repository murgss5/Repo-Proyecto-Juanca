<?php
/**
 * Vista: Horarios del Instructor
 * Muestra el calendario semanal visual con las clases asignadas al instructor autenticado.
 * Permite navegar entre semanas y filtrar por sus fichas asignadas.
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/models/HorarioModel.php';

asegurarSesionIniciada();
$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);

// Parámetros de consulta seguros
$fechaConsulta = filter_input(INPUT_GET, 'fecha', FILTER_DEFAULT);
if (!$fechaConsulta || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaConsulta)) {
    $fechaConsulta = date('Y-m-d');
}

// Estructura de fechas para la semana (Lunes a Sábado, domingo si hay eventos)
$estructuraSemana = HorarioModel::calcularEstructuraSemana($fechaConsulta, false);

// Fichas asignadas al instructor para filtro
$fichasInstructor = HorarioModel::obtenerFichasInstructor($idInstructor);

// Validación segura de filtro por ficha (el instructor no puede consultar fichas ajenas)
$idFichaFiltro = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT);
if ($idFichaFiltro) {
    $esFichaValida = false;
    foreach ($fichasInstructor as $f) {
        if ((int)$f['id_ficha'] === $idFichaFiltro) {
            $esFichaValida = true;
            break;
        }
    }
    if (!$esFichaValida) {
        $idFichaFiltro = null;
    }
}

// Consultar clases reales asignadas en la base de datos
$horarios = HorarioModel::obtenerHorariosInstructor(
    $idInstructor,
    $estructuraSemana['fecha_inicio_semana'],
    $estructuraSemana['fecha_fin_semana'],
    $idFichaFiltro
);

// Calcular posicionamiento geométrico proporcional para la cuadrícula
$cuadricula = HorarioModel::procesarEventosParaCuadricula($horarios, $estructuraSemana['dias']);

// Variables para el layout
$paginaTitulo = 'Mis Horarios de Formación';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Horarios']
];

$urlBaseModulo       = RUTA_BASE . 'views/instructor/horarios/index.php';
$rolActualId         = 2;
$fichasFiltro        = $fichasInstructor;
$idFichaSeleccionada = $idFichaFiltro;

ob_start();
?>
<link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/calendario.css">

<div class="container-fluid p-0">
    <!-- Componente de Calendario Semanal Visual -->
    <?php include dirname(__DIR__, 2) . '/components/calendario_semanal.php'; ?>
</div>

<?php
$contenido = ob_get_clean();
include dirname(__DIR__, 2) . '/layouts/layout_instructor.php';
