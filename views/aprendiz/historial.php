<?php
/**
 * ============================================================
 * VISTA: MI HISTORIAL (BITÁCORA DE ASISTENCIA)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Consulta cronológica de registros de asistencia, ingresos,
 * salidas y novedades del aprendiz autenticado.
 * ============================================================
 */

// Carga de autenticación y verificación de rol Aprendiz (rol 3)
require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);

require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

asegurarSesionIniciada();

// Variables del layout
$paginaTitulo = 'Mi historial';
$menuActivo   = 'historial';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi historial'],
];

$conexion = (new Conexion())->conectar();
$idUsuario = (int)($_SESSION['aprendiz']['id_usuario'] ?? 0);

// Consulta cronológica de asistencias del aprendiz autenticado
$consultaHistorial = $conexion->prepare(
    "SELECT
        asistencia.fecha_asistencia,
        DATE_FORMAT(asistencia.entrada, '%H:%i') AS entrada,
        DATE_FORMAT(asistencia.salida, '%H:%i') AS salida,
        asistencia.estado_entrada,
        asistencia.estado_salida
     FROM asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     WHERE aprendiz.fk_usuario = :id_usuario
     ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC"
);
$consultaHistorial->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
$consultaHistorial->execute();
$historialAprendiz = $consultaHistorial->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Conteo acumulado real del historial
$totalRetardos = 0;
$totalInasistencias = 0;

foreach ($historialAprendiz as $reg) {
    if ($reg['estado_entrada'] === 'RETARDO') {
        $totalRetardos++;
    }
    if ($reg['estado_entrada'] === 'INASISTENCIA') {
        $totalInasistencias++;
    }
}

// Diccionarios de formato de fecha en español
$diasSemanaEspanol = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
];

$mesesEspanol = [
    1 => 'enero',
    2 => 'febrero',
    3 => 'marzo',
    4 => 'abril',
    5 => 'mayo',
    6 => 'junio',
    7 => 'julio',
    8 => 'agosto',
    9 => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre',
];

$mesesCortosEspanol = [
    1 => 'ENE',
    2 => 'FEB',
    3 => 'MAR',
    4 => 'ABR',
    5 => 'MAY',
    6 => 'JUN',
    7 => 'JUL',
    8 => 'AGO',
    9 => 'SEP',
    10 => 'OCT',
    11 => 'NOV',
    12 => 'DIC',
];

ob_start();
?>

<!-- Estilos exclusivos: Panel integrado de historial de asistencia -->
<style>
/* 1. Panel contenedor principal integrado */
.hist-apr-panel {
  background-color: #ffffff;
  border: 1px solid #E2E8F0;
  border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

/* 2. Cabecera del panel */
.hist-apr-panel-header {
  background-color: #ffffff;
  border-bottom: 1px solid #E2E8F0;
}

.hist-apr-panel-title {
  font-size: 1.0625rem;
  font-weight: 700;
  color: #1E293B;
  line-height: 1.3;
}

.hist-apr-panel-subtitle {
  font-size: 0.8125rem;
  color: #64748B;
}

/* Franja compacta de resumen acumulado */
.hist-apr-resumen-strip {
  background-color: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  padding: 0.35rem 0.65rem;
}

.hist-apr-resumen-label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #64748B;
}

.hist-apr-chip {
  display: inline-flex;
  align-items: center;
  font-size: 0.8125rem;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  font-weight: 500;
  line-height: 1.2;
}

.hist-apr-chip-retardo {
  background-color: #FFFBEB;
  color: #D97706;
  border: 1px solid #FDE68A;
}

.hist-apr-chip-inasistencia {
  background-color: #FEF2F2;
  color: #DC2626;
  border: 1px solid #FECACA;
}

/* 3. Barra de herramientas integrada */
.hist-apr-toolbar {
  background-color: #F8FAFC;
  border-bottom: 1px solid #E2E8F0;
}

