<?php
/**
 * ============================================================
 * VISTA: APRENDICES DE MIS FICHAS (INSTRUCTOR)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Lista los aprendices reales de las fichas asignadas al instructor.
 * ============================================================
 */

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// --- Variables del layout ---
$paginaTitulo = 'Aprendices de mis fichas';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Aprendices de mis fichas'],
];

$conexion = (new Conexion())->conectar();

// Las fichas se consultan por separado para que el filtro muestre incluso las vacías.
$consultaFichas = $conexion->prepare(
    'SELECT id_ficha
     FROM ficha
     WHERE fk_usuario = :id_instructor
     ORDER BY id_ficha'
);
$consultaFichas->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaFichas->execute();
$fichasInstructor = $consultaFichas->fetchAll();

$consultaAprendices = $conexion->prepare(
    'SELECT
        usuario.identificacion AS documento,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
        usuario.telefono,
        aprendiz.codigo_rfid,
        ficha.id_ficha
     FROM aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
     ORDER BY usuario.apellido, usuario.nombre'
);
$consultaAprendices->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaAprendices->execute();
$aprendicesInstructor = $consultaAprendices->fetchAll();

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
        <select class="form-control filtro-tabla" data-col="4">
          <option value="">Todas mis fichas</option>
          <?php foreach ($fichasInstructor as $ficha): ?>
            <option value="<?= htmlspecialchars($ficha['id_ficha']) ?>">Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Documento</th>
            <th>Nombre completo</th>
            <th>Teléfono</th>
            <th>RFID</th>
            <th>Ficha</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($aprendicesInstructor === []): ?>
            <tr><td colspan="6" class="text-center text-muted">No hay aprendices registrados en sus fichas.</td></tr>
          <?php else: ?>
          <?php foreach ($aprendicesInstructor as $aprendiz): ?>
            <tr>
              <td><?= htmlspecialchars($aprendiz['documento']) ?></td>
              <td><?= htmlspecialchars($aprendiz['nombre']) ?></td>
              <td><?= htmlspecialchars($aprendiz['telefono'] ?? 'Sin registro') ?></td>
              <td><code><?= htmlspecialchars($aprendiz['codigo_rfid']) ?></code></td>
              <td><span class="badge badge-info">Ficha #<?= htmlspecialchars($aprendiz['id_ficha']) ?></span></td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <button type="button" class="btn btn-info" data-toggle="modal"
                          data-target="#modalVer"
                          data-nombre="<?= htmlspecialchars($aprendiz['nombre']) ?>"
                          data-documento="<?= htmlspecialchars($aprendiz['documento']) ?>"
                          data-telefono="<?= htmlspecialchars($aprendiz['telefono'] ?? 'Sin registro') ?>"
                          data-rfid="<?= htmlspecialchars($aprendiz['codigo_rfid']) ?>"
                          data-ficha="<?= htmlspecialchars($aprendiz['id_ficha']) ?>"
                          title="Ver"><i class="fas fa-eye"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> Los aprendices y las fichas provienen de la base de datos.</p>
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
        <p class="mb-1"><i class="fas fa-id-card text-sena"></i> <strong>Documento:</strong> <span class="documento-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-phone text-sena"></i> <strong>Teléfono:</strong> <span class="telefono-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Ficha:</strong> Ficha #<span class="ficha-aprendiz"></span></p>
        <p class="mb-1"><i class="fas fa-rss text-sena"></i> <strong>RFID:</strong> <span class="rfid-aprendiz"></span></p>
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
include __DIR__ . '/../../layouts/layout_instructor.php';
