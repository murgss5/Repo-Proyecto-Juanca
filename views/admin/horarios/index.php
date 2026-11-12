<?php
/**
 * Vista de administración de horarios.
 * Permite alternar entre la Vista de Calendario Semanal Visual y la Vista de Tabla / Listado.
 * Admite filtrado por ficha e instructor, y gestión CRUD completa.
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';
require_once dirname(__DIR__, 3) . '/models/HorarioModel.php';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$conexion = (new Conexion())->conectar();

// Parámetros de vista y filtros
$vista = $_GET['vista'] ?? (filter_input(INPUT_GET, 'vista', FILTER_DEFAULT) ?: 'calendario');
$idFichaFiltro = !empty($_GET['ficha']) ? (int)$_GET['ficha'] : (filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: null);
$idInstructorFiltro = !empty($_GET['instructor']) ? (int)$_GET['instructor'] : (filter_input(INPUT_GET, 'instructor', FILTER_VALIDATE_INT) ?: null);

$fechaConsulta = $_GET['fecha'] ?? filter_input(INPUT_GET, 'fecha', FILTER_DEFAULT);
if (!$fechaConsulta || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaConsulta)) {
    $fechaConsulta = date('Y-m-d');
}

// Estructura de semana
$estructuraSemana = HorarioModel::calcularEstructuraSemana($fechaConsulta, false);

// Listados para filtros
$fichasLista = FichaModel::obtenerFichasActivas();
$instructoresLista = InstructorModel::obtenerInstructoresConFichas();

// Consulta de horarios con datos completos
if ($vista === 'tabla') {
    // Modo tabla: traer todos los horarios pertenecientes a la ficha/instructor sin restringir por semana
    $horarios = HorarioModel::obtenerHorariosTablaAdmin($idFichaFiltro, $idInstructorFiltro);
} else {
    // Modo calendario visual: consultar franja semanal activa
    $horarios = HorarioModel::obtenerHorariosAdmin(
        $idFichaFiltro,
        $idInstructorFiltro,
        $estructuraSemana['fecha_inicio_semana'],
        $estructuraSemana['fecha_fin_semana']
    );
}

// Procesar para la cuadrícula visual
$cuadricula = HorarioModel::procesarEventosParaCuadricula($horarios, $estructuraSemana['dias']);

// Variables para el layout
$paginaTitulo = 'Horarios de Formación';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios']
];

$urlBaseModulo       = RUTA_BASE . 'views/admin/horarios/index.php';
$rolActualId         = 1;
$fichasFiltro        = $fichasLista;
$idFichaSeleccionada = $idFichaFiltro;

ob_start();
?>
<link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/calendario.css">

<?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
    </div>
<?php endif; ?>

<!-- Barra superior de gestión y selector de vista -->
<div class="card card-outline card-success shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 10px;">
            <!-- Selector de vista -->
            <div class="btn-group btn-group-sm" role="group">
                <a href="<?= RUTA_BASE ?>views/admin/horarios/index.php?vista=calendario&fecha=<?= urlencode($estructuraSemana['fecha_referencia']) ?><?= $idFichaFiltro ? '&ficha=' . $idFichaFiltro : '' ?><?= $idInstructorFiltro ? '&instructor=' . $idInstructorFiltro : '' ?>" 
                   class="btn <?= ($vista === 'calendario') ? 'btn-success text-white' : 'btn-outline-secondary' ?>">
                    <i class="fas fa-calendar-alt mr-1"></i> Calendario Semanal
                </a>
                <a href="<?= RUTA_BASE ?>views/admin/horarios/index.php?vista=tabla&fecha=<?= urlencode($estructuraSemana['fecha_referencia']) ?><?= $idFichaFiltro ? '&ficha=' . $idFichaFiltro : '' ?><?= $idInstructorFiltro ? '&instructor=' . $idInstructorFiltro : '' ?>" 
                   class="btn <?= ($vista === 'tabla') ? 'btn-success text-white' : 'btn-outline-secondary' ?>">
                    <i class="fas fa-list mr-1"></i> Vista Tabla
                </a>
            </div>

            <!-- Filtros administrativos por Ficha e Instructor -->
            <form method="get" action="<?= RUTA_BASE ?>views/admin/horarios/index.php" class="form-inline m-0">
                <input type="hidden" name="vista" value="<?= htmlspecialchars($vista) ?>">
                <input type="hidden" name="fecha" value="<?= htmlspecialchars($estructuraSemana['fecha_referencia']) ?>">

                <label class="mr-2 text-sm text-muted">Ficha:</label>
                <select name="ficha" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="0">Todas las fichas</option>
                    <?php foreach ($fichasLista as $f): ?>
                        <option value="<?= $f['id_ficha'] ?>" <?= ($idFichaFiltro === (int)$f['id_ficha']) ? 'selected' : '' ?>>
                            #<?= $f['id_ficha'] ?> &mdash; <?= htmlspecialchars($f['nombre_programa']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label class="mr-2 text-sm text-muted">Instructor:</label>
                <select name="instructor" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                    <option value="0">Todos los instructores</option>
                    <?php foreach ($instructoresLista as $inst): ?>
                        <option value="<?= $inst['id_usuario'] ?>" <?= ($idInstructorFiltro === (int)$inst['id_usuario']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inst['nombre'] . ' ' . $inst['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <!-- Acciones -->
            <div>
                <a href="<?= RUTA_BASE ?>views/admin/horarios/create.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> Nuevo horario
                </a>
                <a href="<?= RUTA_BASE ?>views/admin/horarios/importar.php" class="btn btn-info btn-sm ml-1">
                    <i class="fas fa-file-import mr-1"></i> Importar Excel
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($vista === 'calendario'): ?>
    <!-- Vista 1: Calendario Semanal Visual (idéntico a la referencia visual) -->
    <?php include dirname(__DIR__, 2) . '/components/calendario_semanal.php'; ?>
<?php else: ?>
    <!-- Vista 2: Tabla de gestión corporativa Enterprise -->
    <div class="card card-outline card-success shadow-sm">
        <div class="card-header bg-white">
            <h3 class="card-title font-weight-bold text-dark">
                <i class="fas fa-table mr-2 text-success"></i> Listado de Horarios
            </h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle tabla-datos mb-0" data-empty-message="No hay horarios registrados con los filtros seleccionados.">
                    <thead class="thead-light">
                        <tr>
                            <th>Ficha</th>
                            <th>Programa / Competencia</th>
                            <th>Día</th>
                            <th>Horario</th>
                            <th>Ambiente</th>
                            <th>Instructor Asignado</th>
                            <th>Estado</th>
                            <th class="text-center" style="width: 110px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($horarios)): ?>
                            <?php foreach ($horarios as $h): ?>
                            <tr>
                                <td><span class="badge badge-info px-2 py-1">#<?= htmlspecialchars($h['id_ficha']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($h['nombre_programa']) ?></strong>
                                    <?php if (!empty($h['competencia'])): ?>
                                        <br><small class="text-muted"><i class="fas fa-bookmark text-success mr-1"></i><?= htmlspecialchars($h['competencia']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($h['dia_semana']) ?></strong>
                                    <?php if (!empty($h['fecha_inicio'])): ?>
                                        <br><small class="text-muted"><i class="far fa-calendar-alt mr-1"></i><?= date('d/m/Y', strtotime($h['fecha_inicio'])) ?><?php if (!empty($h['fecha_fin']) && $h['fecha_fin'] !== $h['fecha_inicio']): ?> - <?= date('d/m/Y', strtotime($h['fecha_fin'])) ?><?php endif; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-light border px-2 py-1">
                                        <?= htmlspecialchars(substr($h['entrada'], 0, 5)) ?> &ndash; <?= htmlspecialchars(substr($h['salida'], 0, 5)) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($h['ambiente'] ?? 'Por definir') ?></td>
                                <td>
                                    <i class="fas fa-chalkboard-teacher text-muted mr-1"></i>
                                    <?= htmlspecialchars($h['instructor_nombre'] ?? 'No asignado') ?>
                                </td>
                                <td>
                                    <?php if (($h['estado'] ?? 'Activo') === 'Activo'): ?>
                                        <span class="badge badge-success px-2 py-1">Activo</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary px-2 py-1">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-warning" href="<?= RUTA_BASE ?>views/admin/horarios/edit.php?id=<?= $h['id_horario'] ?>&vista=tabla" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-eliminar-horario-tabla" 
                                                data-id="<?= $h['id_horario'] ?>"
                                                data-ficha="<?= htmlspecialchars((string)$h['id_ficha']) ?>"
                                                data-programa="<?= htmlspecialchars($h['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-dia="<?= htmlspecialchars($h['dia_semana'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-horario="<?= htmlspecialchars(substr($h['entrada'], 0, 5)) ?> &ndash; <?= htmlspecialchars(substr($h['salida'], 0, 5)) ?>"
                                                data-instructor="<?= htmlspecialchars($h['instructor_nombre'] ?? 'No asignado', ENT_QUOTES, 'UTF-8') ?>"
                                                title="Eliminar horario">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Formulario Oculto para Eliminación Segura de Horarios en Vista Tabla -->
<form id="formEliminarHorarioTabla" method="post" action="<?= RUTA_BASE ?>controllers/HorarioController.php" style="display: none;">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id_horario" id="eliminar_tabla_id_horario" value="">
    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btnsEliminarTabla = document.querySelectorAll('.btn-eliminar-horario-tabla');
    btnsEliminarTabla.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var idHorario = this.getAttribute('data-id');
            var ficha = this.getAttribute('data-ficha');
            var programa = this.getAttribute('data-programa');
            var dia = this.getAttribute('data-dia');
            var horario = this.getAttribute('data-horario');
            var instructor = this.getAttribute('data-instructor');

            var infoFicha = '#' + ficha + (programa ? ' - ' + programa : '');
            var diaHorario = dia + ' (' + horario + ')';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar este bloque horario?',
                    html: '<div class="text-left p-3 bg-light rounded border mb-3 small" style="line-height: 1.6;">' +
                          '<div><strong class="text-dark">Ficha:</strong> ' + infoFicha + '</div>' +
                          '<div><strong class="text-dark">Día y Hora:</strong> ' + diaHorario + '</div>' +
                          '<div><strong class="text-dark">Instructor:</strong> ' + instructor + '</div>' +
                          '</div>' +
                          '<p class="text-danger small font-weight-bold mb-0">' +
                          '<i class="fas fa-exclamation-triangle mr-1"></i> Esta acción eliminará el horario y limpiará en cascada las sesiones asociadas para evitar registros huérfanos.' +
                          '</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-arrow-right mr-1"></i> Continuar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true
                }).then(function(result1) {
                    if (result1.isConfirmed) {
                        // Doble confirmación de seguridad estricta
                        Swal.fire({
                            title: '¿Confirmación definitiva?',
                            text: 'El bloque desaparecerá de forma permanente de los calendarios y del panel de apertura de jornada de los instructores. ¿Está totalmente seguro de proceder?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> Sí, eliminar definitivamente',
                            cancelButtonText: 'Regresar',
                            reverseButtons: true
                        }).then(function(result2) {
                            if (result2.isConfirmed) {
                                var form = document.getElementById('formEliminarHorarioTabla');
                                if (form) {
                                    document.getElementById('eliminar_tabla_id_horario').value = idHorario;
                                    form.submit();
                                }
                            }
                        });
                    }
                });
            } else {
                if (confirm('¿Está seguro de eliminar el horario de ' + diaHorario + ' para la ficha ' + infoFicha + '?')) {
                    if (confirm('CONFIRMACIÓN DEFINITIVA: ¿Desea proceder con la eliminación?')) {
                        var form = document.getElementById('formEliminarHorarioTabla');
                        if (form) {
                            document.getElementById('eliminar_tabla_id_horario').value = idHorario;
                            form.submit();
                        }
                    }
                }
            }
        });
    });
});
</script>

<?php
$contenido = ob_get_clean();
include dirname(__DIR__, 2) . '/layouts/layout_admin.php';
