<?php
/**
 * ============================================================
 * VISTA: DASHBOARD DEL INSTRUCTOR
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Dashboard del instructor';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Dashboard'],
];

ob_start();
?>
<!-- Tarjetas de estadísticas -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-layer-group"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Mis fichas</span>
        <span class="info-box-number">3</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena-oscuro">
      <span class="info-box-icon"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Aprendices a cargo</span>
        <span class="info-box-number">74</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Presentes hoy</span>
        <span class="info-box-number">61</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-file-medical"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Excusas pendientes</span>
        <span class="info-box-number">2</span>
      </div>
    </div>
  </div>
</div>

<!-- Gráfica y registros del día -->
<div class="row">
  <div class="col-md-7 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line text-sena"></i> Asistencia de mi semana</h3>
      </div>
      <div class="card-body">
        <div style="height: 280px;">
          <canvas id="graficoSemanaInstructor"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-5 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sign-in-alt text-sena"></i> Ingresos registrados hoy</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr><th>Hora</th><th>Aprendiz</th><th>Ficha</th></tr>
          </thead>
          <tbody>
            <?php foreach ($ultimosIngresos as $ingreso): ?>
              <tr>
                <td><span class="badge badge-success"><?= htmlspecialchars($ingreso['hora']) ?></span></td>
                <td><?= htmlspecialchars($ingreso['aprendiz']) ?></td>
                <td><?= htmlspecialchars($ingreso['ficha']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Acciones rápidas -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-cog text-sena"></i> Gestión rápida</h3>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/instructor/aprendices/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-users fa-2x mb-2"></i><br>Mis aprendices
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/instructor/asistencias/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-clipboard-check fa-2x mb-2"></i><br>Asistencias
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/instructor/excusas/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-file-medical fa-2x mb-2"></i><br>Excusas
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/reportes/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-chart-bar fa-2x mb-2"></i><br>Reportes
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
include __DIR__ . '/../layouts/layout_instructor.php';