<?php
/**
 * ============================================================
 * VISTA: MI DASHBOARD (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Mi dashboard';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi dashboard'],
];

ob_start();
?>
<!-- Resumen de asistencia personal -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Asistencias del mes</span>
        <span class="info-box-number">18</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Retardos del mes</span>
        <span class="info-box-number">3</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Inasistencias del mes</span>
        <span class="info-box-number">2</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-file-medical"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Excusas pendientes</span>
        <span class="info-box-number">1</span>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Gráfica personal -->
  <div class="col-md-5 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie text-sena"></i> Mi asistencia del mes</h3>
      </div>
      <div class="card-body">
        <div style="height: 260px;">
          <canvas id="graficoMiAsistencia"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Estado de mis excusas -->
  <div class="col-md-7 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-medical text-sena"></i> Estado de mis excusas</h3>
        <div class="card-tools">
          <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-cloud-upload-alt"></i> Subir excusa
          </a>
        </div>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Fecha de inasistencia</th>
              <th>Fecha de envío</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($excusas as $excusa): ?>
              <?php
                $claseBadge = 'badge-secondary';
                if ($excusa['estado'] === 'Pendiente') { $claseBadge = 'badge-warning'; }
                if ($excusa['estado'] === 'Aprobada')  { $claseBadge = 'badge-success'; }
                if ($excusa['estado'] === 'Rechazada') { $claseBadge = 'badge-danger'; }
              ?>
              <tr>
                <td><?= htmlspecialchars($excusa['fecha_inasistencia']) ?></td>
                <td><?= htmlspecialchars($excusa['fecha_envio']) ?></td>
                <td><span class="badge badge-estado <?= $claseBadge ?>"><?= htmlspecialchars($excusa['estado']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Acciones rápidas del aprendiz -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-cog text-sena"></i> Acciones</h3>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-md col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/aprendiz/perfil.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-user fa-2x mb-2"></i><br>Mi perfil
        </a>
      </div>
      <div class="col-md col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/aprendiz/historial.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-history fa-2x mb-2"></i><br>Mi historial
        </a>
      </div>
      <div class="col-md col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-file-medical fa-2x mb-2"></i><br>Mis excusas
        </a>
      </div>
      <div class="col-md col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i><br>Subir excusa
        </a>
      </div>
    </div>
  </div>
</div>

<div class="alert alert-info py-2" role="alert">
  <i class="fas fa-info-circle"></i> Datos de <strong>demostración</strong>. Esta vista se conectará a la base de datos cuando se implementen los modelos y controladores.
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';
