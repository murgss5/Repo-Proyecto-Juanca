<?php
/**
 * ============================================================
 * NAVBAR / BARRA SUPERIOR (AdminLTE)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Incluye: botón de menú, enlaces directos, notificaciones y
 * menú del usuario.
 * ============================================================
 */

/**
 * Variables provistas por views/layouts/header.php antes de incluir este archivo.
 *
 * @var int    $rolActualId
 * @var string $nombreRolActual
 * @var string $nombreUsuarioActual
 * @var string $fotoUsuario
 */

// Enlaces rápidos según el rol real del usuario
$enlacesRapidos = [];
if ($rolActualId === 1) {
    $enlacesRapidos = [
        ['url' => RUTA_BASE . 'views/admin/aprendices/index.php', 'icono' => 'fa-users', 'texto' => 'Aprendices'],
        ['url' => RUTA_BASE . 'views/admin/asistencias/index.php', 'icono' => 'fa-clipboard-check', 'texto' => 'Asistencias'],
    ];
} elseif ($rolActualId === 2) {
    $enlacesRapidos = [
        ['url' => RUTA_BASE . 'views/instructor/aprendices/index.php', 'icono' => 'fa-users', 'texto' => 'Mis aprendices'],
        ['url' => RUTA_BASE . 'views/instructor/asistencias/index.php', 'icono' => 'fa-clipboard-check', 'texto' => 'Asistencias'],
    ];
} else {
    $enlacesRapidos = [
        ['url' => RUTA_BASE . 'views/aprendiz/perfil.php', 'icono' => 'fa-user', 'texto' => 'Mi perfil'],
        ['url' => RUTA_BASE . 'views/aprendiz/excusas/create.php', 'icono' => 'fa-file-medical', 'texto' => 'Subir excusa'],
    ];
}
?>
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <!-- Botón para contraer el menú lateral -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>

  </ul>

  <!-- Enlaces rápidos (derecha) -->
  <ul class="navbar-nav ml-auto">
    <?php foreach ($enlacesRapidos as $enlace): ?>
      <li class="nav-item d-none d-md-inline-block">
        <a href="<?= $enlace['url'] ?>" class="nav-link" title="<?= htmlspecialchars($enlace['texto']) ?>">
          <i class="fas <?= $enlace['icono'] ?> text-sena"></i> <span class="d-none d-lg-inline"><?= htmlspecialchars($enlace['texto']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>

    <!-- Menú del usuario -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false">
        <img src="<?= $fotoUsuario ?>" class="img-circle elevation-2" alt="Foto de usuario" width="32" height="32">
        <span class="d-none d-md-inline ml-1"><?= htmlspecialchars($nombreUsuarioActual) ?></span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <a href="#" class="dropdown-item">
          <i class="fas fa-user-circle mr-2 text-sena"></i> <?= htmlspecialchars($nombreUsuarioActual) ?>
          <small class="float-right text-muted"><?= htmlspecialchars($nombreRolActual) ?></small>
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= RUTA_BASE ?>controllers/logout.php" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
        </a>
      </div>
    </li>
  </ul>
</nav>
