<?php
/**
 * ============================================================
 * VISTA: DASHBOARD DEL APRENDIZ
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Resumen de indicadores de asistencia del mes, excusas y
 * accesos directos del aprendiz autenticado.
 * ============================================================
 */

// Carga la autenticación y verifica que sea aprendiz (rol 3)
require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);

// Carga constantes y conexión a la base de datos
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

asegurarSesionIniciada();

// Variables del layout
$paginaTitulo = 'Mi dashboard';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi dashboard'],
];

$conexion = (new Conexion())->conectar();
$idUsuario = (int)$_SESSION['id_usuario'];

// 1. Consulta información académica básica del aprendiz (programa y ficha)
$consultaDatosAprendiz = $conexion->prepare(
    "SELECT aprendiz.id_aprendiz, ficha.id_ficha, ficha.nombre_programa
     FROM aprendiz
     LEFT JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE aprendiz.fk_usuario = :id_usuario
     LIMIT 1"
);
$consultaDatosAprendiz->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
$consultaDatosAprendiz->execute();
$datosAprendiz = $consultaDatosAprendiz->fetch(PDO::FETCH_ASSOC) ?: [];

// 2. Consulta resumen de asistencia del mes actual
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
$consultaResumen->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
$consultaResumen->execute();
$resumenMes = $consultaResumen->fetch(PDO::FETCH_ASSOC) ?: [];

$puntualesMes        = (int)($resumenMes['puntuales'] ?? 0);
$retardosMes         = (int)($resumenMes['retardos'] ?? 0);
$inasistenciasMes    = (int)($resumenMes['inasistencias'] ?? 0);
$salidasTempranasMes = (int)($resumenMes['salidas_tempranas'] ?? 0);
$totalRegistrosMes   = $puntualesMes + $retardosMes + $inasistenciasMes + $salidasTempranasMes;

// Datos exportados para compatibilidad
$datosGraficaAprendiz = [
    $puntualesMes,
    $retardosMes,
    $inasistenciasMes,
    $salidasTempranasMes,
];

// 3. Consulta las últimas 5 excusas registradas del aprendiz
$consultaExcusas = $conexion->prepare(
    "SELECT asistencia.fecha_asistencia, excusa.fecha_subida, excusa.estado
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
     ORDER BY excusa.fecha_subida DESC, excusa.id_excusa DESC
     LIMIT 5"
);
$consultaExcusas->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
$consultaExcusas->execute();
$excusasAprendiz = $consultaExcusas->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 4. Consulta el total global de excusas pendientes (alcance real del sistema)
$consultaExcusasPendientes = $conexion->prepare(
    "SELECT COUNT(*)
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
       AND excusa.estado = 'PENDIENTE'"
);
$consultaExcusasPendientes->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
$consultaExcusasPendientes->execute();
$excusasPendientes = (int)$consultaExcusasPendientes->fetchColumn();

// 5. Consulta estado de invitación de cambio de contraseña
$stmtInvitacion = $conexion->prepare(
    "SELECT estado_invitacion_clave FROM usuario WHERE id_usuario = :id AND fk_rol = 3 LIMIT 1"
);
$stmtInvitacion->execute([':id' => $idUsuario]);
$estadoInvitacion = $stmtInvitacion->fetchColumn() ?: 'MANTENIDA';
$mostrarInvitacionClave = ($estadoInvitacion === 'PENDIENTE');

// Formato en español del mes actual
$nombresMeses = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
];
$mesActualNum = (int)date('n');
$anioActual   = (int)date('Y');
$nombreMesActual = ucfirst($nombresMeses[$mesActualNum] ?? date('F')) . ' ' . $anioActual;

// Nombre para el saludo
$nombreSaludo = trim($_SESSION['nombre'] ?? '');
if ($nombreSaludo === '') {
    $nombreSaludo = $_SESSION['nombre_usuario'] ?? 'Aprendiz';
}

ob_start();
?>

<!-- Estilos específicos del Dashboard Aprendiz -->
<style>
/* Reglas para Modo Claro (por defecto) */
body:not(.dark-mode) .dash-apr-card {
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 0.5rem;
  background-color: #ffffff;
}

