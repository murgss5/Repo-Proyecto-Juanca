<?php


require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';
require_once dirname(__DIR__) . '/../models/HorarioModel.php';


// --- Variables del layout ---
$paginaTitulo = 'Dashboard del instructor';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Dashboard'],
];

// Instructor autenticado
$idInstructor = (int)($_SESSION['instructor']['id_usuario'] ?? 0);
$conexion = (new Conexion())->conectar();


// Total fichas
$consulta = $conexion->prepare('SELECT COUNT(*) FROM ficha WHERE fk_usuario = :id_instructor');
$consulta->execute([':id_instructor' => $idInstructor]);
$totalFichas = (int) $consulta->fetchColumn();

// Total aprendices
$consulta = $conexion->prepare('SELECT COUNT(*) FROM aprendiz INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha WHERE ficha.fk_usuario = :id_instructor');
$consulta->execute([':id_instructor' => $idInstructor]);
$totalAprendices = (int) $consulta->fetchColumn();

// Presentes hoy
$consulta = $conexion->prepare("SELECT COUNT(*) FROM asistencia INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha WHERE ficha.fk_usuario = :id_instructor AND asistencia.fecha_asistencia = CURDATE() AND asistencia.estado_entrada IN ('PUNTUAL', 'RETARDO')");
$consulta->execute([':id_instructor' => $idInstructor]);
$presentesHoy = (int) $consulta->fetchColumn();


// Excusas pendientes
$consulta = $conexion->prepare("SELECT COUNT(*) FROM excusa INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha WHERE ficha.fk_usuario = :id_instructor AND excusa.estado = 'PENDIENTE'");
$consulta->execute([':id_instructor' => $idInstructor]);
$excusasPendientes = (int) $consulta->fetchColumn();

// Últimos ingresos hoy
$consulta = $conexion->prepare("SELECT DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora, CONCAT(usuario.nombre, ' ', usuario.apellido) AS aprendiz, ficha.id_ficha AS ficha FROM asistencia INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha WHERE ficha.fk_usuario = :id_instructor AND asistencia.fecha_asistencia = CURDATE() AND asistencia.entrada IS NOT NULL ORDER BY asistencia.entrada DESC LIMIT 5");
$consulta->execute([':id_instructor' => $idInstructor]);
$ultimosIngresosHoy = $consulta->fetchAll();

// Resumen semana para gráfica
$resumenSemana = [
    'labels' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
    'puntuales' => array_fill(0, 6, 0),
    'retardos' => array_fill(0, 6, 0),
];
$consulta = $conexion->prepare("SELECT WEEKDAY(asistencia.fecha_asistencia) AS indice_dia, SUM(CASE WHEN asistencia.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales, SUM(CASE WHEN asistencia.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos FROM asistencia INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha WHERE ficha.fk_usuario = :id_instructor AND asistencia.fecha_asistencia >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) AND asistencia.fecha_asistencia < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY) GROUP BY WEEKDAY(asistencia.fecha_asistencia)");
$consulta->execute([':id_instructor' => $idInstructor]);
foreach ($consulta->fetchAll() as $dia) {
    $indice = (int) $dia['indice_dia'];
    $resumenSemana['puntuales'][$indice] = (int) $dia['puntuales'];
    $resumenSemana['retardos'][$indice] = (int) $dia['retardos'];
}

// Fichas con horario hoy
date_default_timezone_set('America/Bogota');
$diaSemana = date('l');
$diasMap = [
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sábado',
    'Sunday'    => 'Domingo'
];
$diasMapAlt = [
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miercoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sabado',
    'Sunday'    => 'Domingo'
];
$diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;
$diaSemanaAlt = $diasMapAlt[$diaSemana] ?? $diaSemana;
$fechaHoy = date('Y-m-d');
// Carga estricta por fecha exacta de hoy para evitar la duplicación de históricos
$fichasConHorarioHoy = HorarioModel::obtenerHorariosHoyInstructor($idInstructor, $fechaHoy);

