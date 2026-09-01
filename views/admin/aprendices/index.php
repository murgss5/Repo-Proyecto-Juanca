<?php

// esta es la vista de detalle del aprendiz en el panel de administración

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

// aqui tenemos las variables para el layout de la vista de aprendices
$paginaTitulo = 'Aprendices';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices'],
];

$conexion = (new Conexion())->conectar();
if (empty($_SESSION['token_csrf'])) $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
$consultaFichas = $conexion->query('SELECT id_ficha FROM ficha ORDER BY id_ficha');
$fichasRegistradas = $consultaFichas->fetchAll();

$consultaAprendices = $conexion->query(
    'SELECT
        aprendiz.id_aprendiz,
        aprendiz.fk_usuario AS id_usuario,
        usuario.identificacion AS documento,
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
        usuario.telefono,
        ficha.id_ficha,
        aprendiz.codigo_rfid
     FROM aprendiz
     INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     ORDER BY usuario.apellido, usuario.nombre'
);
$aprendicesRegistrados = $consultaAprendices->fetchAll();

ob_start();
?>
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fas fa-users text-sena"></i> Listado de aprendices</h3>
    <div class="card-tools">
      <a href="<?= RUTA_BASE ?>views/admin/aprendices/create.php" class="btn btn-primary btn-sm">
        <i class="fas fa-user-plus"></i> Nuevo aprendiz
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if(isset($_GET['mensaje'])): ?><div class="alert alert-success"><?=htmlspecialchars($_GET['mensaje'])?></div><?php endif; ?>
    <?php if(isset($_GET['error'])): ?><div class="alert alert-danger"><?=htmlspecialchars($_GET['error'])?></div><?php endif; ?>

<!-- apartado del buscador y filtros visuales  -->

    <div class="row mb-3">
      <div class="col-md-4 col-sm-12 mb-2">
        <div class="input-group">
          <div class="input-group-prepend">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
          </div>
          <input type="text" class="form-control buscador-tabla" placeholder="Buscar aprendiz por nombre, documento o RFID...">
        </div>
      </div>
      <div class="col-md-3 col-sm-6 mb-2">
        <select class="form-control filtro-tabla" data-col="3">
          <option value="">Todas las fichas</option>
          <?php foreach ($fichasRegistradas as $ficha): ?>
            <option value="Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?>">Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
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
            <th>Ficha</th>
            <th>Código RFID</th>
            <th class="text-center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendicesRegistrados as $aprendiz): ?>
            <tr>
              <td><?= htmlspecialchars($aprendiz['documento']) ?></td>
              <td><?= htmlspecialchars($aprendiz['nombre']) ?></td>
              <td><?= htmlspecialchars($aprendiz['telefono'] ?? 'Sin registro') ?></td>
              <td><span class="badge badge-info">Ficha #<?= htmlspecialchars($aprendiz['id_ficha']) ?></span></td>
              <td><code><?= htmlspecialchars($aprendiz['codigo_rfid']) ?></code></td>
              <td class="text-center"><a class="btn btn-sm btn-warning" href="<?=RUTA_BASE?>views/admin/aprendices/edit.php?id=<?=htmlspecialchars($aprendiz['id_usuario'])?>"><i class="fas fa-edit"></i></a><form action="<?=RUTA_BASE?>controllers/gestionar_usuario.php" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este aprendiz?');"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="rol" value="3"><input type="hidden" name="id_usuario" value="<?=htmlspecialchars($aprendiz['id_usuario'])?>"><input type="hidden" name="token_csrf" value="<?=htmlspecialchars($_SESSION['token_csrf'])?>"><button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button></form></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> El listado y las acciones de creación, edición y eliminación consultan la base de datos.</p>
  </div>
</div>

<?php
$contenido = ob_get_clean();

// este es el render del layout principal del panel de administración
include __DIR__ . '/../../layouts/layout_admin.php';
