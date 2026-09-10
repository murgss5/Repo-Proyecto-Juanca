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
        case 'Pendiente': return 'badge-warning';
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
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter text-sena"></i> Filtros</h3>
            </div>
            <div class="card-body">
                <form method="get" class="row">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="">Todos</option>
                            <option value="PENDIENTE" <?= $filtroEstado === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="APROBADA" <?= $filtroEstado === 'APROBADA' ? 'selected' : '' ?>>Aprobada</option>
                            <option value="RECHAZADA" <?= $filtroEstado === 'RECHAZADA' ? 'selected' : '' ?>>Rechazada</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted">Fecha inicial</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($filtroFechaIni ?? '') ?>">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted">Fecha final</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($filtroFechaFin ?? '') ?>">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted">Ficha</label>
                        <select name="ficha" class="form-control">
                            <option value="">Todas las fichas</option>
                            <?php foreach ($fichas as $ficha): ?>
                                <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>" <?= $filtroFicha == $ficha['id_ficha'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ficha['id_ficha'] . ' - ' . $ficha['nombre_programa']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 col-sm-12 mb-2">
                        <label class="small text-muted">Aprendiz (nombre o identificación)</label>
                        <input type="text" name="aprendiz" class="form-control" placeholder="Buscar por nombre o documento..." value="<?= htmlspecialchars($filtroAprendiz ?? '') ?>">
                    </div>
                    <div class="col-md-6 col-sm-12 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Filtrar</button>
                        <a href="<?= RUTA_BASE ?>views/admin/excusas/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Resumen de estados -->
<div class="row mb-3">
    <?php
    $pendientes = 0; $aprobadas = 0; $rechazadas = 0;
    foreach ($excusas as $excusa) {
        if ($excusa['estado'] === 'PENDIENTE') { $pendientes++; }
        if ($excusa['estado'] === 'APROBADA')  { $aprobadas++; }
        if ($excusa['estado'] === 'RECHAZADA') { $rechazadas++; }
    }
    ?>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="info-box info-box-amarillo">
            <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas pendientes</span>
                <span class="info-box-number"><?= $pendientes ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="info-box info-box-sena">
            <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas aprobadas</span>
                <span class="info-box-number"><?= $aprobadas ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="info-box info-box-rojo">
            <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas rechazadas</span>
                <span class="info-box-number"><?= $rechazadas ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-medical text-sena"></i> Listado de excusas (<?= count($excusas) ?>)</h3>
    </div>

    <div class="card-body">
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover table-bordered tabla-datos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Aprendiz</th>
                        <th>Identificación</th>
                        <th>Programa / Ficha</th>
                        <th>Fecha de inasistencia</th>
                        <th>Fecha de subida</th>
                        <th>Observación</th>
                        <th>Estado</th>
                        <th>Fecha de revisión</th>
                        <th>Instructor revisor</th>
                        <th class="text-center">Adjunto</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($excusas)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-4">
                                <i class="fas fa-file-medical fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No se encontraron excusas con los filtros actuales.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($excusas as $excusa): ?>
                            <tr>
                                <td><?= htmlspecialchars($excusa['id_excusa']) ?></td>
                                <td><?= htmlspecialchars($excusa['aprendiz']) ?></td>
                                <td><?= htmlspecialchars($excusa['identificacion_aprendiz']) ?></td>
                                <td>
                                    <span class="badge badge-info">Ficha #<?= htmlspecialchars($excusa['id_ficha']) ?></span>
                                    <br><small><?= htmlspecialchars($excusa['nombre_programa']) ?></small>
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
                                <td><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado_mostrado']) ?>"><?= htmlspecialchars($excusa['estado_mostrado']) ?></span></td>
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