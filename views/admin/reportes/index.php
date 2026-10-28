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
        case 'RETARDO': return 'badge-warning text-white';
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

<!-- 1. Sección de Filtros (Generador Enterprise) -->
<div class="card card-outline card-success shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title font-weight-bold text-dark m-0">
            <i class="fas fa-filter text-success mr-2"></i> Generador de reportes de asistencia
        </h3>
        <span class="badge badge-light border text-muted px-2 py-1 small">
            <i class="far fa-calendar-alt mr-1"></i> Período: <?= htmlspecialchars($filtroFechaIni) ?> al <?= htmlspecialchars($filtroFechaFin) ?>
        </span>
    </div>
    <div class="card-body">
        <form method="get" id="formFiltroReportes">
            <!-- Fila 1: Fechas, Ficha y Aprendiz -->
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="far fa-calendar mr-1 text-success"></i> Fecha inicial
                    </label>
                    <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroFechaIni) ?>">
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="far fa-calendar mr-1 text-success"></i> Fecha final
                    </label>
                    <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroFechaFin) ?>">
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-layer-group mr-1 text-success"></i> Ficha de formación
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
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-user mr-1 text-success"></i> Aprendiz
                    </label>
                    <input type="text" name="aprendiz" class="form-control form-control-sm" placeholder="Nombre o identificación..." value="<?= htmlspecialchars($filtroAprendiz ?? '') ?>">
                </div>
            </div>

            <!-- Fila 2: Estados y Grupo de acciones -->
            <div class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-sign-in-alt mr-1 text-success"></i> Estado entrada
                    </label>
                    <select name="estado_entrada" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="PUNTUAL" <?= $filtroEntrada === 'PUNTUAL' ? 'selected' : '' ?>>Puntual</option>
                        <option value="RETARDO" <?= $filtroEntrada === 'RETARDO' ? 'selected' : '' ?>>Retardo</option>
                        <option value="INASISTENCIA" <?= $filtroEntrada === 'INASISTENCIA' ? 'selected' : '' ?>>Inasistencia</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-sign-out-alt mr-1 text-success"></i> Estado salida
                    </label>
                    <select name="estado_salida" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">Todos los estados</option>
                        <option value="SALIDA_NORMAL" <?= $filtroSalida === 'SALIDA_NORMAL' ? 'selected' : '' ?>>Normal</option>
                        <option value="SALIDA_ANTICIPADA" <?= $filtroSalida === 'SALIDA_ANTICIPADA' ? 'selected' : '' ?>>Anticipada</option>
                        <option value="SIN_SALIDA" <?= $filtroSalida === 'SIN_SALIDA' ? 'selected' : '' ?>>Sin salida</option>
                    </select>
                </div>
                <div class="col-md-6 col-12 mb-3">
                    <div class="d-flex flex-wrap justify-content-md-end align-items-center" style="gap: 8px;">
                        <button type="submit" class="btn btn-sm btn-primary font-weight-bold shadow-sm">
                            <i class="fas fa-play mr-1"></i> Generar reporte
                        </button>
                        <button type="submit" name="filtro_semana" value="1" class="btn btn-sm btn-outline-info font-weight-bold shadow-sm" title="Filtrar automáticamente esta semana">
                            <i class="fas fa-calendar-week mr-1"></i> Esta semana
                        </button>
                        <a href="<?= RUTA_BASE ?>views/admin/reportes/exportar_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-success font-weight-bold shadow-sm" title="Exportar a archivo CSV">
                            <i class="fas fa-file-excel mr-1"></i> Exportar CSV
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger font-weight-bold shadow-sm" onclick="window.print()" title="Imprimir reporte o guardar como PDF">
                            <i class="fas fa-file-pdf mr-1"></i> Guardar PDF
                        </button>
                        <a href="<?= RUTA_BASE ?>views/admin/reportes/index.php" class="btn btn-sm btn-outline-secondary shadow-sm" title="Restablecer filtros">
                            <i class="fas fa-times mr-1"></i> Limpiar
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 2. Sección de KPIs: Métricas consolidadas (AdminLTE info-box Enterprise) -->
<div class="row justify-content-center mb-4">
    <!-- Aprendices -->
    <div class="col-12 col-sm-6 col-md-4 col-lg mb-3">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-success elevation-1"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-truncate font-weight-bold text-muted small" title="Aprendices evaluados">Aprendices</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($totales['total_aprendices']) ?></span>
            </div>
        </div>
    </div>

    <!-- Retardos -->
    <div class="col-12 col-sm-6 col-md-4 col-lg mb-3">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-warning text-white elevation-1"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-truncate font-weight-bold text-muted small" title="Retardos registrados">Retardos</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($totales['total_retardos']) ?></span>
            </div>
        </div>
    </div>

    <!-- Inasistencias injustificadas -->
    <div class="col-12 col-sm-6 col-md-4 col-lg mb-3">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-user-times"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-truncate font-weight-bold text-muted small" title="Inasistencias injustificadas">Inasist. injustificadas</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($totales['total_inasistencias_no_justificadas']) ?></span>
            </div>
        </div>
    </div>

    <!-- Inasistencias justificadas -->
    <div class="col-12 col-sm-6 col-md-4 col-lg mb-3">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-info elevation-1"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-truncate font-weight-bold text-muted small" title="Inasistencias justificadas con excusa aprobada">Inasist. justificadas</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($totales['total_inasistencias_justificadas']) ?></span>
            </div>
        </div>
    </div>

    <!-- Excusas pendientes -->
    <div class="col-12 col-sm-6 col-md-4 col-lg mb-3">
        <div class="info-box shadow-sm h-100 mb-0">
            <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
                <span class="info-box-text text-truncate font-weight-bold text-muted small" title="Excusas pendientes por revisar">Excusas pendientes</span>
                <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.35rem;"><?= number_format($totales['total_excusas_pendientes']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- 3. Sección de Tablas: Resumen consolidado por aprendiz -->
