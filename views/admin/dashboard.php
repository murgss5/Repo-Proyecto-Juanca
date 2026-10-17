<?php
/**
 * ============================================================
 * VISTA: DASHBOARD DEL ADMINISTRADOR
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Dashboard con datos reales de la base de datos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

// --- Variables del layout ---
$paginaTitulo = 'Dashboard';
$menuActivo = 'dashboard';
$miga = [
  ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
  ['label' => 'Dashboard'],
];

$conexion = (new Conexion())->conectar();

$totalAprendices = (int) $conexion->query('SELECT COUNT(*) FROM aprendiz')->fetchColumn();
$totalFichas = (int) $conexion->query('SELECT COUNT(*) FROM ficha')->fetchColumn();
$excusasPendientes = (int) $conexion->query("SELECT COUNT(*) FROM excusa WHERE estado = 'PENDIENTE'")->fetchColumn();

$consultaHoy = $conexion->query(
  "SELECT
        SUM(CASE WHEN estado_entrada IN ('PUNTUAL', 'RETARDO') THEN 1 ELSE 0 END) AS presentes,
        SUM(CASE WHEN estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
        SUM(CASE WHEN estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
        SUM(CASE WHEN estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_tempranas
     FROM asistencia WHERE fecha_asistencia = CURDATE()"
)->fetch();
$presentesHoy = (int) ($consultaHoy['presentes'] ?? 0);
$retardosHoy = (int) ($consultaHoy['retardos'] ?? 0);
$inasistenciasHoy = (int) ($consultaHoy['inasistencias'] ?? 0);

$graficaSemana = [
  'labels' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
  'presentes' => array_fill(0, 6, 0),
  'retardos' => array_fill(0, 6, 0),
  'inasistencias' => array_fill(0, 6, 0),
];
$consultaSemana = $conexion->query(
  "SELECT
        WEEKDAY(fecha_asistencia) AS dia,
        SUM(CASE WHEN estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS presentes,
        SUM(CASE WHEN estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
        SUM(CASE WHEN estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias
     FROM asistencia
     WHERE fecha_asistencia >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
       AND fecha_asistencia < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
     GROUP BY WEEKDAY(fecha_asistencia)"
);
foreach ($consultaSemana as $dia) {
  $indice = (int) $dia['dia'];
  $graficaSemana['presentes'][$indice] = (int) $dia['presentes'];
  $graficaSemana['retardos'][$indice] = (int) $dia['retardos'];
  $graficaSemana['inasistencias'][$indice] = (int) $dia['inasistencias'];
}

$consultaIngresos = $conexion->query(
  "SELECT DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
        ficha.id_ficha, aprendiz.codigo_rfid
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE asistencia.fecha_asistencia = CURDATE() AND asistencia.entrada IS NOT NULL
     ORDER BY asistencia.entrada DESC LIMIT 5"
);
$ultimosIngresos = $consultaIngresos->fetchAll();

$consultaRetardos = $conexion->query(
  "SELECT DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
        ficha.id_ficha, asistencia.minutos_retardo
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE asistencia.estado_entrada = 'RETARDO'
     ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC LIMIT 5"
);
$retardosRecientes = $consultaRetardos->fetchAll();

$mesesEspanol = [
  1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
  5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
  9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$mesActual = $mesesEspanol[(int) date('n')] . ' ' . date('Y');

// --- Captura del contenido de la vista ---
ob_start();
?>
<!-- ==================== TARJETAS DE ESTADÍSTICAS (KPIs) ==================== -->
<div class="row">
  <!-- Total de Aprendices -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-success hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">TOTAL APRENDICES</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalAprendices) ?></h2>
            <small class="text-muted"><i class="fas fa-info-circle mr-1 text-success"></i> Registrados en sistema</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #bbf7d0; color: #166534; font-size: 0.72rem;"><?= $mesActual ?></span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-users"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Retardos del Día -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-warning hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">RETARDOS DEL DÍA</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($retardosHoy) ?></h2>
            <small class="text-muted"><i class="fas fa-info-circle mr-1 text-warning"></i> Ingresos fuera de horario</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #fef08a; color: #854d0e; font-size: 0.72rem;"><?= $mesActual ?></span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-clock"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Inasistencias del Día -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-danger hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">INASISTENCIAS DEL DÍA</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($inasistenciasHoy) ?></h2>
            <small class="text-muted"><i class="fas fa-info-circle mr-1 text-danger"></i> Sin registro de entrada</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #fecaca; color: #991b1b; font-size: 0.72rem;"><?= $mesActual ?></span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-user-slash"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Excusas Pendientes -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-info hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">EXCUSAS PENDIENTES</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($excusasPendientes) ?></h2>
            <small class="text-muted"><i class="fas fa-info-circle mr-1 text-primary"></i> Pendientes de revisión</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #bfdbfe; color: #1e40af; font-size: 0.72rem;"><?= $mesActual ?></span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-file-medical"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== GRÁFICAS ==================== -->
<div class="row">
  <div class="col-lg-7 col-md-12 mb-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
        <h5 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-chart-bar text-muted mr-2"></i> Asistencia de la semana
        </h5>
      </div>
      <div class="card-body p-4">
        <div style="height: 300px; position: relative;">
          <script>
            window.datosGraficaAdminSemana = <?= json_encode($graficaSemana) ?>;
            window.datosGraficaAdminHoy = <?= json_encode([$presentesHoy, $retardosHoy, $inasistenciasHoy, (int) ($consultaHoy['salidas_tempranas'] ?? 0)]) ?>;
          </script>
          <canvas id="graficoAsistenciaSemanal"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5 col-md-12 mb-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
        <h5 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-chart-pie text-muted mr-2"></i> Asistencia de hoy
        </h5>
      </div>
      <div class="card-body p-4">
        <div style="height: 300px; position: relative;">
          <canvas id="graficoAsistenciaHoy"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== TABLAS RECIENTES ==================== -->
<div class="row">
  <!-- Últimos ingresos -->
  <div class="col-lg-6 col-md-12 mb-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
        <h5 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-sign-in-alt text-muted mr-2"></i> Últimos ingresos
        </h5>
        <a href="<?= RUTA_BASE ?>views/admin/asistencias/index.php" class="btn btn-sm btn-link text-sena font-weight-bold p-0">Ver todos</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-borderless table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th class="font-weight-bold text-muted small text-uppercase">Hora</th>
                <th class="font-weight-bold text-muted small text-uppercase">Aprendiz</th>
                <th class="font-weight-bold text-muted small text-uppercase">Ficha</th>
                <th class="font-weight-bold text-muted small text-uppercase">RFID</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ultimosIngresos)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">No hay ingresos registrados el día de hoy</td>
                </tr>
              <?php else: ?>
                <?php foreach ($ultimosIngresos as $ingreso): ?>
                  <tr>
                    <td><span class="badge badge-pill badge-success px-2 py-1"><?= htmlspecialchars($ingreso['hora']) ?></span></td>
                    <td class="font-weight-500 text-dark"><?= htmlspecialchars($ingreso['aprendiz']) ?></td>
                    <td class="text-muted">Ficha #<?= htmlspecialchars($ingreso['id_ficha']) ?></td>
                    <td><code class="text-muted bg-light px-2 py-1 rounded"><?= htmlspecialchars($ingreso['codigo_rfid']) ?></code></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Retardos recientes -->
  <div class="col-lg-6 col-md-12 mb-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
        <h5 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-exclamation-triangle text-muted mr-2"></i> Retardos recientes
        </h5>
        <a href="<?= RUTA_BASE ?>views/admin/asistencias/index.php" class="btn btn-sm btn-link text-sena font-weight-bold p-0">Ver todos</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-borderless table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th class="font-weight-bold text-muted small text-uppercase">Hora</th>
                <th class="font-weight-bold text-muted small text-uppercase">Aprendiz</th>
                <th class="font-weight-bold text-muted small text-uppercase">Ficha</th>
                <th class="font-weight-bold text-muted small text-uppercase text-right">Min. retardo</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($retardosRecientes)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">No se registran retardos recientes</td>
                </tr>
              <?php else: ?>
                <?php foreach ($retardosRecientes as $retardo): ?>
                  <tr>
                    <td class="text-muted"><?= htmlspecialchars($retardo['hora']) ?></td>
                    <td class="font-weight-500 text-dark"><?= htmlspecialchars($retardo['aprendiz']) ?></td>
                    <td class="text-muted">Ficha #<?= htmlspecialchars($retardo['id_ficha']) ?></td>
                    <td class="text-right">
                      <span class="badge badge-pill badge-warning px-2 py-1 font-weight-bold"><?= htmlspecialchars($retardo['minutos_retardo']) ?> min</span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_admin.php';
