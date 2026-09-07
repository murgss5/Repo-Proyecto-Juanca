<?php
/**
 * Vista: Dashboard del aprendiz.
 * Resume las asistencias y excusas reales del aprendiz autenticado.
 */

// Carga la autenticacion y verifica que sea aprendiz
require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);
// Carga constantes y conexion a la base de datos
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

// Variables del layout
$paginaTitulo = 'Mi dashboard';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi dashboard'],
];

$conexion = (new Conexion())->conectar();

// Consulta resumen del mes actual
$consultaResumen = $conexion->prepare(
    "SELECT
        SUM(CASE WHEN asistencia.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
        SUM(CASE WHEN asistencia.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos,
        SUM(CASE WHEN asistencia.estado_entrada = 'INASISTENCIA' THEN 1 ELSE 0 END) AS inasistencias,
        SUM(CASE WHEN asistencia.estado_salida = 'SALIDA_ANTICIPADA' THEN 1 ELSE 0 END) AS salidas_tempranas
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
       AND YEAR(asistencia.fecha_asistencia) = YEAR(CURDATE())
       AND MONTH(asistencia.fecha_asistencia) = MONTH(CURDATE())"
);
$consultaResumen->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaResumen->execute();
$resumenMes = $consultaResumen->fetch() ?: [];

// Datos para la grafica
$datosGraficaAprendiz = [
    (int) ($resumenMes['puntuales'] ?? 0),
    (int) ($resumenMes['retardos'] ?? 0),
    (int) ($resumenMes['inasistencias'] ?? 0),
    (int) ($resumenMes['salidas_tempranas'] ?? 0),
];

// Consulta las ultimas 5 excusas del aprendiz
$consultaExcusas = $conexion->prepare(
    "SELECT asistencia.fecha_asistencia, excusa.fecha_subida, excusa.estado
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
     ORDER BY excusa.fecha_subida DESC, excusa.id_excusa DESC
     LIMIT 5"
);
$consultaExcusas->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaExcusas->execute();
$excusasAprendiz = $consultaExcusas->fetchAll();

// Consulta el total de excusas pendientes
$consultaExcusasPendientes = $conexion->prepare(
    "SELECT COUNT(*)
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
       AND excusa.estado = 'PENDIENTE'"
);
$consultaExcusasPendientes->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaExcusasPendientes->execute();
$excusasPendientes = (int) $consultaExcusasPendientes->fetchColumn();

ob_start();
?>
<!-- aqui esta el resuemen personal de cada aprendiz -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Asistencias del mes</span>
        <span class="info-box-number"><?= $datosGraficaAprendiz[0] ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Retardos del mes</span>
        <span class="info-box-number"><?= $datosGraficaAprendiz[1] ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Inasistencias del mes</span>
        <span class="info-box-number"><?= $datosGraficaAprendiz[2] ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-file-medical"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Excusas pendientes</span>
        <span class="info-box-number"><?= $excusasPendientes ?></span>
      </div>
    </div>
  </div>
</div>

<div class="row">

<!-- esta es la grafica de cada aprendiz -->
  <div class="col-md-5 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-pie text-sena"></i> Mi asistencia del mes</h3>
      </div>
      <div class="card-body">
        <div style="height: 260px;">
          <script>window.datosGraficaAprendiz = <?= json_encode($datosGraficaAprendiz) ?>;</script>
          <canvas id="graficoMiAsistencia"></canvas>
        </div>
      </div>
    </div>
  </div>

<!-- estado de excusas de cada aprendiz -->
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
            <?php foreach ($excusasAprendiz as $excusa): ?>
              <?php
                $claseBadge = 'badge-secondary';
                if ($excusa['estado'] === 'PENDIENTE') { $claseBadge = 'badge-warning'; }
                if ($excusa['estado'] === 'APROBADA')  { $claseBadge = 'badge-success'; }
                if ($excusa['estado'] === 'RECHAZADA') { $claseBadge = 'badge-danger'; }
              ?>
              <tr>
                <td><?= htmlspecialchars($excusa['fecha_asistencia']) ?></td>
                <td><?= htmlspecialchars($excusa['fecha_subida']) ?></td>
                <td><span class="badge badge-estado <?= $claseBadge ?>"><?= htmlspecialchars(ucfirst(strtolower($excusa['estado']))) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- aqui estan las acciones que puede realizar el aprendiz -->
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


<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';
