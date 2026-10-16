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

<div class="login-split-container min-vh-100 d-flex flex-column flex-md-row">
  <!-- Lado izquierdo: Marca y presentación institucional (visible en md y superiores) -->
  <div class="login-brand-side login-split-side col-md-6 d-none d-md-flex flex-column justify-content-center align-items-center text-center p-5 text-white position-relative overflow-hidden">
    <!-- Contenido central 100% centrado -->
    <div class="login-brand-content d-flex flex-column align-items-center text-center my-auto">
      <img src="<?= RUTA_BASE ?>assets/img/sena.svg" alt="Logo SENA" width="200" height="200" class="login-brand-logo mx-auto mb-4 img-fluid">
      <h1 class="display-5 font-weight-bold text-white mb-3 text-center" style="line-height: 1.15; font-size: 2.3rem;">
        Control de Ingreso<br>de Aprendices
      </h1>
      <p class="text-white-50 lead mb-0 text-center mx-auto" style="font-size: 1.05rem; line-height: 1.6; max-width: 440px;">
        Bienvenido al sistema de registro, asistencia y control de acceso presencial para la comunidad SENA.
      </p>
    </div>

    <!-- Pie inferior centrado -->
    <div class="position-absolute pb-4 text-center text-white-50 small w-100" style="bottom: 0; left: 0; right: 0;">
      Servicio Nacional de Aprendizaje
    </div>
  </div>

  <!-- Lado derecho: Formulario de autenticación centrado -->
  <div class="login-split-form-col col-md-6 col-12 d-flex flex-column justify-content-between align-items-center bg-white p-4 p-md-5 min-vh-100">
    <!-- Cabecera visible solo en móvil -->
    <div class="d-md-none w-100 text-center pt-3 pb-2">
      <img src="<?= RUTA_BASE ?>assets/img/sena.svg" alt="Logo SENA" width="60" height="60" class="mb-2">
      <h2 class="h5 font-weight-bold text-dark mb-0">SENA &middot; Control de Ingreso</h2>
    </div>

    <!-- Contenedor central del formulario -->
    <div class="login-form-wrapper my-auto py-4">
      <div class="mb-4">
        <h3 class="font-weight-bold text-dark mb-1">Iniciar sesión</h3>
        <p class="text-muted small mb-0">Ingresa tus datos para acceder al sistema</p>
      </div>

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
        <div class="form-group mb-3">
          <label for="nombre_usuario" class="font-weight-bold text-dark small">Usuario o número de cédula <span class="text-danger">*</span></label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text bg-light border-right-0 text-muted"><i class="fas fa-id-card"></i></span>
            </div>
            <input type="text" id="nombre_usuario" name="nombre_usuario" class="form-control border-left-0" placeholder="Ej. 1002345678 o nombre de usuario" required autocomplete="username">
          </div>
        </div>

        <div class="form-group mb-4">
          <label for="contrasena" class="font-weight-bold text-dark small">Contraseña <span class="text-danger">*</span></label>
          <div class="input-group">
            <div class="input-group-prepend">
              <span class="input-group-text bg-light border-right-0 text-muted"><i class="fas fa-lock"></i></span>
            </div>
            <input type="password" id="contrasena" name="contrasena" class="form-control border-left-0 border-right-0" placeholder="••••••••" required autocomplete="current-password">
            <div class="input-group-append">
              <button type="button" class="btn btn-outline-secondary border-left-0" id="btnToggleContrasena"
                aria-label="Mostrar contraseña"
                aria-controls="contrasena"
                aria-pressed="false"
                tabindex="-1">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm">
          <i class="fas fa-sign-in-alt mr-2"></i> Ingresar
        </button>
      </form>
    </div>

    <!-- Footer discreto en el lado derecho -->
    <div class="login-footer text-center text-muted small pb-2 w-100">
      SENA &copy; 2026 &bull; Todos los derechos reservados
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

      const userField = document.getElementById('nombre_usuario');
      if (userField) {
        userField.focus();
      }
    });
  <?php endif; ?>

  // 2. Validación visual frontal antes de enviar con foco preciso
  const formLogin = document.getElementById('formLogin');
  if (formLogin) {
    formLogin.addEventListener('submit', function(e) {
      const userField = document.getElementById('nombre_usuario');
      const passField = document.getElementById('contrasena');
      const userVal = userField ? userField.value.trim() : '';
      const passVal = passField ? passField.value : '';

      // Si falta el usuario
      if (!userVal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Usuario requerido',
          text: 'Por favor, ingrese su usuario o número de cédula para continuar.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#39A900',
          didClose: function() {
            if (userField) {
              userField.focus();
            }
          }
        }).then(function() {
          if (userField) {
            userField.focus();
          }
        });
        return;
      }

      // Si el usuario está lleno pero falta la contraseña
      if (!passVal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Contraseña requerida',
          text: 'Por favor, ingrese su contraseña para continuar.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#39A900',
          didClose: function() {
            if (passField) {
              passField.focus();
            }
          }
        }).then(function() {
          if (passField) {
            passField.focus();
          }
        });
        return;
      }
    });
  }

  // 3. Comportamiento para mostrar/ocultar contraseña
  const input = document.getElementById('contrasena');
  const btn = document.getElementById('btnToggleContrasena');
  if (!input || !btn) return;

  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const mostrar = input.type === 'password';
    input.type = mostrar ? 'text' : 'password';
    btn.querySelector('i').classList.toggle('fa-eye', !mostrar);
    btn.querySelector('i').classList.toggle('fa-eye-slash', mostrar);
    btn.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
    btn.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
  });
})();
</script>
</body>
</html>