<?php
/**
 * ============================================================
 * NAVBAR / BARRA SUPERIOR (AdminLTE)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Incluye: botón de menú, enlaces directos, notificaciones y
 * menú del usuario. Es solo interfaz visual (sin lógica real).
 * ============================================================
 */

// Enlaces rápidos según el rol de demostración
$enlacesRapidos = [];
if ($rolDemo === 'admin') {
    $enlacesRapidos = [
        ['url' => RUTA_BASE . 'views/admin/aprendices/index.php', 'icono' => 'fa-users', 'texto' => 'Aprendices'],
        ['url' => RUTA_BASE . 'views/admin/asistencias/index.php', 'icono' => 'fa-clipboard-check', 'texto' => 'Asistencias'],
    ];
} elseif ($rolDemo === 'instructor') {
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
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?= RUTA_BASE ?>" class="nav-link"><i class="fas fa-home text-sena"></i> Inicio</a>
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

    <!-- Notificaciones (visual) -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false">
        <i class="far fa-bell"></i>
        <span class="badge badge-sena navbar-badge">3</span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <span class="dropdown-item dropdown-header">3 Notificaciones</span>
        <div class="dropdown-divider"></div>
        <a href="<?= RUTA_BASE ?>views/admin/excusas/index.php" class="dropdown-item">
          <i class="fas fa-file-medical mr-2 text-sena"></i> 2 excusas médicas pendientes
          <span class="float-right text-muted text-sm">Hoy</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= RUTA_BASE ?>views/admin/asistencias/index.php" class="dropdown-item">
          <i class="fas fa-clock mr-2 text-warning"></i> 3 retardos registrados hoy
          <span class="float-right text-muted text-sm">Hoy</span>
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="dropdown-item">
          <i class="fas fa-user-plus mr-2 text-info"></i> 5 aprendices nuevos esta semana
          <span class="float-right text-muted text-sm">Semana</span>
        </a>
      </div>
    </li>

    <!-- Menú del usuario -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="dropdown" href="#" aria-haspopup="true" aria-expanded="false">
        <img src="<?= $fotoUsuario ?>" class="img-circle elevation-2" alt="Foto de usuario" width="32" height="32">
        <span class="d-none d-md-inline ml-1"><?= htmlspecialchars($usuarioDemo) ?></span>
      </a>
      <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <a href="#" class="dropdown-item">
          <i class="fas fa-user-circle mr-2 text-sena"></i> <?= htmlspecialchars($usuarioDemo) ?>
          <small class="float-right text-muted"><?= htmlspecialchars($rolNombre) ?></small>
        </a>
        <div class="dropdown-divider"></div>
        <a href="<?= RUTA_BASE ?>controllers/logout.php" class="dropdown-item text-danger">
          <i class="fas fa-sign-out-alt mr-2"></i> Cerrar sesión
        </a>
      </div>
    </li>
  </ul>
</nav>
