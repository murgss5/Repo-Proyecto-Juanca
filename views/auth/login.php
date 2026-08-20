<?php
/**
 * ============================================================
 * VISTA: INICIO DE SESIÓN (LOGIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * SOLO INTERFAZ VISUAL. No implementa autenticación real;
 * los botones de "Acceso demo" permiten previsualizar cada
 * panel según el rol.
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
  <title>SENA | Iniciar sesión</title>
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
      <h1 class="h4 mt-2 mb-0 font-weight-bold text-sena">SENA</h1>
      <p class="text-muted mb-0">Sistema de Control de Ingreso de Aprendices</p>
    </div>
    <div class="card-body">
     

      <form action="#" method="post" autocomplete="off">
        <div class="form-group">
          <label for="documento">Número de documento</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-id-card"></i></span>
            </div>
            <input type="text" id="documento" name="documento" class="form-control" placeholder="Ej. 1002345678" required>
          </div>
        </div>

        <div class="form-group">
          <label for="password">Contraseña</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
            </div>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
        </div>

        <div class="form-group d-flex justify-content-between align-items-center">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="recordarme">
            <label class="custom-control-label" for="recordarme">Recordarme</label>
          </div>
          <a href="<?= RUTA_BASE ?>views/auth/recuperar_contrasena.php" class="text-sena small">¿Olvidó su contraseña?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <i class="fas fa-sign-in-alt"></i> Ingresar
        </button>
      </form>

      <hr>

      <!-- Acceso de demostración por rol -->
      <p class="text-center text-muted small mb-2">Acceso de demostración (previsualizar por rol):</p>
      <div class="row">
        <div class="col-4">
          <a href="<?= RUTA_BASE ?>views/admin/dashboard.php" class="btn btn-outline-sena btn-block btn-sm">
            <i class="fas fa-user-shield"></i> Admin
          </a>
        </div>
        <div class="col-4">
          <a href="<?= RUTA_BASE ?>views/instructor/dashboard.php" class="btn btn-outline-sena btn-block btn-sm">
            <i class="fas fa-chalkboard-teacher"></i> Instructor
          </a>
        </div>
        <div class="col-4">
          <a href="<?= RUTA_BASE ?>views/aprendiz/dashboard.php" class="btn btn-outline-sena btn-block btn-sm">
            <i class="fas fa-graduation-cap"></i> Aprendiz
          </a>
        </div>
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