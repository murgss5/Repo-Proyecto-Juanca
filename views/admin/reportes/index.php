<?php
/**
 * ============================================================
 * VISTA: REPORTES (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Reporte real de asistencias con filtros, totales y exportacion.
 * ============================================================
 */

// Carga la autenticacion y verifica que sea administrador
require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
// Carga constantes y conexion a la base de datos
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

$conexion = (new Conexion())->conectar();

// --- Obtener filtros GET ---
$filtroFechaIni = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : null;
$filtroFechaFin = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : null;
$filtroFicha    = isset($_GET['ficha']) && $_GET['ficha'] !== '' ? $_GET['ficha'] : null;
$filtroAprendiz = isset($_GET['aprendiz']) && $_GET['aprendiz'] !== '' ? trim($_GET['aprendiz']) : null;
$filtroEntrada  = isset($_GET['estado_entrada']) && $_GET['estado_entrada'] !== '' ? $_GET['estado_entrada'] : null;
$filtroSalida   = isset($_GET['estado_salida']) && $_GET['estado_salida'] !== '' ? $_GET['estado_salida'] : null;

// Si se presiono el boton "Esta semana", calcular lunes y domingo de la semana actual
if (isset($_GET['filtro_semana'])) {
    $hoy = new DateTime();
    $inicioSemana = clone $hoy;
    $inicioSemana->modify('monday this week');
    $finSemana = clone $hoy;
    $finSemana->modify('sunday this week');
    $filtroFechaIni = $inicioSemana->format('Y-m-d');
    $filtroFechaFin = $finSemana->format('Y-m-d');
}

// Validar formato de fechas
if ($filtroFechaIni && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaIni)) {
    $filtroFechaIni = null;
}
if ($filtroFechaFin && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtroFechaFin)) {
    $filtroFechaFin = null;
}

// Valores por defecto: mes actual
if (!$filtroFechaIni) {
    $filtroFechaIni = date('Y-m-01');
}
if (!$filtroFechaFin) {
    $filtroFechaFin = date('Y-m-t');
}

// Calcular fechas de la semana actual (lunes a domingo) para el filtro rapido
$hoy = new DateTime();
$inicioSemana = clone $hoy;
$inicioSemana->modify('monday this week');
$finSemana = clone $hoy;
$finSemana->modify('sunday this week');
$fechaInicioSemana = $inicioSemana->format('Y-m-d');
$fechaFinSemana = $finSemana->format('Y-m-d');

// Obtener fichas para el filtro
$fichas = $conexion->query("SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha")->fetchAll();

// --- Construir consulta principal (resumen por aprendiz) ---
$where = ["a.fecha_asistencia BETWEEN :fecha_ini AND :fecha_fin"];
$params = [
    ':fecha_ini' => $filtroFechaIni,
    ':fecha_fin' => $filtroFechaFin,
];

