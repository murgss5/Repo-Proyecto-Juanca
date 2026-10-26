<?php
/**
 * Vista: Subir excusa médica (Aprendiz).
 * Formulario funcional para registrar una excusa médica.
 */

// Carga la autenticacion y verifica que sea aprendiz
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(3);
// Carga constantes y conexion a la base de datos
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

$conexion = (new Conexion())->conectar();

// Cargar inasistencias del aprendiz que no tengan excusa
$consultaInasistencias = $conexion->prepare(
    "SELECT
        a.id_asistencia,
        a.fecha_asistencia,
        h.entrada,
        h.salida,
        h.competencia
     FROM asistencia a
     INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
     LEFT JOIN horario h ON h.id_horario = a.fk_horario
     LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
     WHERE ap.fk_usuario = :id_usuario
     AND a.estado_entrada = 'INASISTENCIA'
     AND e.id_excusa IS NULL
     ORDER BY a.fecha_asistencia DESC, h.entrada ASC"
);
$consultaInasistencias->bindValue(':id_usuario', $_SESSION['aprendiz']['id_usuario'], PDO::PARAM_INT);
$consultaInasistencias->execute();
$inasistenciasDisponibles = $consultaInasistencias->fetchAll();

// --- Variables del layout ---
$paginaTitulo = 'Subir excusa médica';
$menuActivo   = 'subir-excusa';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mis excusas', 'url' => RUTA_BASE . 'views/aprendiz/excusas/index.php'],
    ['label' => 'Subir excusa'],
];


// Inicia el bufer de salida para evitar errores si despues se necesita usar header().
ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9 col-12">
        <?php if (isset($_GET['mensaje'])): ?>
            <!-- Alerta de éxito -->
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_GET['mensaje']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <!-- Alerta de error -->
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?= htmlspecialchars($_GET['error']) ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="card card-outline card-success shadow-sm">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h3 class="card-title font-weight-bold text-dark mb-0">
                    <i class="fas fa-cloud-upload-alt text-sena mr-2"></i> Subir excusa médica
                </h3>
                <span class="badge badge-light border text-muted px-2 py-1 small">
                    <i class="fas fa-file-medical text-sena mr-1"></i> Formato digital
                </span>
            </div>
            <form action="<?= RUTA_BASE ?>controllers/subir_excusa.php" method="post" enctype="multipart/form-data">
                <div class="card-body p-4">
                    <?php if (empty($inasistenciasDisponibles)): ?>
                        <div class="text-center py-5 px-3">
                            <div class="mb-3 text-muted">
                                <i class="fas fa-check-circle fa-3x text-success"></i>
                            </div>
                            <h4 class="h5 font-weight-bold text-dark mb-1">Sin inasistencias pendientes</h4>
                            <p class="text-muted small mb-3">
                                No tienes fallas pendientes por justificar. Todas tus inasistencias ya cuentan con una excusa registrada.
                            </p>
                            <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/index.php" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-folder-open mr-1"></i> Consultar mis excusas
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-4">
                            Diligencia el formulario para justificar tu inasistencia. La solicitud quedará en estado <strong>Pendiente</strong> para revisión y validación por parte del instructor.
                        </p>

                        <div class="form-group mb-4">
                            <label for="id_asistencia" class="font-weight-bold text-dark">
                                Inasistencia a justificar <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-light text-muted"><i class="fas fa-calendar-alt"></i></span>
                                </div>
                                <select id="id_asistencia" name="id_asistencia" class="form-control" required>
                                    <option value="">-- Seleccione una inasistencia --</option>
                                    <?php foreach ($inasistenciasDisponibles as $inasistencia): 
                                        $infoClase = '';
                                        if (!empty($inasistencia['entrada']) && !empty($inasistencia['salida'])) {
                                            $infoClase = ' (' . substr($inasistencia['entrada'], 0, 5) . ' - ' . substr($inasistencia['salida'], 0, 5) . (!empty($inasistencia['competencia']) ? ' &middot; ' . $inasistencia['competencia'] : '') . ')';
                                        }
                                    ?>
                                        <option value="<?= htmlspecialchars($inasistencia['id_asistencia']) ?>">
                                            <?= htmlspecialchars(date('d/m/Y', strtotime($inasistencia['fecha_asistencia']))) ?><?= $infoClase ?> &middot; Inasistencia registrada
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="form-text text-muted mt-1">Selecciona la fecha en la que faltaste a la formación.</small>
                        </div>

                        <div class="form-group mb-4">
                            <label for="archivo" class="font-weight-bold text-dark">
                                Adjuntar excusa médica <span class="text-danger">*</span>
                            </label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="archivo" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label class="custom-file-label text-truncate" for="archivo" data-browse="Examinar">Seleccione un archivo (PDF, JPG o PNG)</label>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small text-muted">
                                <span><i class="fas fa-info-circle mr-1"></i> Formatos permitidos: PDF, JPG, PNG</span>
                                <span class="badge badge-light border text-muted">Máx. 5 MB</span>
                            </div>
                        </div>

                        <div class="form-group mb-2">
                            <label for="observaciones" class="font-weight-bold text-dark">
                                Observaciones <span class="text-muted font-weight-normal small">(opcional)</span>
                            </label>
                            <textarea id="observaciones" name="observaciones" class="form-control" rows="3"
                                      placeholder="Describa brevemente el motivo de la inasistencia (opcional)..."></textarea>
                            <small class="form-text text-muted mt-1">Detalla cualquier información relevante sobre tu justificación.</small>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-light border-top d-flex justify-content-end align-items-center py-3">
                    <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/index.php" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </a>
                    <?php if (!empty($inasistenciasDisponibles)): ?>
                        <button type="submit" class="btn btn-primary font-weight-medium">
                            <i class="fas fa-cloud-upload-alt mr-1"></i> Enviar excusa
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_aprendiz.php';
