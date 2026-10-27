<?php
/**
 * ============================================================
 * VISTA: EXCUSAS MÉDICAS (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Listado real de excusas con filtros funcionales.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

$conexion = (new Conexion())->conectar();

// --- Obtener filtros GET ---
$filtroEstado   = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
$filtroFechaIni = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : null;
$filtroFechaFin = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : null;
$filtroAprendiz = isset($_GET['aprendiz']) && $_GET['aprendiz'] !== '' ? trim($_GET['aprendiz']) : null;
$filtroFicha    = isset($_GET['ficha']) && $_GET['ficha'] !== '' ? $_GET['ficha'] : null;

// Validar formato de fechas
if ($filtroFechaIni && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaIni)) {
    $filtroFechaIni = null;
}
if ($filtroFechaFin && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaFin)) {
    $filtroFechaFin = null;
}

// --- Construir consulta con filtros ---
$where = [];
$params = [];

if ($filtroEstado) {
    $where[] = "e.estado = :estado";
    $params[':estado'] = $filtroEstado;
}
if ($filtroFechaIni) {
    $where[] = "DATE(e.fecha_subida) >= :fecha_ini";
    $params[':fecha_ini'] = $filtroFechaIni;
}
if ($filtroFechaFin) {
    $where[] = "DATE(e.fecha_subida) <= :fecha_fin";
    $params[':fecha_fin'] = $filtroFechaFin;
}
if ($filtroAprendiz) {
    $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :aprendiz OR u.identificacion LIKE :aprendiz)";
    $params[':aprendiz'] = "%{$filtroAprendiz}%";
}
if ($filtroFicha) {
    $where[] = "f.id_ficha = :ficha";
    $params[':ficha'] = $filtroFicha;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        e.id_excusa,
        e.archivo,
        e.observacion,
        e.estado,
        e.fecha_subida,
        e.fecha_revision,
        a.fecha_asistencia,
        CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
        u.identificacion AS identificacion_aprendiz,
        f.id_ficha,
        f.nombre_programa,
        CONCAT(ui.nombre, CHAR(32), ui.apellido) AS instructor_revisor
    FROM excusa e
    INNER JOIN asistencia a ON a.id_asistencia = e.fk_asistencia
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    LEFT JOIN usuario ui ON ui.id_usuario = e.fk_usuario_instructor
    {$whereSql}
    ORDER BY e.fecha_subida DESC, e.id_excusa DESC
";

$consultaExcusas = $conexion->prepare($sql);
$consultaExcusas->execute($params);
$excusas = $consultaExcusas->fetchAll();

// Obtener fichas para el filtro
$fichas = $conexion->query("SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha")->fetchAll();

// Normalizar estados para visualización
foreach ($excusas as &$excusa) {
    $excusa['estado_mostrado'] = ucfirst(strtolower($excusa['estado']));
}
unset($excusa);

function claseEstadoExcusa($estado) {
    switch ($estado) {
        case 'Pendiente': return 'badge-warning text-white';
        case 'Aprobada':  return 'badge-success';
        case 'Rechazada': return 'badge-danger';
        default:          return 'badge-secondary';
    }
}

// --- Variables del layout ---
$paginaTitulo = 'Excusas médicas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Excusas médicas'],
];

ob_start();
?>

<!-- 1. Sección de Filtros de Búsqueda -->
<div class="card card-outline card-success shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h3 class="card-title font-weight-bold text-dark m-0">
            <i class="fas fa-filter text-success mr-2"></i> Filtros de Búsqueda
        </h3>
    </div>
    <div class="card-body">
        <form method="get" id="formFiltroExcusas">
            <!-- Fila 1: Estado (col-md-2), Fecha inicial (col-md-3), Fecha final (col-md-3), Ficha (col-md-4) -->
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-tag mr-1 text-success"></i> Estado
                    </label>
                    <select name="estado" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">Todos</option>
                        <option value="PENDIENTE" <?= $filtroEstado === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="APROBADA" <?= $filtroEstado === 'APROBADA' ? 'selected' : '' ?>>Aprobada</option>
                        <option value="RECHAZADA" <?= $filtroEstado === 'RECHAZADA' ? 'selected' : '' ?>>Rechazada</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="far fa-calendar-alt mr-1 text-success"></i> Fecha inicial
                    </label>
                    <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroFechaIni ?? '') ?>">
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="far fa-calendar-alt mr-1 text-success"></i> Fecha final
                    </label>
                    <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroFechaFin ?? '') ?>">
                </div>
                <div class="col-md-4 col-sm-12 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-layer-group mr-1 text-success"></i> Ficha
                    </label>
                    <select name="ficha" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">Todas las fichas</option>
                        <?php foreach ($fichas as $ficha): ?>
                            <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>" <?= $filtroFicha == $ficha['id_ficha'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ficha['id_ficha'] . ' - ' . $ficha['nombre_programa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Fila 2: Aprendiz (col-md-8) y Acciones (col-md-4) -->
            <div class="row align-items-end">
                <div class="col-md-8 col-sm-12 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-user mr-1 text-success"></i> Aprendiz
                    </label>
                    <input type="text" name="aprendiz" class="form-control form-control-sm" placeholder="Buscar por nombre o documento..." value="<?= htmlspecialchars($filtroAprendiz ?? '') ?>">
                </div>
                <div class="col-md-4 col-sm-12 mb-3 text-right">
                    <button type="submit" class="btn btn-sm btn-primary font-weight-bold shadow-sm mr-2">
                        <i class="fas fa-search mr-1"></i> Filtrar
                    </button>
                    <a href="<?= RUTA_BASE ?>views/admin/excusas/index.php" class="btn btn-sm btn-outline-secondary shadow-sm">
                        <i class="fas fa-times mr-1"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 2. Sección de Métricas (KPIs AdminLTE Enterprise) -->
<div class="row mb-4">
    <?php
    $pendientes = 0; $aprobadas = 0; $rechazadas = 0;
    foreach ($excusas as $excusa) {
        if ($excusa['estado'] === 'PENDIENTE') { $pendientes++; }
        if ($excusa['estado'] === 'APROBADA')  { $aprobadas++; }
        if ($excusa['estado'] === 'RECHAZADA') { $rechazadas++; }
    }
    ?>
    <div class="col-12 col-md-4 mb-3 mb-md-0">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-warning text-white elevation-1"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-muted font-weight-bold small">Excusas pendientes</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($pendientes) ?></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-3 mb-md-0">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-muted font-weight-bold small">Excusas aprobadas</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($aprobadas) ?></span>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-3 mb-md-0">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-muted font-weight-bold small">Excusas rechazadas</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($rechazadas) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 3. Sección de Listado de Excusas -->
<div class="card card-outline card-primary shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title font-weight-bold text-dark m-0">
            <i class="fas fa-file-medical text-primary mr-2"></i> Listado de excusas
        </h3>
        <span class="badge badge-primary px-3 py-1 font-weight-normal">
            <?= count($excusas) ?> excusas
        </span>
    </div>

    <div class="card-body">
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

        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle table-sm tabla-datos mb-0" data-empty-message="No se encontraron excusas con los filtros actuales.">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0">ID</th>
                        <th class="border-top-0">Aprendiz</th>
                        <th class="border-top-0">Identificación</th>
                        <th class="border-top-0">Programa / Ficha</th>
                        <th class="border-top-0">Fecha de inasistencia</th>
                        <th class="border-top-0">Fecha de subida</th>
                        <th class="border-top-0">Observación</th>
                        <th class="border-top-0 text-center">Estado</th>
                        <th class="border-top-0">Fecha de revisión</th>
                        <th class="border-top-0">Instructor revisor</th>
                        <th class="border-top-0 text-center">Adjunto</th>
                        <th class="border-top-0 text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($excusas)): ?>
                        <?php foreach ($excusas as $excusa): ?>
                            <tr>
                                <td class="font-weight-bold text-muted"><?= htmlspecialchars($excusa['id_excusa']) ?></td>
                                <td class="font-weight-bold"><?= htmlspecialchars($excusa['aprendiz']) ?></td>
                                <td><?= htmlspecialchars($excusa['identificacion_aprendiz']) ?></td>
                                <td>
                                    <span class="badge badge-info">Ficha #<?= htmlspecialchars($excusa['id_ficha']) ?></span>
                                    <br><small class="text-muted"><?= htmlspecialchars($excusa['nombre_programa']) ?></small>
                                </td>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($excusa['fecha_asistencia']))) ?></td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($excusa['fecha_subida']))) ?></td>
                                <td>
                                    <?php if (!empty($excusa['observacion'])): ?>
                                        <span class="small"><?= htmlspecialchars($excusa['observacion']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado_mostrado']) ?>"><?= htmlspecialchars($excusa['estado_mostrado']) ?></span></td>
                                <td>
                                    <?php if ($excusa['fecha_revision']): ?>
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($excusa['fecha_revision']))) ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($excusa['instructor_revisor']): ?>
                                        <?= htmlspecialchars($excusa['instructor_revisor']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= RUTA_BASE ?>views/admin/excusas/descargar.php?id_excusa=<?= htmlspecialchars($excusa['id_excusa']) ?>" class="btn btn-sm btn-outline-primary" title="Ver/Descargar adjunto">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php if ($excusa['estado'] === 'PENDIENTE'): ?>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Acciones de revisión (solo instructor)">
                                            <button type="button" class="btn btn-success" disabled title="La revisión de excusas corresponde al instructor">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger" disabled title="La revisión de excusas corresponde al instructor">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <span class="btn btn-light text-muted small px-2" style="cursor: default;">Solo instructor</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin acciones</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
