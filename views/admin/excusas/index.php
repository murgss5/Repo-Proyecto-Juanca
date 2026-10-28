<?php
/**
 * ============================================================
 * VISTA: EXCUSAS MÉDICAS (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Listado real de excusas con filtros funcionales y visor modal
 * integrado para previsualización de documentos (PDFs e imágenes).
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
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header bg-white">
                <h3 class="card-title font-weight-bold"><i class="fas fa-filter text-sena mr-1"></i> Filtros de Búsqueda</h3>
            </div>
            <div class="card-body">
                <form method="get" class="row">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold">Estado</label>
                        <select name="estado" class="form-control">
                            <option value="">Todos los estados</option>
                            <option value="PENDIENTE" <?= $filtroEstado === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="APROBADA" <?= $filtroEstado === 'APROBADA' ? 'selected' : '' ?>>Aprobada</option>
                            <option value="RECHAZADA" <?= $filtroEstado === 'RECHAZADA' ? 'selected' : '' ?>>Rechazada</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold">Fecha inicial</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($filtroFechaIni ?? '') ?>">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold">Fecha final</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($filtroFechaFin ?? '') ?>">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small text-muted font-weight-bold">Ficha de formación</label>
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
                        <label class="small text-muted font-weight-bold">Aprendiz (nombre o documento)</label>
                        <input type="text" name="aprendiz" class="form-control" placeholder="Buscar por nombre o número de identificación..." value="<?= htmlspecialchars($filtroAprendiz ?? '') ?>">
                    </div>
                    <div class="col-md-6 col-sm-12 mb-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary shadow-sm mr-2"><i class="fas fa-search mr-1"></i> Filtrar</button>
                        <a href="<?= RUTA_BASE ?>views/admin/excusas/index.php" class="btn btn-outline-secondary shadow-sm"><i class="fas fa-times mr-1"></i> Limpiar</a>
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
        <div class="info-box info-box-amarillo shadow-sm">
            <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas pendientes</span>
                <span class="info-box-number"><?= $pendientes ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="info-box info-box-sena shadow-sm">
            <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas aprobadas</span>
                <span class="info-box-number"><?= $aprobadas ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
        <div class="info-box info-box-rojo shadow-sm">
            <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Excusas rechazadas</span>
                <span class="info-box-number"><?= $rechazadas ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-file-medical text-success mr-2"></i> Listado de Excusas Médicas (<?= count($excusas) ?>)
        </h3>
    </div>

    <div class="card-body">
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover table-bordered tabla-datos" data-empty-message="No se encontraron excusas con los filtros actuales.">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Aprendiz</th>
                        <th>Identificación</th>
                        <th>Programa / Ficha</th>
                        <th>Inasistencia</th>
                        <th>Fecha de subida</th>
                        <th>Observación</th>
                        <th>Estado</th>
                        <th>Fecha de revisión</th>
                        <th>Instructor revisor</th>
                        <th class="text-center" style="min-width: 110px;">Adjunto</th>
                        <th class="text-center" style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($excusas)): ?>
                        <?php foreach ($excusas as $excusa): ?>
                            <tr>
                                <td><?= htmlspecialchars($excusa['id_excusa']) ?></td>
                                <td class="font-weight-bold text-dark"><?= htmlspecialchars($excusa['aprendiz']) ?></td>
                                <td><?= htmlspecialchars($excusa['identificacion_aprendiz']) ?></td>
                                <td>
                                    <span class="badge badge-success px-2 py-1">Ficha #<?= htmlspecialchars($excusa['id_ficha']) ?></span>
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
                                <td><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado_mostrado']) ?> px-2 py-1"><?= htmlspecialchars($excusa['estado_mostrado']) ?></span></td>
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
                                <td class="text-center text-nowrap align-middle">
                                    <?php if (!empty($excusa['archivo'])): 
                                        $extArchivo = strtolower(pathinfo($excusa['archivo'], PATHINFO_EXTENSION));
                                        $urlDescarga = RUTA_BASE . 'views/admin/excusas/descargar.php?id_excusa=' . urlencode((string)$excusa['id_excusa']);
                                        $urlPreview = $urlDescarga . '&inline=1';
                                    ?>
                                        <div class="btn-group btn-group-sm shadow-sm" role="group" aria-label="Adjunto">
                                            <button type="button" 
                                                    class="btn btn-info btn-preview-excusa" 
                                                    data-url="<?= htmlspecialchars($urlPreview) ?>"
                                                    data-download="<?= htmlspecialchars($urlDescarga) ?>"
                                                    data-tipo="<?= htmlspecialchars($extArchivo) ?>"
                                                    data-archivo="<?= htmlspecialchars(basename($excusa['archivo'])) ?>"
                                                    data-aprendiz="<?= htmlspecialchars($excusa['aprendiz']) ?>"
                                                    data-fecha="<?= htmlspecialchars(date('d/m/Y', strtotime($excusa['fecha_asistencia']))) ?>"
                                                    title="Previsualizar archivo adjunto">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="<?= htmlspecialchars($urlDescarga) ?>" 
                                               class="btn btn-outline-primary" 
                                               title="Descargar archivo adjunto" 
                                               download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
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

                <!-- Contenedor para formatos no soportados para previsualización directa -->
                <div id="preview-unsupported" class="py-5 px-3 text-muted d-none">
                    <i class="fas fa-file-alt fa-3x mb-3 text-secondary"></i>
                    <h5 class="font-weight-bold text-dark">Formato no reproducible en el navegador</h5>
                    <p class="small text-muted mb-3">Este tipo de archivo no puede previsualizarse directamente. Utilice el botón para descargarlo.</p>
                    <a id="preview-fallback-download" href="#" class="btn btn-primary btn-sm shadow-sm" download>
                        <i class="fas fa-download mr-1"></i> Descargar archivo
                    </a>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center">
                <small class="text-muted" id="preview-archivo-info"></small>
                <button type="button" class="btn btn-secondary btn-sm px-3 shadow-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modalPreviewExcusa');
    const previewImg = document.getElementById('preview-img');
    const previewPdf = document.getElementById('preview-pdf');
    const imgWrapper = document.getElementById('preview-img-wrapper');
    const pdfWrapper = document.getElementById('preview-pdf-wrapper');
    const unsupportedWrapper = document.getElementById('preview-unsupported');
    const loadingEl = document.getElementById('preview-loading');
    const tituloEl = document.getElementById('preview-titulo');
    const infoArchivoEl = document.getElementById('preview-archivo-info');
    const btnDescargar = document.getElementById('preview-btn-descargar');
    const btnFallbackDownload = document.getElementById('preview-fallback-download');

    function limpiarVisor() {
        if (previewImg) {
            previewImg.removeAttribute('src');
        }
        if (previewPdf) {
            previewPdf.removeAttribute('src');
        }
        if (imgWrapper) imgWrapper.classList.add('d-none');
        if (pdfWrapper) pdfWrapper.classList.add('d-none');
        if (unsupportedWrapper) unsupportedWrapper.classList.add('d-none');
        if (loadingEl) loadingEl.classList.add('d-none');
        if (tituloEl) tituloEl.textContent = '';
        if (infoArchivoEl) infoArchivoEl.textContent = '';
        if (btnDescargar) btnDescargar.setAttribute('href', '#');
        if (btnFallbackDownload) btnFallbackDownload.setAttribute('href', '#');
    }

    // Delegación de eventos para los botones de previsualización (compatible con paginación DataTables)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-preview-excusa');
        if (!btn) return;

        e.preventDefault();
        limpiarVisor();

        const url = btn.getAttribute('data-url') || '';
        const downloadUrl = btn.getAttribute('data-download') || url.replace('&inline=1', '');
        const tipo = (btn.getAttribute('data-tipo') || '').toLowerCase().trim();
        const archivo = btn.getAttribute('data-archivo') || 'archivo';
        const aprendiz = btn.getAttribute('data-aprendiz') || 'Aprendiz';
        const fecha = btn.getAttribute('data-fecha') || '';

        tituloEl.textContent = aprendiz + (fecha ? ' (' + fecha + ')' : '');
        infoArchivoEl.textContent = 'Archivo: ' + archivo + ' (.' + tipo + ')';
        btnDescargar.setAttribute('href', downloadUrl);
        btnFallbackDownload.setAttribute('href', downloadUrl);

        loadingEl.classList.remove('d-none');

        const imageTypes = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg'];

        if (tipo === 'pdf') {
            pdfWrapper.classList.remove('d-none');
            previewPdf.onload = function() {
                loadingEl.classList.add('d-none');
            };
            previewPdf.src = url;
            setTimeout(function() {
                loadingEl.classList.add('d-none');
            }, 600);
        } else if (imageTypes.includes(tipo)) {
            imgWrapper.classList.remove('d-none');
            previewImg.onload = function() {
                loadingEl.classList.add('d-none');
            };
            previewImg.onerror = function() {
                loadingEl.classList.add('d-none');
                imgWrapper.classList.add('d-none');
                unsupportedWrapper.classList.remove('d-none');
            };
            previewImg.src = url;
        } else {
            loadingEl.classList.add('d-none');
            unsupportedWrapper.classList.remove('d-none');
        }

        // Abrir modal con Bootstrap / jQuery
        if (window.jQuery && typeof window.jQuery(modalEl).modal === 'function') {
            window.jQuery(modalEl).modal('show');
        } else if (typeof $ === 'function' && typeof $(modalEl).modal === 'function') {
            $(modalEl).modal('show');
        }
    });

    // Limpieza al cerrar el modal para liberar recursos de memoria del navegador
    if (window.jQuery) {
        window.jQuery(modalEl).on('hidden.bs.modal', function () {
            limpiarVisor();
        });
    } else {
        modalEl.addEventListener('hidden.bs.modal', function () {
            limpiarVisor();
        });
    }
});
</script>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