if ($filtroFicha) {
    $where[] = "ap.fk_ficha = :ficha";
    $params[':ficha'] = $filtroFicha;
}
if ($filtroAprendiz) {
    $where[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :aprendiz OR u.identificacion LIKE :aprendiz)";
    $params[':aprendiz'] = "%{$filtroAprendiz}%";
}
if ($filtroEntrada) {
    $where[] = "a.estado_entrada = :estado_entrada";
    $params[':estado_entrada'] = $filtroEntrada;
}
if ($filtroSalida) {
    $where[] = "a.estado_salida = :estado_salida";
    $params[':estado_salida'] = $filtroSalida;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Consulta de resumen agrupada por aprendiz
$sqlResumen = "
    SELECT
        ap.id_aprendiz,
        CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
        u.identificacion,
        f.id_ficha,
        f.nombre_programa,
        f.jornada,
        SUM(CASE WHEN a.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
        SUM(CASE WHEN a.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
        SUM(CASE WHEN a.estado_entrada = 'RETARDO' THEN COALESCE(a.minutos_retardo, 0) ELSE 0 END) AS total_min_retardo,
        SUM(CASE WHEN a.estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
        SUM(CASE WHEN a.estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_anticipadas,
        SUM(CASE WHEN a.estado_salida = 'SALIDA_ANTICIPADA' THEN COALESCE(a.minutos_salida_anticipada, 0) ELSE 0 END) AS total_min_salida_anticipada,
        SUM(CASE WHEN e.estado = 'APROBADA' THEN 1 ELSE 0 END) AS excusas_aprobadas,
        SUM(CASE WHEN e.estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS excusas_pendientes,
        SUM(CASE WHEN e.estado = 'RECHAZADA' THEN 1 ELSE 0 END) AS excusas_rechazadas,
        SUM(CASE WHEN a.estado_entrada = 'INASISTENCIA' AND e.estado = 'APROBADA' THEN 1 ELSE 0 END) AS inasistencias_justificadas
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
    {$whereSql}
    GROUP BY ap.id_aprendiz, u.nombre, u.apellido, u.identificacion, f.id_ficha, f.nombre_programa, f.jornada
    ORDER BY u.apellido, u.nombre
";

$consultaResumen = $conexion->prepare($sqlResumen);
$consultaResumen->execute($params);
$resumenAprendices = $consultaResumen->fetchAll();

// Calcular totales generales
$totales = [
    'total_aprendices' => count($resumenAprendices),
    'total_retardos' => 0,
    'total_min_retardo' => 0,
    'total_inasistencias' => 0,
    'total_inasistencias_justificadas' => 0,
    'total_inasistencias_no_justificadas' => 0,
    'total_salidas_anticipadas' => 0,
    'total_min_salida_anticipada' => 0,
    'total_excusas_pendientes' => 0,
];

foreach ($resumenAprendices as $row) {
    $totales['total_retardos'] += (int)$row['retardos'];
    $totales['total_min_retardo'] += (int)$row['total_min_retardo'];
    $totales['total_inasistencias'] += (int)$row['inasistencias'];
    $totales['total_inasistencias_justificadas'] += (int)$row['inasistencias_justificadas'];
    $totales['total_salidas_anticipadas'] += (int)$row['salidas_anticipadas'];
    $totales['total_min_salida_anticipada'] += (int)$row['total_min_salida_anticipada'];
    $totales['total_excusas_pendientes'] += (int)$row['excusas_pendientes'];
}
$totales['total_inasistencias_no_justificadas'] = $totales['total_inasistencias'] - $totales['total_inasistencias_justificadas'];

// --- Consulta de detalle de asistencias ---
$sqlDetalle = "
    SELECT
        a.fecha_asistencia,
        CONCAT(u.nombre, CHAR(32), u.apellido) AS aprendiz,
        u.identificacion,
        f.id_ficha,
        f.nombre_programa,
        a.entrada,
        a.salida,
        a.estado_entrada,
        a.estado_salida,
        a.minutos_retardo,
        a.minutos_salida_anticipada,
        e.estado AS estado_excusa
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
    LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
    {$whereSql}
    ORDER BY a.fecha_asistencia DESC, u.apellido, u.nombre
";

$consultaDetalle = $conexion->prepare($sqlDetalle);
$consultaDetalle->execute($params);
$detalleAsistencias = $consultaDetalle->fetchAll();

// Función para badge de estado
function badgeEstado($estado) {
    switch ($estado) {
        case 'PUNTUAL': return 'badge-success';
        case 'RETARDO': return 'badge-warning';
        case 'INASISTENCIA': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

function badgeEstadoSalida($estado) {
    switch ($estado) {
        case 'SALIDA_NORMAL': return 'badge-success';
        case 'SALIDA_ANTICIPADA': return 'badge-info';
        case 'SIN_SALIDA': return 'badge-danger';
        default: return 'badge-secondary';
    }
}

// --- Variables del layout ---
$paginaTitulo = 'Reportes';
$menuActivo   = 'reportes';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Reportes'],
];

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar text-sena"></i> Generador de reportes de asistencia</h3>
    </div>
    <div class="card-body">
        <form method="get" class="row">
            <div class="col-md-3 col-sm-6 mb-2">
                <label class="small text-muted">Fecha inicial</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($filtroFechaIni) ?>">
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <label class="small text-muted">Fecha final</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($filtroFechaFin) ?>">
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
            <div class="col-md-3 col-sm-6 mb-2">
                <label class="small text-muted">Aprendiz</label>
                <input type="text" name="aprendiz" class="form-control" placeholder="Nombre o identificación..." value="<?= htmlspecialchars($filtroAprendiz ?? '') ?>">
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <label class="small text-muted">Estado entrada</label>
                <select name="estado_entrada" class="form-control">
                    <option value="">Todos</option>
                    <option value="PUNTUAL" <?= $filtroEntrada === 'PUNTUAL' ? 'selected' : '' ?>>Puntual</option>
                    <option value="RETARDO" <?= $filtroEntrada === 'RETARDO' ? 'selected' : '' ?>>Retardo</option>
                    <option value="INASISTENCIA" <?= $filtroEntrada === 'INASISTENCIA' ? 'selected' : '' ?>>Inasistencia</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <label class="small text-muted">Estado salida</label>
                <select name="estado_salida" class="form-control">
                    <option value="">Todos</option>
                    <option value="SALIDA_NORMAL" <?= $filtroSalida === 'SALIDA_NORMAL' ? 'selected' : '' ?>>Normal</option>
                    <option value="SALIDA_ANTICIPADA" <?= $filtroSalida === 'SALIDA_ANTICIPADA' ? 'selected' : '' ?>>Anticipada</option>
                    <option value="SIN_SALIDA" <?= $filtroSalida === 'SIN_SALIDA' ? 'selected' : '' ?>>Sin salida</option>
                </select>
            </div>
            <div class="col-12 mt-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Generar reporte</button>
                <a href="<?= RUTA_BASE ?>views/admin/reportes/exportar_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success"><i class="fas fa-file-excel"></i> Exportar CSV</a>
                <button type="button" class="btn btn-outline-danger" onclick="window.print()"><i class="fas fa-file-pdf"></i> Imprimir / Guardar como PDF</button>
                <a href="<?= RUTA_BASE ?>views/admin/reportes/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpiar</a>
            </div>
            <div class="col-12 mt-2">
                <button type="submit" name="filtro_semana" value="1" class="btn btn-info"><i class="fas fa-calendar-week"></i> Esta semana</button>
            </div>
        </form>
    </div>
</div>

<!-- Tarjetas de totales -->
<div class="row mb-3">
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-sena">
            <span class="info-box-icon"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Aprendices</span>
                <span class="info-box-number"><?= $totales['total_aprendices'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-amarillo">
            <span class="info-box-icon"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Retardos</span>
                <span class="info-box-number"><?= $totales['total_retardos'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-amarillo">
            <span class="info-box-icon"><i class="fas fa-stopwatch"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Min. retardo</span>
                <span class="info-box-number"><?= $totales['total_min_retardo'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-rojo">
            <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Inasistencias (no justif.)</span>
                <span class="info-box-number"><?= $totales['total_inasistencias_no_justificadas'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-azul">
            <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Inasistencias justificadas</span>
                <span class="info-box-number"><?= $totales['total_inasistencias_justificadas'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-azul">
            <span class="info-box-icon"><i class="fas fa-sign-out-alt"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Salidas anticipadas</span>
                <span class="info-box-number"><?= $totales['total_salidas_anticipadas'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
        <div class="info-box info-box-gris">
            <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas pendientes</span>
                <span class="info-box-number"><?= $totales['total_excusas_pendientes'] ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Resumen por aprendiz -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-table text-sena"></i> Resumen por aprendiz (<?= count($resumenAprendices) ?>)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaReportes" class="table table-hover table-bordered tabla-datos">
                <thead>
                    <tr>
                        <th>Aprendiz</th>
                        <th>Identificación</th>
                        <th>Programa</th>
                        <th>Jornada</th>
                        <th>Ficha</th>
                        <th class="text-center">Puntuales</th>
                        <th class="text-center">Retardos</th>
                        <th class="text-center">Min. retardo</th>
                        <th class="text-center">Inasistencias</th>
                        <th class="text-center">Inasist. justif.</th>
                        <th class="text-center">Salidas anticip.</th>
                        <th class="text-center">Min. salida ant.</th>
                        <th class="text-center">Exc. aprobadas</th>
                        <th class="text-center">Exc. pendientes</th>
                        <th class="text-center">Exc. rechazadas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumenAprendices as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['aprendiz']) ?></td>
                            <td><?= htmlspecialchars($row['identificacion']) ?></td>
                            <td><?= htmlspecialchars($row['nombre_programa']) ?></td>
                            <td><?= htmlspecialchars($row['jornada']) ?></td>
                            <td><span class="badge badge-info">#<?= htmlspecialchars($row['id_ficha']) ?></span></td>
                            <td class="text-center"><?= (int)$row['puntuales'] ?></td>
                            <td class="text-center"><span class="badge badge-warning"><?= (int)$row['retardos'] ?></span></td>
                            <td class="text-center"><?= (int)$row['total_min_retardo'] ?> min</td>
                            <td class="text-center"><span class="badge badge-danger"><?= (int)$row['inasistencias'] ?></span></td>
                            <td class="text-center"><span class="badge badge-info"><?= (int)$row['inasistencias_justificadas'] ?></span></td>
                            <td class="text-center"><span class="badge badge-info"><?= (int)$row['salidas_anticipadas'] ?></span></td>
                            <td class="text-center"><?= (int)$row['total_min_salida_anticipada'] ?> min</td>
                            <td class="text-center"><span class="badge badge-success"><?= (int)$row['excusas_aprobadas'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning"><?= (int)$row['excusas_pendientes'] ?></span></td>
                            <td class="text-center"><span class="badge badge-danger"><?= (int)$row['excusas_rechazadas'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($resumenAprendices)): ?>
            <p class="text-center text-muted py-4"><i class="fas fa-chart-bar fa-2x text-muted mb-2"></i><br>No hay datos con los filtros actuales.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Detalle de asistencias -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list text-sena"></i> Detalle de asistencias (<?= count($detalleAsistencias) ?> registros)</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table id="tablaDetalleAsistencias" class="table table-hover table-bordered tabla-datos">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Aprendiz</th>
                        <th>Identificación</th>
                        <th>Ficha</th>
                        <th>Hora entrada</th>
                        <th>Hora salida</th>
                        <th>Estado entrada</th>
                        <th>Estado salida</th>
                        <th class="text-center">Min. retardo</th>
                        <th class="text-center">Min. salida ant.</th>
                        <th>Estado excusa</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($detalleAsistencias as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_asistencia']))) ?></td>
                            <td><?= htmlspecialchars($row['aprendiz']) ?></td>
                            <td><?= htmlspecialchars($row['identificacion']) ?></td>
                            <td><span class="badge badge-info">#<?= htmlspecialchars($row['id_ficha']) ?></span></td>
                            <td><?= $row['entrada'] ? htmlspecialchars(date('H:i', strtotime($row['entrada']))) : '<span class="text-muted">--</span>' ?></td>
                            <td><?= $row['salida'] ? htmlspecialchars(date('H:i', strtotime($row['salida']))) : '<span class="text-muted">--</span>' ?></td>
                            <td><span class="badge badge-estado <?= badgeEstado($row['estado_entrada']) ?>"><?= htmlspecialchars($row['estado_entrada']) ?></span></td>
                            <td><span class="badge badge-estado <?= badgeEstadoSalida($row['estado_salida']) ?>"><?= htmlspecialchars($row['estado_salida']) ?></span></td>
                            <td class="text-center">
                                <?php if ((int)$row['minutos_retardo'] > 0): ?>
                                    <span class="badge badge-warning"><?= (int)$row['minutos_retardo'] ?> min</span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int)$row['minutos_salida_anticipada'] > 0): ?>
                                    <span class="badge badge-info"><?= (int)$row['minutos_salida_anticipada'] ?> min</span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['estado_excusa']): ?>
                                    <?php
                                        $claseExcusa = 'badge-secondary';
                                        if ($row['estado_excusa'] === 'PENDIENTE') $claseExcusa = 'badge-warning';
                                        if ($row['estado_excusa'] === 'APROBADA') $claseExcusa = 'badge-success';
                                        if ($row['estado_excusa'] === 'RECHAZADA') $claseExcusa = 'badge-danger';
                                    ?>
                                    <span class="badge badge-estado <?= $claseExcusa ?>"><?= htmlspecialchars(ucfirst(strtolower($row['estado_excusa']))) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($detalleAsistencias)): ?>
            <p class="text-center text-muted py-4"><i class="fas fa-calendar-alt fa-2x text-muted mb-2"></i><br>No hay registros de asistencia con los filtros actuales.</p>
        <?php endif; ?>
        <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Los datos provienen de la base de datos. Las inasistencias con excusa APROBADA no se cuentan como inasistencias en el total principal (columna "Inasist. justif.").</p>
    </div>
</div>

<!-- CSS para impresión -->
<style media="print">
    .no-print { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { background: #f4f6f9 !important; border-bottom: 1px solid #dee2e6 !important; }
    .btn { display: none !important; }
    .info-box { border: 1px solid #dee2e6 !important; margin-bottom: 10px !important; }
    .table { font-size: 11px !important; }
    @page { margin: 1cm; }
</style>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';