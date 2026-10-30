<?php
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$conexion = (new Conexion())->conectar();

// Consulta principal de fichas con agregados
$fichas = $conexion->query(
    "SELECT f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal,
            CONCAT(u.nombre, ' ', u.apellido) AS instructor,
            COUNT(ap.id_aprendiz) AS aprendices
     FROM ficha f
     INNER JOIN usuario u ON u.id_usuario = f.fk_usuario
     LEFT JOIN aprendiz ap ON ap.fk_ficha = f.id_ficha
     GROUP BY f.id_ficha, f.nombre_programa, f.jornada, f.ambiente_principal, u.nombre, u.apellido
     ORDER BY f.id_ficha DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Cálculo de estadísticas superiores
$totalFichas = count($fichas);
$totalAprendices = (int)array_sum(array_column($fichas, 'aprendices'));

// Jornadas activas
$jornadasDisponibles = array_values(array_unique(array_filter(array_map('trim', array_column($fichas, 'jornada')))));
sort($jornadasDisponibles);
$totalJornadasActivas = count($jornadasDisponibles);

$paginaTitulo = 'Fichas de Formación';
$menuActivo   = 'fichas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Fichas']
];

ob_start();
?>

<!-- Alertas de estado -->
<?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- ==================== 1. WIDGETS DE ESTADÍSTICAS SUPERIORES ==================== -->
<div class="row mb-4">
    <!-- Total Fichas Registradas -->
    <div class="col-lg-4 col-md-6 col-12 mb-3 mb-lg-0">
        <div class="card kpi-card-premium kpi-success hover-elevate shadow-sm h-100 mb-0">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">TOTAL DE FICHAS</h6>
                    <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalFichas) ?></h2>
                    <small class="text-muted"><i class="fas fa-check-circle mr-1 text-success"></i> Fichas registradas en el centro</small>
                </div>
                <div class="kpi-icon-circle shadow-xs">
                    <i class="fas fa-layer-group text-success"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Aprendices Matriculados -->
    <div class="col-lg-4 col-md-6 col-12 mb-3 mb-lg-0">
        <div class="card kpi-card-premium kpi-info hover-elevate shadow-sm h-100 mb-0">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">APRENDICES MATRICULADOS</h6>
                    <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalAprendices) ?></h2>
                    <small class="text-muted"><i class="fas fa-users mr-1 text-info"></i> Total vinculados a las fichas</small>
                </div>
                <div class="kpi-icon-circle shadow-xs">
                    <i class="fas fa-user-graduate text-info"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Jornadas Activas -->
    <div class="col-lg-4 col-md-12 col-12">
        <div class="card kpi-card-premium kpi-warning hover-elevate shadow-sm h-100 mb-0">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">JORNADAS ACTIVAS</h6>
                    <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalJornadasActivas) ?></h2>
                    <small class="text-muted">
                        <i class="fas fa-clock mr-1 text-warning"></i>
                        <?= !empty($jornadasDisponibles) ? htmlspecialchars(implode(', ', $jornadasDisponibles)) : 'Sin jornadas activas' ?>
                    </small>
                </div>
                <div class="kpi-icon-circle shadow-xs">
                    <i class="fas fa-business-time text-warning"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== 2. TARJETA PRINCIPAL Y TABLA DE FICHAS ==================== -->
