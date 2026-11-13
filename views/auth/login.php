<?php
/**
 * ============================================================
 * VISTA: INICIO DE SESIÓN (LOGIN)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Integrado con LoginAttemptModel (Protección contra fuerza bruta).
 * Bloqueo automático por 10 minutos tras 5 intentos fallidos.
 * ============================================================
 */

// Cabeceras anti-caché estrictas para el formulario de autenticación
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/../includes/constantes.php';
require_once dirname(__DIR__) . '/../models/LoginAttemptModel.php';

// Verificación activa de bloqueo por IP o por sesión
$ipCliente = LoginAttemptModel::obtenerIpCliente();
$bloqueoActual = LoginAttemptModel::verificarBloqueo('', $ipCliente);

$segundosBloqueo = 0;
if ($bloqueoActual !== null && (int)$bloqueoActual['segundos_restantes'] > 0) {
    $segundosBloqueo = (int)$bloqueoActual['segundos_restantes'];
} elseif (isset($_SESSION['login_bloqueo_segundos']) && (int)$_SESSION['login_bloqueo_segundos'] > 0) {
    $segundosBloqueo = (int)$_SESSION['login_bloqueo_segundos'];
} elseif (isset($_GET['segundos']) && is_numeric($_GET['segundos']) && (int)$_GET['segundos'] > 0) {
    $segundosBloqueo = (int)$_GET['segundos'];
}