.hist-apr-label {
  display: block;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1E293B;
  margin-bottom: 0.35rem;
}

.hist-apr-control {
  height: 42px;
  border: 1px solid #CBD5E1;
  border-radius: 8px;
  font-size: 0.875rem;
  color: #1E293B;
  background-color: #ffffff;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.hist-apr-control:focus {
  border-color: #39a900;
  box-shadow: 0 0 0 3px rgba(57, 169, 0, 0.15);
  outline: none;
}

/* Grupo de fecha con botón de calendario integrado */
.hist-apr-date-group {
  position: relative;
  display: flex;
  align-items: stretch;
  width: 100%;
}

.hist-apr-prepend-icon {
  border: 1px solid #CBD5E1;
  border-right: none;
  border-radius: 8px 0 0 8px;
  background-color: #ffffff;
  padding: 0 0.75rem;
  font-size: 0.8125rem;
  display: flex;
  align-items: center;
}

.hist-apr-date-input {
  height: 42px;
  border: 1px solid #CBD5E1;
  border-left: none;
  border-right: none;
  border-radius: 0;
  font-size: 0.875rem;
  color: #1E293B;
  background-color: #ffffff;
}

.hist-apr-date-input:focus {
  border-color: #39a900;
  box-shadow: none;
  outline: none;
}

.hist-apr-date-group:focus-within {
  box-shadow: 0 0 0 3px rgba(57, 169, 0, 0.15);
  border-radius: 8px;
}

.hist-apr-date-group:focus-within .hist-apr-prepend-icon,
.hist-apr-date-group:focus-within .hist-apr-date-input,
.hist-apr-date-group:focus-within .hist-apr-btn-calendar {
  border-color: #39a900;
}

.hist-apr-btn-calendar {
  height: 42px;
  border: 1px solid #CBD5E1;
  border-left: none;
  border-radius: 0 8px 8px 0;
  background-color: #ffffff;
  color: #39a900;
  padding: 0 0.85rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}

.hist-apr-btn-calendar:hover,
.hist-apr-btn-calendar:focus {
  background-color: #F8FDF6;
  color: #2e8500;
  border-color: #CBD5E1;
  outline: none;
}

/* Selector nativo con posicionamiento para showPicker y fallback */
.hist-apr-date-native {
  position: absolute;
  right: 0;
  bottom: 0;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: 0;
  border: none;
  opacity: 0;
  pointer-events: none;
  z-index: -1;
}

/* Alternativa funcional / fallback cuando no se soporta showPicker */
.hist-apr-date-native.hist-apr-fallback-visible {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  bottom: auto;
  width: auto;
  height: 40px;
  padding: 0.25rem 0.5rem;
  border: 1px solid #CBD5E1;
  border-radius: 8px;
  background-color: #ffffff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
  opacity: 1;
  pointer-events: auto;
  z-index: 1050;
}

.hist-apr-btn-limpiar {
  height: 42px;
  padding: 0 1.25rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  border-color: #CBD5E1;
  color: #475569;
  background-color: #ffffff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.hist-apr-btn-limpiar:hover {
  background-color: #F1F5F9;
  border-color: #94A3B8;
  color: #0F172A;
}

/* 4. Tabla de registros tipo bitácora */
.hist-apr-table {
  border-collapse: separate;
  border-spacing: 0;
  width: 100% !important;
}

.hist-apr-table thead th {
  background-color: #F8FAFC;
  color: #475569;
  font-size: 0.8125rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.9rem 1.5rem;
  border-bottom: 1px solid #E2E8F0;
  border-top: none;
}

.hist-apr-table tbody tr {
  transition: background-color 0.15s ease;
}

.hist-apr-table tbody tr:hover {
  background-color: #F8FDF6 !important;
}

.hist-apr-table tbody td {
  padding: 1.1rem 1.5rem;
  vertical-align: middle;
  border-top: 1px solid #F1F5F9;
  font-size: 0.875rem;
}