// Jornadas de hoy
$consulta = $conexion->prepare("SELECT id_sesion, ficha_id, horario_id, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado FROM sesiones_jornada WHERE instructor_id = :id_instructor AND fecha = CURDATE() AND estado IN ('abierta', 'cerrada_manual') ORDER BY hora_apertura_real DESC");
$consulta->execute([':id_instructor' => $idInstructor]);
$jornadas = $consulta->fetchAll();

// Indexar jornadas por ficha
$jornadasPorFicha = [];
foreach ($jornadas as $j) {
    $jornadasPorFicha[$j['ficha_id']] = $j;
}

$mesesEspanol = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
$mesActual = $mesesEspanol[(int) date('n')] . ' ' . date('Y');

ob_start();
?>
<!-- Mensajes flash -->
<?php $flash = getFlash(); ?>
<?php if ($flash): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="alert alert-<?= $flash['tipo'] === 'success' ? 'success' : ($flash['tipo'] === 'error' ? 'danger' : $flash['tipo']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash['mensaje']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ==================== TARJETAS DE ESTADÍSTICAS (KPIs SaaS) ==================== -->
<div class="row">
  <!-- Mis Fichas -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-success hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">MIS FICHAS</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalFichas) ?></h2>
            <small class="text-muted"><i class="fas fa-layer-group mr-1 text-success"></i> Fichas asignadas</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #bbf7d0; color: #166534; font-size: 0.72rem;"><?= $mesActual ?></span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-layer-group"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Aprendices a Cargo -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-info hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">APRENDICES A CARGO</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($totalAprendices) ?></h2>
            <small class="text-muted"><i class="fas fa-users mr-1 text-primary"></i> Total en mis fichas</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #bfdbfe; color: #1e40af; font-size: 0.72rem;"><?= $mesActual ?></span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-users"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Presentes Hoy -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-info hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">PRESENTES HOY</h6>
            <h2 class="font-weight-bold text-dark mb-1"><span id="kpiPresentesHoy"><?= number_format($presentesHoy) ?></span></h2>
            <small class="text-muted"><i class="fas fa-user-check mr-1 text-primary"></i> Asistencia confirmada</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #bfdbfe; color: #1e40af; font-size: 0.72rem;">Jornada de hoy</span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-user-check"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- Excusas Pendientes -->
  <div class="col-xl-3 col-md-6 mb-4">
    <div class="card kpi-card-premium kpi-warning hover-elevate h-100">
      <div class="card-body p-3 d-flex justify-content-between align-items-start">
        <div class="d-flex flex-column justify-content-between h-100 pr-2">
          <div>
            <h6 class="text-uppercase font-weight-bold text-muted mb-1" style="font-size: 0.75rem;">EXCUSAS PENDIENTES</h6>
            <h2 class="font-weight-bold text-dark mb-1"><?= number_format($excusasPendientes) ?></h2>
            <small class="text-muted"><i class="fas fa-file-medical mr-1 text-warning"></i> Por revisar y validar</small>
          </div>
          <div class="mt-2">
            <span class="badge font-weight-bold px-2 py-1" style="background-color: #fef08a; color: #854d0e; font-size: 0.72rem;">Por gestionar</span>
          </div>
        </div>
        <div class="kpi-icon-circle">
          <i class="fas fa-file-medical"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Control de jornadas por ficha -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                <h5 class="card-title font-weight-bold text-dark mb-0"><i class="fas fa-clock text-muted mr-2"></i> Control de jornadas</h5>
            </div>
            <div class="card-body p-4">
                <?php if (empty($fichasConHorarioHoy)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p class="mb-0">No tienes jornadas asignadas para el día de hoy (<?= $diaSemanaEs ?>, <?= $fechaHoy ?>).</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($fichasConHorarioHoy as $ficha): 
                            $fichaId = $ficha['id_ficha'];
                            $jornada = $jornadasPorFicha[$fichaId] ?? null;
                            $estado = $jornada ? $jornada['estado'] : 'sin_iniciar';
                            $puedeAbrir = $estado === 'sin_iniciar';
                            $puedeCerrar = $estado === 'abierta';
                            $horarioEntrada = date('H:i', strtotime($ficha['entrada']));
                            $horarioSalida = date('H:i', strtotime($ficha['salida']));
                            $aperturaFmt = $jornada && $jornada['hora_apertura_real'] ? date('H:i', strtotime($jornada['hora_apertura_real'])) : '--:--';
                            $limiteFmt = $jornada && $jornada['hora_limite_puntualidad'] ? date('H:i', strtotime($jornada['hora_limite_puntualidad'])) : '--:--';
                            $cierreFmt = $jornada && $jornada['hora_cierre_real'] ? date('H:i', strtotime($jornada['hora_cierre_real'])) : '--:--';
                        ?>
                        <div class="col-lg-6 col-md-12 mb-3">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <span class="badge badge-info">Ficha #<?= htmlspecialchars($fichaId) ?></span>
                                    <span class="badge badge-<?= $estado === 'abierta' ? 'success' : ($estado === 'cerrada_manual' ? 'danger' : 'secondary') ?> px-3 py-2">
                                        <?php
                                            $labels = ['sin_iniciar' => 'Sin iniciar', 'abierta' => 'Abierta', 'cerrada_manual' => 'Cerrada'];
                                            echo $labels[$estado] ?? $estado;
                                        ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <strong><?= htmlspecialchars($ficha['nombre_programa']) ?></strong>
                                        <span class="text-muted ml-2">(<?= htmlspecialchars($ficha['jornada']) ?>)</span>
                                    </div>
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Entrada</small>
                                            <div class="h5 mb-0"><?= $horarioEntrada ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Salida</small>
                                            <div class="h5 mb-0"><?= $horarioSalida ?></div>
                                        </div>
                                    </div>
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Aprendices</small>
                                            <div class="h5 mb-0"><?= (int)$ficha['total_aprendices'] ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Horario</small>
                                            <div class="h5 mb-0">#<?= htmlspecialchars($ficha['id_horario']) ?></div>
                                        </div>
                                    </div>
                                    <?php if ($estado === 'abierta'): ?>
                                        <div class="mb-2 p-2 bg-light rounded">
                                            <small>
                                                <strong>Jornada abierta a las <?= $aperturaFmt ?>.</strong> Límite sin retardo: <?= $limiteFmt ?>
                                            </small>
                                        </div>
                                    <?php elseif ($estado === 'cerrada_manual'): ?>
                                        <div class="mb-2 p-2 bg-light rounded">
                                            <small>
                                                <strong>Cierre:</strong> <?= $cierreFmt ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex gap-2">
                                        <form method="post" action="<?= RUTA_BASE ?>controllers/abrir_jornada.php" class="flex-fill">
                                            <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($fichaId) ?>">
                                            <input type="hidden" name="horario_id" value="<?= htmlspecialchars($ficha['id_horario']) ?>">
                                            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf'] ?? '') ?>">
                                            <button type="submit" 
                                                    class="btn btn-success btn-sm flex-fill" 
                                                    <?= !$puedeAbrir ? 'disabled' : '' ?>
                                                    aria-label="Abrir jornada Ficha #<?= $fichaId ?>">
                                                <i class="fas fa-door-open mr-1"></i> Abrir
                                            </button>
                                        </form>
                                        <form method="post" action="<?= RUTA_BASE ?>controllers/cerrar_jornada.php" class="flex-fill">
                                            <input type="hidden" name="ficha_id" value="<?= htmlspecialchars($fichaId) ?>">
                                            <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf'] ?? '') ?>">
                                            <button type="submit" 
                                                    class="btn btn-danger btn-sm flex-fill" 
                                                    <?= !$puedeCerrar ? 'disabled' : '' ?>
                                                    aria-label="Cerrar jornada Ficha #<?= $fichaId ?>">
                                                <i class="fas fa-door-closed mr-1"></i> Cerrar
                                            </button>
                                        </form>
                                    </div>
                                    <?php if (defined('MODO_PRUEBAS_RFID') && MODO_PRUEBAS_RFID === true && $estado === 'abierta'): ?>
                                    <div class="mt-3 p-3 border border-warning rounded bg-light simulador-rfid-box">
                                        <div class="text-warning font-weight-bold mb-2" style="font-size: 0.85rem;">
                                            <i class="fas fa-flask mr-1"></i> Modo de prueba: esta acción guarda asistencias reales sin utilizar el lector físico
                                        </div>
                                        <button type="button" 
                                                class="btn btn-warning btn-sm btn-block btn-simular-ingreso font-weight-bold" 
                                                data-ficha-id="<?= htmlspecialchars($fichaId) ?>">
                                            <i class="fas fa-id-card mr-1"></i> Simular ingreso del siguiente aprendiz
                                        </button>
                                        <div class="simulador-resultado mt-2" style="display: none;"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // pasar datos de jornadas a JavaScript
    window.jornadasPorFicha = <?= json_encode($jornadasPorFicha ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.rutaBase = '<?= RUTA_BASE ?>';
    window.tokenCsrf = '<?= $_SESSION['token_csrf'] ?? '' ?>';
</script>

<!-- Gráfica y registros del día -->
<div class="row">
  <div class="col-lg-7 col-md-12 mb-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
        <h5 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-chart-line text-muted mr-2"></i> Asistencia de mi semana
        </h5>
      </div>
      <div class="card-body p-4">
        <div style="height: 280px; position: relative;">
          <script>
            window.datosGraficaSemanaInstructor = <?= json_encode(
                $resumenSemana,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?>;
          </script>
          <canvas id="graficoSemanaInstructor"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5 col-md-12 mb-4">
    <div class="card border-0 shadow-sm mb-4 h-100">
      <div class="card-header bg-transparent border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
        <h5 class="card-title font-weight-bold text-dark mb-0">
          <i class="fas fa-sign-in-alt text-muted mr-2"></i> Ingresos registrados hoy
        </h5>
        <a href="<?= RUTA_BASE ?>views/instructor/asistencias/index.php" class="btn btn-sm btn-link text-sena font-weight-bold p-0">Ver todos</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-borderless table-hover mb-0">
            <thead class="thead-light">
              <tr>
                <th class="font-weight-bold text-muted small text-uppercase">Hora</th>
                <th class="font-weight-bold text-muted small text-uppercase">Aprendiz</th>
                <th class="font-weight-bold text-muted small text-uppercase">Ficha</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($ultimosIngresosHoy === []): ?>
                <tr>
                  <td colspan="3" class="text-center text-muted py-4">No hay ingresos registrados hoy.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($ultimosIngresosHoy as $ingreso): ?>
                  <tr>
                    <td><span class="badge badge-pill badge-success px-2 py-1"><?= htmlspecialchars($ingreso['hora']) ?></span></td>
                    <td class="font-weight-500 text-dark"><?= htmlspecialchars($ingreso['aprendiz']) ?></td>
                    <td class="text-muted">Ficha #<?= htmlspecialchars($ingreso['ficha']) ?></td>
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


<!-- Control de jornadas por ficha - inicialización visual -->
<script>
    (function () {
        'use strict';

        var rutaBase = window.rutaBase || '';
        var tokenCsrf = window.tokenCsrf || '';

        // actualizar estado visual de una tarjeta de ficha
        function actualizarEstadoTarjeta(fichaId, estado, datos) {
            var card = document.querySelector('button[type="submit"][formaction*="abrir_jornada"][form*="ficha_id"][value="' + fichaId + '"]')?.closest('.card');
            if (!card) return;

            var btnAbrir = card.querySelector('form[action*="abrir_jornada"] button[type="submit"]');
            var btnCerrar = card.querySelector('form[action*="cerrar_jornada"] button[type="submit"]');
            var badgeEstado = card.querySelector('.badge[class*="badge-"]');
            var detalleDiv = card.querySelector('.mb-2.p-2.bg-light.rounded');

            // actualizar badge de estado
            var labels = { sin_iniciar: 'Sin iniciar', abierta: 'Abierta', cerrada_manual: 'Cerrada' };
            var badgeClasses = { sin_iniciar: 'badge-secondary', abierta: 'badge-success', cerrada_manual: 'badge-danger' };
            if (badgeEstado) {
                badgeEstado.className = 'badge ' + (badgeClasses[estado] || 'badge-secondary') + ' px-3 py-2';
                badgeEstado.textContent = labels[estado] || estado;
            }

            // actualizar botones
            if (btnAbrir) {
                btnAbrir.disabled = estado !== 'sin_iniciar';
                btnAbrir.setAttribute('aria-disabled', estado !== 'sin_iniciar' ? 'true' : 'false');
            }
            if (btnCerrar) {
                btnCerrar.disabled = estado !== 'abierta';
                btnCerrar.setAttribute('aria-disabled', estado !== 'abierta' ? 'true' : 'false');
            }

            // actualizar detalle de horas
            if (estado === 'abierta' && datos) {
                var rawApertura = datos.hora_apertura_real || datos.hora_apertura || '';
                var aperturaFmt = rawApertura.length >= 16 ? rawApertura.substring(11, 16) : (rawApertura.substring(0, 5) || '--:--');
                var rawLimite = datos.hora_limite_puntualidad || '';
                var limiteFmt = rawLimite.length >= 16 ? rawLimite.substring(11, 16) : (rawLimite.substring(0, 5) || '--:--');

                if (!detalleDiv) {
                    detalleDiv = document.createElement('div');
                    detalleDiv.className = 'mb-2 p-2 bg-light rounded';
                    detalleDiv.innerHTML = '<small><strong>Jornada abierta a las ' + aperturaFmt + '.</strong> Límite sin retardo: ' + limiteFmt + '</small>';
                    if (btnAbrir) btnAbrir.parentNode.insertBefore(detalleDiv, btnAbrir);
                } else {
                    detalleDiv.innerHTML = '<small><strong>Jornada abierta a las ' + aperturaFmt + '.</strong> Límite sin retardo: ' + limiteFmt + '</small>';
                }
                if (detalleDiv) detalleDiv.style.display = 'block';
            } else if (estado === 'cerrada_manual' && datos) {
                var cierreFmt = datos.hora_cierre ? datos.hora_cierre.substring(0,5) : '--:--';
                if (!detalleDiv) {
                    detalleDiv = document.createElement('div');
                    detalleDiv.className = 'mb-2 p-2 bg-light rounded';
                    detalleDiv.innerHTML = '<small><strong>Cierre:</strong> ' + cierreFmt + '</small>';
                    if (btnAbrir) btnAbrir.parentNode.insertBefore(detalleDiv, btnAbrir);
                } else {
                    detalleDiv.innerHTML = '<small><strong>Cierre:</strong> ' + cierreFmt + '</small>';
                }
                if (detalleDiv) detalleDiv.style.display = 'block';
            } else if (detalleDiv) {
                detalleDiv.style.display = 'none';
            }

            var simBox = card.querySelector('.simulador-rfid-box');
            if (simBox) {
                simBox.style.display = (estado === 'abierta') ? 'block' : 'none';
            }
        }

        // Manejador para simular ingreso del siguiente aprendiz
        var confirmacionPreviaSimulacion = false;

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-simular-ingreso');
            if (!btn) return;

            e.preventDefault();

            var fichaId = btn.getAttribute('data-ficha-id');
            if (!fichaId) return;

            var card = btn.closest('.card');
            var resultadoDiv = card ? card.querySelector('.simulador-resultado') : null;

            if (!confirmacionPreviaSimulacion) {
                var confirmacion = window.confirm(
                    "¿Confirma que desea simular el ingreso del siguiente aprendiz para esta jornada abierta?\n\nEsta acción registrará una asistencia real en la base de datos."
                );
                if (!confirmacion) return;
                confirmacionPreviaSimulacion = true;
            }

            // Deshabilitar botón durante procesamiento para evitar dobles clics
            btn.disabled = true;
            var contenidoOriginal = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Procesando...';

            if (resultadoDiv) {
                resultadoDiv.style.display = 'none';
                resultadoDiv.innerHTML = '';
            }

            var formData = new FormData();
            formData.append('ficha_id', fichaId);
            formData.append('token_csrf', tokenCsrf);

            fetch(rutaBase + 'controllers/simular_ingreso_rfid.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, ok: res.ok, data: data };
                });
            })
            .then(function (resObj) {
                var data = resObj.data;
                if (!resObj.ok || !data.success) {
                    if (resultadoDiv) {
                        resultadoDiv.className = 'simulador-resultado mt-2 alert alert-warning p-2 mb-0';
                        resultadoDiv.innerHTML = '<i class="fas fa-info-circle mr-1"></i> ' + escapeHtml(data.message || 'No fue posible realizar la simulación');
                        resultadoDiv.style.display = 'block';
                    }
                    if (data && data.pendientes_restantes === 0) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Sin aprendices pendientes';
                        return;
                    }
                } else {
                    var d = data.data;
                    var badgeClase = d.estado === 'PUNTUAL' ? 'badge-success' : 'badge-warning';
                    var textoEstado = d.estado;
                    if (d.estado === 'RETARDO' && d.minutos_retardo > 0) {
                        textoEstado += ' (' + d.minutos_retardo + ' min)';
                    }

                    if (resultadoDiv) {
                        resultadoDiv.className = 'simulador-resultado mt-2 alert alert-success p-2 mb-0';
                        resultadoDiv.innerHTML = 
                            '<div class="d-flex justify-content-between align-items-center mb-1">' +
                                '<strong><i class="fas fa-user-check mr-1"></i> ' + escapeHtml(d.aprendiz) + '</strong>' +
                                '<span class="badge ' + badgeClase + '">' + textoEstado + '</span>' +
                            '</div>' +
                            '<div class="small text-muted">' +
                                'Hora: ' + d.hora + ' | Pendientes con RFID: <strong>' + d.pendientes_restantes + '</strong>' +
                            '</div>';
                        resultadoDiv.style.display = 'block';
                    }

                    // Actualizar contador "Presentes hoy" en las tarjetas superiores si existe
                    var presentesElem = document.querySelector('#kpiPresentesHoy') || document.querySelector('.info-box-azul .info-box-number');
                    if (presentesElem) {
                        var valActual = parseInt(presentesElem.textContent.trim(), 10) || 0;
                        presentesElem.textContent = valActual + 1;
                    }

                    // Actualizar tabla de últimos ingresos hoy si existe
                    var tablaIngresos = null;
                    var headers = document.querySelectorAll('.card-header h3, .card-header h5, .card-header .card-title');
                    headers.forEach(function (h) {
                        if (h.textContent.indexOf('Ingresos registrados hoy') !== -1) {
                            tablaIngresos = h.closest('.card').querySelector('table tbody');
                        }
                    });
                    if (tablaIngresos) {
                        var filaVacia = tablaIngresos.querySelector('td.text-muted');
                        if (filaVacia && filaVacia.getAttribute('colspan')) {
                            tablaIngresos.innerHTML = '';
                        }
                        var nuevaFila = document.createElement('tr');
                        nuevaFila.innerHTML = 
                            '<td><span class="badge badge-pill badge-success px-2 py-1">' + d.hora + '</span></td>' +
                            '<td class="font-weight-500 text-dark">' + escapeHtml(d.aprendiz) + '</td>' +
                            '<td class="text-muted">Ficha #' + fichaId + '</td>';
                        tablaIngresos.insertBefore(nuevaFila, tablaIngresos.firstChild);
                    }

                    if (d.pendientes_restantes === 0) {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fas fa-check-circle mr-1"></i> Sin aprendices pendientes';
                        return;
                    }
                }

                // Restaurar botón
                btn.disabled = false;
                btn.innerHTML = contenidoOriginal;
            })
            .catch(function (err) {
                if (resultadoDiv) {
                    resultadoDiv.className = 'simulador-resultado mt-2 alert alert-danger p-2 mb-0';
                    resultadoDiv.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> Error al conectar con el servidor';
                    resultadoDiv.style.display = 'block';
                }
                btn.disabled = false;
                btn.innerHTML = contenidoOriginal;
            });
        });

        function escapeHtml(str) {
            return String(str || '').replace(/[&<>"']/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
            });
        }

        // inicializar UI segun estado del servidor
        function inicializarUI() {
            var jornadas = window.jornadasPorFicha || {};
            Object.keys(jornadas).forEach(function (fichaId) {
                var jornada = jornadas[fichaId];
                if (jornada && jornada.estado) {
                    actualizarEstadoTarjeta(fichaId, jornada.estado, jornada);
                }
            });
        }

        // inicializar al cargar
        document.addEventListener('DOMContentLoaded', inicializarUI);
})();
</script>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_instructor.php';
