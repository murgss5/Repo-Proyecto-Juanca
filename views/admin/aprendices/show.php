<?php
/**
 * ============================================================
 * VISTA: DETALLE DEL APRENDIZ (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. Los datos son de demostración.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/constantes.php';

// Aprendiz de demostración
$aprendizDemo = [
    'id'        => 1,
    'documento' => '1002345678',
    'nombre'    => 'Juan David Pérez',
    'correo'    => 'jdperez@misena.edu.co',
    'telefono'  => '3001112233',
    'ficha'     => '2693854',
    'programa'  => 'Tecnólogo en Análisis y Desarrollo de Software',
    'jornada'   => 'Diurna',
    'rfid'      => 'RF-2026-001',
    'estado'    => 'Activo',
    'ingresado' => '2026-02-10',
];

// --- Variables del layout ---
$paginaTitulo = 'Detalle del aprendiz';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php'],
    ['label' => 'Detalle'],
];

ob_start();
?>
<div class="row">
  <!-- Tarjeta de perfil -->
  <div class="col-md-4 mb-3">
    <div class="card">
      <div class="card-body text-center">
        <img src="<?= RUTA_BASE ?>assets/img/avatar.png" class="avatar-grande img-circle mb-3" alt="Foto del aprendiz">
        <h4><?= htmlspecialchars($aprendizDemo['nombre']) ?></h4>
        <p class="text-muted mb-2"><?= htmlspecialchars($aprendizDemo['programa']) ?></p>
        <span class="badge badge-estado badge-success mb-3"><?= htmlspecialchars($aprendizDemo['estado']) ?></span>
        <hr>
        <p class="mb-1"><i class="fas fa-id-card text-sena"></i> <strong>Documento:</strong> <?= htmlspecialchars($aprendizDemo['documento']) ?></p>
        <p class="mb-1"><i class="fas fa-rss text-sena"></i> <strong>Código RFID:</strong> <code><?= htmlspecialchars($aprendizDemo['rfid']) ?></code></p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Ficha:</strong> <?= htmlspecialchars($aprendizDemo['ficha']) ?></p>
        <p class="mb-1"><i class="far fa-calendar-alt text-sena"></i> <strong>Ingresó:</strong> <?= htmlspecialchars($aprendizDemo['ingresado']) ?></p>
        <div class="mt-3">
          <a href="<?= RUTA_BASE ?>views/admin/aprendices/edit.php?id=<?= $aprendizDemo['id'] ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-edit"></i> Editar
          </a>
          <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Información adicional -->
  <div class="col-md-8 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-info-circle text-sena"></i> Información de contacto</h3>
      </div>
      <div class="card-body">
        <table class="table table-bordered">
          <tr><th style="width: 200px;">Correo electrónico</th><td><?= htmlspecialchars($aprendizDemo['correo']) ?></td></tr>
          <tr><th>Teléfono</th><td><?= htmlspecialchars($aprendizDemo['telefono']) ?></td></tr>
          <tr><th>Jornada</th><td><?= htmlspecialchars($aprendizDemo['jornada']) ?></td></tr>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clipboard-check text-sena"></i> Asistencia reciente</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Fecha</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr>
          </thead>
          <tbody>
            <?php foreach ($asistencias as $asistencia): ?>
              <?php if ($asistencia['aprendiz'] !== 'Juan David Pérez') { continue; } ?>
              <tr>
                <td><?= htmlspecialchars($asistencia['fecha']) ?></td>
                <td><?= htmlspecialchars($asistencia['entrada']) ?></td>
                <td><?= htmlspecialchars($asistencia['salida']) ?></td>
                <td>
                  <?php
                    $claseBadge = 'badge-secondary';
                    if ($asistencia['estado'] === 'Presente')       { $claseBadge = 'badge-success'; }
                    if ($asistencia['estado'] === 'Retardo')        { $claseBadge = 'badge-warning'; }
                    if ($asistencia['estado'] === 'Inasistencia')   { $claseBadge = 'badge-danger'; }
                    if ($asistencia['estado'] === 'Salida temprana'){ $claseBadge = 'badge-info'; }
                  ?>
                  <span class="badge badge-estado <?= $claseBadge ?>"><?= htmlspecialchars($asistencia['estado']) ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';