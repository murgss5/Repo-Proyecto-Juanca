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

// Garantiza que las constantes y datos de demostración existan
if (!defined('RUTA_BASE')) {
    require_once dirname(__DIR__) . '/includes/constantes.php';
}

// Valores por defecto (pueden ser definidos por cada vista)
$paginaTitulo = $paginaTitulo ?? 'Panel';
$menuActivo   = $menuActivo ?? '';
$rolDemo      = $rolDemo ?? 'admin';
$rolNombre    = $rolNombre ?? 'Usuario';

// Datos visibles del usuario autenticado.
$usuarioDemo = $usuarioDemo ?? trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
$usuarioDemo = $usuarioDemo !== '' ? $usuarioDemo : 'Usuario';
$fotoUsuario = $fotoUsuario ?? RUTA_BASE . 'assets/img/avatar.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Sistema de Control de Ingreso de Aprendices - SENA (prototipo visual)">
  <title>SENA | <?= htmlspecialchars($paginaTitulo) ?></title>

  <!-- Icono de la página -->
  <link rel="icon" href="<?= RUTA_BASE ?>assets/img/logo-sena.svg">

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

  <!-- Estilos personalizados del proyecto -->
  <link rel="stylesheet" href="<?= RUTA_BASE ?>assets/css/custom.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed" data-base="<?= RUTA_BASE ?>">
<div class="wrapper">
