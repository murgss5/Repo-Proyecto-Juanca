<?php
/**
 * ============================================================
 * VISTA: MI HISTORIAL (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Mi historial';
$menuActivo   = 'historial';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi historial'],
];

function claseEstadoHistorial($estado) {
    switch ($estado) {
        case 'Presente':        return 'badge-success';
        case 'Retardo':         return 'badge-warning';
        case 'Salida temprana': return 'badge-info';
        case 'Inasistencia':    return 'badge-danger';
        default:                return 'badge-secondary';
    }
}

ob_start();
?>
<!-- Resumen del historial -->
<div class="row">
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-sena">
      <span class="info-box-icon"><i class="fas fa-user-check"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Asistencias</span>
        <span class="info-box-number">18</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-amarillo">
      <span class="info-box-icon"><i class="fas fa-clock"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Retardos</span>
        <span class="info-box-number">3</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-rojo">
      <span class="info-box-icon"><i class="fas fa-user-slash"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Inasistencias</span>
        <span class="info-box-number">2</span>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
    <div class="info-box info-box-azul">
      <span class="info-box-icon"><i class="fas fa-sign-out-alt"></i></span>
      <div class="info-box-content">
        <span class="info-box-text">Salidas tempranas</span>
        <span class="info-box-number">1</span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-history text-sena"></i> Historial de asistencia personal</h3>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-5 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar por fecha...">
        </div>
      </div>
      <div class="col-md-4 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="3" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Presente</option>
          <option>Retardo</option>
          <option>Salida temprana</option>
          <option>Inasistencia</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora entrada</th>
            <th>Hora salida</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($historialPersonal as $registro): ?>
            <tr>
              <td><?= htmlspecialchars($registro['fecha']) ?></td>
              <td><?= htmlspecialchars($registro['entrada']) ?></td>
              <td><?= htmlspecialchars($registro['salida']) ?></td>
              <td><span class="badge badge-estado <?= claseEstadoHistorial($registro['estado']) ?>"><?= htmlspecialchars($registro['estado']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Datos de demostración: la paginación, búsqueda y filtros funcionan visualmente.</p>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';
