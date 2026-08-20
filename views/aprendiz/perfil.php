<?php
/**
 * ============================================================
 * VISTA: MI PERFIL (APRENDIZ)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL con datos de demostración estáticos.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/constantes.php';

// Datos de demostración del aprendiz
$perfilDemo = [
    'nombre'   => 'Juan David Pérez',
    'documento' => '1002345678',
    'correo'   => 'jdperez@misena.edu.co',
    'telefono' => '3001112233',
    'ficha'    => '2693854',
    'programa' => 'Tecnólogo en Análisis y Desarrollo de Software',
    'jornada'  => 'Diurna',
    'rfid'     => 'RF-2026-001',
    'ingresado' => '2026-02-10',
    'estado'   => 'Activo',
];

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
  <!-- Tarjeta de perfil -->
  <div class="col-md-4 mb-3">
    <div class="card">
      <div class="card-body text-center">
        <img src="<?= RUTA_BASE ?>assets/img/avatar.png" class="avatar-grande img-circle mb-3" alt="Foto del aprendiz">
        <h4><?= htmlspecialchars($perfilDemo['nombre']) ?></h4>
        <p class="text-muted mb-2"><?= htmlspecialchars($perfilDemo['programa']) ?></p>
        <span class="badge badge-estado badge-success"><?= htmlspecialchars($perfilDemo['estado']) ?></span>
        <hr>
        <p class="mb-1"><i class="fas fa-rss text-sena"></i> <strong>Código RFID:</strong> <code><?= htmlspecialchars($perfilDemo['rfid']) ?></code></p>
        <p class="mb-1"><i class="fas fa-layer-group text-sena"></i> <strong>Ficha:</strong> <?= htmlspecialchars($perfilDemo['ficha']) ?></p>
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
          <tr><th style="width: 220px;">Documento</th><td><?= htmlspecialchars($perfilDemo['documento']) ?></td></tr>
          <tr><th>Nombre completo</th><td><?= htmlspecialchars($perfilDemo['nombre']) ?></td></tr>
          <tr><th>Correo electrónico</th><td><?= htmlspecialchars($perfilDemo['correo']) ?></td></tr>
          <tr><th>Teléfono</th><td><?= htmlspecialchars($perfilDemo['telefono']) ?></td></tr>
          <tr><th>Jornada</th><td><?= htmlspecialchars($perfilDemo['jornada']) ?></td></tr>
          <tr><th>Fecha de ingreso</th><td><?= htmlspecialchars($perfilDemo['ingresado']) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>
<?php
$contenido = ob_get_clean();

// --- Render del layout según el rol ---
include __DIR__ . '/../layouts/layout_aprendiz.php';