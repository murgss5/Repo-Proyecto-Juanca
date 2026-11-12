<?php
/**
 * =============================================================================
 * COMPONENTE REUTILIZABLE: Módulo de Reportes de Asistencia
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Compatible con AdminLTE y Bootstrap 4.
 * Parametrizado por:
 *   - $origen          : 'admin' | 'instructor'
 *   - $fichas          : listado de fichas disponibles
 *   - $resumen         : listado consolidado agrupado por aprendiz
 *   - $detalle         : listado detallado registro a registro
 *   - $kpis            : métricas totales calculadas
 *   - $filtros         : valores activos de filtros (fechas, ficha, etc.)
 *   - $urlExportarCsv  : URL de destino para la descarga del archivo CSV
 * =============================================================================
 */

$origen = $origen ?? 'admin';
$urlExportarCsv = $urlExportarCsv ?? 'exportar_csv.php';
$filtros = $filtros ?? [];
$kpis = $kpis ?? [];
$resumen = $resumen ?? [];
$detalle = $detalle ?? [];
$fichas = $fichas ?? [];

$paramsCsv = array_merge($filtros, ['export' => 'csv']);
$queryCsv = http_build_query(array_filter($paramsCsv, fn($v) => $v !== null && $v !== ''));
$enlaceDescargaCsv = $urlExportarCsv . ($queryCsv ? '?' . $queryCsv : '');
?>

<!-- ==================== BARRA SUPERIOR Y TARJETAS KPI ==================== -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="font-weight-bold text-dark mb-0">
            <i class="fas fa-chart-line text-success mr-2"></i> Reportes Analíticos de Asistencia
        </h4>
        <small class="text-muted">
            <?= $origen === 'admin' ? 'Consolidado institucional del centro' : 'Reporte de asistencia de mis fichas asignadas' ?>
        </small>
    </div>
    <div>
        <a href="<?= htmlspecialchars($enlaceDescargaCsv) ?>" class="btn btn-outline-success font-weight-bold shadow-sm">
            <i class="fas fa-file-csv mr-1"></i> Exportar a Excel (CSV)
        </a>
    </div>
</div>

