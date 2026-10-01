<?php
/**
 * ============================================================
 * VISTA: PRUEBA DE LECTOR RFID (Solo Administrador)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * Vista temporal para probar el lector RFID USB en el navegador.
 * No guarda datos en base de datos, solo muestra lecturas en memoria.
 * ============================================================
 */

require_once dirname(__DIR__) . '/../includes/autenticacion.php';
exigirRol(1);
require_once dirname(__DIR__) . '/../includes/constantes.php';

// --- Variables del layout ---
$paginaTitulo = 'Prueba lector RFID';
$menuActivo   = '';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Prueba RFID'],
];

// --- Captura del contenido de la vista ---
ob_start();
?>
<!-- ==================== PRUEBA RFID ==================== -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fas fa-id-badge text-sena"></i> Prueba de lector RFID</h3>
      </div>
      <div class="card-body">
        <div class="alert alert-info mb-4">
          <i class="fas fa-info-circle mr-2"></i>
          Acerque una tarjeta al lector. El lector debe escribir el código y finalizar con Enter.
        </div>

        <div class="row mb-4">
          <div class="col-md-8">
            <div class="form-group">
              <label for="codigo_rfid_prueba"><strong>Código RFID:</strong></label>
              <input
                type="text"
                id="codigo_rfid_prueba"
                class="form-control form-control-lg"
                autocomplete="off"
                placeholder="Acerque la tarjeta RFID…"
                autofocus
                style="font-size: 1.25rem; font-family: monospace;"
              >
              <small class="form-text text-muted" id="estado_lectura">Esperando lectura…</small>
            </div>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="btn-group w-100" role="group">
              <button type="button" id="btnLimpiar" class="btn btn-outline-secondary" title="Borrar lista de lecturas">
                <i class="fas fa-trash-alt mr-1"></i> Limpiar lecturas
              </button>
              <button type="button" id="btnProbarFoco" class="btn btn-outline-primary" title="Devolver foco al campo RFID">
                <i class="fas fa-mouse-pointer mr-1"></i> Probar foco
              </button>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title mb-0"><i class="fas fa-list mr-2"></i> Lecturas realizadas (<span id="contador_lecturas">0</span>)</h3>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="tabla_lecturas">
                <thead class="thead-light">
                  <tr>
                    <th style="width: 60px;">#</th>
                    <th>Código leído</th>
                    <th style="width: 180px;">Hora local</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Las filas se agregan dinámicamente por JavaScript -->
                </tbody>
              </table>
            </div>
            <div id="sin_lecturas" class="text-center py-4 text-muted" style="display: none;">
              <i class="fas fa-rfid fa-2x mb-2 d-block"></i>
              Sin lecturas aún. Acerque una tarjeta al lector.
            </div>
          </div>
        </div>

        <div id="ultimo_codigo" class="mt-3 p-3 bg-light border rounded" style="display: none;">
          <strong>Último código leído:</strong>
          <code id="ultimo_codigo_valor" class="ml-2" style="font-size: 1.1rem;"></code>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  'use strict';

  // Elementos del DOM
  const inputRfid = document.getElementById('codigo_rfid_prueba');
  const estadoLectura = document.getElementById('estado_lectura');
  const tablaLecturas = document.getElementById('tabla_lecturas').querySelector('tbody');
  const contadorLecturas = document.getElementById('contador_lecturas');
  const sinLecturas = document.getElementById('sin_lecturas');
  const ultimoCodigoDiv = document.getElementById('ultimo_codigo');
  const ultimoCodigoValor = document.getElementById('ultimo_codigo_valor');
  const btnLimpiar = document.getElementById('btnLimpiar');
  const btnProbarFoco = document.getElementById('btnProbarFoco');

  // Estado interno
  let lecturas = [];
  let contador = 0;
  let procesandoEnter = false;

  // Función para formatear hora local
  function formatearHora() {
    const ahora = new Date();
    return ahora.toLocaleTimeString('es-CO', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    });
  }

  // Función para actualizar contador y visibilidad
  function actualizarVista() {
    contadorLecturas.textContent = lecturas.length;
    if (lecturas.length === 0) {
      sinLecturas.style.display = 'block';
      tablaLectras.style.display = 'none';
    } else {
      sinLecturas.style.display = 'none';
      tablaLecturas.style.display = '';
    }
  }

  // Función para agregar lectura a la tabla
  function agregarLectura(codigo) {
    contador++;
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td class="text-center">${contador}</td>
      <td><code>${codigo}</code></td>
      <td class="text-center">${formatearHora()}</td>
    `;
    tablaLecturas.appendChild(fila);

    // Mostrar último código
    ultimoCodigoValor.textContent = codigo;
    ultimoCodigoDiv.style.display = 'block';

    // Scroll hacia la nueva fila
    fila.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // Función para limpiar lista
  function limpiarLista() {
    lecturas = [];
    contador = 0;
    tablaLecturas.innerHTML = '';
    ultimoCodigoDiv.style.display = 'none';
    actualizarVista();
    enfocarInput();
  }

  // Función para enfocar el input
  function enfocarInput() {
    inputRfid.focus();
    inputRfid.select();
  }

  // Evento: tecla Enter en el input
  inputRfid.addEventListener('keydown', function(event) {
    if (event.key === 'Enter') {
      event.preventDefault();

      // Evitar doble procesamiento por mantener Enter presionado
      if (procesandoEnter) {
        return;
      }
      procesandoEnter = true;

      const valorBruto = inputRfid.value;
      const codigo = valorBruto.trim().replace(/[\s\t\n\r]+/g, '');

      if (codigo !== '') {
        lecturas.push({ codigo: codigo, hora: formatearHora() });
        agregarLectura(codigo);
        actualizarVista();
      }

      // Limpiar input y re-enfocar
      inputRfid.value = '';
      enfocarInput();

      // Resetear flag después de un breve delay
      setTimeout(function() {
        procesandoEnter = false;
      }, 100);
    }
  });

  // Evento: botón Limpiar lecturas
  btnLimpiar.addEventListener('click', function() {
    limpiarLista();
  });

  // Evento: botón Probar foco
  btnProbarFoco.addEventListener('click', function() {
    enfocarInput();
  });

  // Inicialización
  actualizarVista();
  enfocarInput();
})();
</script>
<?php
$contenido = ob_get_clean();

// --- Render del layout ---
include __DIR__ . '/../layouts/layout_admin.php';