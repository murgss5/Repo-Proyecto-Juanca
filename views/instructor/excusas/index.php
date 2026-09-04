<?php
// Lista las excusas reales de los aprendices de las fichas del instructor 

require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';
exigirRol(2);
require_once dirname(__DIR__, 3) . '/includes/constantes.php';
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// todas las variables del layout
$paginaTitulo = 'Excusas médicas de mis fichas';
$menuActivo   = 'excusas';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
    ['label' => 'Excusas médicas'],
];

$conexion = (new Conexion())->conectar();
$consultaExcusas = $conexion->prepare(
    "SELECT
        excusa.id_excusa,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS aprendiz,
        ficha.id_ficha,
        asistencia.fecha_asistencia,
        excusa.archivo,
        excusa.fecha_subida,
        excusa.estado,
        excusa.observacion
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE ficha.fk_usuario = :id_instructor
     ORDER BY excusa.fecha_subida DESC, excusa.id_excusa DESC"
);
$consultaExcusas->bindValue(':id_instructor', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaExcusas->execute();
$excusasInstructor = $consultaExcusas->fetchAll();

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

foreach ($excusasInstructor as &$excusa) {
    $excusa['estado_mostrado'] = ucfirst(strtolower($excusa['estado']));
}
unset($excusa);

function claseEstadoExcusa($estado) {
    switch ($estado) {
        case 'Pendiente': return 'badge-warning';
        case 'Aprobada':  return 'badge-success';
        case 'Rechazada': return 'badge-danger';
        default:          return 'badge-secondary';
    }
}

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-file-medical text-sena"></i> Excusas de mis aprendices</h3>
  </div>

  <div class="card-body">
    <?php if (isset($_GET['mensaje'])): ?>
      <div class="alert alert-success" role="alert"><?= htmlspecialchars($_GET['mensaje']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger" role="alert"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>
    <div class="row mb-3">
      <div class="col-md-5 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar por aprendiz o ficha...">
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="4" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Pendiente</option>
          <option>Aprobada</option>
          <option>Rechazada</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-bordered tabla-datos">
        <thead>
          <tr>
            <th>Aprendiz</th>
            <th>Ficha</th>
            <th>Fecha de inasistencia</th>
            <th>Archivo adjunto</th>
            <th>Fecha de envío</th>
            <th>Estado</th>
            <th>Observaciones</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($excusasInstructor as $excusa): ?>
            <tr>
              <td><?= htmlspecialchars($excusa['aprendiz']) ?></td>
              <td><span class="badge badge-info">Ficha #<?= htmlspecialchars($excusa['id_ficha']) ?></span></td>
              <td><?= htmlspecialchars($excusa['fecha_asistencia']) ?></td>
              <td>
                <a href="<?= RUTA_BASE ?>views/instructor/excusas/descargar.php?id_excusa=<?= htmlspecialchars($excusa['id_excusa']) ?>" class="text-sena" title="Descargar archivo">
                  <i class="fas fa-paperclip"></i> <?= htmlspecialchars($excusa['archivo']) ?>
                </a>
              </td>
              <td><?= htmlspecialchars($excusa['fecha_subida']) ?></td>
              <td><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado_mostrado']) ?>"><?= htmlspecialchars($excusa['estado_mostrado']) ?></span></td>
              <td>
                <?php if (!empty($excusa['observacion'])): ?>
                  <span class="small"><?= htmlspecialchars($excusa['observacion']) ?></span>
                <?php else: ?>
                  <span class="text-muted">&mdash;</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($excusa['estado'] === 'PENDIENTE'): ?>
                  <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalAprobar"
                            data-id-excusa="<?= htmlspecialchars($excusa['id_excusa']) ?>"
                            data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>" title="Aprobar">
                      <i class="fas fa-check"></i>
                    </button>
                    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#modalRechazar"
                            data-id-excusa="<?= htmlspecialchars($excusa['id_excusa']) ?>"
                            data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>" title="Rechazar">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                <?php else: ?>
                  <span class="text-muted small">Sin acciones</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAprobar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header bg-success text-white">
      <h5 class="modal-title"><i class="fas fa-check-circle"></i> Aprobar excusa</h5>
      <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
    </div>
    <form action="<?= RUTA_BASE ?>controllers/revisar_excusa.php" method="post">
      <div class="modal-body">
        ¿Desea aprobar la excusa presentada por <strong class="nombre-aprendiz"></strong>?
        <input type="hidden" name="id_excusa" class="id-excusa">
        <input type="hidden" name="decision" value="APROBADA">
        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success">Aprobar</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="modalRechazar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header bg-danger text-white">
      <h5 class="modal-title"><i class="fas fa-times-circle"></i> Rechazar excusa</h5>
      <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
    </div>
    <form action="<?= RUTA_BASE ?>controllers/revisar_excusa.php" method="post">
      <div class="modal-body">
        ¿Desea rechazar la excusa presentada por <strong class="nombre-aprendiz"></strong>?
        <input type="hidden" name="id_excusa" class="id-excusa">
        <input type="hidden" name="decision" value="RECHAZADA">
        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-danger">Rechazar</button></div>
    </form>
  </div></div>
</div>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_instructor.php';