<!-- Tarjetas de Métricas KPI -->
<div class="row">
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-light border-left border-primary shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Aprendices</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($kpis['total_aprendices'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-light border-left border-success shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Puntuales</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($kpis['total_puntuales'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-light border-left border-warning shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Retardos</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800">
                    <?= number_format($kpis['total_retardos'] ?? 0) ?>
                    <small class="text-muted font-weight-normal" style="font-size: 0.75rem;">(<?= number_format($kpis['total_min_retardo'] ?? 0) ?> min)</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-light border-left border-danger shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Inasistencias</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($kpis['total_inasistencias'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-light border-left border-info shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Justificadas</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($kpis['total_justificadas'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card bg-light border-left border-secondary shadow-sm h-100">
            <div class="card-body p-3">
                <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Salidas Temp.</div>
                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($kpis['total_salidas_ant'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== BARRA DE FILTROS ==================== -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-filter text-success mr-2"></i> Filtros de Búsqueda y Rango Temporal
        </h6>
    </div>
    <div class="card-body p-3">
        <form method="get" class="row align-items-end" id="formFiltrosReportes">
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Fecha Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Fecha Fin</label>
                <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['fecha_fin'] ?? '') ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Ficha de Formación</label>
                <select name="ficha" class="form-control form-control-sm custom-select custom-select-sm">
                    <option value="">Todas las fichas</option>
                    <?php foreach ($fichas as $f): ?>
                        <option value="<?= $f['id_ficha'] ?>" <?= (string)($filtros['ficha'] ?? '') === (string)$f['id_ficha'] ? 'selected' : '' ?>>
                            Ficha #<?= $f['id_ficha'] ?> - <?= htmlspecialchars($f['nombre_programa']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Aprendiz / Documento</label>
                <input type="text" name="aprendiz" class="form-control form-control-sm" placeholder="Nombre o cédula..." value="<?= htmlspecialchars($filtros['aprendiz'] ?? '') ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label class="small font-weight-bold text-muted mb-1">Estado de Entrada</label>
                <select name="estado_entrada" class="form-control form-control-sm custom-select custom-select-sm">
                    <option value="">Todos los estados</option>
                    <option value="PUNTUAL" <?= ($filtros['estado_entrada'] ?? '') === 'PUNTUAL' ? 'selected' : '' ?>>Puntual</option>
                    <option value="RETARDO" <?= ($filtros['estado_entrada'] ?? '') === 'RETARDO' ? 'selected' : '' ?>>Retardo</option>
                    <option value="INASISTENCIA" <?= ($filtros['estado_entrada'] ?? '') === 'INASISTENCIA' ? 'selected' : '' ?>>Inasistencia</option>
                    <option value="JUSTIFICADA" <?= ($filtros['estado_entrada'] ?? '') === 'JUSTIFICADA' ? 'selected' : '' ?>>Justificada</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-12 mb-2 d-flex gap-2">
                <button type="submit" class="btn btn-success btn-sm font-weight-bold flex-fill shadow-sm">
                    <i class="fas fa-search mr-1"></i> Filtrar
                </button>
                <a href="<?= htmlspecialchars(explode('?', $_SERVER['REQUEST_URI'] ?? '')[0]) ?>" class="btn btn-outline-secondary btn-sm shadow-sm" title="Restablecer filtros">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- ==================== PESTAÑAS: RESUMEN Y DETALLE ==================== -->
<div class="card card-outline card-success card-outline-tabs shadow-sm">
    <div class="card-header p-0 border-bottom-0">
        <ul class="nav nav-tabs" id="tabsReporte" role="tablist">
            <li class="nav-item">
                <a class="nav-link active font-weight-bold" id="tab-resumen-link" data-toggle="pill" href="#tab-resumen" role="tab" aria-controls="tab-resumen" aria-selected="true">
                    <i class="fas fa-users mr-1"></i> Consolidado por Aprendiz (<?= count($resumen) ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link font-weight-bold" id="tab-detalle-link" data-toggle="pill" href="#tab-detalle" role="tab" aria-controls="tab-detalle" aria-selected="false">
                    <i class="fas fa-list-alt mr-1"></i> Detalle Registro a Registro (<?= count($detalle) ?>)
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="tabsReporteContent">
            <!-- Pestaña 1: Resumen por Aprendiz -->
            <div class="tab-pane fade show active" id="tab-resumen" role="tabpanel" aria-labelledby="tab-resumen-link">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center align-middle" id="tablaResumenReporte" style="width: 100%;">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-left">Aprendiz</th>
                                <th>Documento</th>
                                <th>Ficha</th>
                                <th class="text-success"><i class="fas fa-check-circle mr-1"></i> Puntuales</th>
                                <th class="text-warning"><i class="fas fa-clock mr-1"></i> Retardos</th>
                                <th>Min. Retardo</th>
                                <th class="text-danger"><i class="fas fa-times-circle mr-1"></i> Inasistencias</th>
                                <th class="text-info"><i class="fas fa-file-medical mr-1"></i> Justificadas</th>
                                <th>Salidas Temp.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resumen as $r): ?>
                                <tr>
                                    <td class="text-left font-weight-bold"><?= htmlspecialchars($r['aprendiz']) ?></td>
                                    <td><code><?= htmlspecialchars($r['identificacion']) ?></code></td>
                                    <td><span class="badge badge-light border">#<?= $r['id_ficha'] ?></span></td>
                                    <td><span class="badge badge-success"><?= $r['puntuales'] ?></span></td>
                                    <td><span class="badge badge-warning"><?= $r['retardos'] ?></span></td>
                                    <td><span class="text-muted"><?= $r['total_min_retardo'] ?> min</span></td>
                                    <td>
                                        <?php if ((int)$r['inasistencias'] > 0): ?>
                                            <span class="badge badge-danger"><?= $r['inasistencias'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$r['justificadas'] > 0): ?>
                                            <span class="badge badge-info"><?= $r['justificadas'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$r['salidas_anticipadas'] > 0): ?>
                                            <span class="badge badge-secondary"><?= $r['salidas_anticipadas'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pestaña 2: Detalle Registro a Registro -->
            <div class="tab-pane fade" id="tab-detalle" role="tabpanel" aria-labelledby="tab-detalle-link">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center align-middle" id="tablaDetalleReporte" style="width: 100%;">
                        <thead class="thead-light">
                            <tr>
                                <th>Fecha</th>
                                <th class="text-left">Aprendiz</th>
                                <th>Ficha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado Entrada</th>
                                <th>Estado Salida</th>
                                <th>Retardo</th>
                                <th>Excusa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalle as $d): ?>
                                <?php
                                $estadoFmt = AsistenciaModel::formatearEstado($d['estado_entrada'], $d['estado_salida']);
                                $claseBadge = AsistenciaModel::claseInsigniaEstado($estadoFmt);
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['fecha_asistencia']) ?></td>
                                    <td class="text-left font-weight-bold"><?= htmlspecialchars($d['aprendiz']) ?></td>
                                    <td><span class="badge badge-light border">#<?= $d['id_ficha'] ?></span></td>
                                    <td><?= htmlspecialchars($d['hora_entrada'] ?: '--:--') ?></td>
                                    <td><?= htmlspecialchars($d['hora_salida'] ?: '--:--') ?></td>
                                    <td><span class="badge <?= $claseBadge ?>"><?= htmlspecialchars($estadoFmt) ?></span></td>
                                    <td>
                                        <?php if ($d['estado_salida'] === 'SALIDA_ANTICIPADA'): ?>
                                            <span class="badge badge-warning">Anticipada (<?= $d['minutos_salida_anticipada'] ?>m)</span>
                                        <?php else: ?>
                                            <span class="text-muted"><?= htmlspecialchars($d['estado_salida'] ?: 'Normal') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$d['minutos_retardo'] > 0): ?>
                                            <span class="badge badge-warning"><?= $d['minutos_retardo'] ?> min</span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($d['estado_excusa'])): ?>
                                            <span class="badge <?= ExcusaModel::claseInsigniaEstado($d['estado_excusa']) ?>">
                                                <?= htmlspecialchars($d['estado_excusa']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.inicializarTablaSENA === 'function') {
        window.inicializarTablaSENA('#tablaResumenReporte');
        
        // Inicializar la tabla de detalle cuando se activa la pestaña para asegurar render responsive
        $('a[data-toggle="pill"]').on('shown.bs.tab', function (e) {
            if (e.target.id === 'tab-detalle-link' && !$.fn.DataTable.isDataTable('#tablaDetalleReporte')) {
                window.inicializarTablaSENA('#tablaDetalleReporte');
            } else if ($.fn.DataTable.isDataTable('#tablaDetalleReporte')) {
                $('#tablaDetalleReporte').DataTable().columns.adjust().responsive.recalc();
            }
        });
    }
});
</script>
