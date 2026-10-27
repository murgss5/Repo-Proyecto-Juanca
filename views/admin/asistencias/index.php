<?php
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

function fechaFiltroValida(string $fecha): bool
{
    $convertida = DateTime::createFromFormat('Y-m-d', $fecha);
    return $convertida !== false && $convertida->format('Y-m-d') === $fecha;
}

function claseEstadoAsistenciaAdmin(string $estado): string
{
    return match ($estado) {
        'Presente' => 'badge-success',
        'Retardo' => 'badge-warning text-white',
        'Salida temprana' => 'badge-info',
        'Inasistencia' => 'badge-danger',
        default => 'badge-secondary',
    };
}

$conexion = (new Conexion())->conectar();
$fechaDesde = trim($_GET['desde'] ?? '');
$fechaHasta = trim($_GET['hasta'] ?? '');
$fichaSeleccionada = filter_input(INPUT_GET, 'ficha', FILTER_VALIDATE_INT) ?: '';
$estadoSeleccionado = $_GET['estado'] ?? '';
$estadosPermitidos = ['Presente', 'Retardo', 'Salida temprana', 'Inasistencia'];

if ($fechaDesde !== '' && !fechaFiltroValida($fechaDesde)) $fechaDesde = '';
if ($fechaHasta !== '' && !fechaFiltroValida($fechaHasta)) $fechaHasta = '';
if (!in_array($estadoSeleccionado, $estadosPermitidos, true)) $estadoSeleccionado = '';

$fichas = $conexion->query('SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha')->fetchAll();
$sql = "SELECT a.fecha_asistencia, CONCAT(u.nombre, ' ', u.apellido) AS aprendiz,
    f.id_ficha, ap.codigo_rfid, DATE_FORMAT(a.entrada, '%H:%i') AS entrada,
    DATE_FORMAT(a.salida, '%H:%i') AS salida, a.estado_entrada, a.estado_salida, a.minutos_retardo
    FROM asistencia a
    INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
    INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
    INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha WHERE 1=1";
$parametros = [];
if ($fechaDesde !== '') { $sql .= ' AND a.fecha_asistencia >= :desde'; $parametros[':desde'] = $fechaDesde; }
if ($fechaHasta !== '') { $sql .= ' AND a.fecha_asistencia <= :hasta'; $parametros[':hasta'] = $fechaHasta; }
if ($fichaSeleccionada !== '') { $sql .= ' AND f.id_ficha = :ficha'; $parametros[':ficha'] = $fichaSeleccionada; }
if ($estadoSeleccionado === 'Presente') $sql .= " AND a.estado_entrada = 'PUNTUAL' AND (a.estado_salida IS NULL OR a.estado_salida <> 'SALIDA_ANTICIPADA')";
elseif ($estadoSeleccionado === 'Retardo') $sql .= " AND a.estado_entrada = 'RETARDO' AND (a.estado_salida IS NULL OR a.estado_salida <> 'SALIDA_ANTICIPADA')";
elseif ($estadoSeleccionado === 'Salida temprana') $sql .= " AND a.estado_salida = 'SALIDA_ANTICIPADA'";
elseif ($estadoSeleccionado === 'Inasistencia') $sql .= " AND a.estado_entrada = 'INASISTENCIA'";
$sql .= ' ORDER BY a.fecha_asistencia DESC, a.entrada DESC, a.id_asistencia DESC';
$consulta = $conexion->prepare($sql);
$consulta->execute($parametros);
$asistencias = $consulta->fetchAll();

foreach ($asistencias as &$asistencia) {
    $asistencia['estado'] = $asistencia['estado_entrada'] === 'INASISTENCIA' 
        ? 'Inasistencia' 
        : ($asistencia['estado_salida'] === 'SALIDA_ANTICIPADA' 
            ? 'Salida temprana' 
            : ($asistencia['estado_entrada'] === 'RETARDO' ? 'Retardo' : 'Presente'));
}
unset($asistencia);

$paginaTitulo = 'Asistencias';
$menuActivo   = 'asistencias';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Asistencias']
];

ob_start();
?>

