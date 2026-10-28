<?php
/**
 * ============================================================
 * SCRIPTS / SCRIPTS GENERALES (AdminLTE)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Carga jQuery, Bootstrap, AdminLTE, Chart.js, DataTables y el
 * archivo personalizado custom.js. Cierra el documento HTML.
 * Optimizado con 'defer' para mejorar métricas de Lighthouse.
 * ============================================================
 */
?>
<!-- jQuery (Cargado antes del cierre del body con defer) -->
<script src="<?= RUTA_BASE ?>assets/js/jquery.min.js" defer></script>

<!-- Bootstrap 4 -->
<script src="<?= RUTA_BASE ?>assets/js/bootstrap.bundle.min.js" defer></script>

<!-- Chart.js -->
<script src="<?= RUTA_BASE ?>assets/plugins/chart.js/chart.min.js" defer></script>

<!-- DataTables -->
<script src="<?= RUTA_BASE ?>assets/plugins/datatables/jquery.dataTables.min.js" defer></script>
<script src="<?= RUTA_BASE ?>assets/plugins/datatables/dataTables.bootstrap4.min.js" defer></script>

<!-- AdminLTE -->
<script src="<?= RUTA_BASE ?>assets/js/adminlte.min.js" defer></script>

<!-- SweetAlert2 -->
<script src="<?= RUTA_BASE ?>assets/plugins/sweetalert2/sweetalert2.min.js" defer></script>

<!-- Scripts personalizados del proyecto -->
<script src="<?= RUTA_BASE ?>assets/js/custom.js" defer></script>

<!-- Revalidación estricta ante navegación hacia atrás/adelante (bfcache) -->
<script>
  window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
      window.location.reload();
    }
  });
</script>
</body>
</html>
