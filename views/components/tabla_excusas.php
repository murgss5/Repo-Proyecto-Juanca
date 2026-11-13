<?php
/**
 * =============================================================================
 * COMPONENTE REUTILIZABLE: Tabla y Gestión de Excusas Médicas
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Compatible con AdminLTE y Bootstrap 4.
 * Parametrizado por:
 *   - $origen           : 'admin' | 'instructor'
 *   - $excusas          : listado de registros de excusas
 *   - $fichas           : listado de fichas disponibles
 *   - $filtros          : valores activos de filtros (estado, fechas, ficha, aprendiz)
 *   - $urlDescargaBase  : URL base del script descargar.php
 *   - $tokenCsrf        : token CSRF para acciones de revisión
 * =============================================================================
 */

require_once dirname(__DIR__, 2) . '/models/ExcusaModel.php';

$origen          = $origen ?? 'admin';
$excusas         = $excusas ?? [];
$fichas          = $fichas ?? [];
$filtros         = $filtros ?? [];
$urlDescargaBase = $urlDescargaBase ?? (RUTA_BASE . 'views/' . $origen . '/excusas/descargar.php');
$tokenCsrf       = $tokenCsrf ?? ($_SESSION['token_csrf'] ?? '');

$filtroEstado   = $filtros['estado'] ?? '';
$filtroFechaIni = $filtros['fecha_inicio'] ?? '';
$filtroFechaFin = $filtros['fecha_fin'] ?? '';
$filtroFicha    = $filtros['ficha'] ?? '';
$filtroAprendiz = $filtros['aprendiz'] ?? '';

$urlLimpiar = RUTA_BASE . 'views/' . $origen . '/excusas/index.php';

// Conteo rápido de estados
$pendientes = 0;
$aprobadas  = 0;
$rechazadas = 0;

foreach ($excusas as $exc) {
    $est = strtoupper(trim((string)($exc['estado'] ?? '')));
    if ($est === 'PENDIENTE') { $pendientes++; }
    elseif ($est === 'APROBADA') { $aprobadas++; }
    elseif ($est === 'RECHAZADA') { $rechazadas++; }
}
?>

<!-- Alertas informativas -->
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

<!-- Tarjeta de Filtros de Búsqueda -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-outline card-success shadow-sm mb-3">
            <div class="card-header bg-white py-3">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-filter text-success mr-2"></i> Filtros de Búsqueda de Excusas
                </h3>
            </div>
            <div class="card-body p-3">
                <form method="get" class="row align-items-end" id="formFiltrosExcusas">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Estado</label>
                        <select name="estado" class="form-control form-control-sm custom-select">
                            <option value="">Todos los estados</option>
                            <option value="PENDIENTE" <?= $filtroEstado === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="APROBADA" <?= $filtroEstado === 'APROBADA' ? 'selected' : '' ?>>Aprobada</option>
                            <option value="RECHAZADA" <?= $filtroEstado === 'RECHAZADA' ? 'selected' : '' ?>>Rechazada</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Fecha inicial</label>
                        <input type="date" name="fecha_inicio" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroFechaIni) ?>">
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Fecha final</label>
                        <input type="date" name="fecha_fin" class="form-control form-control-sm" value="<?= htmlspecialchars($filtroFechaFin) ?>">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Ficha de formación</label>
                        <select name="ficha" class="form-control form-control-sm custom-select">
                            <option value="">Todas las fichas</option>
                            <?php foreach ($fichas as $f): ?>
                                <option value="<?= htmlspecialchars((string)$f['id_ficha']) ?>" <?= (string)$filtroFicha === (string)$f['id_ficha'] ? 'selected' : '' ?>>
                                    Ficha #<?= htmlspecialchars((string)$f['id_ficha']) ?> - <?= htmlspecialchars($f['nombre_programa'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold mb-1">Aprendiz</label>
                        <input type="text" name="aprendiz" class="form-control form-control-sm" placeholder="Nombre o CC..." value="<?= htmlspecialchars($filtroAprendiz) ?>">
                    </div>
                    <div class="col-12 mt-2 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success btn-sm font-weight-bold shadow-sm mr-2">
                            <i class="fas fa-search mr-1"></i> Filtrar
                        </button>
                        <a href="<?= htmlspecialchars($urlLimpiar) ?>" class="btn btn-outline-secondary btn-sm shadow-sm">
                            <i class="fas fa-times mr-1"></i> Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tarjetas Resumen de Estados KPI -->