$intentosRestantes = null;
if (isset($_GET['restantes']) && is_numeric($_GET['restantes'])) {
    $intentosRestantes = (int)$_GET['restantes'];
} elseif (isset($_SESSION['login_intentos_restantes'])) {
    $intentosRestantes = (int)$_SESSION['login_intentos_restantes'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SENA | Iniciar sesión</title>
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
<div id="pageTransitionBar" class="page-transition-bar"></div>

<div class="login-split-container min-vh-100 d-flex flex-column flex-md-row fade-in-content">
  <!-- Lado izquierdo / Banner superior móvil: Marca y presentación institucional -->
  <div class="login-brand-side login-split-side col-md-6 col-12 d-flex flex-column justify-content-center align-items-center text-center p-4 p-md-5 text-white position-relative overflow-hidden">
    <!-- Contenido central -->
    <div class="login-brand-content d-flex flex-column align-items-center text-center my-auto w-100">
      <img src="<?= RUTA_BASE ?>assets/img/sena.svg" alt="Logo SENA" width="200" height="200" class="login-brand-logo mx-auto mb-3 mb-md-4 img-fluid">
      <h1 class="font-weight-bold text-white mb-2 mb-md-3 text-center login-brand-title">
        Control de Ingreso<br class="d-none d-md-inline"> de Aprendices
      </h1>
      <p class="text-white-50 lead mb-0 text-center mx-auto login-brand-desc">
        Bienvenido al sistema de registro, asistencia y control de acceso presencial para la comunidad SENA.
      </p>
    </div>

    <!-- Pie inferior centrado (solo desktop) -->
    <div class="position-absolute pb-4 text-center text-white-50 small w-100 d-none d-md-block" style="bottom: 0; left: 0; right: 0;">
      Servicio Nacional de Aprendizaje
    </div>
  </div>

  <!-- Lado derecho / Sección de autenticación -->
  <div class="login-split-form-col col-md-6 col-12 d-flex flex-column justify-content-between align-items-center p-3 p-md-5">

    <!-- Contenedor central del formulario -->
    <div class="login-form-wrapper my-auto py-3 py-md-4">
      <div class="mb-4">
        <h3 class="font-weight-bold text-dark mb-1">Iniciar sesión</h3>
        <p class="text-muted small mb-0">Ingresa tus datos para acceder al sistema</p>
      </div>

      <!-- Banner visual de bloqueo temporal con reloj regresivo en tiempo real -->
      <div class="alert alert-warning border border-warning shadow-sm mb-4 rounded" id="bannerBloqueoLogin" role="alert" style="<?= $segundosBloqueo > 0 ? '' : 'display: none;' ?>">
        <div class="d-flex align-items-center">
          <div class="mr-3 text-warning">
            <i class="fas fa-user-lock fa-3x"></i>
          </div>
          <div>
            <h6 class="font-weight-bold mb-1 text-dark">
              <i class="fas fa-shield-alt mr-1"></i> Acceso bloqueado temporalmente
            </h6>
            <p class="mb-1 text-muted small">
              Has alcanzado el límite de <strong>5 intentos fallidos consecutivos</strong>. Por motivos de seguridad, debes esperar:
            </p>
            <div class="font-weight-bold text-danger h5 mb-0" id="contadorRegresivoReloj">
              <i class="fas fa-clock mr-1"></i> <span id="tiempoRestanteTexto">--:--</span>
            </div>
          </div>
        </div>
      </div>

      <?php if (isset($_GET['error'])): ?>
        <!-- Mensaje de error accesible si JS está desactivado -->
        <noscript>
          <div class="alert alert-danger" role="alert">
            <?php
              if ($_GET['error'] === 'credenciales') {
                  echo 'El usuario o la contraseña no son correctos.';
              } elseif ($_GET['error'] === 'bloqueado' || $_GET['error'] === 'limite') {
                  echo 'Acceso temporalmente restringido por 5 intentos fallidos. Espere 10 minutos.';
              } else {
                  echo htmlspecialchars($_GET['error']);
              }
            ?>
          </div>
        </noscript>
      <?php endif; ?>

      <!-- Formulario de login que envía a controllers/AuthController.php -->
      <form id="formLogin" action="<?= RUTA_BASE ?>controllers/AuthController.php" method="post" autocomplete="off" novalidate>
        <input type="hidden" name="accion" value="login">
        
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

        <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm btn-submit-login" id="btnSubmitLogin" <?= $segundosBloqueo > 0 ? 'disabled' : '' ?>>
          <i class="fas <?= $segundosBloqueo > 0 ? 'fa-lock' : 'fa-sign-in-alt' ?> mr-2" id="iconoSubmitLogin"></i>
          <span id="textoSubmitLogin"><?= $segundosBloqueo > 0 ? 'Bloqueado por seguridad' : 'Ingresar' ?></span>
        </button>
      </form>
    </div>

    <!-- Footer discreto -->
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
  // Configuración de bloqueo y tiempos
  let tiempoRestante = <?= (int)$segundosBloqueo ?>;
  const intentosRestantes = <?= json_encode($intentosRestantes) ?>;

  const btnSubmit = document.getElementById('btnSubmitLogin');
  const bannerBloqueo = document.getElementById('bannerBloqueoLogin');
  const textoReloj = document.getElementById('tiempoRestanteTexto');
  const textoSubmit = document.getElementById('textoSubmitLogin');
  const iconoSubmit = document.getElementById('iconoSubmitLogin');
  const inputUsuario = document.getElementById('nombre_usuario');
  const inputContrasena = document.getElementById('contrasena');

  function formatearTiempo(segundos) {
    const min = Math.floor(segundos / 60);
    const seg = segundos % 60;
    return (min < 10 ? '0' : '') + min + ':' + (seg < 10 ? '0' : '') + seg;
  }

  function actualizarVisualReloj() {
    const textoFmt = formatearTiempo(tiempoRestante);
    if (textoReloj) {
      textoReloj.textContent = textoFmt;
    }
    if (textoSubmit && btnSubmit && btnSubmit.disabled) {
      textoSubmit.textContent = 'Bloqueado (' + textoFmt + ')';
    }
  }

  // Activación del temporizador regresivo si existe bloqueo activo
  if (tiempoRestante > 0) {
    if (btnSubmit) btnSubmit.disabled = true;
    if (iconoSubmit) {
      iconoSubmit.classList.remove('fa-sign-in-alt');
      iconoSubmit.classList.add('fa-lock');
    }
    actualizarVisualReloj();

    const intervalo = setInterval(function() {
      tiempoRestante--;
      if (tiempoRestante <= 0) {
        clearInterval(intervalo);
        if (bannerBloqueo) {
          bannerBloqueo.style.display = 'none';
        }
        if (btnSubmit) {
          btnSubmit.disabled = false;
          if (textoSubmit) textoSubmit.textContent = 'Ingresar';
          if (iconoSubmit) {
            iconoSubmit.classList.remove('fa-lock');
            iconoSubmit.classList.add('fa-sign-in-alt');
          }
        }
        Swal.fire({
          icon: 'info',
          title: 'Tiempo de espera finalizado',
          text: 'El bloqueo temporal de 10 minutos ha culminado. Ya puedes volver a ingresar tus credenciales.',
          confirmButtonText: 'Continuar',
          confirmButtonColor: '#39A900'
        }).then(() => {
          if (inputUsuario) inputUsuario.focus();
        });
      } else {
        actualizarVisualReloj();
      }
    }, 1000);
  }

  // 1. Manejo de alertas con SweetAlert2 basadas en parámetros backend
  <?php if (isset($_GET['error'])): ?>
    const errorRecibido = <?= json_encode($_GET['error']) ?>;
    const errorMsgLower = errorRecibido.toLowerCase();
    
    let iconType = 'error';
    let titleText = 'No fue posible iniciar sesión';
    let messageText = 'Ocurrió un error al intentar iniciar sesión. Inténtelo de nuevo.';

    if (errorRecibido === 'bloqueado' || errorRecibido === 'bloqueo' || errorRecibido === 'limite') {
      iconType = 'warning';
      titleText = 'Acceso bloqueado temporalmente';
      messageText = 'Has superado el límite de 5 intentos fallidos consecutivos. Por motivos de seguridad, el acceso ha quedado bloqueado durante 10 minutos.';
    } else if (errorRecibido === 'credenciales' || errorRecibido === 'credenciales_intentos') {
      iconType = 'warning';
      titleText = 'Credenciales incorrectas';
      if (intentosRestantes !== null && intentosRestantes > 0) {
        const palabraIntento = (intentosRestantes === 1) ? 'intento' : 'intentos';
        messageText = `El usuario o la contraseña no son correctos. Te quedan ${intentosRestantes} ${palabraIntento} antes del bloqueo temporal de 10 minutos.`;
      } else {
        messageText = 'El usuario o la contraseña no son correctos.';
      }
    } else if (errorMsgLower.includes('obligatorio') || errorMsgLower.includes('incompleto') || errorMsgLower.includes('vacío')) {
      iconType = 'warning';
      titleText = 'Campos incompletos';
      messageText = 'Ingrese su usuario y contraseña para continuar.';
    } else {
      messageText = errorRecibido;
    }

    Swal.fire({
      icon: iconType,
      title: titleText,
      text: messageText,
      confirmButtonText: 'Entendido',
      confirmButtonColor: '#39A900'
    }).then(() => {
      // Limpieza segura de los parámetros de URL tras mostrar la alerta
      const url = new URL(window.location.href);
      url.searchParams.delete('error');
      url.searchParams.delete('segundos');
      url.searchParams.delete('restantes');
      window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);

      if (inputUsuario && !btnSubmit.disabled) {
        inputUsuario.focus();
      }
    });
  <?php endif; ?>

  // 2. Validación visual frontal antes de enviar
  const formLogin = document.getElementById('formLogin');
  if (formLogin) {
    formLogin.addEventListener('submit', function(e) {
      if (btnSubmit && btnSubmit.disabled) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Acceso bloqueado',
          text: 'Debes esperar a que finalice el contador regresivo para volver a intentar.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#39A900'
        });
        return;
      }

      const userVal = inputUsuario ? inputUsuario.value.trim() : '';
      const passVal = inputContrasena ? inputContrasena.value : '';

      if (!userVal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Usuario requerido',
          text: 'Por favor, ingrese su usuario o número de cédula para continuar.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#39A900',
          didClose: function() {
            if (inputUsuario) inputUsuario.focus();
          }
        });
        return;
      }

      if (!passVal) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Contraseña requerida',
          text: 'Por favor, ingrese su contraseña para continuar.',
          confirmButtonText: 'Entendido',
          confirmButtonColor: '#39A900',
          didClose: function() {
            if (inputContrasena) inputContrasena.focus();
          }
        });
        return;
      }

      iniciarTransicion();
    });
  }

  // 3. Comportamiento para mostrar/ocultar contraseña
  const btnToggle = document.getElementById('btnToggleContrasena');
  if (inputContrasena && btnToggle) {
    btnToggle.addEventListener('click', function(e) {
      e.preventDefault();
      const mostrar = inputContrasena.type === 'password';
      inputContrasena.type = mostrar ? 'text' : 'password';
      btnToggle.querySelector('i').classList.toggle('fa-eye', !mostrar);
      btnToggle.querySelector('i').classList.toggle('fa-eye-slash', mostrar);
      btnToggle.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
      btnToggle.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
    });
  }

  // 4. Control de barra de carga de navegación (Loading bar)
  function iniciarTransicion() {
    let bar = document.getElementById('pageTransitionBar');
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'pageTransitionBar';
      bar.className = 'page-transition-bar';
      document.body.prepend(bar);
    }
    bar.classList.remove('page-transition-finish');
    bar.classList.remove('page-transition-loading');
    void bar.offsetWidth;
    bar.classList.add('page-transition-loading');
  }

  function finalizarTransicion() {
    const bar = document.getElementById('pageTransitionBar');
    if (bar) {
      bar.classList.remove('page-transition-loading');
      bar.classList.add('page-transition-finish');
      setTimeout(function() {
        if (bar && bar.classList.contains('page-transition-finish')) {
          bar.classList.remove('page-transition-finish');
        }
      }, 350);
    }
  }

  if (document.readyState === 'complete') {
    finalizarTransicion();
  } else {
    window.addEventListener('load', finalizarTransicion);
  }
  window.addEventListener('pageshow', finalizarTransicion);
})();
</script>
</body>
</html>