body:not(.dark-mode) .dash-apr-kpi {
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-top-width: 4px !important;
  border-radius: 0.5rem;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
body:not(.dark-mode) .dash-apr-kpi:hover {
  box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.06);
}

/* 1. Retardos del mes (Claro): Ámbar (#D97706, fondo #FFFBEB, círculo #FEF3C7, icono ámbar oscuro #92400E) */
body:not(.dark-mode) .dash-apr-kpi-retardos {
  background-color: #FFFBEB !important;
  border-top-color: #D97706 !important;
}
body:not(.dark-mode) .dash-apr-kpi-retardos .dash-apr-icon-bubble {
  background-color: #FEF3C7 !important;
  color: #92400E !important;
}
body:not(.dark-mode) .dash-apr-kpi-retardos .dash-apr-badge {
  background-color: #FEF3C7;
  color: #92400E;
  border: 1px solid #FDE68A;
}

/* 2. Inasistencias del mes (Claro): Rojo (#DC2626, fondo #FEF2F2, círculo #FEE2E2, icono rojo oscuro #991B1B) */
body:not(.dark-mode) .dash-apr-kpi-inasistencias {
  background-color: #FEF2F2 !important;
  border-top-color: #DC2626 !important;
}
body:not(.dark-mode) .dash-apr-kpi-inasistencias .dash-apr-icon-bubble {
  background-color: #FEE2E2 !important;
  color: #991B1B !important;
}
body:not(.dark-mode) .dash-apr-kpi-inasistencias .dash-apr-badge {
  background-color: #FEE2E2;
  color: #991B1B;
  border: 1px solid #FECACA;
}

/* 3. Excusas pendientes (Claro): Azul (#2563EB, fondo #EFF6FF, círculo #DBEAFE, icono azul oscuro #1E40AF) */
body:not(.dark-mode) .dash-apr-kpi-excusas {
  background-color: #EFF6FF !important;
  border-top-color: #2563EB !important;
}
body:not(.dark-mode) .dash-apr-kpi-excusas .dash-apr-icon-bubble {
  background-color: #DBEAFE !important;
  color: #1E40AF !important;
}
body:not(.dark-mode) .dash-apr-kpi-excusas .dash-apr-badge {
  background-color: #DBEAFE;
  color: #1E40AF;
  border: 1px solid #BFDBFE;
}

body:not(.dark-mode) .dash-apr-kpi-title {
  color: #374151;
  letter-spacing: 0.025em;
}
body:not(.dark-mode) .dash-apr-kpi-desc {
  color: #4B5563;
}

