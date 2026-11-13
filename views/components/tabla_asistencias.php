<?php
/**
 * =============================================================================
 * COMPONENTE REUTILIZABLE: Tabla y Filtros de Asistencias
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Compatible con AdminLTE y Bootstrap 4.
 * Parametrizado por:
 *   - $origen             : 'admin' | 'instructor'
 *   - $asistencias        : listado de registros de asistencia
 *   - $fichas             : listado de fichas disponibles
 *   - $filtros            : valores activos de filtros (desde, hasta, ficha, estado)
 *   - $estadosPermitidos  : array de estados válidos para filtrar
 *   - $tokenCsrf          : token CSRF para acciones seguras
 *   - $currentUrl         : URL de retorno
 * =============================================================================
 */

require_once dirname(__DIR__, 2) . '/models/AsistenciaModel.php';

$origen            = $origen ?? 'admin';
$asistencias       = $asistencias ?? [];
$fichas            = $fichas ?? [];
$filtros           = $filtros ?? [];
$estadosPermitidos = $estadosPermitidos ?? ['Presente', 'Retardo', 'Salida temprana', 'Inasistencia', 'Justificada'];
$tokenCsrf         = $tokenCsrf ?? ($_SESSION['token_csrf'] ?? '');
$currentUrl        = $currentUrl ?? (
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' .
    ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '')
);

$fechaDesde        = $filtros['desde'] ?? '';
$fechaHasta        = $filtros['hasta'] ?? '';
$fichaSeleccionada = $filtros['ficha'] ?? '';
$estadoSeleccionado = $filtros['estado'] ?? '';
$urlLimpiar        = $origen === 'admin' ? RUTA_BASE . 'views/admin/asistencias/index.php' : RUTA_BASE . 'views/instructor/asistencias/index.php';
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

