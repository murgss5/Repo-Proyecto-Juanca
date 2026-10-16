<?php
/**
 * ============================================================
 * VISTA: INICIO DE SESION (LOGIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Usa el controlador de autenticacion real del proyecto.
 * ============================================================
 */

// cabeceras anti cache para login
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Carga de rutas base y datos de demostracion
require_once dirname(__DIR__) . '/../includes/constantes.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SENA | Iniciar sesion</title>
  <link rel="icon" href="<?= RUTA_BASE ?>assets/img/sena.svg">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=swap">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/icons/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/adminlte.min.css">
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/plugins/sweetalert2/sweetalert2.min.css">
  <!-- Estilos personalizados del proyecto -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/custom.css">
</head>
<body class="hold-transition">

<div class="pagina-login position-relative">
  <div class="login-card card card-outline">
    <div class="card-header text-center">
      <img src="<?= RUTA_BASE ?>assets/img/sena.svg" alt="Logo SENA" width="72" height="72">
      <h1 class="h4 mt-2 mb-0 font-weight-bold text-sena">SENA</h1>
      <p class="text-muted mb-0">Sistema de Control de Ingreso de Aprendices</p>
    </div>
    <div class="card-body">
      <?php if (isset($_GET['error'])): ?>
        <!-- Muestra mensaje de error seguro como alternativa si JS esta desactivado -->
        <noscript>
          <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
          </div>
        </noscript>
      <?php endif; ?>

      <!-- Formulario de login que envia a controllers/login.php -->
      <form id="formLogin" action="<?= RUTA_BASE ?>controllers/login.php" method="post" autocomplete="off" novalidate>
        <div class="form-group">
          <label for="nombre_usuario">Usuario o número de cédula</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-id-card"></i></span>
            </div>
            <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control" placeholder="Ej. 1002345678 o nombre de usuario" required>
          </div>
        </div>

        <div class="form-group">
          <label for="contrasena">Contrasena</label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
            </div>
            <input type="password" id="contrasena" name="contrasena" class="form-control" placeholder="••••••••" required>
            <div class="input-group-append">
              <button type="button" class="btn btn-outline-secondary" id="btnToggleContrasena"
                aria-label="Mostrar contrasena"
                aria-controls="contrasena"
                aria-pressed="false"
                tabindex="-1">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <div class="form-group d-flex justify-content-between align-items-center">
          <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="recordarme">
            <label class="custom-control-label" for="recordarme">Recordarme</label>
          </div>
          <!-- Se conserva la vista recuperar_contrasena.php como referencia de la plantilla.
               El enlace queda deshabilitado hasta implementar el flujo real de recuperacion. -->
          <!-- <a href="<?= RUTA_BASE ?>views/auth/recuperar_contrasena.php" class="text-sena small">Olvido su contrasena?</a> -->
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <i class="fas fa-sign-in-alt"></i> Ingresar
        </button>
      </form>

    </div>
    <div class="card-footer text-center">
      <small class="text-muted">&copy; <?= date('Y') ?> SENA &middot; Servicio Nacional de Aprendizaje</small>
    </div>
  </div>
</div>

<script src="<?= RUTA_BASE ?>assets/js/jquery.min.js"></script>
<script src="<?= RUTA_BASE ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= RUTA_BASE ?>assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<script>
(function() {
  // 1. Manejo seguro de errores desde el backend
  <?php if (isset($_GET['error'])): ?>
    const errorRecibido = <?= json_encode($_GET['error']) ?>;
    const errorMsgLower = errorRecibido.toLowerCase();
    
    let iconType = 'error';
    let titleText = 'No fue posible iniciar sesión';
    let messageText = 'Ocurrió un error al intentar iniciar sesión. Inténtelo de nuevo.';

    if (errorRecibido === 'credenciales') {
      titleText = 'Credenciales inválidas';
      messageText = 'El usuario o la contraseña no son correctos.';
    } else if (errorMsgLower.includes('obligatorio') || errorMsgLower.includes('incompleto') || errorMsgLower.includes('vacío')) {
      iconType = 'warning';
      titleText = 'Campos incompletos';
      messageText = 'Ingrese su usuario y contraseña para continuar.';
    }

    Swal.fire({
      icon: iconType,
      title: titleText,
      text: messageText,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#39A900'
    }).then(() => {
      // Limpieza segura de la URL
      const url = new URL(window.location.href);
      url.searchParams.delete('error');
      window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
    });
  <?php endif; ?>

  // 2. Validación visual frontal antes de enviar
  const formLogin = document.getElementById('formLogin');
  if (formLogin) {
    formLogin.addEventListener('submit', function(e) {
      const userField = document.getElementById('nombre_usuario');
      const passField = document.getElementById('contrasena');
      const userVal = userField.value.trim();
      const passVal = passField.value;

      if (!userVal || !passVal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Campos incompletos',
          text: 'Ingrese su usuario y contraseña para continuar.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#39A900'
        }).then(() => {
          if (!userVal) {
            userField.focus();
          } else {
            passField.focus();
          }
        });
      }
    });
  }

  // 3. Comportamiento original para mostrar/ocultar contraseña
  const input = document.getElementById('contrasena');
  const btn = document.getElementById('btnToggleContrasena');
  if (!input || !btn) return;

  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    btn.querySelector('i').classList.toggle('fa-eye', !mostrar);
    btn.querySelector('i').classList.toggle('fa-eye-slash', mostrar);
    btn.setAttribute('aria-label', mostrar ? 'Ocultar contrasena' : 'Mostrar contrasena');
    btn.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
  });
})();
</script>
</body>
</html>