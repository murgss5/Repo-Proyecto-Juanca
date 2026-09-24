<?php
/**
 * vista: resultado de importacion de horarios (fase 3)
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// validar que existe resultado en sesion
if (empty($_SESSION['import_resultado'])) {
    header('Location: ' . RUTA_BASE . 'views/admin/horarios/importar.php?error=No hay resultado de importacion para mostrar');
    exit;
}

$resultado = $_SESSION['import_resultado'];
$totalSeleccionadas = $resultado['total_seleccionadas'] ?? 0;
$creadas = $resultado['creadas'] ?? 0;
$omitidas = $resultado['omitidas'] ?? 0;
$rechazadas = $resultado['rechazadas'] ?? 0;
$detalles = $resultado['detalles'] ?? [];

$paginaTitulo = 'Resultado de importacion de horarios';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios', 'url' => RUTA_BASE . 'views/admin/horarios/index.php'],
    ['label' => 'Importar Excel', 'url' => RUTA_BASE . 'views/admin/horarios/importar.php'],
    ['label' => 'Resultado']
];

// limpiar resultado de sesion para evitar reenvio al recargar
unset($_SESSION['import_resultado']);

ob_start();
?>

<!-- resumen -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="info-box bg-light border">
            <div class="info-box-content">
                <span class="info-box-text">Filas seleccionadas</span>
                <span class="info-box-number"><?= $totalSeleccionadas ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-success text-white">
            <div class="info-box-content">
                <span class="info-box-text">Horarios creados</span>
                <span class="info-box-number"><?= $creadas ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-warning text-dark">
            <div class="info-box-content">
                <span class="info-box-text">Duplicados omitidos</span>
                <span class="info-box-number"><?= $omitidas ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-danger text-white">
            <div class="info-box-content">
                <span class="info-box-text">Rechazadas</span>
                <span class="info-box-number"><?= $rechazadas ?></span>
            </div>
        </div>
    </div>
</div>

<!-- mensaje global -->
<?php if ($creadas > 0): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> Se crearon <strong><?= $creadas ?></strong> horario(s) nuevo(s).
</div>
<?php endif; ?>
<?php if ($omitidas > 0): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> Se omitieron <strong><?= $omitidas ?></strong> fila(s) por duplicado exacto en BD.
</div>
<?php endif; ?>
<?php if ($rechazadas > 0): ?>
<div class="alert alert-danger">
    <i class="fas fa-times-circle"></i> Se rechazaron <strong><?= $rechazadas ?></strong> fila(s) por validacion.
</div>
<?php endif; ?>

<!-- tabla de detalles -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detalle por fila</h3>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover table-bordered tabla-datos">
            <thead class="thead-light">
                <tr>
                    <th style="width: 50px;">Fila Excel</th>
                    <th>Instructor</th>
                    <th>Ficha</th>
                    <th>Dia</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th style="width: 130px;">Resultado</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                <tr class="<?= $d['resultado'] === 'CREADA' ? 'table-success' : ($d['resultado'] === 'OMITIDA_DUPLICADO' ? 'table-warning' : 'table-danger') ?>">
                    <td><?= $d['fila'] ?></td>
                    <td><?= htmlspecialchars($d['instructor']) ?></td>
                    <td><?= htmlspecialchars($d['ficha']) ?></td>
                    <td><?= htmlspecialchars($d['dia']) ?></td>
                    <td><?= htmlspecialchars($d['inicio']) ?></td>
                    <td><?= htmlspecialchars($d['fin']) ?></td>
                    <td class="text-center">
                        <span class="badge badge-<?= $d['resultado'] === 'CREADA' ? 'success' : ($d['resultado'] === 'OMITIDA_DUPLICADO' ? 'warning' : 'danger') ?> px-2 py-1">
                            <?= $d['resultado'] ?>
                        </span>
                    </td>
                    <td class="small text-muted">
                        <?= htmlspecialchars($d['detalle']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <a href="<?= RUTA_BASE ?>views/admin/horarios/index.php" class="btn btn-primary">
            <i class="fas fa-list"></i> Volver al listado de horarios
        </a>
        <a href="<?= RUTA_BASE ?>views/admin/horarios/importar.php" class="btn btn-info ml-2">
            <i class="fas fa-plus"></i> Nueva importacion
        </a>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';