<!-- Tarjeta Principal de Asistencias -->
<div class="card card-outline card-success shadow-sm mb-4">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-2 mb-sm-0">
            <i class="fas fa-clipboard-check text-success mr-2"></i> 
            <?= $origen === 'admin' ? 'Registro General de Asistencias' : 'Asistencias de Mis Fichas' ?>
        </h3>
        <div class="card-tools ml-auto">
            <a href="<?= htmlspecialchars($urlLimpiar) ?>" class="btn btn-outline-secondary btn-sm shadow-sm" title="Restablecer filtros">
                <i class="fas fa-sync-alt mr-1"></i> Actualizar
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Formulario de Filtros de Búsqueda -->
        <form method="get" class="row mb-4 align-items-end" id="formFiltrosAsistencia">
            <div class="col-lg-3 col-md-6 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Desde</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-calendar-alt text-muted"></i></span>
                    </div>
                    <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Hasta</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-calendar-alt text-muted"></i></span>
                    </div>
                    <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Ficha de formación</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-layer-group text-muted"></i></span>
                    </div>
                    <select name="ficha" class="form-control custom-select">
                        <option value="">Todas las fichas</option>
                        <?php foreach ($fichas as $f): ?>
                            <option value="<?= htmlspecialchars((string)$f['id_ficha']) ?>" <?= (string)$fichaSeleccionada === (string)$f['id_ficha'] ? 'selected' : '' ?>>
                                Ficha #<?= htmlspecialchars((string)$f['id_ficha']) ?> - <?= htmlspecialchars($f['nombre_programa'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Estado</label>
                <select name="estado" class="form-control form-control-sm custom-select">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estadosPermitidos as $ep): ?>
                        <option value="<?= htmlspecialchars($ep) ?>" <?= $estadoSeleccionado === $ep ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ep) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-lg-1 col-md-2 col-sm-6 mb-2">
                <button type="submit" class="btn btn-success btn-sm btn-block font-weight-bold shadow-sm" title="Aplicar filtros">
                    <i class="fas fa-filter"></i>
                </button>
            </div>
        </form>

        <!-- Tabla Responsiva de Asistencias -->
        <div class="table-responsive">
            <table class="table table-hover table-striped text-nowrap mb-0" id="tablaAsistenciasPrincipal" data-empty-message="No se encontraron registros de asistencia con los filtros seleccionados.">
                <thead class="thead-light">
                    <tr>
                        <th class="col-documento" style="width: 105px;">Fecha</th>
                        <th>Aprendiz</th>
                        <th class="d-none d-md-table-cell" style="width: 110px;">Ficha</th>
                        <th style="width: 125px;">Hora Entrada</th>
                        <th style="width: 160px;">Hora Salida</th>
                        <th class="text-center" style="width: 115px;">Estado</th>
                        <th class="text-center d-none d-lg-table-cell" style="width: 100px;">Min. Retardo</th>
                        <?php if ($origen === 'admin'): ?>
                            <th class="d-none d-lg-table-cell" style="width: 110px;">RFID</th>
                        <?php endif; ?>
                        <?php if ($origen === 'instructor'): ?>
                            <th class="text-center col-acciones" style="width: 80px;">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistencias as $asistencia): ?>
                        <?php
                            $horaEntrada = !empty($asistencia['entrada']) ? $asistencia['entrada'] : (!empty($asistencia['hora_entrada']) ? substr($asistencia['hora_entrada'], 0, 5) : null);
                            $horaSalida  = !empty($asistencia['salida']) ? $asistencia['salida'] : (!empty($asistencia['hora_salida_fmt']) ? $asistencia['hora_salida_fmt'] : (!empty($asistencia['hora_salida']) ? substr($asistencia['hora_salida'], 0, 5) : null));
                            $esInasistencia = ($asistencia['estado_entrada'] === 'INASISTENCIA');
                        ?>
                        <tr>
                            <!-- Fecha -->
                            <td class="align-middle font-weight-bold col-documento">
                                <?= htmlspecialchars($asistencia['fecha_asistencia']) ?>
                            </td>

                            <!-- Aprendiz -->
                            <td class="align-middle">
                                <span class="font-weight-bold text-dark"><?= htmlspecialchars($asistencia['aprendiz']) ?></span>
                                <?php if (!empty($asistencia['documento'])): ?>
                                    <small class="text-muted d-block"><i class="fas fa-id-card mr-1"></i>CC: <?= htmlspecialchars($asistencia['documento']) ?></small>
                                <?php endif; ?>
                                <div class="d-md-none text-muted small mt-1">
                                    <span class="badge badge-info mr-1">Ficha #<?= htmlspecialchars((string)$asistencia['id_ficha']) ?></span>
                                    <?php if (!empty($asistencia['minutos_retardo']) && (int)$asistencia['minutos_retardo'] > 0): ?>
                                        <span class="badge badge-warning"><?= (int)$asistencia['minutos_retardo'] ?>m retardo</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Ficha -->
                            <td class="align-middle d-none d-md-table-cell">
                                <span class="badge badge-info px-2 py-1">Ficha #<?= htmlspecialchars((string)$asistencia['id_ficha']) ?></span>
                            </td>

                            <!-- Hora de Entrada -->
                            <td class="align-middle">
                                <?php if (!empty($horaEntrada)): ?>
                                    <span class="font-weight-bold text-dark">
                                        <i class="fas fa-sign-in-alt text-success mr-1"></i><?= htmlspecialchars($horaEntrada) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted font-italic">--</span>
                                <?php endif; ?>
                            </td>

                            <!-- Hora de Salida -->
                            <td class="align-middle">
                                <?php if (!empty($horaSalida)): ?>
                                    <span class="font-weight-bold text-dark">
                                        <i class="fas fa-sign-out-alt text-primary mr-1"></i><?= htmlspecialchars($horaSalida) ?>
                                    </span>
                                    <?php if ($asistencia['estado_salida'] === 'SALIDA_ANTICIPADA'): ?>
                                        <span class="badge badge-info ml-1" title="Salida anticipada (<?= (int)$asistencia['minutos_salida_anticipada'] ?> min)">Anticipada</span>
                                    <?php endif; ?>
                                <?php elseif ($asistencia['estado_entrada'] === 'JUSTIFICADA'): ?>
                                    <span class="badge badge-info text-white px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Excusa aprobada</span>
                                <?php elseif ($esInasistencia): ?>
                                    <span class="text-muted font-italic">No asistió</span>
                                <?php elseif (!empty($asistencia['entrada'])): ?>
                                    <span class="badge badge-warning text-dark font-weight-bold px-2 py-1 shadow-xs" title="El aprendiz no ha registrado salida">
                                        <i class="fas fa-user-clock mr-1"></i>En formación
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-light border text-muted px-2 py-1">Pendiente de salida</span>
                                <?php endif; ?>
                            </td>

                            <!-- Estado General -->
                            <td class="text-center align-middle">
                                <span class="badge badge-estado <?= AsistenciaModel::claseInsigniaEstado($asistencia['estado']) ?> px-2 py-1">
                                    <?= htmlspecialchars($asistencia['estado']) ?>
                                </span>
                            </td>

                            <!-- Minutos de Retardo -->
                            <td class="text-center align-middle d-none d-lg-table-cell">
                                <?php if ((int)$asistencia['minutos_retardo'] > 0): ?>
                                    <span class="badge badge-warning px-2 py-1 font-weight-bold"><?= (int)$asistencia['minutos_retardo'] ?> min</span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>

                            <!-- RFID (Solo Admin) -->
                            <?php if ($origen === 'admin'): ?>
                                <td class="align-middle d-none d-lg-table-cell">
                                    <code><?= htmlspecialchars($asistencia['codigo_rfid'] ?? '--') ?></code>
                                </td>
                            <?php endif; ?>

                            <!-- Acciones (Solo Instructor) -->
                            <?php if ($origen === 'instructor'): ?>
                                <td class="text-center align-middle col-acciones">
                                    <?php if (!$esInasistencia && $asistencia['estado_entrada'] !== 'JUSTIFICADA'): ?>
                                        <button type="button" 
                                                class="btn btn-warning btn-sm shadow-sm btn-ajustar-salida" 
                                                data-id="<?= htmlspecialchars((string)$asistencia['id_asistencia']) ?>"
                                                data-aprendiz="<?= htmlspecialchars((string)$asistencia['aprendiz']) ?>"
                                                data-documento="<?= htmlspecialchars((string)($asistencia['documento'] ?? '')) ?>"
                                                data-fecha="<?= htmlspecialchars((string)$asistencia['fecha_asistencia']) ?>"
                                                data-entrada="<?= htmlspecialchars((string)($horaEntrada ?? '')) ?>"
                                                data-salida="<?= htmlspecialchars((string)($horaSalida ?? '')) ?>"
                                                title="Ingresar o modificar hora de salida">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm disabled" title="No aplica para inasistencias" style="opacity: 0.4; cursor: not-allowed;">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($origen === 'instructor'): ?>
