<?php
/**
 * ============================================================
 * VISTA: MIS EXCUSAS (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Lista las excusas reales del aprendiz autenticado.
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

$conexion = (new Conexion())->conectar();

// Consultar excusas del aprendiz autenticado
$consultaExcusas = $conexion->prepare(
    "SELECT
        e.id_excusa,
        e.archivo,
        e.observacion,
        e.estado,
        e.fecha_subida,
        e.fecha_revision,
        a.fecha_asistencia
     FROM excusa e
     INNER JOIN asistencia a ON a.id_asistencia = e.fk_asistencia
     INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
     WHERE ap.fk_usuario = :id_usuario
     ORDER BY e.fecha_subida DESC, a.fecha_asistencia DESC"
);
$consultaExcusas->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaExcusas->execute();
$excusas = $consultaExcusas->fetchAll();

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
$paginaTitulo = 'Mis excusas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mis excusas'],
];

ob_start();
?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-medical text-sena"></i> Excusas enviadas</h3>
        <div class="card-tools">
            <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-cloud-upload-alt"></i> Subir excusa
            </a>
        </div>
    </div>

    <div class="card-body">
        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row mb-3">
            <div class="col-md-5 col-sm-12 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                    </div>
                    <input type="text" class="form-control buscador-tabla" placeholder="Buscar por fecha...">
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-2">
                <select class="form-control filtro-tabla" data-col="3" data-exacto="true">
                    <option value="">Todos los estados</option>
                    <option>Pendiente</option>
                    <option>Aprobada</option>
                    <option>Rechazada</option>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-bordered tabla-datos">
                <thead>
                    <tr>
                        <th>Fecha de inasistencia</th>
                        <th>Archivo adjunto</th>
                        <th>Fecha de envío</th>
                        <th>Estado</th>
                        <th>Fecha de revisión</th>
                        <th>Observaciones</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($excusas as $excusa): ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($excusa['fecha_asistencia']))) ?></td>
                                <td>
                                    <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/descargar.php?id_excusa=<?= htmlspecialchars($excusa['id_excusa']) ?>" class="text-sena" data-toggle="tooltip" title="Descargar archivo">
                                        <i class="fas fa-paperclip"></i> <?= htmlspecialchars($excusa['archivo']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($excusa['fecha_subida']))) ?></td>
                                <td><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado_mostrado']) ?>"><?= htmlspecialchars($excusa['estado_mostrado']) ?></span></td>
                                <td>
                                    <?php if ($excusa['fecha_revision']): ?>
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($excusa['fecha_revision']))) ?>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($excusa['observacion'])): ?>
                                        <span class="small"><?= htmlspecialchars($excusa['observacion']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/descargar.php?id_excusa=<?= htmlspecialchars($excusa['id_excusa']) ?>" class="btn btn-sm btn-outline-primary" title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Las excusas provienen de la base de datos.</p>
    </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_aprendiz.php';
