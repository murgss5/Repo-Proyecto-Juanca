<?php

// Formulario funcional para registrar una excusa médica.

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

$conexion = (new Conexion())->conectar();

// Cargar inasistencias del aprendiz que no tengan excusa
$consultaInasistencias = $conexion->prepare(
    "SELECT
        a.id_asistencia,
        a.fecha_asistencia
     FROM asistencia a
     INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
     LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
     WHERE ap.fk_usuario = :id_usuario
     AND a.estado_entrada = 'INASISTENCIA'
     AND e.id_excusa IS NULL
     ORDER BY a.fecha_asistencia DESC"
);
$consultaInasistencias->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
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


// Inicia el búfer de salida para evitar errores si después se necesita usar header().
ob_start();
?>

<?php if (isset($_GET['mensaje'])): ?>
    // <!-- Muestra una alerta verde cuando se recibe un mensaje de éxito por la URL. -->
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i>

        <!-- htmlspecialchars evita que se ejecute codigo HTML o JavaScript enviado por la URL. -->
        <?= htmlspecialchars($_GET['mensaje']) ?>

        <!-- Boton para cerrar la alerta. -->
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <!-- Muestra una alerta roja cuando se recibe un mensaje de error por la URL. -->
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle"></i>

        <!-- Se muestra el mensaje de error de forma segura. -->
        <?= htmlspecialchars($_GET['error']) ?>

        <!-- Botón para cerrar la alerta. -->
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cloud-upload-alt text-sena"></i> Nueva excusa médica</h3>
            </div>
            <form action="<?= RUTA_BASE ?>controllers/subir_excusa.php" method="post" enctype="multipart/form-data">
                <div class="card-body">
                    <?php if (empty($inasistenciasDisponibles)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No tienes inasistencias pendientes de justificar.
                            <br>Todas tus inasistencias ya tienen una excusa registrada.
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label for="id_asistencia">Inasistencia a justificar <span class="text-danger">*</span></label>
                            <select id="id_asistencia" name="id_asistencia" class="form-control" required>
                                <option value="">-- Seleccione una inasistencia --</option>
                                <?php foreach ($inasistenciasDisponibles as $inasistencia): ?>
                                    <option value="<?= htmlspecialchars($inasistencia['id_asistencia']) ?>">
                                        <?= htmlspecialchars(date('d/m/Y', strtotime($inasistencia['fecha_asistencia']))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Seleccione la fecha en la que faltó a la formación.</small>
                        </div>

                        <div class="form-group">
                            <label for="archivo">Adjuntar excusa médica <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="archivo" name="archivo" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label class="custom-file-label" for="archivo">Seleccione un archivo (PDF, JPG o PNG)</label>
                            </div>
                            <small class="form-text text-muted">Tamaño máximo permitido: 5 MB. Formatos: PDF, JPG, PNG.</small>
                        </div>

                        <div class="form-group">
                            <label for="observaciones">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" rows="3"
                                      placeholder="Describa brevemente el motivo de la inasistencia (opcional)..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer text-right">
                    <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php if (!empty($inasistenciasDisponibles)): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-cloud-upload-alt"></i> Enviar excusa
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle text-sena"></i> ¿Cómo funciona?</h3>
            </div>
            <div class="card-body">
                <ol class="pl-3 small">
                    <li>Seleccione la inasistencia que desea justificar.</li>
                    <li>Adjunte la excusa médica en formato PDF, JPG o PNG.</li>
                    <li>Escriba una breve observación si lo desea.</li>
                    <li>Envíe la solicitud; el instructor podrá aprobarla o rechazarla.</li>
                </ol>
                <div class="alert alert-warning py-2 small mb-0">
                    <i class="fas fa-info-circle"></i> La excusa quedará en estado <strong>PENDIENTE</strong> hasta que el instructor la revise.
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_aprendiz.php';
