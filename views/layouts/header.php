<?php
/**
 * ============================================================
 * HEADER / CABECERA GENERAL (AdminLTE)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Abre el documento HTML, carga los estilos CSS y define las
 * variables generales que usa el resto del layout.
 * ============================================================
 */

/**
 * Variables opcionales provistas por la vista antes de incluir el layout.
 *
 * @var string|null $paginaTitulo  Título de la página (fallback: 'Panel')
 * @var string|null $menuActivo    Clave del menú activo para sidebar
 * @var array|null  $miga          Array de breadcrumbs [['label', 'url?'], ...]
 */

// Garantiza que las constantes existan
if (!defined('RUTA_BASE')) {
    require_once dirname(__DIR__) . '/includes/constantes.php';
}

// Cargar autenticacion para acceder a usuarioActual()
require_once __DIR__ . '/../../includes/autenticacion.php';

// Obtener usuario actual desde la sesion real
$usuarioActual = usuarioActual();

// Si no hay usuario valido, redirigir al login
if ($usuarioActual === null) {
    redirigirAlLogin();
}

// Variables derivadas de la sesion real
$rolActualId = $usuarioActual['fk_rol'];
$rolClaveActual = rolClave($rolActualId) ?? 'admin';
$nombreRolActual = $usuarioActual['nombre_rol'];
$nombreUsuarioActual = $usuarioActual['nombre_completo'];
$fotoUsuario = RUTA_BASE . 'assets/img/sena.svg';

$paginaTitulo = $paginaTitulo ?? 'Panel';
$menuActivo   = $menuActivo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Sistema de Control de Ingreso de Aprendices - SENA (prototipo visual)">
  <title>SENA | <?= htmlspecialchars($paginaTitulo) ?></title>

  <!-- Icono de la página -->
  <link rel="icon" href="<?= RUTA_BASE ?>assets/img/sena.svg">

  <!-- Preconexión a Google Fonts para acelerar FCP / LCP (Lighthouse) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Fuente institucional (opcional; si no hay internet usa la fuente del sistema) -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=swap">

  <!-- Font Awesome (íconos) -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/icons/fontawesome/css/all.min.css">

  <!-- Bootstrap 4 -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/bootstrap.min.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/plugins/datatables/dataTables.bootstrap4.min.css">

  <!-- AdminLTE -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/adminlte.min.css">

  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/plugins/sweetalert2/sweetalert2.min.css">

  <!-- Estilos personalizados del proyecto -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/custom.css">

  <!-- Estilos del calendario semanal visual -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/calendario.css">
  <style>
    /* Desplazamiento natural */
    html {
        scroll-behavior: smooth;
    }

    /* Scrollbar personalizada corporativa */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }
    ::-webkit-scrollbar-track {
        background: #f8f9fa; 
    }
    ::-webkit-scrollbar-thumb {
        background: #d1d5db; 
        border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #39A900; /* Verde SENA */
    }
  </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed" data-base="<?= RUTA_BASE ?>">
<div id="pageTransitionBar" class="page-transition-bar"></div>
<div class="wrapper fade-in-content">
