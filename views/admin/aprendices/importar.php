<?php
/**
 * Vista: Importar aprendices (Fase 1 - Carga)
 */
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1); // Solo administrador
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Consultar fichas disponibles para el select
$conexion = (new Conexion())->conectar();
$consultaFichas = $conexion->query('SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha DESC');
$fichasRegistradas = $consultaFichas->fetchAll(PDO::FETCH_ASSOC);

$paginaTitulo = 'Importar aprendices';
$menuActivo   = 'aprendices-importar';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php'],
    ['label' => 'Importar'],
];

ob_start();
?>

<?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['tipo']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['mensaje']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['import_preview_aprendices'])): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Existe una previsualización temporal anterior. Puedes cargar otro archivo para reemplazarla o cancelarla desde la vista de <a href="<?= RUTA_BASE ?>views/admin/aprendices/importar_preview.php" class="alert-link">Previsualización</a>.
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Seleccionar archivo Excel</h3>
            </div>
            <form action="<?= RUTA_BASE ?>controllers/importar_aprendices_excel.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                <div class="card-body">
                    <div class="form-group">
                        <label for="id_ficha">Ficha de destino <span class="text-danger">*</span></label>
                        <select name="id_ficha" id="id_ficha" class="form-control" required>
                            <option value="">-- Seleccione la ficha --</option>
                            <?php foreach ($fichasRegistradas as $ficha): ?>
                                <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>">
                                    Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?> - <?= htmlspecialchars($ficha['nombre_programa']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Todos los aprendices válidos del archivo se previsualizarán asociados a esta ficha.</small>
                    </div>
                    
                    <div class="form-group mt-4">
                        <label for="archivo_excel">Archivo exportado de Sofia Plus (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" name="archivo_excel" id="archivo_excel" class="form-control-file" accept=".xlsx" required>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Analizar y previsualizar</button>
                    <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="btn btn-default float-right">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle text-info"></i> Formato requerido</h3>
            </div>
            <div class="card-body">
                <p>El archivo debe ser un Excel original (<code>.xlsx</code>) exportado de Sofia Plus con los siguientes encabezados en la primera fila, exactamente en este orden:</p>
                <ol>
                    <li><strong>Tipo de Documento</strong></li>
                    <li><strong>Número de Documento</strong></li>
                    <li><strong>Nombre</strong></li>
                    <li><strong>Apellidos</strong></li>
                    <li><strong>Celular</strong></li>
                    <li><strong>Correo Electrónico</strong></li>
                    <li><strong>Estado</strong></li>
                </ol>
                <p class="text-muted text-sm mt-3">
                    <strong>Notas importantes:</strong><br>
                    - No modifique los encabezados ni altere el orden de las columnas.<br>
                    - El sistema leerá el número de documento como texto, preservando ceros iniciales si existen.<br>
                    - En esta fase solo se realizará una validación y previsualización de los datos.
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$contenido = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/layout_admin.php';
