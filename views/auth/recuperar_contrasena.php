<?php
/**
 * ============================================================
 * VISTA: RECUPERAR CONTRASEÑA
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. El envío del enlace no está
 * implementado (se desarrollará junto con la autenticación).
 * ============================================================
 */

// Carga de rutas base y datos de demostración
require_once dirname(__DIR__) . '/../includes/constantes.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SENA | Recuperar contraseña</title>
  <link rel="icon" href="<?= RUTA_BASE ?>assets/img/logo-sena.svg">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=swap">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/icons/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/adminlte.min.css">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/custom.css">
</head>
<body class="hold-transition">

<div class="pagina-login">
  <div class="login-card card card-outline">
    <div class="card-header text-center">
      <img src="<?= RUTA_BASE ?>assets/img/logo-sena.svg" alt="Logo SENA" width="72" height="72">
      <h1 class="h4 mt-2 mb-0 font-weight-bold text-sena">Recuperar contraseña</h1>
      <p class="text-muted mb-0">Ingrese su documento o correo para restablecerla</p>
    </div>
    <div class="card-body">
      <!-- Aviso de demostración -->
      <div class="alert alert-warning py-2 small" role="alert">
        <i class="fas fa-info-circle"></i> Prototipo visual &middot; el envío del enlace se implementará más adelante.
      </div>

      <form action="#" method="post" autocomplete="off">
        <div class="form-group">
          <label for="documento">Documento o correo electrónico</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            </div>
            <input type="text" id="documento" name="documento" class="form-control"
                   placeholder="Ej. 1002345678 o jdperez@misena.edu.co" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <i class="fas fa-paper-plane"></i> Enviar enlace de recuperación
        </button>
      </form>

      <div class="text-center mt-3">
        <a href="<?= RUTA_BASE ?>views/auth/login.php" class="text-sena">
          <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
        </a>
      </div>
    </div>
    <div class="card-footer text-center">
      <small class="text-muted">&copy; <?= date('Y') ?> SENA &middot; Servicio Nacional de Aprendizaje</small>
    </div>
  </div>
</div>

<script src="<?= RUTA_BASE ?>assets/js/jquery.min.js"></script>
<script src="<?= RUTA_BASE ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>