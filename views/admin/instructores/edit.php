<?php
$rolGestionado = 2;
$edicion = true;
require __DIR__ . '/../usuario_form.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var docInput = document.getElementById('documento');
    if (docInput) {
        document.getElementById('documento').addEventListener('input', function() {
            let docValue = this.value;
            document.getElementById('usuario').value = docValue;
            document.getElementById('contrasena').value = docValue;
        });
    }
});
</script>
