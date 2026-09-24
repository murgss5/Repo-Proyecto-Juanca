<?php
/**
 * vista: previsualizacion de importacion de horarios (fase 2)
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// validar que existe preview en sesion
if (empty($_SESSION['import_preview'])) {
    header('Location: ' . RUTA_BASE . 'views/admin/horarios/importar.php?error=No hay datos de previsualizacion. Suba un archivo primero.');
    exit;
}

$preview = $_SESSION['import_preview'];
$filas = $preview['filas'] ?? [];
$resumen = $preview['resumen'] ?? [];

// token csrf por si se usa en fase 3
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

$paginaTitulo = 'Previsualizacion de importacion';
$menuActivo   = 'horarios';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Horarios', 'url' => RUTA_BASE . 'views/admin/horarios/index.php'],
    ['label' => 'Importar Excel', 'url' => RUTA_BASE . 'views/admin/horarios/importar.php'],
    ['label' => 'Previsualizacion']
];

ob_start();
?>

<!-- mensaje de fase -->
<div class="alert alert-warning">
    <h5><i class="fas fa-exclamation-triangle"></i> Fase 2: Previsualizacion solamente</h5>
    <p class="mb-0">
        <strong>Esta pantalla no guarda horarios.</strong> La confirmacion y guardado se implementaran en la Fase 3.
        Revise el estado de cada fila. Las filas con ERROR no se pueden importar.
    </p>
</div>

<!-- resumen -->
<div class="row mb-3">
    <div class="col-md-3">
        <div class="info-box bg-light border">
            <div class="info-box-content">
                <span class="info-box-text">Filas leidas</span>
                <span class="info-box-number"><?= $resumen['total_filas'] ?? 0 ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-light border">
            <div class="info-box-content">
                <span class="info-box-text">Procesadas</span>
                <span class="info-box-number"><?= $resumen['filas_procesadas'] ?? 0 ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-light border">
            <div class="info-box-content">
                <span class="info-box-text">Vacias ignoradas</span>
                <span class="info-box-number"><?= $resumen['filas_vacias'] ?? 0 ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="info-box bg-success text-white">
            <div class="info-box-content">
                <span class="info-box-text">Validas</span>
                <span class="info-box-number"><?= $resumen['validas'] ?? 0 ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-warning text-dark">
            <div class="info-box-content">
                <span class="info-box-text">Advertencias</span>
                <span class="info-box-number"><?= $resumen['advertencias'] ?? 0 ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-info text-white">
            <div class="info-box-content">
                <span class="info-box-text">Pendientes ficha</span>
                <span class="info-box-number"><?= $resumen['pendientes'] ?? 0 ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="info-box bg-danger text-white">
            <div class="info-box-content">
                <span class="info-box-text">Errores</span>
                <span class="info-box-number"><?= $resumen['errores'] ?? 0 ?></span>
            </div>
        </div>
    </div>
</div>

<!-- tabla -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Detalle de filas</h3>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-hover table-bordered tabla-datos">
            <thead class="thead-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Instructor</th>
                    <th>Ficha</th>
                    <th>Dia</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Ambiente</th>
                    <th style="width: 100px;">Accion</th>
                    <th style="width: 100px;">Estado</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                <tr class="<?= $f['estado'] === 'ERROR' ? 'table-danger' : ($f['estado'] === 'ADVERTENCIA' ? 'table-warning' : ($f['estado'] === 'PENDIENTE_FICHA' ? 'table-info' : '')) ?>">
                    <td><?= $f['fila'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($f['instructor_nombre'] ?: '—') ?></strong><br>
                        <small class="text-muted">ID: <?= htmlspecialchars($f['identificacion_instructor']) ?></small>
                    </td>
                    <td>
                        <?php if ($f['pendiente_ficha']): ?>
                            <select class="form-control form-control-sm" disabled>
                                <option value="">Seleccione ficha...</option>
                                <?php foreach ($f['fichas_disponibles'] as $fd): ?>
                                    <option value="<?= $fd['id_ficha'] ?>">#<?= $fd['id_ficha'] ?> - <?= htmlspecialchars($fd['nombre_programa']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted d-block">PENDIENTE: seleccione ficha del instructor</small>
                        <?php elseif ($f['ficha_id']): ?>
                            <span class="badge badge-info">#<?= $f['ficha_id'] ?></span>
                            <br><small><?= htmlspecialchars($f['ficha_nombre']) ?></small>
                        <?php else: ?>
                            <span class="text-danger">Sin ficha</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($f['dia'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['hora_inicio'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($f['hora_fin'] ?? '—') ?></td>
                    <td>
                        <?= htmlspecialchars($f['ambiente'] ?? '—') ?>
                        <?php if (!empty($f['ambiente']) && strpos($f['detalle'], 'ambiente_principal aun no esta configurado') !== false): ?>
                            <span class="badge badge-warning ml-1">Advertencia</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($f['seleccionable']): ?>
                            <input type="checkbox" class="fila-check" checked disabled title="Seleccionable en Fase 3">
                        <?php else: ?>
                            <input type="checkbox" class="fila-check" disabled title="No seleccionable (ERROR)">
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-<?= $f['estado'] === 'VALIDA' ? 'success' : ($f['estado'] === 'ADVERTENCIA' ? 'warning' : ($f['estado'] === 'PENDIENTE_FICHA' ? 'info' : 'danger')) ?> px-2 py-1">
                            <?= $f['estado'] ?>
                        </span>
                    </td>
                    <td class="small text-muted" style="max-width: 300px;">
                        <?= htmlspecialchars($f['detalle'] ?: '—') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <a href="<?= RUTA_BASE ?>views/admin/horarios/importar.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a corregir
        </a>
        <button type="button" class="btn btn-primary ml-2" disabled>
            <i class="fas fa-save"></i> Confirmar importacion (Fase 3)
        </button>
        <span class="text-muted small ml-3">
            El boton estara habilitado en la Fase 3 cuando se implemente la confirmacion con transaccion.
        </span>
    </div>
</div>

<?php
$contenido = ob_get_clean();
include __DIR__ . '/../../layouts/layout_admin.php';