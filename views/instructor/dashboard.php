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

<!-- Control de jornada -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clock text-sena"></i> Control de jornada</h3>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span id="estadoJornada" class="badge badge-secondary px-3 py-2" style="font-size: 0.9rem;">
                        Jornada no iniciada
                    </span>
                    <button type="button" id="btnAbrirJornada" class="btn btn-success" aria-label="Abrir jornada">
                        <i class="fas fa-door-open mr-1"></i> Abrir jornada
                    </button>
                    <button type="button" id="btnCerrarJornada" class="btn btn-danger" disabled aria-label="Cerrar jornada" aria-disabled="true">
                        <i class="fas fa-door-closed mr-1"></i> Cerrar jornada
                    </button>
                    <!-- TODO: eliminar al implementar la logica real de apertura/cierre -->
                    <button type="button" id="btnPreviewCierre" class="btn btn-outline-secondary btn-sm ml-auto" aria-label="Vista previa de cierre" style="display: none;">
                        <i class="fas fa-eye mr-1"></i> Vista previa de cierre
                    </button>
                </div>
                <div class="mt-2 text-muted small">
                    Estado actual: <span id="textoEstado">Sin iniciar</span>
                </div>
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

<!-- Control de jornada - solo interfaz visual -->
<script>
    (function () {
        'use strict';

        var btnAbrir = document.getElementById('btnAbrirJornada');
        var btnCerrar = document.getElementById('btnCerrarJornada');
        var btnPreview = document.getElementById('btnPreviewCierre');
        var estadoBadge = document.getElementById('estadoJornada');
        var textoEstado = document.getElementById('textoEstado');

        // funcion para mostrar sweetalert de abrir jornada
        function mostrarModalAbrir() {
            Swal.fire({
                title: 'Abrir jornada',
                text: 'Al confirmar, los aprendices podran registrar su asistencia.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Si, abrir',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                allowOutsideClick: true,
                allowEscapeKey: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    // segundo sweetalert - interfaz preparada
                    Swal.fire({
                        title: 'Interfaz preparada',
                        text: 'La apertura real de jornada sera habilitada en la siguiente actualizacion.',
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#17a2b8',
                        allowOutsideClick: true,
                        allowEscapeKey: true
                    });
                    // no cambiar estados - mantener como estaba
                }
            });
        }

        // funcion para mostrar sweetalert de cerrar jornada
        function mostrarModalCerrar() {
            Swal.fire({
                title: 'Cerrar jornada',
                text: 'Al confirmar, los aprendices ya no podran registrar asistencia.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Si, cerrar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                allowOutsideClick: true,
                allowEscapeKey: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    // segundo sweetalert - interfaz preparada
                    Swal.fire({
                        title: 'Interfaz preparada',
                        text: 'El cierre real de jornada sera habilitado en una proxima actualizacion.',
                        icon: 'info',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#17a2b8',
                        allowOutsideClick: true,
                        allowEscapeKey: true
                    }).then(function () {
                        // restablecer a estado inicial
                        restablecerEstadoInicial();
                    });
                }
            });
        }

        // restablecer a estado inicial
        function restablecerEstadoInicial() {
            btnAbrir.disabled = false;
            btnAbrir.setAttribute('aria-disabled', 'false');
            btnCerrar.disabled = true;
            btnCerrar.setAttribute('aria-disabled', 'true');
            estadoBadge.className = 'badge badge-secondary px-3 py-2';
            estadoBadge.style.fontSize = '0.9rem';
            estadoBadge.textContent = 'Jornada no iniciada';
            textoEstado.textContent = 'Sin iniciar';
        }

        // evento abrir jornada
        if (btnAbrir) {
            btnAbrir.addEventListener('click', mostrarModalAbrir);
        }

        // evento cerrar jornada
        if (btnCerrar) {
            btnCerrar.addEventListener('click', mostrarModalCerrar);
        }

        // vista previa de cierre - solo para desarrollo
        // TODO: eliminar al implementar la logica real de apertura/cierre
        if (btnPreview) {
            btnPreview.addEventListener('click', function () {
                var habilitado = btnCerrar.disabled === false;
                btnCerrar.disabled = !habilitado;
                btnCerrar.setAttribute('aria-disabled', habilitado ? 'true' : 'false');
                btnPreview.textContent = habilitado
                    ? ' \u2022 Vista previa de cierre'
                    : ' \u2022 Vista previa de cierre (ACTIVO)';
                btnPreview.classList.toggle('btn-outline-secondary');
                btnPreview.classList.toggle('btn-outline-warning');
            });
        }

        // exponer btnPreview en window para prueba manual desde consola
        // TODO: eliminar al implementar la logica real de apertura/cierre
        window.__jornadaPreview = btnPreview;
    })();
</script>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_instructor.php';
