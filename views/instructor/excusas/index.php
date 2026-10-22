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
$consultaExcusas->bindValue(':id_instructor', $_SESSION['instructor']['id_usuario'], PDO::PARAM_INT);
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
<style>
.table.align-middle td, .table.align-middle th {
  vertical-align: middle;
}
</style>

<div class="card card-outline card-success shadow-sm mb-4">
  <div class="card-header">
    <h3 class="card-title font-weight-bold"><i class="fas fa-file-medical mr-2 text-sena"></i> Excusas de mis aprendices</h3>
  </div>

  <div class="card-body">
    <?php if (isset($_GET['mensaje'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-1"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle mr-1"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
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
        <select class="form-control filtro-tabla" data-col="5" data-exacto="true">
          <option value="">Todos los estados</option>
          <option>Pendiente</option>
          <option>Aprobada</option>
          <option>Rechazada</option>
        </select>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover table-striped align-middle tabla-datos">
        <thead class="thead-light">
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
          <?php foreach ($excusasInstructor as $excusa): 
                $extArchivo = strtolower(pathinfo($excusa['archivo'], PATHINFO_EXTENSION));
                $urlVisor = RUTA_BASE . 'views/instructor/excusas/descargar.php?id_excusa=' . urlencode((string)$excusa['id_excusa']) . '&inline=1';
          ?>
            <tr>
              <td><?= htmlspecialchars($excusa['aprendiz']) ?></td>
              <td><span class="badge badge-success px-2 py-1">Ficha #<?= htmlspecialchars((string)$excusa['id_ficha']) ?></span></td>
              <td><?= htmlspecialchars($excusa['fecha_asistencia']) ?></td>
              <td>
                <button type="button" 
                        class="btn btn-sm btn-outline-info shadow-sm btn-preview" 
                        data-url="<?= htmlspecialchars($urlVisor) ?>" 
                        data-tipo="<?= htmlspecialchars($extArchivo) ?>"
                        data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>"
                        data-fecha="<?= htmlspecialchars($excusa['fecha_asistencia']) ?>">
                  <i class="fas fa-eye mr-1"></i> Ver documento
                </button>
              </td>
              <td><?= htmlspecialchars($excusa['fecha_subida']) ?></td>
              <td><span class="badge badge-estado <?= claseEstadoExcusa($excusa['estado_mostrado']) ?> px-2 py-1"><?= htmlspecialchars($excusa['estado_mostrado']) ?></span></td>
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
                    <button type="button" class="btn btn-success shadow-sm" data-toggle="modal" data-target="#modalAprobar"
                            data-id-excusa="<?= htmlspecialchars((string)$excusa['id_excusa']) ?>"
                            data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>" title="Aprobar excusa">
                      <i class="fas fa-check mr-1"></i> Aprobar
                    </button>
                    <button type="button" class="btn btn-danger shadow-sm" data-toggle="modal" data-target="#modalRechazar"
                            data-id-excusa="<?= htmlspecialchars((string)$excusa['id_excusa']) ?>"
                            data-nombre="<?= htmlspecialchars($excusa['aprendiz']) ?>" title="Rechazar excusa">
                      <i class="fas fa-times mr-1"></i> Rechazar
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

<!-- Modal Visor de Documentos (Previsualización) -->
<div class="modal fade" id="modalVisorExcusa" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content shadow-lg border-0">
      <div class="modal-header bg-light py-2 px-3">
        <h5 class="modal-title font-weight-bold" style="font-size: 1.05rem;">
          <i class="fas fa-file-alt mr-2 text-sena"></i> Previsualización de documento &mdash; <span id="visorTitulo" class="text-dark font-weight-normal"></span>
        </h5>
        <div class="ml-auto d-flex align-items-center">
          <a id="btnDescargarModal" href="#" class="btn btn-sm btn-outline-success mr-2 shadow-sm" download>
            <i class="fas fa-download mr-1"></i> Descargar
          </a>
          <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>
      <div class="modal-body p-0">
        <div id="visorContenedor" class="text-center p-3" style="min-height: 480px; background-color: #f8fafc;"></div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Aprobar Excusa -->
<div class="modal fade" id="modalAprobar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fas fa-check-circle mr-1"></i> Aprobar excusa</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <form action="<?= RUTA_BASE ?>controllers/revisar_excusa.php" method="post">
        <div class="modal-body">
          <p class="mb-0">¿Desea aprobar la excusa médica presentada por <strong class="nombre-aprendiz"></strong>?</p>
          <input type="hidden" name="id_excusa" class="id-excusa">
          <input type="hidden" name="decision" value="APROBADA">
          <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success btn-sm shadow-sm"><i class="fas fa-check mr-1"></i> Sí, aprobar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Rechazar Excusa -->
<div class="modal fade" id="modalRechazar" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content shadow-sm">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="fas fa-times-circle mr-1"></i> Rechazar excusa</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
      </div>
      <form action="<?= RUTA_BASE ?>controllers/revisar_excusa.php" method="post">
        <div class="modal-body">
          <p class="mb-0">¿Desea rechazar la excusa médica presentada por <strong class="nombre-aprendiz"></strong>?</p>
          <input type="hidden" name="id_excusa" class="id-excusa">
          <input type="hidden" name="decision" value="RECHAZADA">
          <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger btn-sm shadow-sm"><i class="fas fa-times mr-1"></i> Sí, rechazar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    $(document).on('click', '.btn-preview', function () {
        var url = $(this).data('url');
        var tipo = ($(this).data('tipo') || '').toLowerCase();
        var nombre = $(this).data('nombre') || '';
        var fecha = $(this).data('fecha') || '';
        
        $('#visorTitulo').text(nombre + (fecha ? ' (' + fecha + ')' : ''));

        // Configura el enlace de descarga directa en el modal (sin el parámetro inline)
        var downloadUrl = url.replace('&inline=1', '');
        $('#btnDescargarModal').attr('href', downloadUrl);

        var $contenedor = $('#visorContenedor');
        $contenedor.html('<div class="py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2 text-sena"></i><br>Cargando previsualización...</div>');

        if (tipo === 'pdf') {
            $contenedor.html('<iframe src="' + url + '" style="width: 100%; height: 75vh; border: none;" class="rounded border"></iframe>');
        } else if (tipo === 'jpg' || tipo === 'jpeg' || tipo === 'png') {
            $contenedor.html('<img src="' + url + '" class="img-fluid rounded shadow-sm border" style="max-height: 75vh;" alt="Documento adjunto">');
        } else {
            $contenedor.html(
                '<div class="py-5 text-muted">' +
                    '<i class="fas fa-file-download fa-3x mb-3 text-secondary"></i>' +
                    '<p class="mb-2">El formato de este archivo no se puede previsualizar directamente en el navegador.</p>' +
                    '<a href="' + downloadUrl + '" class="btn btn-primary btn-sm shadow-sm"><i class="fas fa-download mr-1"></i> Descargar archivo</a>' +
                '</div>'
            );
        }

        $('#modalVisorExcusa').modal('show');
    });

    // Limpiar iframe al cerrar el modal para liberar memoria y evitar que siga cargando
    $('#modalVisorExcusa').on('hidden.bs.modal', function () {
        $('#visorContenedor').empty();
    });
});
</script>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_instructor.php';
