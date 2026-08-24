<?php
/**
 * ============================================================
 * VISTA: MI PERFIL (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Muestra la información real del aprendiz autenticado.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(3);
require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../config/conexion.php';

$conexion = (new Conexion())->conectar();
$consultaPerfil = $conexion->prepare(
    'SELECT
        CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
        usuario.identificacion AS documento,
        usuario.telefono,
        aprendiz.codigo_rfid,
        ficha.id_ficha,
        ficha.nombre_programa,
        ficha.jornada
     FROM usuario
     INNER JOIN aprendiz ON aprendiz.fk_usuario = usuario.id_usuario
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE usuario.id_usuario = :id_usuario
     LIMIT 1'
);
$consultaPerfil->bindValue(':id_usuario', $_SESSION['id_usuario'], PDO::PARAM_INT);
$consultaPerfil->execute();
$perfilAprendiz = $consultaPerfil->fetch();

// --- Variables del layout ---
$paginaTitulo = 'Mi perfil';
$menuActivo   = 'perfil';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
    ['label' => 'Mi perfil'],
];

ob_start();
?>
<div class="row">
  <?php if (!$perfilAprendiz): ?>
    <div class="col-12"><div class="alert alert-warning">No se encontró un perfil de aprendiz asociado a esta cuenta.</div></div>
  <?php else: ?>
  <!-- Tarjeta de perfil -->
  <div class="col-md-4 mb-3">
    <div class="card">
      <div class="card-body text-center">
        <img src="<?= RUTA_BASE ?>assets/img/avatar.png" class="avatar-grande img-circle mb-3" alt="Foto del aprendiz">
        <h4><?= htmlspecialchars($perfilAprendiz['nombre']) ?></h4>
        <p class="text-muted mb-2"><?= htmlspecialchars($perfilAprendiz['nombre_programa']) ?></p>
        <hr>
        <p class="mb-1"><i class="fas fa-rss text-sena"></i> <strong>Código RFID:</strong> <code><?= htmlspecialchars($perfilAprendiz['codigo_rfid']) ?></code></p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Ficha:</strong> Ficha #<?= htmlspecialchars($perfilAprendiz['id_ficha']) ?></p>
      </div>
    </div>
  </div>

  <!-- Datos personales -->
  <div class="col-md-8 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-id-card text-sena"></i> Datos personales</h3>
      </div>
      <div class="card-body">
        <table class="table table-bordered">
          <tr><th style="width: 220px;">Documento</th><td><?= htmlspecialchars($perfilAprendiz['documento']) ?></td></tr>
          <tr><th>Nombre completo</th><td><?= htmlspecialchars($perfilAprendiz['nombre']) ?></td></tr>
          <tr><th>Teléfono</th><td><?= htmlspecialchars($perfilAprendiz['telefono'] ?? 'Sin registro') ?></td></tr>
          <tr><th>Ficha</th><td>Ficha #<?= htmlspecialchars($perfilAprendiz['id_ficha']) ?></td></tr>
          <tr><th>Programa de formación</th><td><?= htmlspecialchars($perfilAprendiz['nombre_programa']) ?></td></tr>
          <tr><th>Jornada</th><td><?= htmlspecialchars($perfilAprendiz['jornada']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';
