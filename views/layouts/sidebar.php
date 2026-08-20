<?php
/**
 * ============================================================
 * SIDEBAR / MENÚ LATERAL (AdminLTE)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * El menú cambia visualmente según el rol de demostración
 * ($rolDemo): administrador, instructor o aprendiz.
 * ============================================================
 */

// Icono según el rol
$iconoRol = 'fa-user-shield';
if ($rolDemo === 'instructor') {
    $iconoRol = 'fa-chalkboard-teacher';
} elseif ($rolDemo === 'aprendiz') {
    $iconoRol = 'fa-graduation-cap';
}

// ------------------------------------------------------------
// Definición del menú por rol
// ------------------------------------------------------------
$itemsMenu = [];

if ($rolDemo === 'admin') {
    $itemsMenu = [
        ['tipo' => 'header', 'texto' => 'PRINCIPAL'],
        ['tipo' => 'enlace', 'clave' => 'dashboard', 'icono' => 'fa-tachometer-alt', 'texto' => 'Dashboard', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
        ['tipo' => 'header', 'texto' => 'GESTIÓN ACADÉMICA'],
        ['tipo' => 'enlace', 'clave' => 'aprendices', 'icono' => 'fa-users', 'texto' => 'Aprendices', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php',
            'hijos' => [
                ['texto' => 'Listado', 'url' => RUTA_BASE . 'views/admin/aprendices/index.php', 'clave' => 'aprendices'],
                ['texto' => 'Nuevo aprendiz', 'url' => RUTA_BASE . 'views/admin/aprendices/create.php', 'clave' => 'aprendices-nuevo'],
            ]],
        ['tipo' => 'enlace', 'clave' => 'instructores', 'icono' => 'fa-chalkboard-teacher', 'texto' => 'Instructores', 'url' => RUTA_BASE . 'views/admin/instructores/index.php'],
        ['tipo' => 'enlace', 'clave' => 'fichas', 'icono' => 'fa-layer-group', 'texto' => 'Fichas', 'url' => RUTA_BASE . 'views/admin/fichas/index.php',
            'hijos' => [
                ['texto' => 'Listado', 'url' => RUTA_BASE . 'views/admin/fichas/index.php', 'clave' => 'fichas'],
                ['texto' => 'Nueva ficha', 'url' => RUTA_BASE . 'views/admin/fichas/create.php', 'clave' => 'fichas-nueva'],
            ]],
        ['tipo' => 'enlace', 'clave' => 'horarios', 'icono' => 'fa-calendar-alt', 'texto' => 'Horarios', 'url' => RUTA_BASE . 'views/admin/horarios/index.php'],
        ['tipo' => 'enlace', 'clave' => 'asistencias', 'icono' => 'fa-clipboard-check', 'texto' => 'Asistencias', 'url' => RUTA_BASE . 'views/admin/asistencias/index.php'],
        ['tipo' => 'enlace', 'clave' => 'excusas', 'icono' => 'fa-file-medical', 'texto' => 'Excusas médicas', 'url' => RUTA_BASE . 'views/admin/excusas/index.php'],
        ['tipo' => 'enlace', 'clave' => 'reportes', 'icono' => 'fa-chart-bar', 'texto' => 'Reportes', 'url' => RUTA_BASE . 'views/admin/reportes/index.php'],
        ['tipo' => 'header', 'texto' => 'SISTEMA'],
        ['tipo' => 'enlace', 'clave' => 'configuracion', 'icono' => 'fa-cog', 'texto' => 'Configuración', 'url' => '#'],
        ['tipo' => 'enlace', 'clave' => 'logout', 'icono' => 'fa-sign-out-alt', 'texto' => 'Cerrar sesión', 'url' => RUTA_BASE . 'views/auth/login.php'],
    ];
} elseif ($rolDemo === 'instructor') {
    $itemsMenu = [
        ['tipo' => 'header', 'texto' => 'PRINCIPAL'],
        ['tipo' => 'enlace', 'clave' => 'dashboard', 'icono' => 'fa-tachometer-alt', 'texto' => 'Dashboard', 'url' => RUTA_BASE . 'views/instructor/dashboard.php'],
        ['tipo' => 'enlace', 'clave' => 'aprendices', 'icono' => 'fa-users', 'texto' => 'Aprendices de mis fichas', 'url' => RUTA_BASE . 'views/instructor/aprendices/index.php'],
        ['tipo' => 'enlace', 'clave' => 'asistencias', 'icono' => 'fa-clipboard-check', 'texto' => 'Asistencias', 'url' => RUTA_BASE . 'views/instructor/asistencias/index.php'],
        ['tipo' => 'enlace', 'clave' => 'excusas', 'icono' => 'fa-file-medical', 'texto' => 'Excusas médicas', 'url' => RUTA_BASE . 'views/instructor/excusas/index.php'],
        ['tipo' => 'enlace', 'clave' => 'reportes', 'icono' => 'fa-chart-bar', 'texto' => 'Reportes', 'url' => RUTA_BASE . 'views/admin/reportes/index.php'],
        ['tipo' => 'header', 'texto' => 'SISTEMA'],
        ['tipo' => 'enlace', 'clave' => 'logout', 'icono' => 'fa-sign-out-alt', 'texto' => 'Cerrar sesión', 'url' => RUTA_BASE . 'views/auth/login.php'],
    ];
} else {
    $itemsMenu = [
        ['tipo' => 'header', 'texto' => 'PRINCIPAL'],
        ['tipo' => 'enlace', 'clave' => 'dashboard', 'icono' => 'fa-tachometer-alt', 'texto' => 'Mi dashboard', 'url' => RUTA_BASE . 'views/aprendiz/dashboard.php'],
        ['tipo' => 'enlace', 'clave' => 'perfil', 'icono' => 'fa-user', 'texto' => 'Mi perfil', 'url' => RUTA_BASE . 'views/aprendiz/perfil.php'],
        ['tipo' => 'enlace', 'clave' => 'historial', 'icono' => 'fa-history', 'texto' => 'Mi historial', 'url' => RUTA_BASE . 'views/aprendiz/historial.php'],
        ['tipo' => 'enlace', 'clave' => 'excusas', 'icono' => 'fa-file-medical', 'texto' => 'Mis excusas', 'url' => RUTA_BASE . 'views/aprendiz/excusas/index.php'],
        ['tipo' => 'enlace', 'clave' => 'subir-excusa', 'icono' => 'fa-cloud-upload-alt', 'texto' => 'Subir excusa', 'url' => RUTA_BASE . 'views/aprendiz/excusas/create.php'],
        ['tipo' => 'header', 'texto' => 'SISTEMA'],
        ['tipo' => 'enlace', 'clave' => 'logout', 'icono' => 'fa-sign-out-alt', 'texto' => 'Cerrar sesión', 'url' => RUTA_BASE . 'views/auth/login.php'],
    ];
}

// Verifica si una clave de menú está activa
function menuActivo($clave) {
    global $menuActivo;
    return $menuActivo === $clave;
}
?>
<aside class="main-sidebar sidebar-dark-sena elevation-4">
  <!-- Marca del sistema -->
  <a href="<?= RUTA_BASE ?>" class="brand-link">
    <img src="<?= RUTA_BASE ?>assets/img/logo-sena.svg" alt="Logo SENA" class="brand-image img-circle elevation-3">
    <span class="brand-text font-weight-light">SENA &middot; Ingreso</span>
  </a>

  <!-- Contenido del menú -->
  <div class="sidebar">
    <!-- Usuario de demostración -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="<?= $fotoUsuario ?>" class="img-circle elevation-2" alt="Foto de usuario">
      </div>
      <div class="info">
        <a href="#" class="d-block"><?= htmlspecialchars($usuarioDemo) ?></a>
        <span class="text-sm text-muted"><i class="fas <?= $iconoRol ?>"></i> <?= htmlspecialchars($rolNombre) ?></span>
      </div>
    </div>

    <!-- Navegación -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <?php foreach ($itemsMenu as $item): ?>
          <?php if ($item['tipo'] === 'header'): ?>
            <li class="nav-header"><?= htmlspecialchars($item['texto']) ?></li>
          <?php else: ?>
            <?php
              $activo = menuActivo($item['clave']);
              $tieneHijos = isset($item['hijos']) && count($item['hijos']) > 0;
              $hijoActivo = false;
              if ($tieneHijos) {
                  foreach ($item['hijos'] as $hijo) {
                      if (menuActivo($hijo['clave'])) { $hijoActivo = true; }
                  }
              }
              $claseLi = $tieneHijos ? 'has-treeview' : '';
              if ($tieneHijos && ($hijoActivo || $activo)) { $claseLi .= ' menu-open'; }
            ?>
            <li class="nav-item <?= trim($claseLi) ?>">
              <a href="<?= $item['url'] ?>" class="nav-link <?= ($activo || $hijoActivo) ? 'active' : '' ?>">
                <i class="nav-icon fas <?= $item['icono'] ?>"></i>
                <p>
                  <?= htmlspecialchars($item['texto']) ?>
                  <?php if ($tieneHijos): ?><i class="right fas fa-angle-left"></i><?php endif; ?>
                </p>
              </a>
              <?php if ($tieneHijos): ?>
                <ul class="nav nav-treeview">
                  <?php foreach ($item['hijos'] as $hijo): ?>
                    <li class="nav-item">
                      <a href="<?= $hijo['url'] ?>" class="nav-link <?= menuActivo($hijo['clave']) ? 'active' : '' ?>">
                        <i class="far fa-circle nav-icon"></i>
                        <p><?= htmlspecialchars($hijo['texto']) ?></p>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</aside>