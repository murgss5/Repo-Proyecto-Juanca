<?php
// listado de excusas del aprrendiz correspondiente

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

$conexion = (new Conexion())->conectar();

// Consultar excusas del aprendiz 
$consultaExcusas = $conexion->prepare(
    "SELECT
        e.id_excusa,
        e.archivo,
        e.observacion,
        e.estado,
        e.fecha_subida,
        e.fecha_revision,
        a.fecha_asistencia
     FROM excusa e
     INNER JOIN asistencia a ON a.id_asistencia = e.fk_asistencia
     INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
     WHERE ap.fk_usuario = :id_usuario
     ORDER BY e.fecha_subida DESC, a.fecha_asistencia DESC"
);
$consultaExcusas->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaExcusas->execute();
$excusas = $consultaExcusas->fetchAll();

// Normalizar estados para visualización
foreach ($excusas as &$excusa) {
    $excusa['estado_mostrado'] = ucfirst(strtolower($excusa['estado']));
}
unset($excusa);

function claseEstadoExcusa($estado) {
    switch ($estado) {
        case 'Pendiente': return 'badge-warning';
        case 'Aprobada':  return 'badge-success';
        case 'Rechazada': return 'badge-danger';
        default:          return 'badge-secondary';
    }
}

// --- Variables del layout ---
$paginaTitulo = 'Mis excusas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mis excusas'],
];

ob_start();
?>
<?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_GET['mensaje']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Encabezado de la Sección -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
    <div>
        <h3 class="font-weight-bold text-dark mb-1">
            <i class="fas fa-file-medical text-sena mr-2"></i> Mis excusas
        </h3>
        <p class="text-muted mb-0 small">Consulta y seguimiento del estado de tus justificaciones médicas</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex align-items-center">
        <span class="badge badge-light border text-muted px-3 py-2 mr-3 font-weight-normal shadow-sm" id="counterExcusas">
            <?= count($excusas) ?> <?= count($excusas) === 1 ? 'excusa registrada' : 'excusas registradas' ?>
        </span>
        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-success shadow-sm font-weight-bold px-3">
            <i class="fas fa-cloud-upload-alt mr-1"></i> Subir excusa
        </a>
    </div>
</div>

<!-- Filtros Superiores (Vanilla JS) -->
<div class="row mb-4">
    <div class="col-md-7 col-lg-8 mb-3 mb-md-0">
        <div class="input-group shadow-sm rounded bg-white">
            <div class="input-group-prepend">
                <span class="input-group-text bg-white border-0 pl-3">
                    <i class="fas fa-search text-muted"></i>
                </span>
            </div>
            <input type="text" id="searchExcusa" class="form-control form-control-lg border-0 shadow-none pl-2" placeholder="Buscar por fecha, observación o archivo...">
        </div>
    </div>
    <div class="col-md-5 col-lg-4">
        <div class="input-group shadow-sm rounded bg-white">
            <div class="input-group-prepend">
                <span class="input-group-text bg-white border-0 pl-3">
                    <i class="fas fa-filter text-muted"></i>
                </span>
            </div>
            <select id="filterEstado" class="form-control form-control-lg border-0 shadow-none pl-2">
                <option value="">Todos los estados</option>
                <option value="pendiente">Pendiente</option>
                <option value="aprobada">Aprobada</option>
                <option value="rechazada">Rechazada</option>
            </select>
        </div>
    </div>
</div>