<!-- 1. Tarjeta de Filtros (Parte Superior) -->
<div class="card card-outline card-success shadow-sm mb-4">
    <div class="card-header border-bottom-0">
        <h3 class="card-title font-weight-bold">
            <i class="fas fa-filter mr-2 text-success"></i> Filtros de Búsqueda
        </h3>
        <div class="card-tools">
            <?php if ($fechaDesde !== '' || $fechaHasta !== '' || $fichaSeleccionada !== '' || $estadoSeleccionado !== ''): ?>
                <a href="<?= RUTA_BASE ?>views/admin/asistencias/index.php" class="btn btn-outline-secondary btn-sm" title="Restablecer filtros">
                    <i class="fas fa-times mr-1"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="get">
            <div class="row align-items-end">
                <!-- Columna 1: Desde -->
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="far fa-calendar-alt text-success mr-1"></i> Desde
                    </label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>

                <!-- Columna 2: Hasta -->
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="far fa-calendar-alt text-success mr-1"></i> Hasta
                    </label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>

                <!-- Columna 3: Ficha -->
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-layer-group text-success mr-1"></i> Ficha
                    </label>
                    <select name="ficha" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">Todas las fichas</option>
                        <?php foreach ($fichas as $ficha): ?>
                            <option value="<?= $ficha['id_ficha'] ?>" <?= strval($fichaSeleccionada) === strval($ficha['id_ficha']) ? 'selected' : '' ?>>
                                Ficha #<?= $ficha['id_ficha'] ?> — <?= htmlspecialchars($ficha['nombre_programa']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Columna 4: Estado -->
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">
                        <i class="fas fa-tag text-success mr-1"></i> Estado
                    </label>
                    <select name="estado" class="form-control form-control-sm custom-select custom-select-sm">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadosPermitidos as $estado): ?>
                            <option value="<?= $estado ?>" <?= $estadoSeleccionado === $estado ? 'selected' : '' ?>>
                                <?= $estado ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Columna 5: Botón Filtrar -->
                <div class="col-md-2 col-sm-12 mb-2 text-right">
                    <button type="submit" class="btn btn-success btn-block btn-sm shadow-sm font-weight-bold">
                        <i class="fas fa-filter mr-1"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 2. Tarjeta de la Tabla (Parte Inferior) -->
<div class="card card-outline card-primary shadow-sm">
    <div class="card-header border-bottom-0">
        <h3 class="card-title font-weight-bold">
            <i class="fas fa-clipboard-check mr-2 text-primary"></i> Listado de Asistencias
        </h3>
        <div class="card-tools">
            <span class="badge badge-primary px-3 py-1 font-weight-normal">
                <?= count($asistencias) ?> registros
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle table-sm tabla-datos mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="border-top-0">Fecha</th>
                        <th class="border-top-0">Aprendiz</th>
                        <th class="border-top-0">Ficha</th>
                        <th class="border-top-0">Hora entrada</th>
                        <th class="border-top-0">Hora salida</th>
                        <th class="text-center border-top-0">Estado</th>
                        <th class="text-center border-top-0">Min. retardo</th>
                        <th class="border-top-0">Código RFID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($asistencias as $asistencia): ?>
                        <tr>
                            <td><?= htmlspecialchars($asistencia['fecha_asistencia']) ?></td>
                            <td class="font-weight-bold"><?= htmlspecialchars($asistencia['aprendiz']) ?></td>
                            <td><span class="badge badge-info">Ficha #<?= $asistencia['id_ficha'] ?></span></td>
                            <td><?= htmlspecialchars($asistencia['entrada'] ?? '--') ?></td>
                            <td><?= htmlspecialchars($asistencia['salida'] ?? '--') ?></td>
                            <td class="text-center">
                                <span class="badge badge-estado <?= claseEstadoAsistenciaAdmin($asistencia['estado']) ?>">
                                    <?= htmlspecialchars($asistencia['estado']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($asistencia['minutos_retardo'] > 0): ?>
                                    <span class="badge badge-warning text-white"><?= (int)$asistencia['minutos_retardo'] ?> min</span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($asistencia['codigo_rfid'] ?? '--') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($asistencias)): ?>
            <p class="text-center text-muted py-4 mb-0">
                <i class="far fa-folder-open fa-2x text-muted mb-2"></i><br>
                No se encontraron registros de asistencia con los filtros aplicados.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';