<!-- ============================================================ -->
<!-- MODAL DE AJUSTE RÁPIDO DE HORA DE SALIDA                      -->
<!-- ============================================================ -->
<div class="modal fade" id="modalAjustarSalida" tabindex="-1" role="dialog" aria-labelledby="modalAjustarSalidaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form method="post" action="<?= RUTA_BASE ?>controllers/AsistenciaController.php" autocomplete="off">
                <input type="hidden" name="accion" value="actualizar_salida">
                <input type="hidden" name="id_asistencia" id="modal_asistencia_id" value="">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($tokenCsrf) ?>">
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentUrl) ?>">

                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title font-weight-bold mb-0" id="modalAjustarSalidaLabel">
                        <i class="fas fa-clock mr-2"></i> Ajustar Hora de Salida
                    </h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body p-4">
                    <!-- Resumen del Registro -->
                    <div class="alert alert-light border mb-3 p-3">
                        <div class="row">
                            <div class="col-12 mb-2">
                                <span class="text-muted small text-uppercase font-weight-bold d-block">Aprendiz:</span>
                                <span class="font-weight-bold text-dark h6 mb-0 d-block" id="modal_aprendiz_nombre">--</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted small text-uppercase font-weight-bold d-block">Fecha:</span>
                                <span class="text-dark font-weight-medium" id="modal_fecha_asistencia">--</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted small text-uppercase font-weight-bold d-block">Hora Entrada:</span>
                                <span class="text-success font-weight-bold" id="modal_hora_entrada">--</span>
                            </div>
                        </div>
                    </div>

                    <!-- Campo de Entrada para la Hora de Salida -->
                    <div class="form-group mb-2">
                        <label for="modal_hora_salida" class="font-weight-bold text-dark small">
                            Hora de Salida del Aprendiz <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-stopwatch text-muted"></i></span>
                            </div>
                            <input type="time" name="hora_salida" id="modal_hora_salida" class="form-control" required>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="btnHoraActual" title="Establecer hora actual">
                                    <i class="fas fa-clock mr-1"></i> Ahora
                                </button>
                            </div>
                        </div>
                        <small class="text-muted">Formato 24 horas (HH:MM). Se actualizará el estado de la jornada para este aprendiz.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                        <i class="fas fa-save mr-1"></i> Guardar Salida
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    function iniciarTablaAsistencias() {
        if (typeof window.inicializarTablaSENA === 'function') {
            window.inicializarTablaSENA('#tablaAsistenciasPrincipal', {
                order: [[0, 'desc']]
            });
        } else if (window.jQuery && $.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('#tablaAsistenciasPrincipal')) {
                $('#tablaAsistenciasPrincipal').DataTable().destroy();
            }
            $('#tablaAsistenciasPrincipal').DataTable({
                destroy: true,
                order: [[0, 'desc']]
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarTablaAsistencias);
    } else {
        iniciarTablaAsistencias();
    }
})();

    <?php if ($origen === 'instructor'): ?>
    var btns = document.querySelectorAll('.btn-ajustar-salida');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-aprendiz');
            var fecha = this.getAttribute('data-fecha');
            var entrada = this.getAttribute('data-entrada') || '--';
            var salida = this.getAttribute('data-salida') || '';

            var elId = document.getElementById('modal_asistencia_id');
            var elNombre = document.getElementById('modal_aprendiz_nombre');
            var elFecha = document.getElementById('modal_fecha_asistencia');
            var elEntrada = document.getElementById('modal_hora_entrada');
            var inputSalida = document.getElementById('modal_hora_salida');

            if (elId) elId.value = id;
            if (elNombre) elNombre.textContent = nombre;
            if (elFecha) elFecha.textContent = fecha;
            if (elEntrada) elEntrada.textContent = entrada;
            
            if (inputSalida) {
                if (salida) {
                    inputSalida.value = salida;
                } else {
                    var now = new Date();
                    var hh = String(now.getHours()).padStart(2, '0');
                    var mm = String(now.getMinutes()).padStart(2, '0');
                    inputSalida.value = hh + ':' + mm;
                }
            }

            if (typeof $ !== 'undefined') {
                $('#modalAjustarSalida').modal('show');
            }
        });
    });

    var btnAhora = document.getElementById('btnHoraActual');
    if (btnAhora) {
        btnAhora.addEventListener('click', function() {
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            var inputSalida = document.getElementById('modal_hora_salida');
            if (inputSalida) inputSalida.value = hh + ':' + mm;
        });
    }
    <?php endif; ?>
});
</script>
