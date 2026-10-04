<?php
/**
 * Vista: Previsualización de importación de aprendices
 */
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);

require_once dirname(__DIR__, 3) . '/includes/constantes.php';

$previewData = $_SESSION['import_preview_aprendices'] ?? null;

// Validar si existe sesión y si no ha caducado (30 minutos)
if (!$previewData || (time() - $previewData['timestamp'] > 1800)) {
    unset($_SESSION['import_preview_aprendices']);
    $_SESSION['flash'] = ['tipo' => 'warning', 'mensaje' => 'La sesión de previsualización ha caducado o no existe. Cargue el archivo nuevamente.'];
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/importar.php');
    exit;
}

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$ficha = $previewData['ficha'];
$resumen = $previewData['resumen'];
$filas = $previewData['filas'];

$paginaTitulo = 'Previsualización de Aprendices';
$menuActivo   = 'aprendices-importar';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php'],
    ['label' => 'Previsualización']
];

ob_start();
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title">Resumen de Importación - Ficha: <strong>#<?= htmlspecialchars($ficha['id']) ?> — <?= htmlspecialchars($ficha['programa']) ?></strong></h3>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-2 col-sm-4 col-6 mb-3">
                        <div class="info-box bg-light">
                            <div class="info-box-content">
                                <span class="info-box-text text-muted">Total Filas Leídas</span>
                                <span class="info-box-number h4 mb-0"><?= htmlspecialchars($resumen['total']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6 mb-3">
                        <div class="info-box bg-success">
                            <div class="info-box-content">
                                <span class="info-box-text">Listas (Válidas)</span>
                                <span class="info-box-number h4 mb-0"><?= htmlspecialchars($resumen['validas'] - $resumen['advertencias']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6 mb-3">
                        <div class="info-box bg-warning">
                            <div class="info-box-content">
                                <span class="info-box-text">Con Advertencia</span>
                                <span class="info-box-number h4 mb-0"><?= htmlspecialchars($resumen['advertencias']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6 mb-3">
                        <div class="info-box bg-danger">
                            <div class="info-box-content">
                                <span class="info-box-text">Rechazadas</span>
                                <span class="info-box-number h4 mb-0"><?= htmlspecialchars($resumen['rechazadas']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6 mb-3">
                        <div class="info-box bg-secondary">
                            <div class="info-box-content">
                                <span class="info-box-text">Duplicadas</span>
                                <span class="info-box-number h4 mb-0"><?= htmlspecialchars($resumen['duplicadas']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-6 mb-3">
                        <div class="info-box bg-light">
                            <div class="info-box-content">
                                <span class="info-box-text text-muted">Vacías Ignoradas</span>
                                <span class="info-box-number h4 mb-0"><?= htmlspecialchars($resumen['ignoradas']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <form action="<?= RUTA_BASE ?>controllers/cancelar_importar_aprendices.php" method="POST" class="d-inline">
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                    <button type="submit" class="btn btn-default"><i class="fas fa-arrow-left"></i> Cancelar y regresar</button>
                </form>
                <form action="<?= RUTA_BASE ?>controllers/cancelar_importar_aprendices.php" method="POST" class="d-inline">
                    <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                    <input type="hidden" name="recargar" value="1">
                    <button type="submit" class="btn btn-info"><i class="fas fa-upload"></i> Cargar otro Excel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detalle de las filas (Solo Previsualización)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>Tipo de Documento</th>
                        <th>Número de Documento</th>
                        <th>Nombre</th>
                        <th>Apellidos</th>
                        <th>Celular</th>
                        <th>Correo Electrónico</th>
                        <th>Estado</th>
                        <th>Validación</th>
                        <th>Observación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filas)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">No se encontraron datos en el archivo cargado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filas as $fila): ?>
                            <?php
                            $badgeClass = 'secondary';
                            if ($fila['estado_validacion'] === 'VALIDA') $badgeClass = 'success';
                            elseif ($fila['estado_validacion'] === 'ADVERTENCIA') $badgeClass = 'warning';
                            elseif ($fila['estado_validacion'] === 'RECHAZADA') $badgeClass = 'danger';
                            elseif ($fila['estado_validacion'] === 'DUPLICADA') $badgeClass = 'secondary';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['tipo_documento']) ?></td>
                                <td><?= htmlspecialchars($fila['documento']) ?></td>
                                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                                <td><?= htmlspecialchars($fila['apellidos']) ?></td>
                                <td><?= htmlspecialchars($fila['celular']) ?></td>
                                <td><?= htmlspecialchars($fila['correo']) ?></td>
                                <td><?= htmlspecialchars($fila['estado']) ?></td>
                                <td><span class="badge badge-<?= $badgeClass ?>"><?= htmlspecialchars($fila['estado_validacion']) ?></span></td>
                                <td>
                                    <?php if ($fila['estado_validacion'] !== 'VALIDA'): ?>
                                        <small class="text-<?= $badgeClass ?>"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($fila['observacion']) ?></small>
                                    <?php else: ?>
                                        <small class="text-success"><i class="fas fa-check"></i> <?= htmlspecialchars($fila['observacion']) ?></small>
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

<?php
$contenido = ob_get_clean();
require dirname(__DIR__, 2) . '/layouts/layout_admin.php';