<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-2 mb-sm-0">
            <i class="fas fa-layer-group text-success mr-2"></i> Fichas de Formación
        </h3>
        <div class="card-tools ml-auto">
            <a href="<?= RUTA_BASE ?>views/admin/fichas/create.php" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-plus mr-1"></i> Nueva ficha
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Barra de Filtros y Búsqueda Interactiva -->
        <div class="row mb-3 align-items-center">
            <div class="col-lg-6 col-md-7 col-12 mb-2 mb-md-0">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control buscador-tabla" placeholder="Buscar por número de ficha, programa o instructor..." autocomplete="off">
                </div>
            </div>
            <div class="col-lg-4 col-md-5 col-12">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-filter text-muted"></i></span>
                    </div>
                    <select class="form-control custom-select filtro-tabla" data-col="2">
                        <option value="">Todas las jornadas</option>
                        <option value="Diurna">Diurna (Mañana)</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Nocturna">Nocturna</option>
                        <?php foreach ($jornadasDisponibles as $jd): ?>
                            <?php if (!in_array($jd, ['Diurna', 'Tarde', 'Nocturna'], true)): ?>
                                <option value="<?= htmlspecialchars($jd) ?>"><?= htmlspecialchars($jd) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabla Responsiva de Fichas -->
        <div class="table-responsive">
            <table class="table table-hover table-striped text-nowrap tabla-datos mb-0" data-empty-message="No se encontraron fichas registradas.">
                <thead class="thead-light">
                    <tr>
                        <th class="col-ficha" style="width: 105px;">Ficha</th>
                        <th>Programa de Formación</th>
                        <th style="width: 120px;">Jornada</th>
                        <th class="d-none d-lg-table-cell">Ambiente Principal</th>
                        <th class="d-none d-md-table-cell">Instructor Líder</th>
                        <th class="text-center d-none d-sm-table-cell" style="width: 130px;">Aprendices</th>
                        <th class="text-center col-acciones" style="width: 90px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fichas as $f): ?>
                    <?php
                        $jornadaLower = mb_strtolower(trim((string)$f['jornada']), 'UTF-8');
                        if (in_array($jornadaLower, ['diurna', 'mañana', 'manana'], true)) {
                            $badgeJornada = 'badge-warning text-dark';
                            $iconoJornada = 'fa-sun mr-1';
                        } elseif ($jornadaLower === 'tarde') {
                            $badgeJornada = 'badge-info text-white';
                            $iconoJornada = 'fa-cloud-sun mr-1';
                        } elseif ($jornadaLower === 'nocturna') {
                            $badgeJornada = 'badge-dark text-white';
                            $iconoJornada = 'fa-moon mr-1';
                        } else {
                            $badgeJornada = 'badge-secondary text-white';
                            $iconoJornada = 'fa-clock mr-1';
                        }
                    ?>
                    <tr>
                        <!-- Ficha Badge -->
                        <td class="align-middle col-ficha">
                            <span class="badge badge-success px-2 py-1 font-weight-bold" style="font-size: 0.88rem; letter-spacing: 0.5px;">
                                <i class="fas fa-hashtag mr-1"></i><?= htmlspecialchars((string)$f['id_ficha']) ?>
                            </span>
                        </td>

                        <!-- Programa de Formación -->
                        <td class="align-middle">
                            <span class="font-weight-bold text-dark d-block text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($f['nombre_programa']) ?>">
                                <?= htmlspecialchars($f['nombre_programa']) ?>
                            </span>
                            <small class="text-muted"><i class="fas fa-graduation-cap text-secondary mr-1"></i> Formación Titulada</small>
                            <div class="d-md-none text-muted small mt-1">
                                <?php if (!empty($f['instructor'])): ?>
                                    <span class="mr-2"><i class="fas fa-chalkboard-teacher text-success mr-1"></i><?= htmlspecialchars($f['instructor']) ?></span>
                                <?php endif; ?>
                                <span class="badge badge-light border border-success text-success px-1 py-0">
                                    <i class="fas fa-users mr-1"></i><?= number_format((int)$f['aprendices']) ?>
                                </span>
                            </div>
                        </td>

                        <!-- Jornada -->
                        <td class="align-middle">
                            <span class="badge <?= $badgeJornada ?> px-2 py-1 font-weight-bold">
                                <i class="fas <?= $iconoJornada ?>"></i><?= htmlspecialchars($f['jornada']) ?>
                            </span>
                        </td>

                        <!-- Ambiente Principal -->
                        <td class="align-middle d-none d-lg-table-cell">
                            <?php if (!empty($f['ambiente_principal']) && $f['ambiente_principal'] !== '—'): ?>
                                <span class="font-weight-medium text-dark">
                                    <i class="fas fa-door-open text-success mr-1"></i><?= htmlspecialchars($f['ambiente_principal']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted font-italic">
                                    <i class="fas fa-door-closed text-muted mr-1"></i> Sin asignar
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Instructor Líder -->
                        <td class="align-middle d-none d-md-table-cell">
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded-circle p-2 mr-2 text-center text-success border" style="width: 32px; height: 32px; line-height: 14px;">
                                    <i class="fas fa-chalkboard-teacher fa-xs"></i>
                                </div>
                                <div>
                                    <span class="font-weight-bold text-dark d-block">
                                        <?= htmlspecialchars($f['instructor'] ?: 'No asignado') ?>
                                    </span>
                                </div>
                            </div>
                        </td>

                        <!-- Aprendices Matriculados -->
                        <td class="text-center align-middle d-none d-sm-table-cell">
                            <?php $cantAprendices = (int)$f['aprendices']; ?>
                            <?php if ($cantAprendices > 0): ?>
                                <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="badge badge-light border border-success text-success px-2 py-1 font-weight-bold shadow-xs" title="Ver <?= $cantAprendices ?> aprendices de esta ficha">
                                    <i class="fas fa-users mr-1"></i><?= number_format($cantAprendices) ?> matriculados
                                </a>
                            <?php else: ?>
                                <span class="badge badge-light border text-muted px-2 py-1" title="Sin aprendices matriculados">
                                    <i class="fas fa-user-slash mr-1"></i>0 matriculados
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Acciones -->
                        <td class="text-center align-middle col-acciones">
                            <div class="btn-group btn-group-sm" role="group">
                                <a class="btn btn-warning btn-sm shadow-sm" href="<?= RUTA_BASE ?>views/admin/fichas/edit.php?id=<?= $f['id_ficha'] ?>" title="Editar ficha #<?= $f['id_ficha'] ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($cantAprendices === 0): ?>
                                    <button type="button" class="btn btn-danger btn-sm shadow-sm ml-1 btn-eliminar-ficha" 
                                            data-id="<?= $f['id_ficha'] ?>" 
                                            data-nombre="<?= htmlspecialchars($f['nombre_programa'], ENT_QUOTES, 'UTF-8') ?>" 
                                            title="Eliminar ficha #<?= $f['id_ficha'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ml-1 disabled" 
                                            title="No se puede eliminar: tiene <?= $cantAprendices ?> aprendiz(ces) asignado(s)" 
                                            style="cursor: not-allowed; opacity: 0.5;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Formulario Oculto para Eliminación Segura de Ficha -->
<form id="formEliminarFicha" method="post" action="<?= RUTA_BASE ?>controllers/gestionar_academico.php" style="display: none;">
    <input type="hidden" name="entidad" value="ficha">
    <input type="hidden" name="accion" value="eliminar">
    <input type="hidden" name="id_ficha" id="eliminar_id_ficha" value="">
    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btnsEliminar = document.querySelectorAll('.btn-eliminar-ficha');
    btnsEliminar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var idFicha = this.getAttribute('data-id');
            var programa = this.getAttribute('data-nombre');
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '¿Eliminar ficha #' + idFicha + '?',
                    text: 'Se eliminará la ficha "' + programa + '". Esta acción no se puede deshacer.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-trash mr-1"></i> Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        document.getElementById('eliminar_id_ficha').value = idFicha;
                        document.getElementById('formEliminarFicha').submit();
                    }
                });
            } else {
                if (confirm('¿Está seguro de eliminar la ficha #' + idFicha + ' (' + programa + ')?')) {
                    document.getElementById('eliminar_id_ficha').value = idFicha;
                    document.getElementById('formEliminarFicha').submit();
                }
            }
        });
    });
});
</script>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';