/* Bloque de fecha tipo calendario */
.hist-apr-cal-block {
  width: 48px;
  height: 52px;
  min-width: 48px;
  background-color: #DCFCE7;
  border: 1px solid #BBF7D0;
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 2px 4px;
}

.hist-apr-cal-mes {
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  color: #166534;
  letter-spacing: 0.04em;
  line-height: 1;
}

.hist-apr-cal-dia {
  font-size: 1.25rem;
  font-weight: 700;
  color: #166534;
  line-height: 1.1;
}

.hist-apr-fecha-dia-semana {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #1E293B;
  line-height: 1.25;
}

.hist-apr-fecha-detalle {
  font-size: 0.8125rem;
  color: #64748B;
  line-height: 1.2;
}

/* Horas tabulares con tipografía monoespaciada */
.hist-apr-hora {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size: 0.9375rem;
  font-weight: 600;
  color: #1E293B;
  font-variant-numeric: tabular-nums;
}

.hist-apr-hora-icon {
  font-size: 0.8125rem;
  opacity: 0.85;
}

.hist-apr-sin-registro {
  font-size: 0.875rem;
  font-style: italic;
  color: #94A3B8;
}

/* Badges de estado con fondo suave y contraste */
.hist-apr-badge {
  font-size: 0.8125rem;
  font-weight: 500;
  padding: 0.35rem 0.65rem;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  line-height: 1.2;
}

.hist-apr-badge-puntual {
  background-color: #DCFCE7;
  color: #166534;
  border: 1px solid #BBF7D0;
}

.hist-apr-badge-retardo {
  background-color: #FEF3C7;
  color: #92400E;
  border: 1px solid #FDE68A;
}

.hist-apr-badge-inasistencia {
  background-color: #FEE2E2;
  color: #991B1B;
  border: 1px solid #FECACA;
}

.hist-apr-badge-salida-ant {
  background-color: #EFF6FF;
  color: #1E40AF;
  border: 1px solid #BFDBFE;
}

/* 5. Integración de DataTables dentro del panel */
.hist-apr-panel .dataTables_wrapper {
  padding: 0;
}

.hist-apr-panel .dataTables_wrapper > .row:first-child {
  padding: 0.75rem 1.5rem 0.5rem;
  margin: 0;
  align-items: center;
}

.hist-apr-panel .dataTables_wrapper > .row:first-child .dataTables_length label {
  font-size: 0.8125rem;
  color: #64748B;
  margin-bottom: 0;
  display: inline-flex;
  align-items: center;
}

.hist-apr-panel .dataTables_wrapper > .row:first-child .dataTables_length select {
  height: 32px;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
  border: 1px solid #CBD5E1;
  margin: 0 0.5rem;
  font-size: 0.8125rem;
}

.hist-apr-panel .dataTables_wrapper > .row:last-child {
  padding: 1rem 1.5rem;
  margin: 0;
  border-top: 1px solid #E2E8F0;
  background-color: #ffffff;
  align-items: center;
}

.hist-apr-panel .dataTables_info {
  font-size: 0.8125rem;
  color: #64748B;
  padding-top: 0 !important;
}

.hist-apr-panel .dataTables_paginate {
  padding-top: 0 !important;
}

.hist-apr-panel .pagination {
  margin-bottom: 0;
  justify-content: flex-end;
}

.hist-apr-panel .page-item .page-link {
  color: #475569;
  border-color: #E2E8F0;
  font-size: 0.8125rem;
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  margin: 0 2px;
}

.hist-apr-panel .page-item .page-link:hover {
  background-color: #F1F5F9;
  color: #1E293B;
  border-color: #CBD5E1;
}

.hist-apr-panel .page-item.active .page-link {
  background-color: #39a900 !important;
  border-color: #39a900 !important;
  color: #ffffff !important;
  font-weight: 600;
}

