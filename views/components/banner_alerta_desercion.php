<?php
/**
 * Componente Reutilizable: Banner de Alertas Tempranas de Deserción
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * -----------------------------------------------------------------------------
 * Parametros esperados:
 * @var array  $aprendicesEnRiesgo Lista de aprendices en riesgo calculados
 * @var string $urlGestionar       URL de destino al pulsar "Gestionar Alertas"
 */

if (empty($aprendicesEnRiesgo)) {
    return;
}

$criticosBanner = count(array_filter($aprendicesEnRiesgo, fn($a) => ($a['nivel_riesgo'] ?? '') === 'CRITICO'));
$advertenciasBanner = count(array_filter($aprendicesEnRiesgo, fn($a) => ($a['nivel_riesgo'] ?? '') === 'ADVERTENCIA'));
?>
<!-- ==================== BANNER COMPACTO: ALERTAS DE DESERCIÓN ==================== -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-left: 5px solid #dc3545 !important;">
      <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center">
        <div class="d-flex align-items-center mb-2 mb-md-0">
          <div class="mr-3 text-danger d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: #fee2e2; border-radius: 8px;">
            <i class="fas fa-exclamation-triangle fa-lg"></i>
          </div>
          <div>
            <h5 class="font-weight-bold text-dark mb-0">Alertas Tempranas de Deserción (Reglamento SENA)</h5>
            <small class="text-muted">
              Se han detectado aprendices en riesgo por inasistencias injustificadas según el Acuerdo 007/2012.
            </small>
          </div>
        </div>
        <div class="d-flex align-items-center flex-wrap">
          <span class="badge badge-danger bg-danger text-white px-2 py-1 mr-2 mb-1" style="font-size: 0.85rem;">
            <i class="fas fa-user-times mr-1"></i> <?= $criticosBanner ?> Críticos
          </span>
          <span class="badge badge-warning bg-warning text-dark px-2 py-1 mr-3 mb-1 font-weight-bold" style="font-size: 0.85rem;">
            <i class="fas fa-exclamation-circle mr-1"></i> <?= $advertenciasBanner ?> Advertencias
          </span>
          <a href="<?= htmlspecialchars($urlGestionar) ?>" class="btn btn-danger btn-sm shadow-sm font-weight-bold mb-1">
            <i class="fas fa-external-link-alt mr-1"></i> Gestionar Alertas y Notificar
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
