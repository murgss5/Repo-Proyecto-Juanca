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
$menuActivo   = 'dashboard';
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

// --- Captura del contenido de la vista ---
ob_start();
?>
<!-- ==================== TARJETAS DE ESTADÍSTICAS ==================== -->
<div class="row">
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Total de aprendices</span>
        <span class="info-box-number"><?= $totalAprendices ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena-oscuro">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Aprendices presentes hoy</span>
        <span class="info-box-number"><?= $presentesHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Retardos del día</span>
        <span class="info-box-number"><?= $retardosHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Inasistencias del día</span>
        <span class="info-box-number"><?= $inasistenciasHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-file-medical"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Excusas pendientes</span>
        <span class="info-box-number"><?= $excusasPendientes ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-4 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-gris">
      <span class="info-box-icon"><i class="fas fa-layer-group"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Fichas registradas</span>
        <span class="info-box-number"><?= $totalFichas ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ==================== GRÁFICAS ==================== -->
<div class="row">
  <div class="col-md-7 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-bar text-sena"></i> Asistencia de la semana</h3>
      </div>
      <div class="card-body">
        <div style="height: 300px;">
          <script>
            window.datosGraficaAdminSemana = <?= json_encode($graficaSemana) ?>;
            window.datosGraficaAdminHoy = <?= json_encode([$presentesHoy, $retardosHoy, $inasistenciasHoy, (int) ($consultaHoy['salidas_tempranas'] ?? 0)]) ?>;
          </script>
          <canvas id="graficoAsistenciaSemanal"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-5 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie text-sena"></i> Asistencia de hoy</h3>
      </div>
      <div class="card-body">
        <div style="height: 300px;">
          <canvas id="graficoAsistenciaHoy"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== TABLAS RECIENTES ==================== -->
<div class="row">
  <div class="col-md-6 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-sign-in-alt text-sena"></i> Últimos ingresos</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Aprendiz</th>
              <th>Ficha</th>
              <th>RFID</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ultimosIngresos as $ingreso): ?>
              <tr>
                <td><span class="badge badge-success"><?= htmlspecialchars($ingreso['hora']) ?></span></td>
                <td><?= htmlspecialchars($ingreso['aprendiz']) ?></td>
                <td>Ficha #<?= htmlspecialchars($ingreso['id_ficha']) ?></td>
                <td><code><?= htmlspecialchars($ingreso['codigo_rfid']) ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-6 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-exclamation-triangle text-warning"></i> Retardos recientes</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Aprendiz</th>
              <th>Ficha</th>
              <th>Min. retardo</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($retardosRecientes as $retardo): ?>
              <tr>
                <td><?= htmlspecialchars($retardo['hora']) ?></td>
                <td><?= htmlspecialchars($retardo['aprendiz']) ?></td>
                <td>Ficha #<?= htmlspecialchars($retardo['id_ficha']) ?></td>
                <td><span class="badge badge-warning"><?= htmlspecialchars($retardo['minutos_retardo']) ?> min</span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ==================== ACCIONES RÁPIDAS ==================== -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-cog text-sena"></i> Administración rápida</h3>
  </div>
  <div class="card-body">
    <div class="row">
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-users fa-2x mb-2"></i><br>Aprendices
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/fichas/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-layer-group fa-2x mb-2"></i><br>Fichas
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/asistencias/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-clipboard-check fa-2x mb-2"></i><br>Asistencias
        </a>
      </div>
      <div class="col-lg col-md-4 col-sm-6 mb-3">
        <a href="<?= RUTA_BASE ?>views/admin/excusas/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
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
  <i class="fas fa-info-circle"></i> Todas las métricas, gráficas y listados de este dashboard provienen de la base de datos.
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_admin.php';
