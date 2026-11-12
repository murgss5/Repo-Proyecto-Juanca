<?php
/**
 * Componente Reutilizable: Vista / Tabla de Gestión de Alertas de Deserción
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * -----------------------------------------------------------------------------
 * Parametros esperados:
 * @var string $origen             'admin' o 'instructor'
 * @var array  $aprendicesEnRiesgo Lista de aprendices en riesgo calculados
 */

$origen = $origen ?? 'admin';
$esInstructor = ($origen === 'instructor');

// Generar token CSRF seguro si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Cálculos de métricas superiores (KPIs)
$totalCriticos = count(array_filter($aprendicesEnRiesgo, fn($a) => ($a['nivel_riesgo'] ?? '') === 'CRITICO'));
$totalAdvertencias = count(array_filter($aprendicesEnRiesgo, fn($a) => ($a['nivel_riesgo'] ?? '') === 'ADVERTENCIA'));
$totalNotificadosHoy = count(array_filter($aprendicesEnRiesgo, fn($a) => !empty($a['notificado_hoy'])));
$criticosNotificables = count(array_filter($aprendicesEnRiesgo, fn($a) => !empty($a['puede_notificar'])));
$criticosConExcusa = count(array_filter($aprendicesEnRiesgo, fn($a) => !empty($a['puede_notificar']) && !empty($a['tiene_excusa_pendiente'])));

// Extraer fichas únicas presentes en los resultados para el filtro
$fichasOpciones = [];
foreach ($aprendicesEnRiesgo as $ap) {
    $fid = (int)$ap['id_ficha'];
    if (!isset($fichasOpciones[$fid])) {
        $fichasOpciones[$fid] = [
            'id' => $fid,
            'programa' => $ap['nombre_programa'] ?? ''
        ];
    }
}
ksort($fichasOpciones);
?>

<style>
/* Estilos premium para tarjetas KPI */
.kpi-card-premium {
    border: none;
    border-radius: 10px;
    box-shadow: 0 0.125rem 0.35rem rgba(0, 0, 0, 0.06);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}
