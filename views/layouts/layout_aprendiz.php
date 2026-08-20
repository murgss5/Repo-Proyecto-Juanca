<?php
/**
 * ============================================================
 * LAYOUT DEL PANEL DE APRENDIZ (AdminLTE)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Estructura visual para el rol "aprendiz".
 * La vista establece: $paginaTitulo, $menuActivo, $miga y
 * captura su contenido en $contenido antes de incluir este
 * archivo. NO implementa autenticación real.
 * ============================================================
 */

$rolDemo   = 'aprendiz';
$rolNombre = 'Aprendiz';

// Miga de pan (breadcrumb) por defecto
$miga = $miga ?? [['label' => $paginaTitulo]];

// ============================================================
// CABECERA + NAVBAR + MENÚ LATERAL
// ============================================================
include __DIR__ . '/header.php';
include __DIR__ . '/navbar.php';
include __DIR__ . '/sidebar.php';
?>

<!-- Contenido principal -->
<div class="content-wrapper">
  <!-- Título de la página + breadcrumb -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1 class="m-0 text-dark"><?= htmlspecialchars($paginaTitulo) ?></h1>
        </div>
        <div class="col-sm-6">
          <ol class="breadcrumb float-sm-right">
            <?php foreach ($miga as $indice => $migaItem): ?>
              <li class="breadcrumb-item <?= ($indice === count($miga) - 1) ? 'active' : '' ?>">
                <?php if (isset($migaItem['url']) && $indice < count($miga) - 1): ?>
                  <a href="<?= $migaItem['url'] ?>"><?= htmlspecialchars($migaItem['label']) ?></a>
                <?php else: ?>
                  <?= htmlspecialchars($migaItem['label']) ?>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- Contenido de la vista -->
  <section class="content">
    <?= $contenido ?>
  </section>
</div>

<?php
// ============================================================
// PIE DE PÁGINA + SCRIPTS
// ============================================================
include __DIR__ . '/footer.php';
include __DIR__ . '/scripts.php';