body:not(.dark-mode) .dash-apr-quick-btn {
  display: flex;
  align-items: center;
  padding: 0.9rem 1.1rem;
  background-color: #ffffff;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 0.5rem;
  color: #212529;
  text-decoration: none !important;
  transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
body:not(.dark-mode) .dash-apr-quick-btn:hover {
  background-color: #f8fdf6;
  border-color: #39a900;
  color: #39a900;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

/* Propiedades comunes */
.dash-apr-badge {
  font-size: 0.75rem;
  padding: 0.35em 0.65em;
  border-radius: 0.25rem;
  font-weight: 500;
}
.dash-apr-icon-bubble {
  width: 52px;
  height: 52px;
  min-width: 52px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dash-apr-quick-btn:focus-visible {
  outline: 2px solid #39a900;
  outline-offset: 2px;
}

/* Reglas explícitas para Modo Oscuro (body.dark-mode) */
body.dark-mode .dash-apr-card {
  border: 1px solid #3B475B !important;
  border-radius: 0.5rem;
  background-color: #1F2937 !important;
}

body.dark-mode .dash-apr-kpi {
  background-color: #1F2937 !important;
  border: 1px solid #3B475B !important;
  border-top-width: 4px !important;
  border-radius: 0.5rem;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
body.dark-mode .dash-apr-kpi:hover {
  box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.4);
}

/* KPI Retardos (Oscuro): Superficie #1F2937, borde ámbar #D97706, icono #FDE68A sobre fondo suave */
body.dark-mode .dash-apr-kpi-retardos {
  background-color: #1F2937 !important;
  border-top-color: #D97706 !important;
}
body.dark-mode .dash-apr-kpi-retardos .dash-apr-icon-bubble {
  background-color: rgba(217, 119, 6, 0.2) !important;
  color: #FDE68A !important;
}
body.dark-mode .dash-apr-kpi-retardos .dash-apr-badge {
  background-color: rgba(217, 119, 6, 0.2) !important;
  color: #FDE68A !important;
  border: 1px solid rgba(217, 119, 6, 0.4) !important;
}

/* KPI Inasistencias (Oscuro): Superficie #1F2937, borde rojo #DC2626, icono #FCA5A5 sobre fondo suave */
body.dark-mode .dash-apr-kpi-inasistencias {
  background-color: #1F2937 !important;
  border-top-color: #DC2626 !important;
}
body.dark-mode .dash-apr-kpi-inasistencias .dash-apr-icon-bubble {
  background-color: rgba(220, 38, 38, 0.2) !important;
  color: #FCA5A5 !important;
}
body.dark-mode .dash-apr-kpi-inasistencias .dash-apr-badge {
  background-color: rgba(220, 38, 38, 0.2) !important;
  color: #FCA5A5 !important;
  border: 1px solid rgba(220, 38, 38, 0.4) !important;
}

/* KPI Excusas (Oscuro): Superficie #1F2937, borde azul #2563EB, icono #93C5FD sobre fondo suave */
body.dark-mode .dash-apr-kpi-excusas {
  background-color: #1F2937 !important;
  border-top-color: #2563EB !important;
}
body.dark-mode .dash-apr-kpi-excusas .dash-apr-icon-bubble {
  background-color: rgba(37, 99, 235, 0.2) !important;
  color: #93C5FD !important;
}
body.dark-mode .dash-apr-kpi-excusas .dash-apr-badge {
  background-color: rgba(37, 99, 235, 0.2) !important;
  color: #93C5FD !important;
  border: 1px solid rgba(37, 99, 235, 0.4) !important;
}

body.dark-mode .dash-apr-kpi-title {
  color: #CBD5E1 !important;
  letter-spacing: 0.025em;
}
body.dark-mode .dash-apr-kpi-desc {
  color: #A8B5C7 !important;
}

/* Botones de acceso rápido (Oscuro) */
body.dark-mode .dash-apr-quick-btn {
  display: flex;
  align-items: center;
  padding: 0.9rem 1.1rem;
  background-color: #1F2937 !important;
  border: 1px solid #3B475B !important;
  border-radius: 0.5rem;
  color: #F1F5F9 !important;
  text-decoration: none !important;
  transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
}
body.dark-mode .dash-apr-quick-btn:hover {
  background-color: #293548 !important;
  border-color: #39a900 !important;
  color: #86efac !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

@media (prefers-reduced-motion: reduce) {
  .dash-apr-kpi,
  .dash-apr-quick-btn {
    transition: none;
  }
}
</style>

<!-- 1. Encabezado de bienvenida -->
<div class="card card-outline card-success mb-4 shadow-sm dash-apr-card">
  <div class="card-body p-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
      <div class="mb-3 mb-md-0">
        <div class="d-flex align-items-center mb-1">
          <span class="badge badge-success px-2 py-1 mr-2"><i class="fas fa-check-circle mr-1"></i> Aprendiz</span>
          <span class="text-muted small"><i class="fas fa-calendar-alt mr-1"></i> <?= htmlspecialchars($nombreMesActual) ?></span>
        </div>
        <h2 class="h4 font-weight-bold mb-1 text-dark text-break">
          ¡Hola, <?= htmlspecialchars($nombreSaludo) ?>!
        </h2>
        <p class="text-muted mb-0">Consulta tu asistencia y gestiona tus excusas</p>
        <?php if (!empty($datosAprendiz['nombre_programa'])): ?>
          <div class="mt-2 text-muted small d-flex flex-wrap align-items-center">
            <span class="mr-2"><i class="fas fa-book-reader mr-1 text-sena"></i> <span class="font-weight-medium text-dark text-break"><?= htmlspecialchars($datosAprendiz['nombre_programa']) ?></span></span>
            <?php if (!empty($datosAprendiz['id_ficha'])): ?>
              <span class="badge badge-light border text-monospace">Ficha #<?= htmlspecialchars((string)$datosAprendiz['id_ficha']) ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="text-md-right">
        <a href="<?= RUTA_BASE ?>views/aprendiz/perfil.php" class="btn btn-sm btn-outline-success">
          <i class="fas fa-user-circle mr-1"></i> Mi perfil
        </a>
      </div>
    </div>
  </div>
</div>

<!-- 2. Fila superior de 3 tarjetas de indicadores (Retardos, Inasistencias, Excusas pendientes) -->
<div class="row">
  <!-- Tarjeta 1: Retardos del mes -->
  <div class="col-lg-4 col-md-6 col-12 mb-4">
    <div class="card h-100 shadow-sm dash-apr-kpi dash-apr-kpi-retardos">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="pr-2">
            <span class="dash-apr-kpi-title small font-weight-bold text-uppercase d-block mb-1">Retardos del mes</span>
            <span class="h2 font-weight-bold mb-0 text-dark"><?= $retardosMes ?></span>
            <span class="small d-block dash-apr-kpi-desc mt-1">
              <i class="fas fa-info-circle mr-1"></i>
              <?= $retardosMes === 0 ? 'Sin retardos registrados' : ($retardosMes === 1 ? '1 llegada tarde registrada' : $retardosMes . ' llegadas tarde registradas') ?>
            </span>
            <span class="badge dash-apr-badge mt-2 small font-weight-medium"><?= htmlspecialchars($nombreMesActual) ?></span>
          </div>
          <div class="dash-apr-icon-bubble" aria-hidden="true">
            <i class="fas fa-clock fa-2x"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tarjeta 2: Inasistencias del mes -->
  <div class="col-lg-4 col-md-6 col-12 mb-4">
    <div class="card h-100 shadow-sm dash-apr-kpi dash-apr-kpi-inasistencias">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="pr-2">
            <span class="dash-apr-kpi-title small font-weight-bold text-uppercase d-block mb-1">Inasistencias del mes</span>
            <span class="h2 font-weight-bold mb-0 text-dark"><?= $inasistenciasMes ?></span>
            <span class="small d-block dash-apr-kpi-desc mt-1">
              <i class="fas fa-info-circle mr-1"></i>
              <?= $inasistenciasMes === 0 ? 'Sin inasistencias registradas' : ($inasistenciasMes === 1 ? '1 inasistencia registrada' : $inasistenciasMes . ' inasistencias registradas') ?>
            </span>
            <span class="badge dash-apr-badge mt-2 small font-weight-medium"><?= htmlspecialchars($nombreMesActual) ?></span>
          </div>
          <div class="dash-apr-icon-bubble" aria-hidden="true">
            <i class="fas fa-user-times fa-2x"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tarjeta 3: Excusas pendientes -->
  <div class="col-lg-4 col-md-12 col-12 mb-4">
    <div class="card h-100 shadow-sm dash-apr-kpi dash-apr-kpi-excusas">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between">
          <div class="pr-2">
            <span class="dash-apr-kpi-title small font-weight-bold text-uppercase d-block mb-1">Excusas pendientes</span>
            <span class="h2 font-weight-bold mb-0 text-dark"><?= $excusasPendientes ?></span>
            <span class="small d-block dash-apr-kpi-desc mt-1">
              <i class="fas fa-info-circle mr-1"></i>
              <?= $excusasPendientes === 0 ? 'Sin trámites pendientes' : ($excusasPendientes === 1 ? '1 excusa en espera' : $excusasPendientes . ' excusas en espera') ?>
            </span>
            <span class="badge dash-apr-badge mt-2 small font-weight-medium">En revisión por instructor</span>
          </div>
          <div class="dash-apr-icon-bubble" aria-hidden="true">
            <i class="fas fa-hourglass-half fa-2x"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 3. Fila intermedia: Resumen de asistencia y Estado de mis excusas -->
<div class="row">
  <!-- Columna 1: Resumen de Asistencia -->
  <div class="col-lg-6 col-12 mb-4">
    <div class="card h-100 shadow-sm dash-apr-card">
      <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-chart-bar text-sena mr-2"></i> Resumen de asistencia
        </h3>
        <span class="badge badge-light border"><?= htmlspecialchars($nombreMesActual) ?></span>
      </div>
      <div class="card-body p-4 d-flex flex-column justify-content-center">
        <?php if ($totalRegistrosMes === 0): ?>
          <div class="text-center py-4">
            <div class="mb-3 text-muted">
              <i class="fas fa-calendar-check fa-3x"></i>
            </div>
            <h4 class="h6 font-weight-bold text-dark mb-1">Sin registros en este mes</h4>
            <p class="text-muted small mb-3">Aún no se registran entradas ni inasistencias en el período de <?= htmlspecialchars($nombreMesActual) ?>.</p>
            <a href="<?= RUTA_BASE ?>views/aprendiz/historial.php" class="btn btn-sm btn-outline-success">
              <i class="fas fa-history mr-1"></i> Consultar historial completo
            </a>
          </div>
        <?php else: ?>
          <div class="row align-items-center">
            <div class="col-sm-7 col-12 mb-3 mb-sm-0">
              <div style="height: 180px; position: relative;">
                <canvas id="graficoAsistenciaAprendiz" role="img" aria-label="Gráfico de asistencia del mes actual"></canvas>
              </div>
            </div>
            <div class="col-sm-5 col-12">
              <ul class="list-unstyled mb-0 small">
                <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                  <span><i class="fas fa-check-circle text-success mr-2"></i>Puntuales</span>
                  <span class="font-weight-bold text-dark"><?= $puntualesMes ?></span>
                </li>
                <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                  <span><i class="fas fa-clock text-warning mr-2"></i>Retardos</span>
                  <span class="font-weight-bold text-dark"><?= $retardosMes ?></span>
                </li>
                <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                  <span><i class="fas fa-user-times text-danger mr-2"></i>Inasistencias</span>
                  <span class="font-weight-bold text-dark"><?= $inasistenciasMes ?></span>
                </li>
                <li class="d-flex justify-content-between align-items-center py-1">
                  <span><i class="fas fa-sign-out-alt text-info mr-2"></i>Salidas anticipadas</span>
                  <span class="font-weight-bold text-dark"><?= $salidasTempranasMes ?></span>
                </li>
              </ul>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-light border-top text-right">
        <a href="<?= RUTA_BASE ?>views/aprendiz/historial.php" class="btn btn-sm btn-outline-success">
          <i class="fas fa-history mr-1"></i> Consultar historial
        </a>
      </div>
    </div>
  </div>

  <!-- Columna 2: Estado de mis excusas -->
  <div class="col-lg-6 col-12 mb-4">
    <div class="card h-100 shadow-sm dash-apr-card">
      <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-file-medical text-sena mr-2"></i> Estado de mis excusas
        </h3>
        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-sm btn-success">
          <i class="fas fa-cloud-upload-alt mr-1"></i> Subir excusa
        </a>
      </div>
      <div class="card-body p-0 d-flex flex-column justify-content-center">
        <?php if (empty($excusasAprendiz)): ?>
          <div class="text-center py-4 px-3 my-auto">
            <div class="mb-3 text-muted">
              <i class="fas fa-file-medical fa-3x"></i>
            </div>
            <h4 class="h6 font-weight-bold text-dark mb-1">Aún no has enviado excusas</h4>
            <p class="text-muted small mb-3">Si tienes inasistencias justificadas por incapacidad médica u otra causa válida, puedes registrar tu excusa para revisión del instructor.</p>
            <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="btn btn-sm btn-outline-success">
              <i class="fas fa-cloud-upload-alt mr-1"></i> Subir excusa
            </a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="bg-light">
                <tr>
                  <th scope="col" class="pl-4">Fecha de inasistencia</th>
                  <th scope="col">Fecha de envío</th>
                  <th scope="col" class="pr-4 text-center">Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($excusasAprendiz as $excusa): ?>
                  <?php
                    $estadoValor = strtoupper(trim((string)$excusa['estado']));
                    $badgeClase  = 'badge-secondary';
                    $iconoEstado = 'fa-info-circle';
                    $textoEstado = 'Registrada';

                    if ($estadoValor === 'PENDIENTE') {
                        $badgeClase  = 'badge-warning text-dark';
                        $iconoEstado = 'fa-clock';
                        $textoEstado = 'Pendiente';
                    } elseif ($estadoValor === 'APROBADA') {
                        $badgeClase  = 'badge-success';
                        $iconoEstado = 'fa-check-circle';
                        $textoEstado = 'Aprobada';
                    } elseif ($estadoValor === 'RECHAZADA') {
                        $badgeClase  = 'badge-danger';
                        $iconoEstado = 'fa-times-circle';
                        $textoEstado = 'Rechazada';
                    }

                    $fInasistencia = !empty($excusa['fecha_asistencia']) ? date('d/m/Y', strtotime($excusa['fecha_asistencia'])) : 'N/A';
                    $fEnvio        = !empty($excusa['fecha_subida']) ? date('d/m/Y', strtotime($excusa['fecha_subida'])) : 'N/A';
                  ?>
                  <tr>
                    <td class="pl-4 font-weight-medium text-dark"><?= htmlspecialchars($fInasistencia) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($fEnvio) ?></td>
                    <td class="pr-4 text-center">
                      <span class="badge <?= $badgeClase ?> px-2 py-1">
                        <i class="fas <?= $iconoEstado ?> mr-1"></i><?= htmlspecialchars($textoEstado) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer bg-light border-top text-right">
        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/index.php" class="btn btn-sm btn-outline-secondary">
          <i class="fas fa-folder-open mr-1"></i> Mis excusas
        </a>
      </div>
    </div>
  </div>
</div>

<!-- 4. Fila inferior: Accesos rápidos compactos -->
<div class="card shadow-sm dash-apr-card mb-4">
  <div class="card-header bg-white border-bottom">
    <h3 class="card-title font-weight-bold text-dark mb-0">
      <i class="fas fa-bolt text-sena mr-2"></i> Accesos rápidos
    </h3>
  </div>
  <div class="card-body p-3">
    <div class="row">
      <div class="col-md-4 col-12 mb-2 mb-md-0">
        <a href="<?= RUTA_BASE ?>views/aprendiz/excusas/create.php" class="dash-apr-quick-btn h-100">
          <div class="dash-apr-icon-bubble bg-light text-sena mr-3" aria-hidden="true">
            <i class="fas fa-cloud-upload-alt fa-lg"></i>
          </div>
          <div>
            <strong class="d-block text-dark">Subir excusa</strong>
            <span class="small text-muted">Registrar justificación médica o académica</span>
          </div>
        </a>
      </div>
      <div class="col-md-4 col-12 mb-2 mb-md-0">
        <a href="<?= RUTA_BASE ?>views/aprendiz/historial.php" class="dash-apr-quick-btn h-100">
          <div class="dash-apr-icon-bubble bg-light text-sena mr-3" aria-hidden="true">
            <i class="fas fa-history fa-lg"></i>
          </div>
          <div>
            <strong class="d-block text-dark">Consultar historial</strong>
            <span class="small text-muted">Ver todas las entradas, retardos y salidas</span>
          </div>
        </a>
      </div>
      <div class="col-md-4 col-12">
        <a href="<?= RUTA_BASE ?>views/aprendiz/perfil.php" class="dash-apr-quick-btn h-100">
          <div class="dash-apr-icon-bubble bg-light text-sena mr-3" aria-hidden="true">
            <i class="fas fa-user-circle fa-lg"></i>
          </div>
          <div>
            <strong class="d-block text-dark">Mi perfil</strong>
            <span class="small text-muted">Editar datos personales y contraseña</span>
          </div>
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Datos globales para compatibilidad de scripts -->
<script>
window.datosGraficaAprendiz = <?= json_encode($datosGraficaAprendiz) ?>;
</script>

<?php if ($totalRegistrosMes > 0): ?>
<!-- Inicialización del gráfico de barras compacto -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var canvasElem = document.getElementById('graficoAsistenciaAprendiz');
  if (canvasElem && typeof Chart !== 'undefined') {
    new Chart(canvasElem, {
      type: 'bar',
      data: {
        labels: ['Puntuales', 'Retardos', 'Inasistencias', 'Salidas ant.'],
        datasets: [{
          label: 'Registros',
          data: [<?= $puntualesMes ?>, <?= $retardosMes ?>, <?= $inasistenciasMes ?>, <?= $salidasTempranasMes ?>],
          backgroundColor: [
            'rgba(57, 169, 0, 0.85)',
            'rgba(243, 156, 18, 0.85)',
            'rgba(220, 53, 69, 0.85)',
            'rgba(23, 162, 184, 0.85)'
          ],
          borderColor: [
            '#39a900',
            '#f39c12',
            '#dc3545',
            '#17a2b8'
          ],
          borderWidth: 1,
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var v = ctx.raw || 0;
                return ' ' + v + (v === 1 ? ' registro' : ' registros');
              }
            }
          }
        },
        scales: {
          x: {
            beginAtZero: true,
            ticks: {
              precision: 0,
              stepSize: 1
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            }
          },
          y: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  }
});
</script>
<?php endif; ?>

<?php if ($mostrarInvitacionClave): ?>
<!-- Invitación interactiva para proteger la cuenta (SweetAlert2) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var tokenCsrf = <?= json_encode($_SESSION['token_csrf'] ?? '') ?>;
  var urlMantener = <?= json_encode(RUTA_BASE . 'controllers/aprendiz/mantener_contrasena.php') ?>;
  var urlCambio = <?= json_encode(RUTA_BASE . 'controllers/aprendiz/cambiar_contrasena.php') ?>;

  function abrirModalCambioClave() {
    var formHtml = ''
      + '<div class="text-left">'
      + '  <div class="form-group mb-3">'
      + '    <label for="swalClaveActual" class="font-weight-bold small text-dark mb-1">Contraseña actual *</label>'
      + '    <div class="input-group">'
      + '      <input type="password" id="swalClaveActual" class="form-control" placeholder="••••••••" autocomplete="current-password">'
      + '      <div class="input-group-append">'
      + '        <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="swalClaveActual" tabindex="-1">'
      + '          <i class="fas fa-eye"></i>'
      + '        </button>'
      + '      </div>'
      + '    </div>'
      + '  </div>'
      + '  <div class="form-group mb-3">'
      + '    <label for="swalNuevaClave" class="font-weight-bold small text-dark mb-1">Nueva contraseña *</label>'
      + '    <div class="input-group">'
      + '      <input type="password" id="swalNuevaClave" class="form-control" placeholder="Mínimo 8 caracteres" autocomplete="new-password">'
      + '      <div class="input-group-append">'
      + '        <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="swalNuevaClave" tabindex="-1">'
      + '          <i class="fas fa-eye"></i>'
      + '        </button>'
      + '      </div>'
      + '    </div>'
      + '    <small class="form-text text-muted">Mínimo 8 caracteres. Se admiten espacios, frases y pegado.</small>'
      + '  </div>'
      + '  <div class="form-group mb-0">'
      + '    <label for="swalConfirmarClave" class="font-weight-bold small text-dark mb-1">Confirmar nueva contraseña *</label>'
      + '    <div class="input-group">'
      + '      <input type="password" id="swalConfirmarClave" class="form-control" placeholder="Repite la nueva contraseña" autocomplete="new-password">'
      + '      <div class="input-group-append">'
      + '        <button class="btn btn-outline-secondary btn-toggle-eye" type="button" data-target="swalConfirmarClave" tabindex="-1">'
      + '          <i class="fas fa-eye"></i>'
      + '        </button>'
      + '      </div>'
      + '    </div>'
      + '  </div>'
      + '</div>';

    Swal.fire({
      title: 'Cambiar contraseña',
      html: formHtml,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: '<i class="fas fa-save mr-1"></i> Guardar contraseña',
      cancelButtonText: '<i class="fas fa-arrow-left mr-1"></i> Volver a la recomendación',
      confirmButtonColor: '#39a900',
      cancelButtonColor: '#6c757d',
      focusConfirm: false,
      didOpen: function () {
        var popup = Swal.getPopup();
        var eyeButtons = popup.querySelectorAll('.btn-toggle-eye');
        eyeButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input = document.getElementById(targetId);
            var icon = btn.querySelector('i');
            if (input && icon) {
              if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
              } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
              }
            }
          });
        });
      },
      preConfirm: function () {
        var claveActual    = document.getElementById('swalClaveActual').value;
        var nuevaClave     = document.getElementById('swalNuevaClave').value;
        var confirmarClave = document.getElementById('swalConfirmarClave').value;

        if (!claveActual || !nuevaClave || !confirmarClave) {
          Swal.showValidationMessage('Todos los campos son obligatorios.');
          return false;
        }

        if (nuevaClave.length < 8) {
          Swal.showValidationMessage('La nueva contraseña debe tener al menos 8 caracteres.');
          return false;
        }

        var byteLength = new TextEncoder().encode(nuevaClave).length;
        if (byteLength > 72) {
          Swal.showValidationMessage('La nueva contraseña no puede exceder 72 bytes por seguridad.');
          return false;
        }

        if (nuevaClave !== confirmarClave) {
          Swal.showValidationMessage('La nueva contraseña y su confirmación no coinciden.');
          return false;
        }

        if (claveActual === nuevaClave) {
          Swal.showValidationMessage('La nueva contraseña no puede ser idéntica a la actual.');
          return false;
        }

        return fetch(urlCambio, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({
            token_csrf: tokenCsrf,
            clave_actual: claveActual,
            nueva_clave: nuevaClave,
            confirmar_clave: confirmarClave
          })
        })
        .then(function (response) {
          return response.json().then(function (data) {
            if (!response.ok || !data.success) {
              throw new Error(data.error || 'No fue posible actualizar la contraseña.');
            }
            return data;
          });
        })
        .catch(function (error) {
          Swal.showValidationMessage(error.message || 'Error en el servidor.');
        });
      }
    }).then(function (result) {
      if (result.isConfirmed && result.value && result.value.success) {
        Swal.fire({
          icon: 'success',
          title: '¡Contraseña actualizada!',
          text: 'Tu contraseña ha sido cambiada exitosamente.',
          confirmButtonColor: '#39a900'
        });
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        mostrarInvitacionInicial();
      }
    });
  }

  function mostrarInvitacionInicial() {
    Swal.fire({
      title: 'Protege tu cuenta',
      text: 'Te recomendamos cambiar tu contraseña inicial. Puedes hacerlo ahora o mantener la actual y cambiarla después desde Mi perfil.',
      icon: 'info',
      showCloseButton: true,
      showDenyButton: true,
      confirmButtonText: '<i class="fas fa-key mr-1"></i> Cambiar contraseña',
      denyButtonText: '<i class="fas fa-shield-alt mr-1"></i> Mantener contraseña actual',
      confirmButtonColor: '#39a900',
      denyButtonColor: '#6c757d',
      allowOutsideClick: false,
      allowEscapeKey: true
    }).then(function (result) {
      if (result.isConfirmed) {
        abrirModalCambioClave();
      } else if (result.isDenied) {
        fetch(urlMantener, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({
            token_csrf: tokenCsrf
          })
        })
        .then(function (response) {
          return response.json().then(function (data) {
            if (!response.ok || !data.success) {
              throw new Error(data.error || 'No fue posible registrar la preferencia.');
            }
            return data;
          });
        })
        .then(function () {
          Swal.fire({
            icon: 'success',
            title: 'Preferencia guardada',
            text: 'Podrás cambiar tu contraseña en cualquier momento desde Mi perfil.',
            confirmButtonColor: '#39a900',
            timer: 2500,
            showConfirmButton: false
          });
        })
        .catch(function (error) {
          Swal.fire({
            icon: 'error',
            title: 'No fue posible guardar',
            text: error.message || 'Ocurrió un error al registrar la decisión. Inténtelo más tarde.',
            confirmButtonColor: '#39a900'
          });
        });
      }
    });
  }

  mostrarInvitacionInicial();
});
</script>
<?php endif; ?>

<?php
$contenido = ob_get_clean();

// Render del layout del panel de aprendiz
include __DIR__ . '/../layouts/layout_aprendiz.php';
