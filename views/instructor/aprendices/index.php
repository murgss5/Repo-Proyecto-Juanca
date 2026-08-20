<?php
/**
 * ============================================================
 * VISTA: APRENDICES DE MIS FICHAS (INSTRUCTOR)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Aprendices de mis fichas';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Aprendices de mis fichas'],
];

// Fichas del instructor (demostración)
$fichasInstructor = ['2693854', '2743910', '2890345'];

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-users text-sena"></i> Aprendices a cargo</h3>
  </div>

  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-5 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar aprendiz...">
        </div>
      </div>
      <div class="col-md-4 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="3">
          <option value="">Todas mis fichas</option>
          <?php foreach ($fichasInstructor as $numero): ?>
            <option value="<?= htmlspecialchars($numero) ?>"><?= htmlspecialchars($numero) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="4" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Activo</option>
          <option>Suspendido</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Nombre completo</th>
            <th>Correo</th>
            <th>Ficha</th>
            <th>Estado</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendices as $aprendiz): ?>
            <?php if (!in_array($aprendiz['ficha'], $fichasInstructor)) { continue; } ?>
            <tr>
              <td><?= htmlspecialchars($aprendiz['documento']) ?></td>
              <td><?= htmlspecialchars($aprendiz['nombre']) ?></td>
              <td><?= htmlspecialchars($aprendiz['correo']) ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($aprendiz['ficha']) ?></span></td>
              <td>
                <span class="badge badge-estado <?= $aprendiz['estado'] === 'Activo' ? 'badge-success' : 'badge-warning' ?>">
                  <?= htmlspecialchars($aprendiz['estado']) ?>
                </span>
              </td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-info" data-toggle="modal"
                          data-target="#modalVer" data-nombre="<?= htmlspecialchars($aprendiz['nombre']) ?>"
                          title="Ver"><i class="fas fa-eye"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Datos de demostración: la paginación, búsqueda y filtros funcionan visualmente.</p>
  </div>
</div>

<!-- Modal: ver aprendiz -->
<div class="modal fade" id="modalVer" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-user text-sena"></i> Detalle del aprendiz</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p class="mb-1"><i class="fas fa-user text-sena"></i> <strong>Nombre:</strong> <span class="nombre-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-id-card text-sena"></i> <strong>Documento:</strong> 1002345678</p>
        <p class="mb-1"><i class="fas fa-envelope text-sena"></i> <strong>Correo:</strong> jdperez@misena.edu.co</p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Ficha:</strong> 2693854</p>
        <p class="mb-1"><i class="fas fa-rss text-sena"></i> <strong>RFID:</strong> RF-2026-001</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_instructor.php';
