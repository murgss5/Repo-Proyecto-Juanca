<?php
/**
 * Vista: Registro y Control de Asistencias del Instructor
 * Muestra las asistencias de las fichas a cargo del instructor autenticado,
 * permite filtrar por fecha, ficha y estado, y habilitar el ajuste manual de hora de salida.
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// Token CSRF para formularios seguros
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Variables del layout
$paginaTitulo = 'Asistencias de mis fichas';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Asistencias'],
];

// Instructor autenticado
$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
$conexion = (new Conexion())->conectar();

// Fichas del instructor (para filtro select)
$consulta = $conexion->prepare('SELECT id_ficha, nombre_programa FROM ficha WHERE fk_usuario = :id_instructor ORDER BY id_ficha');
$consulta->execute([':id_instructor' => $idInstructor]);
$fichasInstructor = $consulta->fetchAll();

// Filtros GET con validación simple
$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';
$estadoSeleccionado = $_GET['estado'] ?? '';
$estadosPermitidos = ['Presente', 'Retardo', 'Salida temprana', 'Inasistencia'];

function esFechaValida(string $fecha): bool
{
    $objetoFecha = DateTime::createFromFormat('Y-m-d', $fecha);
    return $objetoFecha && $objetoFecha->format('Y-m-d') === $fecha;
}

if ($fechaDesde !== '' && !esFechaValida($fechaDesde)) {
    $fechaDesde = '';
}

if ($fechaHasta !== '' && !esFechaValida($fechaHasta)) {
    $fechaHasta = '';
}

if (!in_array($estadoSeleccionado, $estadosPermitidos, true)) {
    $estadoSeleccionado = '';
}

// URL actual para retorno tras actualizar
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// Consulta base de asistencias con campos de entrada y salida completos
$sqlAsistencias = "
    SELECT
        asistencia.id_asistencia,
        asistencia.fecha_asistencia,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
        usuario.identificacion AS documento,
        ficha.id_ficha,
        asistencia.entrada,
        asistencia.salida,
        asistencia.hora_entrada,
        asistencia.hora_salida,
        DATE_FORMAT(asistencia.entrada, '%H:%i') AS entrada_fmt,
        DATE_FORMAT(asistencia.salida, '%H:%i') AS salida_fmt,
        DATE_FORMAT(asistencia.hora_salida, '%H:%i') AS hora_salida_fmt,
        asistencia.estado_entrada,
        asistencia.estado_salida,
        asistencia.minutos_retardo,
        asistencia.minutos_salida_anticipada
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
";

$parametros = [':id_instructor' => $idInstructor];

if ($fechaDesde !== '') {
    $sqlAsistencias .= ' AND asistencia.fecha_asistencia >= :fecha_desde';
    $parametros[':fecha_desde'] = $fechaDesde;
}

if ($fechaHasta !== '') {
    $sqlAsistencias .= ' AND asistencia.fecha_asistencia <= :fecha_hasta';
    $parametros[':fecha_hasta'] = $fechaHasta;
}

if ($fichaSeleccionada !== '') {
    $sqlAsistencias .= ' AND ficha.id_ficha = :id_ficha';
    $parametros[':id_ficha'] = $fichaSeleccionada;
}

if ($estadoSeleccionado === 'Presente') {
    $sqlAsistencias .= " AND asistencia.estado_entrada = 'PUNTUAL'
                         AND (asistencia.estado_salida IS NULL OR asistencia.estado_salida <> 'SALIDA_ANTICIPADA')";
} elseif ($estadoSeleccionado === 'Retardo') {
    $sqlAsistencias .= " AND asistencia.estado_entrada = 'RETARDO'
                         AND (asistencia.estado_salida IS NULL OR asistencia.estado_salida <> 'SALIDA_ANTICIPADA')";
} elseif ($estadoSeleccionado === 'Salida temprana') {
    $sqlAsistencias .= " AND asistencia.estado_salida = 'SALIDA_ANTICIPADA'";
} elseif ($estadoSeleccionado === 'Inasistencia') {
    $sqlAsistencias .= " AND asistencia.estado_entrada = 'INASISTENCIA'";
}

$sqlAsistencias .= ' ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC, asistencia.id_asistencia DESC';

$consulta = $conexion->prepare($sqlAsistencias);
$consulta->execute($parametros);
$asistenciasInstructor = $consulta->fetchAll();

// Post-proceso: mapear estados a etiquetas legibles
foreach ($asistenciasInstructor as &$asistencia) {
    if ($asistencia['estado_entrada'] === 'INASISTENCIA') {
        $asistencia['estado'] = 'Inasistencia';
    } elseif ($asistencia['estado_salida'] === 'SALIDA_ANTICIPADA') {
        $asistencia['estado'] = 'Salida temprana';
    } elseif ($asistencia['estado_entrada'] === 'RETARDO') {
        $asistencia['estado'] = 'Retardo';
    } else {
        $asistencia['estado'] = 'Presente';
    }
}
unset($asistencia);

function claseEstadoAsistencia($estado)
{
    switch ($estado) {
        case 'Presente':
            return 'badge-success';
        case 'Retardo':
            return 'badge-warning';
        case 'Salida temprana':
            return 'badge-info';
        case 'Inasistencia':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

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

<!-- Tarjeta Principal de Asistencias -->
<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-2 mb-sm-0">
            <i class="fas fa-clipboard-check text-success mr-2"></i> Registro de Asistencias
        </h3>
        <div class="card-tools ml-auto">
            <a href="<?= RUTA_BASE ?>views/instructor/asistencias/index.php" class="btn btn-outline-secondary btn-sm shadow-sm" title="Restablecer filtros">
                <i class="fas fa-sync-alt mr-1"></i> Actualizar
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Formulario de Filtros de Búsqueda -->
        <form method="get" class="row mb-4 align-items-end">
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
                        <option value="">Todas mis fichas</option>
                        <?php foreach ($fichasInstructor as $f): ?>
                            <option value="<?= htmlspecialchars($f['id_ficha']) ?>" <?= (string)$fichaSeleccionada === (string)$f['id_ficha'] ? 'selected' : '' ?>>
                                Ficha #<?= htmlspecialchars($f['id_ficha']) ?> - <?= htmlspecialchars($f['nombre_programa'] ?? '') ?>
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
            <table class="table table-hover table-striped text-nowrap tabla-datos mb-0" data-empty-message="No se encontraron registros de asistencia con los filtros seleccionados.">
                <thead class="thead-light">
                    <tr>
                        <th class="col-documento" style="width: 105px;">Fecha</th>
                        <th>Aprendiz</th>
                        <th class="d-none d-md-table-cell" style="width: 110px;">Ficha</th>
                        <th style="width: 125px;">Hora Entrada</th>
                        <th style="width: 160px;">Hora Salida</th>
                        <th class="text-center" style="width: 115px;">Estado</th>
                        <th class="text-center d-none d-lg-table-cell" style="width: 100px;">Min. Retardo</th>
                        <th class="text-center col-acciones" style="width: 80px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistenciasInstructor as $asistencia): ?>
                        <?php
                            $horaEntrada = !empty($asistencia['entrada_fmt']) 
                                ? $asistencia['entrada_fmt'] 
                                : (!empty($asistencia['hora_entrada']) ? substr($asistencia['hora_entrada'], 0, 5) : null);
                            
                            $horaSalida = !empty($asistencia['salida_fmt']) 
                                ? $asistencia['salida_fmt'] 
                                : (!empty($asistencia['hora_salida_fmt']) ? $asistencia['hora_salida_fmt'] : (!empty($asistencia['hora_salida']) ? substr($asistencia['hora_salida'], 0, 5) : null));
                            
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
                                    <span class="badge badge-info mr-1">Ficha #<?= htmlspecialchars($asistencia['id_ficha']) ?></span>
                                    <?php if (!empty($asistencia['minutos_retardo']) && (int)$asistencia['minutos_retardo'] > 0): ?>
                                        <span class="badge badge-warning"><?= (int)$asistencia['minutos_retardo'] ?>m retardo</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Ficha -->
                            <td class="align-middle d-none d-md-table-cell">
                                <span class="badge badge-info px-2 py-1">Ficha #<?= htmlspecialchars($asistencia['id_ficha']) ?></span>
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

                            <!-- Hora de Salida (Etiqueta dinámica si está vacía) -->
                            <td class="align-middle">
                                <?php if (!empty($horaSalida)): ?>
                                    <span class="font-weight-bold text-dark">
                                        <i class="fas fa-sign-out-alt text-primary mr-1"></i><?= htmlspecialchars($horaSalida) ?>
                                    </span>
                                    <?php if ($asistencia['estado_salida'] === 'SALIDA_ANTICIPADA'): ?>
                                        <span class="badge badge-info ml-1" title="Salida anticipada (<?= (int)$asistencia['minutos_salida_anticipada'] ?> min)">Anticipada</span>
                                    <?php endif; ?>
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
                                <span class="badge badge-estado <?= claseEstadoAsistencia($asistencia['estado']) ?> px-2 py-1">
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

                            <!-- Acciones: Botón de Edición Rápida de Salida -->
                            <td class="text-center align-middle col-acciones">
                                <?php if (!$esInasistencia): ?>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL DE AJUSTE RÁPIDO DE HORA DE SALIDA                      -->
<!-- ============================================================ -->
<div class="modal fade" id="modalAjustarSalida" tabindex="-1" role="dialog" aria-labelledby="modalAjustarSalidaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <form method="post" action="<?= RUTA_BASE ?>controllers/instructor/actualizar_asistencia.php" autocomplete="off">
                <div class="modal-header bg-warning text-dark py-3">
                    <h5 class="modal-title font-weight-bold mb-0" id="modalAjustarSalidaLabel">
                        <i class="fas fa-clock mr-2"></i> Ajustar Hora de Salida
                    </h5>
                    <button type="button" class="close text-dark" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body p-4">
                    <input type="hidden" name="id_asistencia" id="modal_asistencia_id" value="">
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                    <input type="hidden" name="return_url" value="<?= htmlspecialchars($currentUrl) ?>">

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btns = document.querySelectorAll('.btn-ajustar-salida');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var nombre = this.getAttribute('data-aprendiz');
            var fecha = this.getAttribute('data-fecha');
            var entrada = this.getAttribute('data-entrada') || '--';
            var salida = this.getAttribute('data-salida') || '';

            document.getElementById('modal_asistencia_id').value = id;
            document.getElementById('modal_aprendiz_nombre').textContent = nombre;
            document.getElementById('modal_fecha_asistencia').textContent = fecha;
            document.getElementById('modal_hora_entrada').textContent = entrada;
            
            var inputSalida = document.getElementById('modal_hora_salida');
            if (salida) {
                inputSalida.value = salida;
            } else {
                // Si aún no tiene salida, predefinir la hora actual
                var now = new Date();
                var hh = String(now.getHours()).padStart(2, '0');
                var mm = String(now.getMinutes()).padStart(2, '0');
                inputSalida.value = hh + ':' + mm;
            }

            if (typeof $ !== 'undefined') {
                $('#modalAjustarSalida').modal('show');
            }
        });
    });

    // Botón para fijar hora actual en un clic
    var btnAhora = document.getElementById('btnHoraActual');
    if (btnAhora) {
        btnAhora.addEventListener('click', function() {
            var now = new Date();
            var hh = String(now.getHours()).padStart(2, '0');
            var mm = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('modal_hora_salida').value = hh + ':' + mm;
        });
    }
});
</script>

<?php
$contenido = ob_get_clean();

// Render del layout principal del panel del instructor
include __DIR__ . '/../../layouts/layout_instructor.php';