<div class="row mb-3">
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="card bg-light border-left border-warning shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="mr-3 text-warning">
                    <i class="fas fa-hourglass-half fa-2x"></i>
                </div>
                <div>
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Excusas Pendientes</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($pendientes) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="card bg-light border-left border-success shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="mr-3 text-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div>
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Excusas Aprobadas</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($aprobadas) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="card bg-light border-left border-danger shadow-sm h-100">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="mr-3 text-danger">
                    <i class="fas fa-times-circle fa-2x"></i>
                </div>
                <div>
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Excusas Rechazadas</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($rechazadas) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Principal de Excusas -->
<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-file-medical text-success mr-2"></i> 
            <?= $origen === 'admin' ? 'Listado Institucional de Excusas Médicas' : 'Excusas de Mis Aprendices' ?>
            <span class="badge badge-light border ml-2"><?= count($excusas) ?></span>
        </h3>
    </div>

    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover table-bordered text-nowrap mb-0" id="tablaExcusasPrincipal" data-empty-message="No se encontraron excusas médicas con los filtros actuales.">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Aprendiz</th>
                        <th class="d-none d-md-table-cell">Identificación</th>
                        <th class="d-none d-lg-table-cell">Programa / Ficha</th>
                        <th>Inasistencia</th>
                        <th class="d-none d-md-table-cell">Fecha Subida</th>
                        <th>Motivo / Observación</th>
                        <th class="text-center">Estado</th>
                        <th class="d-none d-xl-table-cell">Revisión</th>
                        <th class="d-none d-xl-table-cell">Instructor Revisor</th>
                        <th class="text-center" style="min-width: 90px;">Adjunto</th>
                        <th class="text-center col-acciones" style="width: 110px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($excusas as $excusa): ?>
                        <?php
                            $estado = strtoupper(trim((string)($excusa['estado'] ?? '')));
                            $claseBadge = ExcusaModel::claseInsigniaEstado($estado);
                            $urlDescarga = $urlDescargaBase . '?id_excusa=' . urlencode((string)$excusa['id_excusa']);
                            $urlPreview = $urlDescarga . '&inline=1';
                            $archivo = $excusa['archivo'] ?? $excusa['url_documento'] ?? '';
                            $extArchivo = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
                            $fechaInasistenciaFmt = !empty($excusa['fecha_asistencia']) ? date('d/m/Y', strtotime($excusa['fecha_asistencia'])) : '--';
                            $fechaSubidaFmt = !empty($excusa['fecha_subida']) ? date('d/m/Y H:i', strtotime($excusa['fecha_subida'])) : '--';
                            $fechaRevisionFmt = !empty($excusa['fecha_revision']) ? date('d/m/Y H:i', strtotime($excusa['fecha_revision'])) : '--';
                        ?>
                        <tr>
                            <!-- ID -->
                            <td class="align-middle font-weight-bold text-muted">
                                #<?= htmlspecialchars((string)$excusa['id_excusa']) ?>
                            </td>

                            <!-- Aprendiz -->
                            <td class="align-middle">
                                <span class="font-weight-bold text-dark"><?= htmlspecialchars($excusa['aprendiz']) ?></span>
                                <div class="d-md-none text-muted small mt-1">
                                    <span>CC: <?= htmlspecialchars($excusa['identificacion_aprendiz'] ?? '') ?></span> &bull;
                                    <span class="badge badge-info">Ficha #<?= htmlspecialchars((string)$excusa['id_ficha']) ?></span>
                                </div>
                            </td>

                            <!-- Identificación -->
                            <td class="align-middle d-none d-md-table-cell">
                                <?= htmlspecialchars($excusa['identificacion_aprendiz'] ?? '--') ?>
                            </td>

                            <!-- Programa / Ficha -->
                            <td class="align-middle d-none d-lg-table-cell">
                                <span class="badge badge-success px-2 py-1">Ficha #<?= htmlspecialchars((string)$excusa['id_ficha']) ?></span>
                                <small class="text-muted d-block mt-1"><?= htmlspecialchars($excusa['nombre_programa'] ?? '') ?></small>
                            </td>

                            <!-- Fecha Inasistencia -->
                            <td class="align-middle font-weight-bold">
                                <?= htmlspecialchars($fechaInasistenciaFmt) ?>
                            </td>

                            <!-- Fecha Subida -->
                            <td class="align-middle text-muted d-none d-md-table-cell">
                                <?= htmlspecialchars($fechaSubidaFmt) ?>
                            </td>

                            <!-- Motivo / Observación -->
                            <td class="align-middle" style="max-width: 250px; white-space: normal;">
                                <?php if (!empty($excusa['observacion'])): ?>
                                    <span class="small text-dark"><?= htmlspecialchars($excusa['observacion']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted font-italic">&mdash;</span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado -->
                            <td class="text-center align-middle">
                                <span class="badge badge-estado <?= $claseBadge ?> px-2 py-1">
                                    <?= htmlspecialchars(ucfirst(strtolower($estado))) ?>
                                </span>
                            </td>

                            <!-- Fecha Revisión -->
                            <td class="align-middle text-muted small d-none d-xl-table-cell">
                                <?= htmlspecialchars($fechaRevisionFmt) ?>
                            </td>

                            <!-- Instructor Revisor -->
                            <td class="align-middle text-muted small d-none d-xl-table-cell">
                                <?= htmlspecialchars($excusa['instructor_revisor'] ?? '--') ?>
                            </td>

                            <!-- Adjunto (Preview & Download) -->
                            <td class="text-center align-middle text-nowrap">
                                <?php if (!empty($archivo)): ?>
                                    <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="Adjunto">
                                        <button type="button" 
                                                class="btn btn-info btn-preview-excusa" 
                                                data-url="<?= htmlspecialchars($urlPreview) ?>"
                                                data-download="<?= htmlspecialchars($urlDescarga) ?>"
                                                data-tipo="<?= htmlspecialchars($extArchivo) ?>"
                                                data-aprendiz="<?= htmlspecialchars($excusa['aprendiz']) ?>"
                                                data-fecha="<?= htmlspecialchars($fechaInasistenciaFmt) ?>"
                                                title="Previsualizar documento">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="<?= htmlspecialchars($urlDescarga) ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Descargar archivo original" 
                                           download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small font-italic">Sin archivo</span>
                                <?php endif; ?>
                            </td>

                            <!-- Acciones de Revisión -->
                            <td class="text-center align-middle col-acciones">
                                <?php if ($estado === 'PENDIENTE'): ?>
                                    <?php if ($origen === 'instructor'): ?>
                                        <div class="btn-group btn-group-sm shadow-sm" role="group">
                                            <button type="button" 
                                                    class="btn btn-success btn-aprobar-excusa" 
                                                    data-id="<?= htmlspecialchars((string)$excusa['id_excusa']) ?>"
                                                    data-aprendiz="<?= htmlspecialchars($excusa['aprendiz']) ?>"
                                                    title="Aprobar excusa médica">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-danger btn-rechazar-excusa" 
                                                    data-id="<?= htmlspecialchars((string)$excusa['id_excusa']) ?>"
                                                    data-aprendiz="<?= htmlspecialchars($excusa['aprendiz']) ?>"
                                                    title="Rechazar excusa médica">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-light border text-muted px-2 py-1">Solo instructor</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DE PREVISUALIZACIÓN DE ADJUNTOS (PDF / IMÁGENES)       -->
<!-- ============================================================ -->
<div class="modal fade" id="modalPreviewExcusa" tabindex="-1" role="dialog" aria-labelledby="modalPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light py-2 px-3 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="modal-title font-weight-bold text-dark mb-0" id="modalPreviewTitle" style="font-size: 1.05rem;">
                    <i class="fas fa-file-medical text-success mr-2"></i> Previsualización de documento &mdash; <span id="preview-titulo" class="font-weight-normal text-muted"></span>
                </h5>
                <div class="d-flex align-items-center">
                    <a id="preview-btn-descargar" href="#" class="btn btn-sm btn-outline-success mr-2 shadow-sm" download title="Descargar archivo original">
                        <i class="fas fa-download mr-1"></i> Descargar
                    </a>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div class="modal-body p-0 text-center position-relative" style="background-color: #f8fafc; min-height: 520px;">
                <!-- Indicador de carga -->
                <div id="preview-loading" class="py-5 text-muted d-none">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2 text-success"></i>
                    <p class="mb-0 small">Cargando archivo adjunto...</p>
                </div>

                <!-- Contenedor Imagen -->
                <div id="preview-img-wrapper" class="p-3 d-none text-center">
                    <img id="preview-img" class="img-fluid rounded shadow-sm border" style="max-height: 75vh; max-width: 100%; object-fit: contain;" alt="Vista previa del documento adjunto">
                </div>

                <!-- Contenedor PDF -->
                <div id="preview-pdf-wrapper" class="d-none w-100" style="height: 75vh; min-height: 500px;">
                    <iframe id="preview-pdf" style="width: 100%; height: 500px; min-height: 75vh; border: none;" class="w-100 border-0" title="Documento PDF de excusa"></iframe>
                </div>

                <!-- Formatos no reproducibles directamente -->
                <div id="preview-unsupported" class="py-5 px-3 text-muted d-none">
                    <i class="fas fa-file-alt fa-3x mb-3 text-secondary"></i>
                    <h5 class="font-weight-bold text-dark">Formato no reproducible en el navegador</h5>
                    <p class="small text-muted mb-3">Este tipo de archivo no puede previsualizarse directamente. Utilice el botón para descargarlo.</p>
                    <a id="preview-fallback-download" href="#" class="btn btn-primary btn-sm shadow-sm" download>
                        <i class="fas fa-download mr-1"></i> Descargar archivo
                    </a>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($origen === 'instructor'): ?>
<!-- ============================================================ -->
<!-- MODALES DE DICTAMEN / REVISIÓN (INSTRUCTOR)                   -->
<!-- ============================================================ -->
<!-- Modal Aprobar Excusa -->
<div class="modal fade" id="modalAprobar" tabindex="-1" role="dialog" aria-labelledby="modalAprobarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modalAprobarLabel">
                    <i class="fas fa-check-circle mr-1"></i> Aprobar Justificación Médica
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="<?= RUTA_BASE ?>controllers/ExcusaController.php" method="post">
                <input type="hidden" name="accion" value="revisar">
                <input type="hidden" name="id_excusa" id="aprobar_id_excusa" value="">
                <input type="hidden" name="decision" value="APROBADA">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCsrf) ?>">
                
                <div class="modal-body p-4">
                    <p class="mb-0">¿Confirma que desea <strong>aprobar</strong> la excusa médica presentada por <strong id="aprobar_nombre_aprendiz" class="text-success"></strong>?</p>
                    <small class="text-muted d-block mt-2">Al aprobarla, la inasistencia se registrará como justificada de forma automática.</small>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-check mr-1"></i> Sí, Aprobar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Rechazar Excusa -->
