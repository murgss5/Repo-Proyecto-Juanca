<?php
/**
 * ============================================================
 * VISTA: DASHBOARD DEL INSTRUCTOR
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * La tarjeta "Mis fichas" usa datos reales. El resto de la
 * interfaz conserva datos de demostración por ahora.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

// --- Variables del layout ---
$paginaTitulo = 'Dashboard del instructor';
$menuActivo   = 'dashboard';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Dashboard'],
];

// El instructor autenticado es el dueño de sus fichas (ficha.fk_usuario).
$conexion = (new Conexion())->conectar();
$consultaFichas = $conexion->prepare(
    'SELECT COUNT(*) FROM ficha WHERE fk_usuario = :id_instructor'
);
$consultaFichas->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaFichas->execute();
$totalFichas = (int) $consultaFichas->fetchColumn();

// Un aprendiz pertenece a una ficha; la ficha pertenece al instructor.
$consultaAprendices = $conexion->prepare(
    'SELECT COUNT(*)
     FROM aprendiz
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor'
);
$consultaAprendices->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaAprendices->execute();
$totalAprendices = (int) $consultaAprendices->fetchColumn();

// Un aprendiz está presente si registró una entrada puntual o con retardo hoy.
$consultaPresentes = $conexion->prepare(
    "SELECT COUNT(*)
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
       AND asistencia.fecha_asistencia = CURDATE()
       AND asistencia.estado_entrada IN ('PUNTUAL', 'RETARDO')"
);
$consultaPresentes->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaPresentes->execute();
$presentesHoy = (int) $consultaPresentes->fetchColumn();

// Una excusa pertenece a una asistencia, que a su vez pertenece a un aprendiz.
$consultaExcusasPendientes = $conexion->prepare(
    "SELECT COUNT(*)
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
       AND excusa.estado = 'PENDIENTE'"
);
$consultaExcusasPendientes->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaExcusasPendientes->execute();
$excusasPendientes = (int) $consultaExcusasPendientes->fetchColumn();

// Últimas entradas de hoy, limitadas a los aprendices de las fichas del instructor.
$consultaUltimosIngresos = $conexion->prepare(
    "SELECT
        DATE_FORMAT(asistencia.entrada, '%H:%i') AS hora,
        CONCAT(usuario.nombre, ' ', usuario.apellido) AS aprendiz,
        ficha.id_ficha AS ficha
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
       AND asistencia.fecha_asistencia = CURDATE()
       AND asistencia.entrada IS NOT NULL
     ORDER BY asistencia.entrada DESC
     LIMIT 5"
);
$consultaUltimosIngresos->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaUltimosIngresos->execute();
$ultimosIngresosHoy = $consultaUltimosIngresos->fetchAll();

// Resumen de lunes a sábado de la semana actual para la gráfica.
$resumenSemana = [
    'labels' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
    'puntuales' => array_fill(0, 6, 0),
    'retardos' => array_fill(0, 6, 0),
];
$consultaSemana = $conexion->prepare(
    "SELECT
        WEEKDAY(asistencia.fecha_asistencia) AS indice_dia,
        SUM(CASE WHEN asistencia.estado_entrada = 'PUNTUAL' THEN 1 ELSE 0 END) AS puntuales,
        SUM(CASE WHEN asistencia.estado_entrada = 'RETARDO' THEN 1 ELSE 0 END) AS retardos
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
       AND asistencia.fecha_asistencia >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
       AND asistencia.fecha_asistencia < DATE_ADD(
           DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY),
           INTERVAL 6 DAY
       )
     GROUP BY WEEKDAY(asistencia.fecha_asistencia)"
);
$consultaSemana->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaSemana->execute();

foreach ($consultaSemana->fetchAll() as $dia) {
    $indice = (int) $dia['indice_dia'];
    $resumenSemana['puntuales'][$indice] = (int) $dia['puntuales'];
    $resumenSemana['retardos'][$indice] = (int) $dia['retardos'];
}

// Consultar fichas del instructor que tengan horario para hoy, con estado de jornada
date_default_timezone_set('America/Bogota');
$diaSemana = date('l');
$diasMap = [
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miercoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sabado',
    'Sunday' => 'Domingo'
];
$diaSemanaEs = $diasMap[$diaSemana] ?? $diaSemana;
$fechaHoy = date('Y-m-d');
$consultaFichasHoy = $conexion->prepare(
    "SELECT 
        f.id_ficha,
        f.nombre_programa,
        f.jornada,
        h.id_horario,
        h.entrada,
        h.salida,
        COUNT(a.id_aprendiz) AS total_aprendices
     FROM ficha f
     INNER JOIN horario h ON h.fk_ficha = f.id_ficha
     LEFT JOIN aprendiz a ON a.fk_ficha = f.id_ficha
     WHERE f.fk_usuario = :instructor_id
       AND h.dia_semana = :dia_semana
     GROUP BY f.id_ficha, f.nombre_programa, f.jornada, h.id_horario, h.entrada, h.salida
     ORDER BY h.entrada ASC"
);
$consultaFichasHoy->bindValue(':instructor_id', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaFichasHoy->bindValue(':dia_semana', $diaSemanaEs, PDO::PARAM_STR);
$consultaFichasHoy->execute();
$fichasConHorarioHoy = $consultaFichasHoy->fetchAll();

// Consultar estado de jornada para cada ficha
$consultaJornadas = $conexion->prepare(
    "SELECT id_sesion, ficha_id, horario_id, hora_apertura_real, hora_limite_puntualidad, hora_cierre_real, estado
     FROM sesiones_jornada
     WHERE instructor_id = :instructor_id
       AND fecha = CURDATE()
       AND estado IN ('abierta', 'cerrada_manual')
     ORDER BY hora_apertura_real DESC"
);
$consultaJornadas->bindValue(':instructor_id', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaJornadas->execute();
$jornadas = $consultaJornadas->fetchAll();

// Indexar jornadas por ficha_id
$jornadasPorFicha = [];
foreach ($jornadas as $j) {
    $jornadasPorFicha[$j['ficha_id']] = $j;
}

// Detectar entorno de desarrollo (localhost)
$host = $_SERVER['HTTP_HOST'] ?? '';
$esDesarrollo = ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, 'localhost') !== false);

ob_start();
?>
<!-- Tarjetas de estadísticas -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-layer-group"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Mis fichas</span>
        <span class="info-box-number"><?= $totalFichas ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena-oscuro">
      <span class="info-box-icon"><i class="fas fa-users"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Aprendices a cargo</span>
        <span class="info-box-number"><?= $totalAprendices ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Presentes hoy</span>
        <span class="info-box-number"><?= $presentesHoy ?></span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-file-medical"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Excusas pendientes</span>
        <span class="info-box-number"><?= $excusasPendientes ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Control de jornadas por ficha -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock text-sena"></i> Control de jornadas</h3>
            </div>
            <div class="card-body">
                <?php if (empty($fichasConHorarioHoy)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p class="mb-0">No tienes fichas con horario para hoy (<?= $diaSemanaEs ?>, <?= $fechaHoy ?>).</p>
                        <small class="text-muted d-block">PHP date('w')=<?= date('w') ?>, date('l')=<?= date('l') ?>, TZ=<?= date_default_timezone_get() ?></small>
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
                                                <strong>Apertura:</strong> <?= $aperturaFmt ?> |
                                                <strong>Límite puntualidad:</strong> <?= $limiteFmt ?>
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
    window.esDesarrollo = <?= $esDesarrollo ? 'true' : 'false' ?>;
</script>

<!-- Gráfica y registros del día -->
<div class="row">
  <div class="col-md-7 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-chart-line text-sena"></i> Asistencia de mi semana</h3>
      </div>
      <div class="card-body">
        <div style="height: 280px;">
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
            <?php if ($ultimosIngresosHoy === []): ?>
              <tr>
                <td colspan="3" class="text-center text-muted">No hay ingresos registrados hoy.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($ultimosIngresosHoy as $ingreso): ?>
                <tr>
                  <td><span class="badge badge-success"><?= htmlspecialchars($ingreso['hora']) ?></span></td>
                  <td><?= htmlspecialchars($ingreso['aprendiz']) ?></td>
                  <td>Ficha #<?= htmlspecialchars($ingreso['ficha']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
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
        <a href="<?= RUTA_BASE ?>views/instructor/reportes/index.php" class="btn btn-outline-sena btn-block tarjeta-accion py-3">
          <i class="fas fa-chart-bar fa-2x mb-2"></i><br>Reportes
        </a>
      </div>
    </div>
  </div>
</div>


<!-- SweetAlert2 CDN (solo para panel instructor) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                var aperturaFmt = datos.hora_apertura ? datos.hora_apertura.substring(0,5) : '--:--';
                var limiteFmt = datos.hora_limite_puntualidad ? datos.hora_limite_puntualidad.substring(0,5) : '--:--';
                if (!detalleDiv) {
                    detalleDiv = document.createElement('div');
                    detalleDiv.className = 'mb-2 p-2 bg-light rounded';
                    detalleDiv.innerHTML = '<small><strong>Apertura:</strong> ' + aperturaFmt + ' | <strong>Límite puntualidad:</strong> ' + limiteFmt + '</small>';
                    if (btnAbrir) btnAbrir.parentNode.insertBefore(detalleDiv, btnAbrir);
                } else {
                    detalleDiv.innerHTML = '<small><strong>Apertura:</strong> ' + aperturaFmt + ' | <strong>Límite puntualidad:</strong> ' + limiteFmt + '</small>';
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

<?php if ($esDesarrollo): ?>
<!-- Herramientas de desarrollo para prueba manual -->
<script>
    (function () {
        'use strict';

        var btnReiniciar = document.getElementById('btnReiniciarPrueba');
        var btnRegistrarDemo = document.getElementById('btnRegistrarDemo');
        var rutaBase = window.rutaBase || '';
        var tokenCsrf = window.tokenCsrf || '';

        // funcion para hacer POST/GET con CSRF
        function fetchConCsrf(url, options) {
            var opts = options || {};
            opts.headers = opts.headers || {};
            opts.headers['X-CSRF-Token'] = tokenCsrf;
            opts.headers['Content-Type'] = opts.headers['Content-Type'] || 'application/json';
            return fetch(rutaBase + url, opts).then(function (resp) {
                return resp.json();
            });
        }

        // mostrar/ocultar botón demo según estado de jornada
        function actualizarBotonDemo() {
            var jornada = window.jornadaActual;
            if (jornada && jornada.estado === 'abierta') {
                btnRegistrarDemo.style.display = 'inline-block';
            } else {
                btnRegistrarDemo.style.display = 'none';
            }
        }

        // REINICIAR JORNADA DE PRUEBA
        if (btnReiniciar) {
            btnReiniciar.addEventListener('click', function () {
                Swal.fire({
                    title: 'Reiniciar jornada de prueba',
                    text: 'Esto eliminará la sesión de jornada de hoy, permitiendo volver a abrirla. Las asistencias reales NO se borran.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, reiniciar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#ffc107',
                    cancelButtonColor: '#6c757d',
                    reverseButtons: true,
                    allowOutsideClick: true,
                    allowEscapeKey: true
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        title: 'Reiniciando...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function () { Swal.showLoading(); }
                    });

                    fetchConCsrf('controllers/reiniciar_jornada_prueba.php', {
                        method: 'POST',
                        body: JSON.stringify({ token_csrf: tokenCsrf })
                    })
                        .then(function (res) {
                            Swal.close();
                            if (res.success) {
                                // resetear estado UI
                                window.jornadaActual = null;
                                estadoInicial();
                                actualizarBotonDemo();

                                Swal.fire({
                                    title: 'Jornada de prueba reiniciada',
                                    text: res.message,
                                    icon: 'success',
                                    confirmButtonText: 'Entendido',
                                    confirmButtonColor: '#17a2b8',
                                    allowOutsideClick: true,
                                    allowEscapeKey: true
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: res.message || 'No se pudo reiniciar',
                                    icon: 'error',
                                    confirmButtonText: 'Entendido',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        })
                        .catch(function () {
                            Swal.close();
                            Swal.fire({
                                title: 'Error',
                                text: 'Error de comunicación con el servidor',
                                icon: 'error',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#dc3545'
                            });
                        });
                });
            });
        }

        // REGISTRAR ENTRADA DEMO
        if (btnRegistrarDemo) {
            btnRegistrarDemo.addEventListener('click', function () {
                // cargar aprendices del servidor
                Swal.fire({
                    title: 'Cargando aprendices...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetchConCsrf('controllers/obtener_aprendices_demo.php?token_csrf=' + encodeURIComponent(tokenCsrf), {
                    method: 'GET'
                })
                    .then(function (res) {
                        Swal.close();
                        if (!res.success) {
                            Swal.fire({
                                title: 'Error',
                                text: res.message || 'No se pudieron cargar los aprendices',
                                icon: 'error',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: '#dc3545'
                            });
                            return;
                        }

                        var aprendices = res.data.aprendices;
                        var sesionId = res.data.sesion_id;
                        var horaLimite = res.data.hora_limite_puntualidad;

                        if (!aprendices || aprendices.length === 0) {
                            Swal.fire({
                                title: 'Sin aprendices',
                                text: 'No hay aprendices registrados en la ficha de esta jornada',
                                icon: 'info',
                                confirmButtonText: 'Entendido'
                            });
                            return;
                        }

                        // construir opciones para select
                        var opcionesHtml = '<option value="">-- Seleccione un aprendiz --</option>';
                        aprendices.forEach(function (ap) {
                            opcionesHtml += '<option value="' + ap.id_aprendiz + '" data-rfid="' + ap.codigo_rfid + '">' +
                                ap.nombre + ' ' + ap.apellido + ' (RFID: ' + ap.codigo_rfid + ')' +
                                (ap.identificacion ? ' - Doc: ' + ap.identificacion : '') +
                                '</option>';
                        });

                        Swal.fire({
                            title: 'Registrar entrada demo',
                            html:
                                '<div class="form-group text-left">' +
                                '<label>Seleccione aprendiz:</label>' +
                                '<select id="selectAprendizDemo" class="form-control">' + opcionesHtml + '</select>' +
                                '<small class="form-text text-muted mt-1 d-block">Jornada abierta. Límite puntualidad: ' + (horaLimite ? horaLimite.split(' ')[1].substring(0,5) : '--:--') + '</small>' +
                                '</div>',
                            showCancelButton: true,
                            confirmButtonText: 'Registrar entrada',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#17a2b8',
                            cancelButtonColor: '#6c757d',
                            reverseButtons: true,
                            allowOutsideClick: true,
                            allowEscapeKey: true,
                            preConfirm: function () {
                                var select = document.getElementById('selectAprendizDemo');
                                var idAprendiz = select.value;
                                var rfid = select.options[select.selectedIndex].getAttribute('data-rfid');
                                if (!idAprendiz) {
                                    Swal.showValidationMessage('Debe seleccionar un aprendiz');
                                    return false;
                                }
                                return { id_aprendiz: idAprendiz, rfid: rfid };
                            }
                        }).then(function (result) {
                            if (!result.isConfirmed || !result.value) return;

                            var datos = result.value;

                            Swal.fire({
                                title: 'Registrando entrada...',
                                text: 'Por favor espere',
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: function () { Swal.showLoading(); }
                            });

                            // llamar al endpoint RFID existente
                            fetchConCsrf('controllers/registrar_asistencia_rfid.php', {
                                method: 'POST',
                                body: JSON.stringify({
                                    codigo_rfid: datos.rfid,
                                    tipo: 'entrada',
                                    token_csrf: tokenCsrf
                                })
                            })
                                .then(function (resRfid) {
                                    Swal.close();
                                    if (resRfid.success) {
                                        var d = resRfid.data;
                                        var estadoTexto = d.estado === 'PUNTUAL' ? 'PUNTUAL ✓' : 'RETARDO (' + d.minutos_retardo + ' min)';
                                        Swal.fire({
                                            title: 'Entrada registrada',
                                            html:
                                                '<div class="text-left">' +
                                                '<strong>Aprendiz:</strong> ' + d.aprendiz + '<br>' +
                                                '<strong>RFID:</strong> ' + d.rfid + '<br>' +
                                                '<strong>Hora:</strong> ' + d.hora + '<br>' +
                                                '<strong>Estado:</strong> <span class="badge badge-' + (d.estado === 'PUNTUAL' ? 'success' : 'warning') + '">' + estadoTexto + '</span>' +
                                                '</div>',
                                            icon: d.estado === 'PUNTUAL' ? 'success' : 'warning',
                                            confirmButtonText: 'Entendido',
                                            confirmButtonColor: '#17a2b8'
                                        });
                                    } else {
                                        Swal.fire({
                                            title: 'Error',
                                            text: resRfid.message || 'No se pudo registrar la entrada',
                                            icon: 'error',
                                            confirmButtonText: 'Entendido',
                                            confirmButtonColor: '#dc3545'
                                        });
                                    }
                                })
                                .catch(function () {
                                    Swal.close();
                                    Swal.fire({
                                        title: 'Error',
                                        text: 'Error de comunicación con el servidor',
                                        icon: 'error',
                                        confirmButtonText: 'Entendido',
                                        confirmButtonColor: '#dc3545'
                                    });
                                });
                        });
                    })
                    .catch(function () {
                        Swal.close();
                        Swal.fire({
                            title: 'Error',
                            text: 'Error de comunicación con el servidor',
                            icon: 'error',
                            confirmButtonText: 'Entendido',
                            confirmButtonColor: '#dc3545'
                        });
                    });
            });
        }

        // actualizar visibilidad del botón demo al inicializar y cuando cambia jornada
        var originalEstadoAbierta = window.estadoAbierta;
        var originalEstadoCerrada = window.estadoCerrada;
        var originalEstadoInicial = window.estadoInicial;

        window.estadoAbierta = function (jornada) {
            if (originalEstadoAbierta) originalEstadoAbierta(jornada);
            actualizarBotonDemo();
        };

        window.estadoCerrada = function (jornada) {
            if (originalEstadoCerrada) originalEstadoCerrada(jornada);
            actualizarBotonDemo();
        };

        window.estadoInicial = function () {
            if (originalEstadoInicial) originalEstadoInicial();
            actualizarBotonDemo();
        };

        // inicializar visibilidad
        document.addEventListener('DOMContentLoaded', function () {
            actualizarBotonDemo();
        });
    })();
</script>
<?php endif; ?>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_instructor.php';