<div class="card card-outline card-primary shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="card-title font-weight-bold text-dark m-0">
            <i class="fas fa-user-check text-primary mr-2"></i> Resumen por aprendiz
        </h3>
        <span class="badge badge-primary px-3 py-1 font-weight-normal">
            <?= count($resumenAprendices) ?> aprendices
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaReportes" class="table table-hover table-striped align-middle table-sm tabla-datos mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0">Aprendiz</th>
                        <th class="border-top-0">Identificación</th>
                        <th class="border-top-0">Programa</th>
                        <th class="border-top-0">Jornada</th>
                        <th class="border-top-0">Ficha</th>
                        <th class="text-center border-top-0">Puntuales</th>
                        <th class="text-center border-top-0">Retardos</th>
                        <th class="text-center border-top-0">Exc. aprobadas</th>
                        <th class="text-center border-top-0">Exc. pendientes</th>
                        <th class="text-center border-top-0">Exc. rechazadas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resumenAprendices as $row): ?>
                        <tr>
                            <td class="font-weight-bold"><?= htmlspecialchars($row['aprendiz']) ?></td>
                            <td><?= htmlspecialchars($row['identificacion']) ?></td>
                            <td><span class="text-truncate d-inline-block" style="max-width: 200px;" title="<?= htmlspecialchars($row['nombre_programa']) ?>"><?= htmlspecialchars($row['nombre_programa']) ?></span></td>
                            <td><?= htmlspecialchars($row['jornada']) ?></td>
                            <td><span class="badge badge-info">#<?= htmlspecialchars($row['id_ficha']) ?></span></td>
                            <td class="text-center"><?= (int)$row['puntuales'] ?></td>
                            <td class="text-center"><span class="badge badge-warning text-white"><?= (int)$row['retardos'] ?></span></td>
                            <td class="text-center"><span class="badge badge-success"><?= (int)$row['excusas_aprobadas'] ?></span></td>
                            <td class="text-center"><span class="badge badge-warning text-white"><?= (int)$row['excusas_pendientes'] ?></span></td>
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
