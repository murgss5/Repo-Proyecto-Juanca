<?php
/**
 * vista: importar horarios desde excel (fase 2 - solo previsualizacion)
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// token csrf
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// variables layout
$paginaTitulo = 'Importar horarios desde Excel';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios', 'url' => RUTA_BASE . 'views/admin/horarios/index.php'],
    ['label' => 'Importar Excel']
];

ob_start();
?>

<!-- mensajes flash -->
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<div class="alert alert-<?= $flash['tipo'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['mensaje']) ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-import text-sena"></i> Importar horarios desde Excel</h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h5><i class="fas fa-info-circle"></i> Fase 2: Validación y previsualización</h5>
            <p class="mb-0">
                Esta acción <strong>no guarda horarios</strong> en la base de datos.
                Solo valida el archivo y muestra una previsualización con el estado de cada fila.
                La confirmación y guardado se implementarán en la Fase 3.
            </p>
        </div>

        <div class="mb-3">
            <a href="<?= RUTA_BASE ?>controllers/descargar_plantilla_horarios.php" class="btn btn-outline-primary">
                <i class="fas fa-download"></i> Descargar plantilla oficial (XLSX)
            </a>
            <small class="form-text text-muted d-block mt-2">
                La plantilla incluye hoja "Horarios" con encabezados oficiales y filas de ejemplo,
                y hoja "Instrucciones" con reglas y formatos.
            </small>
        </div>

        <form method="post" action="<?= RUTA_BASE ?>controllers/importar_horarios_excel.php" enctype="multipart/form-data">
            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

            <div class="form-group">
                <label for="archivo_excel">Archivo XLSX <span class="text-danger">*</span></label>
                <input type="file" class="form-control-file" id="archivo_excel" name="archivo_excel" accept=".xlsx" required>
                <small class="form-text text-muted">
                    Solo archivos .xlsx &middot; Máximo 5 MB &middot; Hoja requerida: "Horarios"
                </small>
            </div>

            <div class="row">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Validar y previsualizar
                    </button>
                    <a href="<?= RUTA_BASE ?>views/admin/horarios/index.php" class="btn btn-secondary ml-2">
                        <i class="fas fa-arrow-left"></i> Volver al listado
                    </a>
                </div>
            </div>
        </form>

        <hr>

        <h5>Validaciones que se aplican (sin guardar):</h5>
        <ul class="small">
            <li>Archivo .xlsx real (MIME y extensión)</li>
            <li>Hoja "Horarios" presente</li>
            <li>Encabezados obligatorios: dia, hora_inicio, hora_fin, identificacion_instructor, ambiente, id_ficha o numero_ficha</li>
            <li>Instructor existe y tiene rol Instructor</li>
            <li>Ficha existe y pertenece al instructor indicado</li>
            <li>Día válido (Lunes a Domingo)</li>
            <li>Horas en formato HH:MM, fin > inicio</li>
            <li>Duplicados dentro del archivo y contra horarios existentes</li>
            <li>Cruces de horario: misma ficha, mismo instructor, mismo ambiente (solo advertencia por ahora)</li>
            <li>Filas sin ficha → PENDIENTE_FICHA (selección manual en previsualización)</li>
        </ul>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';