<!-- Grid de Tarjetas de Excusas -->
<div class="row" id="excusasGrid">
    <?php if (empty($excusas)): ?>
        <div class="col-12 text-center py-5">
            <div class="card border-0 shadow-sm py-5">
                <div class="card-body">
                    <div class="mb-3">
                        <i class="fas fa-file-medical-alt text-muted fa-4x opacity-50"></i>
                    </div>
                    <h4 class="font-weight-bold text-dark">No has registrado excusas aún</h4>
                    <p class="text-muted">Cuando registres una excusa por inasistencia, podrás hacer seguimiento a su estado aquí.</p>
                    <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-success shadow-sm mt-2 font-weight-bold">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Subir mi primera excusa
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($excusas as $excusa): ?>
            <?php
                $dataEstado = strtolower($excusa['estado_mostrado']);
                $fechaFormato = date('d/m/Y', strtotime($excusa['fecha_asistencia']));
                $dataTexto = strtolower($excusa['fecha_asistencia'] . ' ' . $fechaFormato . ' ' . ($excusa['observacion'] ?? '') . ' ' . $excusa['estado_mostrado'] . ' ' . $excusa['archivo']);
                $ext = strtolower(pathinfo($excusa['archivo'], PATHINFO_EXTENSION));
                $iconoArchivo = 'fa-file-alt text-secondary';
                if ($ext === 'pdf') {
                    $iconoArchivo = 'fa-file-pdf text-danger';
                } elseif (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
                    $iconoArchivo = 'fa-file-image text-info';
                }
            ?>
            <div class="col-md-6 col-xl-4 mb-4 excusa-item" data-estado="<?= htmlspecialchars($dataEstado) ?>" data-texto="<?= htmlspecialchars($dataTexto) ?>">
                <div class="card h-100 border-0 shadow-sm hover-elevate transition-all">
                    <!-- Card Header -->
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4 d-flex justify-content-between align-items-center">
                        <h5 class="font-weight-bold mb-0 text-dark">
                            <i class="fas fa-calendar-alt text-success mr-2"></i><?= htmlspecialchars($fechaFormato) ?>
                        </h5>
                        <span class="badge badge-pill <?= claseEstadoExcusa($excusa['estado_mostrado']) ?> px-3 py-2">
                            <?= htmlspecialchars($excusa['estado_mostrado']) ?>
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <!-- Bloque de Observaciones -->
                        <div class="bg-light p-3 rounded mb-3 text-muted">
                            <i class="fas fa-quote-left mr-2 opacity-50"></i>
                            <?php if (!empty($excusa['observacion'])): ?>
                                <span><?= htmlspecialchars($excusa['observacion']) ?></span>
                            <?php else: ?>
                                <span class="font-italic">Sin observaciones registradas</span>
                            <?php endif; ?>
                        </div>

                        <!-- Fechas secundarias -->
                        <div class="row text-muted small mt-auto pt-2 border-top">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock text-secondary mr-2"></i>
                                    <div>
                                        <span class="d-block text-uppercase font-weight-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Enviado</span>
                                        <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($excusa['fecha_subida']))) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-user-check text-secondary mr-2"></i>
                                    <div>
                                        <span class="d-block text-uppercase font-weight-bold text-muted" style="font-size: 0.68rem; letter-spacing: 0.5px;">Revisión</span>
                                        <span>
                                            <?php if ($excusa['fecha_revision']): ?>
                                                <?= htmlspecialchars(date('d/m/Y H:i', strtotime($excusa['fecha_revision']))) ?>
                                            <?php else: ?>
                                                <span class="text-muted font-italic">&mdash;</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="card-footer bg-white border-top px-4 py-3 d-flex justify-content-between align-items-center">
                        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/descargar.php?id_excusa=<?= htmlspecialchars($excusa['id_excusa']) ?>" class="btn btn-sm btn-outline-primary font-weight-bold rounded-pill px-3 shadow-none" target="_blank" rel="noopener noreferrer" data-toggle="tooltip" title="Ver documento adjunto">
                            <i class="fas <?= $iconoArchivo ?> mr-1"></i> Ver documento
                        </a>
                        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/descargar.php?id_excusa=<?= htmlspecialchars($excusa['id_excusa']) ?>" class="btn btn-sm btn-outline-success rounded-circle shadow-none" style="width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;" download data-toggle="tooltip" title="Descargar archivo">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Mensaje de sin resultados para búsqueda -->
        <div id="noResultsMessage" class="col-12 text-center py-5 d-none">
            <div class="card border-0 shadow-sm py-5">
                <div class="card-body">
                    <i class="fas fa-search-minus text-muted fa-3x mb-3 opacity-50"></i>
                    <h5 class="font-weight-bold text-dark">No se encontraron excusas coincidentes</h5>
                    <p class="text-muted mb-0">Intenta buscar con otros términos o cambia el filtro de estado seleccionado.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Lógica de Filtrado en Tiempo Real (Vanilla JS) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchExcusa');
    const filterEstado = document.getElementById('filterEstado');
    const items = document.querySelectorAll('.excusa-item');
    const noResults = document.getElementById('noResultsMessage');
    const counter = document.getElementById('counterExcusas');

    function filtrarExcusas() {
        const texto = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const estado = filterEstado ? filterEstado.value.trim().toLowerCase() : '';
        let visibles = 0;

        items.forEach(function (item) {
            const itemEstado = (item.getAttribute('data-estado') || '').toLowerCase();
            const itemTexto = (item.getAttribute('data-texto') || '').toLowerCase();

            const coincideEstado = (estado === '' || itemEstado === estado);
            const coincideTexto = (texto === '' || itemTexto.indexOf(texto) !== -1);

            if (coincideEstado && coincideTexto) {
                item.classList.remove('d-none');
                visibles++;
            } else {
                item.classList.add('d-none');
            }
        });

        if (noResults) {
            if (visibles === 0 && items.length > 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        }

        if (counter) {
            counter.textContent = visibles + (visibles === 1 ? ' excusa visible' : ' excusas visibles');
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filtrarExcusas);
    }
    if (filterEstado) {
        filterEstado.addEventListener('change', filtrarExcusas);
    }
});
</script>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_aprendiz.php';