<div class="modal fade" id="modalRechazar" tabindex="-1" role="dialog" aria-labelledby="modalRechazarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title font-weight-bold" id="modalRechazarLabel">
                    <i class="fas fa-times-circle mr-1"></i> Rechazar Justificación Médica
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="<?= RUTA_BASE ?>controllers/ExcusaController.php" method="post">
                <input type="hidden" name="accion" value="revisar">
                <input type="hidden" name="id_excusa" id="rechazar_id_excusa" value="">
                <input type="hidden" name="decision" value="RECHAZADA">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCsrf) ?>">
                
                <div class="modal-body p-4">
                    <p class="mb-0">¿Confirma que desea <strong>rechazar</strong> la excusa médica presentada por <strong id="rechazar_nombre_aprendiz" class="text-danger"></strong>?</p>
                    <small class="text-muted d-block mt-2">La inasistencia permanecerá como injustificada.</small>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-times mr-1"></i> Sí, Rechazar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    function iniciarTablaExcusas() {
        if (typeof window.inicializarTablaSENA === 'function') {
            window.inicializarTablaSENA('#tablaExcusasPrincipal', {
                order: [[0, 'desc']]
            });
        } else if (window.jQuery && $.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('#tablaExcusasPrincipal')) {
                $('#tablaExcusasPrincipal').DataTable().destroy();
            }
            $('#tablaExcusasPrincipal').DataTable({
                destroy: true,
                order: [[0, 'desc']]
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarTablaExcusas);
    } else {
        iniciarTablaExcusas();
    }
})();

    // Previsualización de Documentos
    var btnsPreview = document.querySelectorAll('.btn-preview-excusa');
    btnsPreview.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = this.getAttribute('data-url');
            var downloadUrl = this.getAttribute('data-download');
            var tipo = (this.getAttribute('data-tipo') || '').toLowerCase();
            var aprendiz = this.getAttribute('data-aprendiz') || '';
            var fecha = this.getAttribute('data-fecha') || '';

            var previewTitulo = document.getElementById('preview-titulo');
            var btnDescargar = document.getElementById('preview-btn-descargar');
            var fallbackDescargar = document.getElementById('preview-fallback-download');

            var imgWrapper = document.getElementById('preview-img-wrapper');
            var imgEl = document.getElementById('preview-img');
            var pdfWrapper = document.getElementById('preview-pdf-wrapper');
            var pdfEl = document.getElementById('preview-pdf');
            var unsupportedWrapper = document.getElementById('preview-unsupported');

            if (previewTitulo) previewTitulo.textContent = aprendiz + (fecha ? ' (' + fecha + ')' : '');
            if (btnDescargar) btnDescargar.href = downloadUrl;
            if (fallbackDescargar) fallbackDescargar.href = downloadUrl;

            // Resetear contenedores
            if (imgWrapper) imgWrapper.classList.add('d-none');
            if (imgEl) imgEl.src = '';
            if (pdfWrapper) pdfWrapper.classList.add('d-none');
            if (pdfEl) pdfEl.src = '';
            if (unsupportedWrapper) unsupportedWrapper.classList.add('d-none');

            if (tipo === 'pdf') {
                if (pdfEl && pdfWrapper) {
                    pdfEl.src = url;
                    pdfWrapper.classList.remove('d-none');
                }
            } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(tipo)) {
                if (imgEl && imgWrapper) {
                    imgEl.src = url;
                    imgWrapper.classList.remove('d-none');
                }
            } else {
                if (unsupportedWrapper) {
                    unsupportedWrapper.classList.remove('d-none');
                }
            }

            if (typeof $ !== 'undefined') {
                $('#modalPreviewExcusa').modal('show');
            }
        });
    });

    // Limpiar iframe al cerrar visor
    if (typeof $ !== 'undefined') {
        $('#modalPreviewExcusa').on('hidden.bs.modal', function () {
            var pdfEl = document.getElementById('preview-pdf');
            var imgEl = document.getElementById('preview-img');
            if (pdfEl) pdfEl.src = '';
            if (imgEl) imgEl.src = '';
        });
    }

    <?php if ($origen === 'instructor'): ?>
    // Botones de dictamen para instructor
    var btnsAprobar = document.querySelectorAll('.btn-aprobar-excusa');
    btnsAprobar.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var aprendiz = this.getAttribute('data-aprendiz');
            var elId = document.getElementById('aprobar_id_excusa');
            var elNombre = document.getElementById('aprobar_nombre_aprendiz');
            if (elId) elId.value = id;
            if (elNombre) elNombre.textContent = aprendiz;
            if (typeof $ !== 'undefined') $('#modalAprobar').modal('show');
        });
    });

    var btnsRechazar = document.querySelectorAll('.btn-rechazar-excusa');
    btnsRechazar.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var aprendiz = this.getAttribute('data-aprendiz');
            var elId = document.getElementById('rechazar_id_excusa');
            var elNombre = document.getElementById('rechazar_nombre_aprendiz');
            if (elId) elId.value = id;
            if (elNombre) elNombre.textContent = aprendiz;
            if (typeof $ !== 'undefined') $('#modalRechazar').modal('show');
        });
    });
    <?php endif; ?>
});
</script>