.hist-apr-panel .page-item.disabled .page-link {
  color: #94A3B8;
  background-color: #F8FAFC;
  border-color: #E2E8F0;
}

@media (max-width: 767.98px) {
  .hist-apr-panel-header,
  .hist-apr-toolbar,
  .hist-apr-panel .dataTables_wrapper > .row:last-child {
    padding-left: 1rem;
    padding-right: 1rem;
  }
  .hist-apr-table thead th,
  .hist-apr-table tbody td {
    padding: 0.75rem 0.75rem;
  }
  .hist-apr-resumen-strip {
    width: 100%;
    justify-content: space-between;
  }
  .hist-apr-btn-limpiar {
    width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .hist-apr-table tbody tr,
  .hist-apr-control,
  .hist-apr-btn-limpiar {
    transition: none;
  }
}
</style>

<!-- Subtítulo único bajo el encabezado de página -->
<p class="text-muted mb-3" style="font-size: 0.9375rem; margin-top: -0.25rem;">
  Tus registros de asistencia, organizados por fecha
</p>

<?php if (empty($historialAprendiz)): ?>
  <!-- Panel integrado: Estado cuando no existen registros en el sistema -->
  <div class="card hist-apr-panel mb-4">
    <!-- Cabecera del panel -->
    <div class="hist-apr-panel-header px-4 py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between border-bottom">
      <div class="mb-2 mb-md-0">
        <h2 class="hist-apr-panel-title mb-0">Registro de asistencia</h2>
        <span class="hist-apr-panel-subtitle">Ordenado del más reciente al más antiguo</span>
      </div>
      <div class="hist-apr-resumen-strip d-inline-flex flex-wrap align-items-center">
        <span class="hist-apr-resumen-label mr-2">Acumulado del historial:</span>
        <span class="hist-apr-chip hist-apr-chip-retardo mr-2" title="Total acumulado de retardos">
          <i class="fas fa-clock mr-1" aria-hidden="true"></i>Retardos: <strong class="ml-1">0</strong>
        </span>
        <span class="hist-apr-chip hist-apr-chip-inasistencia" title="Total acumulado de inasistencias">
          <i class="fas fa-user-times mr-1" aria-hidden="true"></i>Inasistencias: <strong class="ml-1">0</strong>
        </span>
      </div>
    </div>
    <!-- Cuerpo con estado vacío -->
    <div class="text-center py-5 px-4">
      <div class="mb-3 text-muted">
        <i class="fas fa-calendar-times fa-3x" style="color: #CBD5E1;" aria-hidden="true"></i>
      </div>
      <h3 class="h5 font-weight-bold text-dark mb-1">Aún no tienes registros en tu historial</h3>
      <p class="text-muted small mb-0">Cuando se registre tu asistencia, podrás consultarla aquí.</p>
    </div>
  </div>
<?php else: ?>
  <!-- Panel integrado: Bitácora de asistencia con herramientas y listado -->
  <div class="card hist-apr-panel mb-4">
    <!-- A. Cabecera del panel -->
    <div class="hist-apr-panel-header px-4 py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between border-bottom">
      <div class="mb-2 mb-md-0">
        <h2 class="hist-apr-panel-title mb-0">Registro de asistencia</h2>
        <span class="hist-apr-panel-subtitle">Ordenado del más reciente al más antiguo</span>
      </div>
      <div class="hist-apr-resumen-strip d-inline-flex flex-wrap align-items-center">
        <span class="hist-apr-resumen-label mr-2">Acumulado del historial:</span>
        <span class="hist-apr-chip hist-apr-chip-retardo mr-2" title="Total acumulado de retardos">
          <i class="fas fa-clock mr-1" aria-hidden="true"></i>Retardos: <strong class="ml-1"><?= (int)$totalRetardos ?></strong>
        </span>
        <span class="hist-apr-chip hist-apr-chip-inasistencia" title="Total acumulado de inasistencias">
          <i class="fas fa-user-times mr-1" aria-hidden="true"></i>Inasistencias: <strong class="ml-1"><?= (int)$totalInasistencias ?></strong>
        </span>
      </div>
    </div>

    <!-- B. Barra de consulta (filtros) -->
    <div class="hist-apr-toolbar px-4 py-3 border-bottom">
      <div class="row align-items-end">
        <!-- Búsqueda y selector de fecha (~45% en escritorio) -->
        <div class="col-lg-5 col-md-5 col-12 mb-3 mb-md-0">
          <label for="buscarFechaHistorial" class="hist-apr-label">Fecha</label>
          <div class="input-group hist-apr-date-group">
            <div class="input-group-prepend">
              <span class="input-group-text hist-apr-prepend-icon bg-white text-muted">
                <i class="fas fa-search fa-sm" aria-hidden="true"></i>
              </span>
            </div>
            <input type="text"
                   id="buscarFechaHistorial"
                   class="form-control hist-apr-control hist-apr-date-input"
                   placeholder="Buscar fecha o DD/MM/AAAA..."
                   autocomplete="off">
            <div class="input-group-append">
              <button type="button"
                      class="btn btn-outline-secondary hist-apr-btn-calendar"
                      id="btnAbrirCalendario"
                      title="Elegir fecha"
                      aria-label="Elegir fecha">
                <i class="fas fa-calendar-alt text-sena" aria-hidden="true"></i>
              </button>
            </div>
            <!-- Selector nativo de fecha accesible -->
            <input type="date"
                   id="pickerFechaNativo"
                   class="hist-apr-date-native"
                   tabindex="-1"
                   aria-label="Elegir fecha desde calendario">
          </div>
        </div>

        <!-- Filtro por estado (~35% en escritorio) -->
        <div class="col-lg-4 col-md-4 col-sm-8 col-12 mb-3 mb-md-0">
          <label for="filtroEstadoHistorial" class="hist-apr-label">Estado</label>
          <select id="filtroEstadoHistorial"
                  class="form-control filtro-tabla hist-apr-control"
                  data-col="3"
                  data-exacto="false">
            <option value="">Todos los estados</option>
            <option value="Puntual">Puntual</option>
            <option value="Retardo">Retardo</option>
            <option value="Inasistencia">Inasistencia</option>
            <option value="Salida anticipada">Salida anticipada</option>
          </select>
        </div>

        <!-- Botón Limpiar con tamaño natural (~20% o auto) -->
        <div class="col-lg-3 col-md-3 col-sm-4 col-12 d-flex align-items-end justify-content-md-end">
          <button type="button"
                  class="btn btn-outline-secondary hist-apr-btn-limpiar"
                  id="btnLimpiarFiltrosHistorial"
                  title="Restablecer filtros">
            <i class="fas fa-times fa-sm mr-1" aria-hidden="true"></i>Limpiar
          </button>
        </div>
      </div>
    </div>

    <!-- C. Tabla de registros -->
    <div class="table-responsive">
      <table class="table mb-0 tabla-datos hist-apr-table" data-empty-message="No encontramos registros con esos filtros.">
        <thead>
          <tr>
            <th scope="col" style="width: 32%;">Fecha</th>
            <th scope="col" style="width: 20%;">Entrada</th>
            <th scope="col" style="width: 20%;">Salida</th>
            <th scope="col" style="width: 28%;" class="text-center">Estado y novedades</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historialAprendiz as $reg): ?>
            <?php
              $timestamp = strtotime($reg['fecha_asistencia']);
              $diaSemana = $diasSemanaEspanol[(int)date('w', $timestamp)] ?? '';
              $diaNum    = date('d', $timestamp);
              $mesNombre = $mesesEspanol[(int)date('n', $timestamp)] ?? '';
              $mesCorto  = $mesesCortosEspanol[(int)date('n', $timestamp)] ?? '';
              $anioNum   = date('Y', $timestamp);
              $fechaIso  = $reg['fecha_asistencia'];
              $fechaCortaEs = date('d/m/Y', $timestamp);
              $diaSinTilde = str_replace(['é', 'á'], ['e', 'a'], mb_strtolower($diaSemana, 'UTF-8'));
              $textoBusquedaFecha = $diaSemana . ' ' . $diaSinTilde . ' ' . $diaNum . ' ' . $mesNombre . ' ' . $mesCorto . ' ' . $anioNum . ' ' . $fechaIso . ' ' . $fechaCortaEs;

              // Identificación de estados
              $badges = [];
              $searchEstadoTerms = [];

              if ($reg['estado_entrada'] === 'INASISTENCIA') {
                  $badges[] = '<span class="badge hist-apr-badge hist-apr-badge-inasistencia"><i class="fas fa-user-times mr-1" aria-hidden="true"></i>Inasistencia</span>';
                  $searchEstadoTerms[] = 'Inasistencia';
              } elseif ($reg['estado_entrada'] === 'RETARDO') {
                  $badges[] = '<span class="badge hist-apr-badge hist-apr-badge-retardo"><i class="fas fa-clock mr-1" aria-hidden="true"></i>Retardo</span>';
                  $searchEstadoTerms[] = 'Retardo';
              } else {
                  $badges[] = '<span class="badge hist-apr-badge hist-apr-badge-puntual"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i>Puntual</span>';
                  $searchEstadoTerms[] = 'Puntual';
              }

              if ($reg['estado_salida'] === 'SALIDA_ANTICIPADA') {
                  $badges[] = '<span class="badge hist-apr-badge hist-apr-badge-salida-ant"><i class="fas fa-sign-out-alt mr-1" aria-hidden="true"></i>Salida anticipada</span>';
                  $searchEstadoTerms[] = 'Salida anticipada';
              }

              $badgeSearchText = implode(' ', $searchEstadoTerms);
              $orderValue = $fechaIso . ' ' . (!empty($reg['entrada']) ? $reg['entrada'] : '00:00');
            ?>
            <tr>
              <!-- Columna 1: Fecha (32%) con bloque calendario y detalle -->
              <td data-order="<?= htmlspecialchars($orderValue) ?>" data-search="<?= htmlspecialchars($textoBusquedaFecha) ?>">
                <div class="d-flex align-items-center">
                  <div class="hist-apr-cal-block mr-3" aria-hidden="true">
                    <span class="hist-apr-cal-mes"><?= htmlspecialchars($mesCorto) ?></span>
                    <span class="hist-apr-cal-dia"><?= htmlspecialchars($diaNum) ?></span>
                  </div>
                  <div>
                    <strong class="d-block hist-apr-fecha-dia-semana">
                      <?= htmlspecialchars($diaSemana) ?>
                    </strong>
                    <span class="hist-apr-fecha-detalle text-muted">
                      <?= htmlspecialchars($diaNum) ?> de <?= htmlspecialchars($mesNombre) ?>, <?= htmlspecialchars($anioNum) ?>
                    </span>
                  </div>
                </div>
              </td>

              <!-- Columna 2: Entrada (20%) con hora tabular e icono -->
              <td data-search="<?= !empty($reg['entrada']) ? htmlspecialchars($reg['entrada']) : '' ?>">
                <?php if (!empty($reg['entrada'])): ?>
                  <div class="d-inline-flex align-items-center hist-apr-hora">
                    <i class="fas fa-sign-in-alt hist-apr-hora-icon text-success mr-2" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($reg['entrada']) ?></span>
                  </div>
                <?php else: ?>
                  <span class="hist-apr-sin-registro">Sin registro</span>
                <?php endif; ?>
              </td>

              <!-- Columna 3: Salida (20%) con hora tabular e icono -->
              <td data-search="<?= !empty($reg['salida']) ? htmlspecialchars($reg['salida']) : '' ?>">
                <?php if (!empty($reg['salida'])): ?>
                  <div class="d-inline-flex align-items-center hist-apr-hora">
                    <i class="fas fa-sign-out-alt hist-apr-hora-icon text-info mr-2" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($reg['salida']) ?></span>
                  </div>
                <?php else: ?>
                  <span class="hist-apr-sin-registro">Sin registro</span>
                <?php endif; ?>
              </td>

              <!-- Columna 4: Estado y novedades (28%) -->
              <td class="text-center" data-search="<?= htmlspecialchars($badgeSearchText) ?>">
                <div class="d-inline-flex flex-wrap align-items-center justify-content-center" style="gap: 0.35rem;">
                  <?= implode('', $badges) ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Script interactivo para filtro de fecha, selector de calendario, estado y limpieza -->
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var inputFecha = document.getElementById('buscarFechaHistorial');
    var btnCalendar = document.getElementById('btnAbrirCalendario');
    var pickerNativo = document.getElementById('pickerFechaNativo');
    var selectEstado = document.getElementById('filtroEstadoHistorial');
    var btnLimpiar = document.getElementById('btnLimpiarFiltrosHistorial');

    // Función auxiliar para obtener la instancia existente de DataTables
    function obtenerDataTable() {
      if (window.jQuery && $.fn.DataTable && $.fn.DataTable.isDataTable('.tabla-datos')) {
        return $('.tabla-datos').DataTable();
      }
      return null;
    }

    // 1. Escritura manual en el campo de fecha
    if (inputFecha) {
      inputFecha.addEventListener('input', function () {
        var texto = this.value.trim();
        var dt = obtenerDataTable();

        // Comprobar si coincide con formato DD/MM/AAAA (ej. 14/10/2026)
        var matchDdmmaaaa = texto.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        // Comprobar si coincide con formato ISO YYYY-MM-DD (ej. 2026-10-14)
        var matchIso = texto.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);

        if (matchDdmmaaaa) {
          var d = matchDdmmaaaa[1].padStart(2, '0');
          var m = matchDdmmaaaa[2].padStart(2, '0');
          var y = matchDdmmaaaa[3];
          var isoSync = y + '-' + m + '-' + d;
          if (pickerNativo) {
            pickerNativo.value = isoSync;
          }
          if (dt) {
            // Filtrar exactamente ese día mediante su valor ISO
            dt.column(0).search(isoSync).draw();
          }
        } else if (matchIso) {
          var yIso = matchIso[1];
          var mIso = matchIso[2].padStart(2, '0');
          var dIso = matchIso[3].padStart(2, '0');
          var isoSync2 = yIso + '-' + mIso + '-' + dIso;
          if (pickerNativo) {
            pickerNativo.value = isoSync2;
          }
          if (dt) {
            dt.column(0).search(isoSync2).draw();
          }
        } else {
          // Si el usuario escribe texto libre (mes, día semana, año o modifica texto),
          // se limpia la selección del calendario para no dejar ningún filtro anterior oculto
          if (pickerNativo) {
            pickerNativo.value = '';
          }
          if (dt) {
            dt.column(0).search(texto).draw();
          }
        }

        if (pickerNativo) {
          pickerNativo.classList.remove('hist-apr-fallback-visible');
        }
      });
    }

    // 2. Botón de calendario (apertura nativa o alternativa funcional)
    if (btnCalendar && pickerNativo) {
      btnCalendar.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        // Sincronizar fecha si el input ya contiene un valor DD/MM/AAAA válido
        if (inputFecha) {
          var textoActual = inputFecha.value.trim();
          var matchFecha = textoActual.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
          if (matchFecha) {
            var d = matchFecha[1].padStart(2, '0');
            var m = matchFecha[2].padStart(2, '0');
            var y = matchFecha[3];
            pickerNativo.value = y + '-' + m + '-' + d;
          }
        }

        // Intento 1: abrir mediante API nativa showPicker() (navegadores modernos)
        if (typeof pickerNativo.showPicker === 'function') {
          try {
            pickerNativo.showPicker();
            return;
          } catch (err) {
            // Si el contexto o navegador no permite showPicker(), continuar a alternativa funcional
          }
        }

        // Alternativa funcional: mostrar selector nativo flotante posicionado bajo el botón
        pickerNativo.classList.toggle('hist-apr-fallback-visible');
        if (pickerNativo.classList.contains('hist-apr-fallback-visible')) {
          pickerNativo.focus();
        }
      });

      // Al seleccionar una fecha desde el calendario
      pickerNativo.addEventListener('change', function () {
        var isoVal = this.value;
        if (isoVal && isoVal.indexOf('-') !== -1) {
          // Conversión por cadenas evitando objetos Date y desfasajes de zona horaria
          var partes = isoVal.split('-');
          if (partes.length === 3) {
            var anio = partes[0];
            var mes  = partes[1];
            var dia  = partes[2];
            var fechaFormateada = dia + '/' + mes + '/' + anio;

            if (inputFecha) {
              inputFecha.value = fechaFormateada;
            }

            var dt = obtenerDataTable();
            if (dt) {
              // Filtrar exactamente ese día mediante su valor ISO YYYY-MM-DD en la columna Fecha
              dt.column(0).search(isoVal).draw();
            }
          }
        }
        this.classList.remove('hist-apr-fallback-visible');
      });

      // Tecla Escape para cerrar la alternativa funcional
      pickerNativo.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          pickerNativo.classList.remove('hist-apr-fallback-visible');
          if (inputFecha) {
            inputFecha.focus();
          }
        }
      });

      // Clic exterior cierra la alternativa funcional
      document.addEventListener('click', function (e) {
        if (!pickerNativo.contains(e.target) && !btnCalendar.contains(e.target)) {
          pickerNativo.classList.remove('hist-apr-fallback-visible');
        }
      });
    }

    // 3. Botón "Limpiar" restablece texto, calendario y estado
    if (btnLimpiar) {
      btnLimpiar.addEventListener('click', function () {
        if (inputFecha) {
          inputFecha.value = '';
        }
        if (pickerNativo) {
          pickerNativo.value = '';
          pickerNativo.classList.remove('hist-apr-fallback-visible');
        }
        if (selectEstado) {
          selectEstado.value = '';
        }

        var dt = obtenerDataTable();
        if (dt) {
          dt.search('').columns().search('').draw();
        }
      });
    }

    // 4. Personalización del mensaje cuando la búsqueda/filtro no encuentra coincidencias
    var dtInstance = obtenerDataTable();
    if (dtInstance) {
      dtInstance.on('draw', function () {
        var info = dtInstance.page.info();
        var emptyCell = $('.tabla-datos tbody tr td.dataTables_empty');
        if (info && info.recordsDisplay === 0 && info.recordsTotal > 0 && emptyCell.length) {
          emptyCell.html(
            '<div class="py-4 text-center">' +
            '  <i class="fas fa-search fa-2x text-muted mb-2 d-block" aria-hidden="true"></i>' +
            '  <strong class="d-block text-dark mb-1">No encontramos registros con esos filtros</strong>' +
            '  <p class="text-muted small mb-2">Prueba cambiando la fecha o el estado seleccionado.</p>' +
            '  <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltrosVacio">' +
            '    <i class="fas fa-times mr-1"></i> Limpiar filtros' +
            '  </button>' +
            '</div>'
          );
          var btnVacio = document.getElementById('btnLimpiarFiltrosVacio');
          if (btnVacio && btnLimpiar) {
            btnVacio.addEventListener('click', function () {
              btnLimpiar.click();
            });
          }
        }
      });
    }
  });
  </script>
<?php endif; ?>

<?php
$contenido = ob_get_clean();

// Render del layout del panel de aprendiz
include __DIR__ . '/../layouts/layout_aprendiz.php';