.kpi-card-premium.hover-elevate:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
}
.kpi-danger { border-left: 5px solid #dc3545; }
.kpi-warning { border-left: 5px solid #ffc107; }
.kpi-success { border-left: 5px solid #28a745; }

.kpi-icon-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.kpi-danger .kpi-icon-circle { background-color: #fee2e2; color: #dc3545; }
.kpi-warning .kpi-icon-circle { background-color: #fef3c7; color: #d97706; }
.kpi-success .kpi-icon-circle { background-color: #dcfce7; color: #16a34a; }

/* Tabla personalizada */
#tablaAlertasDesercion th {
    font-size: 0.82rem;
    letter-spacing: 0.5px;
    background-color: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}
#tablaAlertasDesercion td {
    vertical-align: middle;
}
.shadow-xs {
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
</style>

<!-- ==================== TARJETAS DE ESTADÍSTICAS SUPERIORES (KPIs) ==================== -->
<div class="row">
    <!-- En Riesgo Crítico -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card kpi-card-premium kpi-danger hover-elevate h-100">
            <div class="card-body p-3 d-flex justify-content-between align-items-start">
                <div class="d-flex flex-column justify-content-between h-100 pr-2">
                    <div>
                        <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">
                            <?= $esInstructor ? 'RIESGO CRÍTICO EN MIS FICHAS' : 'RIESGO CRÍTICO DE DESERCIÓN' ?>
                        </h6>
                        <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalCriticos) ?></h2>
                        <small class="text-muted"><i class="fas fa-exclamation-circle mr-1 text-danger"></i> &ge; 3 consecutivas o &ge; 5 en 30 días</small>
                    </div>
                    <div class="mt-2">
                        <span class="badge font-weight-bold px-2 py-1" style="background-color: #fecaca; color: #991b1b; font-size: 0.72rem;">
                            <?= $criticosNotificables ?> pendientes de notificar hoy
                        </span>
                    </div>
                </div>
                <div class="kpi-icon-circle">
                    <i class="fas fa-user-times"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- En Advertencia Preventiva -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card kpi-card-premium kpi-warning hover-elevate h-100">
            <div class="card-body p-3 d-flex justify-content-between align-items-start">
                <div class="d-flex flex-column justify-content-between h-100 pr-2">
                    <div>
                        <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">ADVERTENCIA PREVENTIVA</h6>
                        <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalAdvertencias) ?></h2>
                        <small class="text-muted"><i class="fas fa-info-circle mr-1 text-warning"></i> 2 inasistencias injustificadas seguidas</small>
                    </div>
                    <div class="mt-2">
                        <span class="badge font-weight-bold px-2 py-1" style="background-color: #fef08a; color: #854d0e; font-size: 0.72rem;">
                            Racha preventiva activa
                        </span>
                    </div>
                </div>
                <div class="kpi-icon-circle">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Notificados Hoy -->
    <div class="col-xl-4 col-md-12 mb-4">
        <div class="card kpi-card-premium kpi-success hover-elevate h-100">
            <div class="card-body p-3 d-flex justify-content-between align-items-start">
                <div class="d-flex flex-column justify-content-between h-100 pr-2">
                    <div>
                        <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">NOTIFICADOS EL DÍA DE HOY</h6>
                        <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalNotificadosHoy) ?></h2>
                        <small class="text-muted"><i class="fas fa-check-circle mr-1 text-success"></i> Con registro de alerta en la fecha</small>
                    </div>
                    <div class="mt-2">
                        <span class="badge font-weight-bold px-2 py-1" style="background-color: #bbf7d0; color: #166534; font-size: 0.72rem;">
                            <?= date('d/m/Y') ?>
                        </span>
                    </div>
                </div>
                <div class="kpi-icon-circle">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== TARJETA PRINCIPAL CON FILTROS Y DATATABLES ==================== -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom pt-3 pb-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-clipboard-list text-danger mr-2"></i> Aprendices en Riesgo de Deserción
            </h5>
            <small class="text-muted d-block mt-1">
                Fundamento legal: Reglamento del Aprendiz SENA (Acuerdo 007 de 2012, Art. 22 - Deserción).
            </small>
        </div>
        <div class="mt-2 mt-md-0">
            <button type="button" 
                    id="btnNotificarMasivo" 
                    class="btn btn-danger btn-sm shadow-sm font-weight-bold px-3 py-2"
                    <?= ($criticosNotificables === 0) ? 'disabled title="Todos los críticos ya fueron notificados hoy o no cuentan con correo"' : '' ?>>
                <i class="fas fa-paper-plane mr-1"></i> Notificar Críticos por Correo (<?= $criticosNotificables ?>)
            </button>
        </div>
    </div>

    <!-- Barra de Herramientas y Filtros Rápidos -->
    <div class="card-body bg-light border-bottom py-3 px-4">
        <div class="row align-items-center">
            <!-- Buscador en tiempo real -->
            <div class="col-lg-4 col-md-6 mb-2 mb-lg-0">
                <label for="buscadorAlertas" class="small font-weight-bold text-muted mb-1">Búsqueda rápida</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" id="buscadorAlertas" class="form-control" placeholder="Buscar aprendiz, cédula o programa...">
                </div>
            </div>

            <!-- Filtro por Ficha -->
            <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
                <label for="filtroFicha" class="small font-weight-bold text-muted mb-1">Filtrar por Ficha</label>
                <select id="filtroFicha" class="form-control form-control-sm">
                    <option value="">Todas las fichas</option>
                    <?php foreach ($fichasOpciones as $f): ?>
                        <option value="<?= $f['id'] ?>">Ficha #<?= $f['id'] ?> - <?= htmlspecialchars($f['programa']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro por Nivel de Riesgo -->
            <div class="col-lg-3 col-md-6 mb-2 mb-lg-0">
                <label for="filtroRiesgo" class="small font-weight-bold text-muted mb-1">Nivel de Riesgo</label>
                <select id="filtroRiesgo" class="form-control form-control-sm">
                    <option value="">Todos los niveles</option>
                    <option value="Riesgo Crítico">Riesgo Crítico (3+ faltas)</option>
                    <option value="Advertencia">Advertencia (2 faltas)</option>
                    <option value="Excusa pendiente">Con excusa pendiente</option>
                </select>
            </div>

            <!-- Botón Limpiar Filtros -->
            <div class="col-lg-2 col-md-6 mb-2 mb-lg-0 text-lg-right align-self-end">
                <button type="button" id="btnLimpiarFiltros" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="fas fa-undo mr-1"></i> Limpiar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla DataTables -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="tablaAlertasDesercion" class="table table-hover align-middle mb-0" style="width: 100%;">
                <thead class="thead-light">
                    <tr>
                        <th class="font-weight-bold text-muted small text-uppercase py-3 px-3">Aprendiz</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3">Documento</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3">Correo Electrónico</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3">Ficha de Formación</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3 text-center">Faltas Consecutivas</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3 text-center">Faltas (30 días)</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3 text-center">Nivel de Riesgo</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3 text-center">Última Notificación</th>
                        <th class="font-weight-bold text-muted small text-uppercase py-3 text-center px-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($aprendicesEnRiesgo)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle text-success fa-3x mb-3 d-block"></i>
                                <strong>¡Excelente! No se registran aprendices en riesgo preventivo o crítico de deserción.</strong>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($aprendicesEnRiesgo as $ap): ?>
                            <tr>
                                <!-- Aprendiz -->
                                <td class="px-3 py-3">
                                    <div class="font-weight-bold text-dark"><?= htmlspecialchars($ap['nombre_completo']) ?></div>
                                    <small class="text-muted"><i class="fas fa-phone-alt mr-1"></i><?= htmlspecialchars($ap['telefono'] ?: 'Sin teléfono') ?></small>
                                </td>

                                <!-- Documento -->
                                <td class="py-3 font-weight-500 text-dark">
                                    <?= htmlspecialchars($ap['identificacion']) ?>
                                </td>

                                <!-- Correo Electrónico -->
                                <td class="py-3">
                                    <?php if (!empty($ap['correo_electronico'])): ?>
                                        <span class="text-dark"><i class="fas fa-envelope mr-1 text-muted"></i><?= htmlspecialchars($ap['correo_electronico']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary bg-secondary text-white px-2 py-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i> Sin correo
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Ficha -->
                                <td class="py-3">
                                    <span class="badge badge-light border text-dark font-weight-bold">#<?= htmlspecialchars($ap['id_ficha']) ?></span>
                                    <small class="text-muted d-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($ap['nombre_programa']) ?>">
                                        <?= htmlspecialchars($ap['nombre_programa']) ?>
                                    </small>
                                </td>

                                <!-- Faltas Consecutivas -->
                                <td class="py-3 text-center" data-order="<?= (int)$ap['fallas_consecutivas'] ?>">
                                    <?php if ($ap['fallas_consecutivas'] >= 3): ?>
                                        <span class="badge badge-danger bg-danger text-white px-2 py-1 font-weight-bold">
                                            <?= $ap['fallas_consecutivas'] ?> consecutivas
                                        </span>
                                    <?php elseif ($ap['fallas_consecutivas'] === 2): ?>
                                        <span class="badge badge-warning bg-warning text-dark px-2 py-1 font-weight-bold">
                                            <?= $ap['fallas_consecutivas'] ?> consecutivas
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted font-weight-bold"><?= $ap['fallas_consecutivas'] ?></span>
                                    <?php endif; ?>
                                </td>

                                <!-- Faltas en 30 días -->
                                <td class="py-3 text-center" data-order="<?= (int)$ap['fallas_30_dias'] ?>">
                                    <span class="font-weight-bold <?= ($ap['fallas_30_dias'] >= 5) ? 'text-danger' : 'text-dark' ?>">
                                        <?= $ap['fallas_30_dias'] ?> inasistencias
                                    </span>
                                </td>

                                <!-- Nivel de Riesgo -->
                                <td class="py-3 text-center">
                                    <?php if ($ap['nivel_riesgo'] === 'CRITICO'): ?>
                                        <span class="badge badge-danger bg-danger text-white px-2 py-1 font-weight-bold">
                                            <i class="fas fa-skull-crossbones mr-1"></i> Riesgo Crítico
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning bg-warning text-dark px-2 py-1 font-weight-bold">
                                            <i class="fas fa-exclamation-triangle mr-1"></i> Advertencia
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($ap['tiene_excusa_pendiente'])): ?>
                                        <div class="mt-1">
                                            <span class="badge badge-info bg-info text-white px-2 py-1 font-weight-bold shadow-xs" title="El aprendiz tiene una excusa médica pendiente de revisión por el instructor">
                                                <i class="fas fa-file-medical mr-1"></i> Excusa pendiente de revisión
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Última Notificación -->
                                <td class="py-3 text-center">
                                    <?php if (!empty($ap['ultima_alerta_desercion'])): ?>
                                        <?php if ($ap['notificado_hoy']): ?>
                                            <span class="badge badge-success bg-success text-white px-2 py-1">
                                                <i class="fas fa-check mr-1"></i> Notificado hoy
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted font-weight-500">
                                                <i class="far fa-calendar-alt mr-1"></i><?= htmlspecialchars($ap['ultima_alerta_desercion']) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge badge-light border text-muted px-2 py-1">Sin notificar</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Acciones individuales -->
                                <td class="py-3 text-center px-3">
                                    <?php if ($ap['puede_notificar']): ?>
                                        <button type="button" 
                                                class="btn btn-outline-danger btn-sm shadow-sm btn-notificar-individual"
                                                data-id="<?= $ap['id_aprendiz'] ?>"
                                                data-nombre="<?= htmlspecialchars($ap['nombre_completo']) ?>"
                                                data-correo="<?= htmlspecialchars($ap['correo_electronico']) ?>"
                                                data-tiene-excusa="<?= !empty($ap['tiene_excusa_pendiente']) ? '1' : '0' ?>"
                                                title="Enviar notificación formal de alerta a este aprendiz">
                                            <i class="fas fa-paper-plane mr-1"></i> Notificar
                                        </button>
                                    <?php elseif ($ap['notificado_hoy']): ?>
                                        <span class="text-success small font-weight-bold" title="Ya se notificó en el día de hoy">
                                            <i class="fas fa-check-double mr-1"></i> Al día
                                        </span>
                                    <?php elseif (empty($ap['correo_electronico'])): ?>
                                        <span class="text-muted small" title="Registre el correo del aprendiz para notificar">
                                            <i class="fas fa-ban mr-1"></i> Sin correo
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">En advertencia</span>
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

<!-- ==================== SCRIPT DE COMPORTAMIENTO (DATATABLES & SWEETALERT2) ==================== -->
<script>
window.addEventListener('load', function () {
    if (typeof jQuery === 'undefined' || typeof $.fn.DataTable === 'undefined') {
        return;
    }

    var tokenCsrf = '<?= htmlspecialchars($_SESSION['token_csrf']) ?>';
    var origenContexto = '<?= htmlspecialchars($origen) ?>';

    // Inicialización de DataTables
    var tabla = $('#tablaAlertasDesercion').DataTable({
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: "<'row'<'col-sm-12'tr>><'row align-items-center py-3 px-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>",
        order: [[4, 'desc'], [5, 'desc']], // Ordenar por mayor número de faltas consecutivas
        columnDefs: [
            { targets: [8], orderable: false, searchable: false }
        ],
        language: {
            emptyTable: "No se encontraron aprendices en riesgo con los criterios seleccionados",
            info: "Mostrando _START_ a _END_ de _TOTAL_ aprendices en riesgo",
            infoEmpty: "Mostrando 0 a 0 de 0 aprendices",
            infoFiltered: "(filtrado de _MAX_ registros en total)",
            lengthMenu: "Mostrar _MENU_ registros",
            loadingRecords: "Cargando...",
            processing: "Procesando...",
            search: "Buscar:",
            zeroRecords: "No se encontraron resultados coincidentes",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "&raquo;",
                previous: "&laquo;"
            }
        }
    });

    // Enlace de Filtros Personalizados
    if (tabla) {
        // Buscador en tiempo real
        $('#buscadorAlertas').on('keyup input', function () {
            tabla.search(this.value).draw();
        });

        // Filtro por Ficha
        $('#filtroFicha').on('change', function () {
            var val = this.value;
            tabla.column(3).search(val ? '#' + val : '').draw();
        });

        // Filtro por Nivel de Riesgo
        $('#filtroRiesgo').on('change', function () {
            var val = this.value;
            tabla.column(6).search(val ? val : '').draw();
        });

        // Limpiar filtros
        $('#btnLimpiarFiltros').on('click', function () {
            $('#buscadorAlertas').val('');
            $('#filtroFicha').val('');
            $('#filtroRiesgo').val('');
            tabla.search('').columns().search('').draw();
        });
    }

    // Despacho Masivo de Notificaciones
    var btnMasivo = document.getElementById('btnNotificarMasivo');
    if (btnMasivo) {
        btnMasivo.addEventListener('click', function () {
            if (btnMasivo.disabled) return;

            var pendientes = <?= (int)$criticosNotificables ?>;
            var criticosConExcusa = <?= (int)$criticosConExcusa ?>;
            var texto = 'Se despachará una notificación por correo electrónico a ' + pendientes + ' aprendiz(ces) en estado Crítico que no han sido alertados el día de hoy.';

            if (typeof Swal === 'undefined') {
                if (criticosConExcusa > 0) {
                    texto += '\n\nAtención: ' + criticosConExcusa + ' de estos aprendices tienen excusas médicas pendientes de revisión.';
                }
                if (confirm(texto)) {
                    enviarNotificacionesAjax(null);
                }
                return;
            }

            var htmlMasivo = '<p class="mb-2">Se despachará una notificación por correo electrónico a <strong>' + pendientes + '</strong> aprendiz(ces) en estado Crítico que no han sido alertados el día de hoy.</p>';
            if (criticosConExcusa > 0) {
                htmlMasivo += '<div class="alert alert-warning text-left small mt-2 mb-0 border-0"><i class="fas fa-exclamation-triangle mr-1"></i> <strong>Aviso:</strong> Hay <strong>' + criticosConExcusa + '</strong> aprendiz(ces) con excusas médicas <strong>pendientes de revisión</strong> dentro de este grupo.</div>';
            }

            Swal.fire({
                title: '¿Notificar Críticos por Correo?',
                html: htmlMasivo,
                icon: criticosConExcusa > 0 ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: '#39a900',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Sí, enviar notificaciones',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    enviarNotificacionesAjax(null);
                }
            });
        });
    }

    // Despacho Individual de Notificación por Aprendiz
    $(document).on('click', '.btn-notificar-individual', function () {
        var idAprendiz = $(this).data('id');
        var nombre = $(this).data('nombre');
        var correo = $(this).data('correo');
        var tieneExcusa = $(this).data('tiene-excusa') == '1';

        var texto = '¿Desea enviar la alerta formal de riesgo de deserción a ' + nombre + ' (' + correo + ')?';

        if (typeof Swal === 'undefined') {
            if (tieneExcusa) {
                texto += '\n\nATENCIÓN: Este aprendiz tiene una excusa médica pendiente de revisión.';
            }
            if (confirm(texto)) {
                enviarNotificacionesAjax(idAprendiz);
            }
            return;
        }

        var htmlContenido = '<p class="mb-2">¿Desea enviar la alerta formal de riesgo de deserción a <strong>' + $('<div>').text(nombre).html() + '</strong> (' + $('<div>').text(correo).html() + ')?</p>';
        if (tieneExcusa) {
            htmlContenido += '<div class="alert alert-warning text-left small mt-2 mb-0 border-0"><i class="fas fa-exclamation-triangle mr-1"></i> <strong>Atención:</strong> Este aprendiz cuenta con una o más excusas médicas <strong>pendientes de revisión</strong>. Se recomienda validarlas antes de emitir la alerta formal.</div>';
        }

        Swal.fire({
            title: '¿Enviar Alerta Individual?',
            html: htmlContenido,
            icon: tieneExcusa ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonColor: '#39a900',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-paper-plane mr-1"></i> Sí, notificar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                enviarNotificacionesAjax(idAprendiz);
            }
        });
    });

    // Función auxiliar para petición AJAX
    function enviarNotificacionesAjax(idAprendizEspecifico) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Enviando Alertas...',
                text: 'Por favor espere mientras se despachan los correos electrónicos.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () {
                    Swal.showLoading();
                }
            });
        }

        var formData = new FormData();
        formData.append('token_csrf', tokenCsrf);
        formData.append('origen', origenContexto);
        if (idAprendizEspecifico) {
            formData.append('id_aprendiz', idAprendizEspecifico);
        }

        fetch('<?= RUTA_BASE ?>controllers/ajax/notificar_desercion.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (res) {
            return res.json();
        })
        .then(function (data) {
            if (data.status === 'ok') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: '¡Notificaciones Despachadas!',
                        html: '<p class="mb-1">' + (data.mensaje || 'Se han enviado las notificaciones correctamente.') + '</p>' +
                              (data.enviados > 0 ? '<span class="badge badge-success px-2 py-1">' + data.enviados + ' enviados con éxito</span> ' : '') +
                              (data.omitidos > 0 ? '<span class="badge badge-secondary px-2 py-1">' + data.omitidos + ' omitidos</span>' : ''),
                        icon: 'success',
                        confirmButtonColor: '#39a900',
                        confirmButtonText: 'Entendido'
                    }).then(function () {
                        window.location.reload();
                    });
                } else {
                    alert(data.mensaje);
                    window.location.reload();
                }
            } else if (data.status === 'info') {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Información',
                        text: data.mensaje,
                        icon: 'info',
                        confirmButtonColor: '#39a900',
                        confirmButtonText: 'Entendido'
                    });
                } else {
                    alert(data.mensaje);
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Atención',
                        text: data.mensaje || 'No se pudieron despachar las notificaciones.',
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'Cerrar'
                    });
                } else {
                    alert(data.mensaje || 'Error al despachar las notificaciones.');
                }
            }
        })
        .catch(function () {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Error de Comunicación',
                    text: 'No fue posible conectar con el servidor para despachar los correos.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Cerrar'
                });
            } else {
                alert('Error de conexión al despachar las notificaciones.');
            }
        });
    }
});
</script>
