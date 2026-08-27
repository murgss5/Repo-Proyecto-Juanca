<?php
/**
 * ============================================================
 * VISTA: LISTADO DE APRENDICES (ADMIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Lista los aprendices registrados en la base de datos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

// --- Variables del layout ---
$paginaTitulo = 'Aprendices';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices'],
];

$conexion = (new Conexion())->conectar();
$consultaFichas = $conexion->query('SELECT id_ficha FROM ficha ORDER BY id_ficha');
$fichasRegistradas = $consultaFichas->fetchAll();

$consultaAprendices = $conexion->query(
    'SELECT
        aprendiz.id_aprendiz,
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
    <!-- Buscador y filtros visuales -->
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
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <p class="text-muted small mb-0"><i class="fas fa-info-circle"></i> El listado y las fichas provienen de la base de datos. El CRUD se implementará después.</p>
  </div>
</div>

<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../../layouts/layout_admin.php';
