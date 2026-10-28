<?php
/**
 * Vista: Listado de aprendices (Administrador).
 * Muestra el listado real de aprendices con opciones de editar y eliminar,
 * indicador de estado de correo, modal de despacho masivo de credenciales
 * y modal de asignación rápida de correo para aprendices sin correo registrado.
 */

// Carga la autenticacion y verifica que sea administrador
require_once dirname(__DIR__) . '/../../includes/autenticacion.php';
exigirRol(1);

// Carga constantes y conexion a la base de datos
require_once dirname(__DIR__) . '/../../includes/constantes.php';
require_once dirname(__DIR__) . '/../../config/conexion.php';

// Variables para el layout
$paginaTitulo = 'Aprendices';
$menuActivo   = 'aprendices';
$miga = [
    ['label' => 'Inicio', 'url' => RUTA_BASE . 'views/admin/dashboard.php'],
    ['label' => 'Aprendices'],
];

$conexion = (new Conexion())->conectar();

// Genera token CSRF si no existe
if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

// Garantizar columna correo_enviado en tabla aprendiz
try {
    $conexion->exec("ALTER TABLE aprendiz ADD COLUMN correo_enviado TINYINT(1) NOT NULL DEFAULT 0");
} catch (\Throwable $e) {}

// Obtiene las fichas para el filtro
$consultaFichas = $conexion->query('SELECT id_ficha FROM ficha ORDER BY id_ficha');
$fichasRegistradas = $consultaFichas->fetchAll();

// Consulta los aprendices con sus datos relacionados y estado de correo
try {
    $consultaAprendices = $conexion->query(
        'SELECT
            aprendiz.id_aprendiz,
            aprendiz.fk_usuario AS id_usuario,
            usuario.identificacion AS documento,
            CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
            usuario.telefono,
            usuario.correo_electronico,
            ficha.id_ficha,
            aprendiz.codigo_rfid,
            COALESCE(aprendiz.correo_enviado, 0) AS correo_enviado
         FROM aprendiz
         INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
         INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
         ORDER BY usuario.apellido, usuario.nombre'
    );
    $aprendicesRegistrados = $consultaAprendices->fetchAll();
} catch (\Throwable $e) {
    $consultaAprendices = $conexion->query(
        'SELECT
            aprendiz.id_aprendiz,
            aprendiz.fk_usuario AS id_usuario,
            usuario.identificacion AS documento,
            CONCAT(usuario.nombre, CHAR(32), usuario.apellido) AS nombre,
            usuario.telefono,
            usuario.correo_electronico,
            ficha.id_ficha,
            aprendiz.codigo_rfid,
            0 AS correo_enviado
         FROM aprendiz
         INNER JOIN usuario ON aprendiz.fk_usuario = usuario.id_usuario
         INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
         ORDER BY usuario.apellido, usuario.nombre'
    );
    $aprendicesRegistrados = $consultaAprendices->fetchAll();
}

// Filtrar aprendices con correo válido pendientes de notificación
$aprendicesPendientes = array_filter($aprendicesRegistrados, function($a) {
    return (empty($a['correo_enviado']) || (int)$a['correo_enviado'] === 0)
        && !empty($a['correo_electronico'])
        && filter_var($a['correo_electronico'], FILTER_VALIDATE_EMAIL);
});
$totalPendientes = count($aprendicesPendientes);

ob_start();
?>

<!-- Alertas de estado -->
<?php if (isset($_GET['mensaje'])): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_GET['mensaje']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($_GET['error']) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Tarjeta Principal de Aprendices -->
<div class="card card-outline card-success shadow-sm">
    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
        <h3 class="card-title font-weight-bold text-dark mb-0">
            <i class="fas fa-user-graduate text-success mr-2"></i> Listado de Aprendices
        </h3>
        <div class="card-tools ml-auto d-flex align-items-center">
            <!-- Botón de Envío de Correos Pendientes -->
            <button type="button" class="btn btn-info btn-sm font-weight-bold shadow-sm mr-2" data-toggle="modal" data-target="#modalCorreosPendientes" title="Enviar credenciales a aprendices pendientes">
                <i class="fas fa-paper-plane mr-1"></i> Enviar Gmail Pendientes
                <?php if ($totalPendientes > 0): ?>
                    <span class="badge badge-light text-info font-weight-bold ml-1"><?= $totalPendientes ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= RUTA_BASE ?>views/admin/aprendices/importar.php" class="btn btn-outline-success btn-sm font-weight-bold shadow-sm mr-2">
                <i class="fas fa-file-excel mr-1"></i> Importar Excel
            </a>
            <a href="<?= RUTA_BASE ?>views/admin/aprendices/create.php" class="btn btn-success btn-sm font-weight-bold shadow-sm">
                <i class="fas fa-plus mr-1"></i> Nuevo Aprendiz
            </a>
        </div>
    </div>

    <div class="card-body p-4">
        <!-- Barra superior interna: Buscador y Filtros -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-5 col-sm-12 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    </div>
                    <input type="text" class="form-control buscador-tabla" placeholder="Buscar aprendiz por nombre, documento o RFID...">
                </div>
            </div>
            <div class="col-md-4 col-sm-6 mb-2">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white"><i class="fas fa-layer-group text-muted"></i></span>
                    </div>
                    <select class="form-control custom-select filtro-tabla" data-col="3">
                        <option value="">Todas las fichas</option>
                        <?php foreach ($fichasRegistradas as $ficha): ?>
                            <option value="Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?>">Ficha #<?= htmlspecialchars($ficha['id_ficha']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tabla de Datos -->
        <div class="table-responsive">
            <table class="table table-hover table-striped text-nowrap tabla-datos" data-empty-message="No se encontraron aprendices registrados.">
                <thead class="thead-light">
                    <tr>
                        <th>Documento</th>
                        <th>Nombre Completo</th>
                        <th>Teléfono</th>
                        <th>Ficha</th>
                        <th>Código RFID</th>
                        <th class="text-center" style="width: 140px;">Credenciales</th>
                        <th class="text-center" style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aprendicesRegistrados as $aprendiz): ?>
                        <tr>
                            <td class="align-middle font-weight-bold"><?= htmlspecialchars($aprendiz['documento']) ?></td>
                            <td class="align-middle"><?= htmlspecialchars($aprendiz['nombre']) ?></td>
                            <td class="align-middle text-muted"><?= htmlspecialchars($aprendiz['telefono'] ?? 'Sin registro') ?></td>
                            <td class="align-middle">
                                <span class="badge badge-info px-2 py-1">Ficha #<?= htmlspecialchars($aprendiz['id_ficha']) ?></span>
                            </td>
                            <td class="align-middle">
                                <code><?= htmlspecialchars($aprendiz['codigo_rfid']) ?></code>
                            </td>
                            <!-- Indicador de Estado de Correo / Botón Asignar Correo -->
                            <td class="text-center align-middle">
                                <?php if (!empty($aprendiz['correo_enviado']) && (int)$aprendiz['correo_enviado'] === 1): ?>
                                    <span class="badge badge-success px-2 py-1" title="Credenciales enviadas a <?= htmlspecialchars($aprendiz['correo_electronico'] ?? '') ?>">
                                        <i class="fas fa-check-circle mr-1"></i> Enviado
                                    </span>
                                <?php elseif (!empty($aprendiz['correo_electronico']) && filter_var($aprendiz['correo_electronico'], FILTER_VALIDATE_EMAIL)): ?>
                                    <span class="badge badge-warning text-dark px-2 py-1" title="Pendiente de enviar credenciales a <?= htmlspecialchars($aprendiz['correo_electronico']) ?>">
                                        <i class="fas fa-clock mr-1"></i> Pendiente
                                    </span>
                                <?php else: ?>
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-sm shadow-sm btn-abrir-asignar-correo text-nowrap" 
                                            data-toggle="modal" 
                                            data-target="#modalAsignarCorreo" 
                                            data-id-usuario="<?= htmlspecialchars($aprendiz['id_usuario']) ?>" 
                                            data-id-aprendiz="<?= htmlspecialchars($aprendiz['id_aprendiz']) ?>" 
                                            data-nombre="<?= htmlspecialchars($aprendiz['nombre']) ?>" 
                                            data-documento="<?= htmlspecialchars($aprendiz['documento']) ?>" 
                                            title="Asignar correo electrónico para habilitar envío de credenciales">
                                        <i class="fas fa-envelope-open-text mr-1"></i> Asignar correo
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td class="text-center align-middle">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-warning btn-sm shadow-sm" href="<?= RUTA_BASE ?>views/admin/aprendices/edit.php?id=<?= htmlspecialchars($aprendiz['id_usuario']) ?>" title="Editar aprendiz">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="<?= RUTA_BASE ?>controllers/gestionar_usuario.php" method="post" class="d-inline ml-1" onsubmit="return confirm('¿Está seguro de eliminar este aprendiz?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="rol" value="3">
                                        <input type="hidden" name="id_usuario" value="<?= htmlspecialchars($aprendiz['id_usuario']) ?>">
                                        <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm shadow-sm" title="Eliminar aprendiz">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL 1: ENVÍO DE CREDENCIALES PENDIENTES POR CORREO GMAIL  -->
<!-- ============================================================ -->
<div class="modal fade" id="modalCorreosPendientes" tabindex="-1" role="dialog" aria-labelledby="modalCorreosLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-info text-white py-3">
                <h5 class="modal-title font-weight-bold mb-0" id="modalCorreosLabel">
                    <i class="fas fa-paper-plane mr-2"></i> Enviar Gmail de Credenciales Pendientes
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="<?= RUTA_BASE ?>controllers/enviar_correos_pendientes.php" method="post" id="formEnvioMasivoCorreos">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">

                <div class="modal-body p-4">
                    <?php if (empty($aprendicesPendientes)): ?>
                        <div class="text-center py-5">
                            <div class="mb-3 text-success">
                                <i class="fas fa-check-circle fa-4x"></i>
                            </div>
                            <h5 class="font-weight-bold text-dark mb-2">¡Todos los correos están al día!</h5>
                            <p class="text-muted small mb-0">
                                No se encontraron aprendices con credenciales pendientes de envío por correo electrónico.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border border-info mb-3 p-3 rounded">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle text-info fa-2x mr-3"></i>
                                <div class="small text-dark">
                                    Se despachará una notificación por correo electrónico con las credenciales institucionales (<strong>Usuario: CC</strong> y <strong>Contraseña: CC</strong>) a cada aprendiz seleccionado.
                                </div>
                            </div>
                        </div>

                        <!-- Barra de Control del Modal: Selección y Búsqueda Rápida -->
                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 bg-light p-2 rounded border">
                            <div class="d-flex align-items-center mb-1 mb-sm-0">
                                <div class="custom-control custom-checkbox mr-3">
                                    <input type="checkbox" class="custom-control-input" id="checkSeleccionarTodos">
                                    <label class="custom-control-label font-weight-bold" for="checkSeleccionarTodos" style="cursor: pointer;">
                                        Seleccionar todos
                                    </label>
                                </div>
                                <span class="badge badge-info px-2 py-1">
                                    Seleccionados: <strong id="contadorSeleccionadosModal">0</strong> / <?= count($aprendicesPendientes) ?>
                                </span>
                            </div>
                            <div>
                                <input type="text" id="filtroModalAprendices" class="form-control form-control-sm" placeholder="Buscar en esta lista..." style="max-width: 210px;">
                            </div>
                        </div>

                        <!-- Tabla de Selección de Aprendices -->
                        <div class="table-responsive border rounded" style="max-height: 330px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0" id="tablaAprendicesModal">
                                <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width: 45px;" class="text-center">#</th>
                                        <th>Documento (CC)</th>
                                        <th>Aprendiz</th>
                                        <th>Correo Electrónico</th>
                                        <th>Ficha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($aprendicesPendientes as $ap): ?>
                                        <tr class="fila-aprendiz-modal">
                                            <td class="text-center align-middle">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" name="aprendices_ids[]" value="<?= htmlspecialchars($ap['id_aprendiz']) ?>" class="custom-control-input check-aprendiz-modal" id="chk_ap_<?= htmlspecialchars($ap['id_aprendiz']) ?>">
                                                    <label class="custom-control-label" for="chk_ap_<?= htmlspecialchars($ap['id_aprendiz']) ?>" style="cursor: pointer;"></label>
                                                </div>
                                            </td>
                                            <td class="align-middle font-weight-bold"><?= htmlspecialchars($ap['documento']) ?></td>
                                            <td class="align-middle"><?= htmlspecialchars($ap['nombre']) ?></td>
                                            <td class="align-middle text-muted small"><?= htmlspecialchars($ap['correo_electronico']) ?></td>
                                            <td class="align-middle">
                                                <span class="badge badge-info">Ficha #<?= htmlspecialchars($ap['id_ficha']) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <?php if (!empty($aprendicesPendientes)): ?>
                        <button type="submit" id="btnEnviarSeleccionadosModal" class="btn btn-success font-weight-bold btn-sm shadow-sm px-3" disabled>
                            <i class="fas fa-paper-plane mr-1"></i> Despachar a Seleccionados (<span id="btnNumSeleccionados">0</span>)
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- MODAL 2: ASIGNACIÓN RÁPIDA DE CORREO ELECTRÓNICO             -->
<!-- ============================================================ -->
<div class="modal fade" id="modalAsignarCorreo" tabindex="-1" role="dialog" aria-labelledby="modalAsignarCorreoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title font-weight-bold mb-0" id="modalAsignarCorreoLabel">
                    <i class="fas fa-envelope-open-text mr-2"></i> Asignar Correo Electrónico
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form action="<?= RUTA_BASE ?>controllers/admin/asignar_correo_aprendiz.php" method="post" id="formAsignarCorreo">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars($_SESSION['token_csrf']) ?>">
                <input type="hidden" name="id_usuario" id="asignarIdUsuario" value="">

                <div class="modal-body p-4">
                    <!-- Ficha informativa del aprendiz seleccionado -->
                    <div class="bg-light p-3 rounded border mb-3">
                        <div class="small text-muted mb-1">Aprendiz seleccionado:</div>
                        <h6 class="font-weight-bold text-dark mb-1" id="asignarNombreTexto">&mdash;</h6>
                        <div class="small text-muted">
                            <span><i class="fas fa-id-card mr-1 text-success"></i> Documento: <strong id="asignarDocTexto" class="text-dark">&mdash;</strong></span>
                        </div>
                    </div>

                    <!-- Input del Correo Electrónico -->
                    <div class="form-group mb-2">
                        <label for="inputCorreoAsignar" class="font-weight-bold text-dark">
                            Correo Electrónico de Destino <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white border-right-0">
                                    <i class="fas fa-envelope text-success"></i>
                                </span>
                            </div>
                            <input 
                                type="email" 
                                class="form-control border-left-0" 
                                id="inputCorreoAsignar" 
                                name="correo" 
                                required 
                                placeholder="ejemplo@gmail.com"
                                autocomplete="email"
                            >
                        </div>
                        <small class="form-text text-muted mt-2">
                            Al registrar el correo, el aprendiz pasará automáticamente a estado <span class="badge badge-warning text-dark px-1">Pendiente</span> y podrá recibir sus credenciales de acceso por Gmail.
                        </small>
                    </div>
                </div>

                <div class="modal-footer py-2 bg-light d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnGuardarCorreoModal" class="btn btn-success font-weight-bold btn-sm shadow-sm px-3">
                        <i class="fas fa-save mr-1"></i> Guardar y Habilitar Envío
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ------------------------------------------------------------
    // Lógica del Modal 1: Envío de Correos Pendientes
    // ------------------------------------------------------------
    const chkTodos = document.getElementById('checkSeleccionarTodos');
    const chks = document.querySelectorAll('.check-aprendiz-modal');
    const contadorTexto = document.getElementById('contadorSeleccionadosModal');
    const btnNum = document.getElementById('btnNumSeleccionados');
    const btnEnviar = document.getElementById('btnEnviarSeleccionadosModal');
    const inputFiltro = document.getElementById('filtroModalAprendices');
    const filas = document.querySelectorAll('.fila-aprendiz-modal');
    const formMasivo = document.getElementById('formEnvioMasivoCorreos');

    function actualizarContador() {
        const checksVisibles = Array.from(chks).filter(function (c) {
            const tr = c.closest('tr');
            return tr && tr.style.display !== 'none';
        });
        const seleccionados = Array.from(chks).filter(function (c) {
            return c.checked;
        }).length;

        if (contadorTexto) contadorTexto.textContent = seleccionados;
        if (btnNum) btnNum.textContent = seleccionados;
        if (btnEnviar) {
            btnEnviar.disabled = (seleccionados === 0);
        }

        if (chkTodos) {
            const visiblesChecked = checksVisibles.filter(function (c) {
                return c.checked;
            }).length;
            chkTodos.checked = checksVisibles.length > 0 && visiblesChecked === checksVisibles.length;
            chkTodos.indeterminate = visiblesChecked > 0 && visiblesChecked < checksVisibles.length;
        }
    }

    if (chkTodos) {
        chkTodos.addEventListener('change', function () {
            const marcar = this.checked;
            chks.forEach(function (chk) {
                const tr = chk.closest('tr');
                if (!tr || tr.style.display !== 'none') {
                    chk.checked = marcar;
                }
            });
            actualizarContador();
        });
    }

    chks.forEach(function (chk) {
        chk.addEventListener('change', actualizarContador);
    });

    if (inputFiltro) {
        inputFiltro.addEventListener('input', function () {
            const termino = this.value.trim().toLowerCase();
            filas.forEach(function (fila) {
                const texto = fila.textContent.toLowerCase();
                if (texto.includes(termino)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
            actualizarContador();
        });
    }

    if (formMasivo && btnEnviar) {
        formMasivo.addEventListener('submit', function (e) {
            const seleccionados = Array.from(chks).filter(function (c) {
                return c.checked;
            }).length;
            if (seleccionados === 0) {
                e.preventDefault();
                return;
            }
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Despachando correos...';
        });
    }

    actualizarContador();

    // ------------------------------------------------------------
    // Lógica del Modal 2: Asignación Rápida de Correo Electrónico
    // ------------------------------------------------------------
    const modalAsignar = $('#modalAsignarCorreo');
    const inputIdUsuario = document.getElementById('asignarIdUsuario');
    const textoNombre = document.getElementById('asignarNombreTexto');
    const textoDoc = document.getElementById('asignarDocTexto');
    const inputCorreo = document.getElementById('inputCorreoAsignar');
    const formAsignar = document.getElementById('formAsignarCorreo');
    const btnGuardarCorreo = document.getElementById('btnGuardarCorreoModal');

    // Delegación de eventos en document para funcionar con paginación DataTables
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-abrir-asignar-correo');
        if (!btn) return;

        const idUsuario = btn.getAttribute('data-id-usuario') || '';
        const nombre = btn.getAttribute('data-nombre') || '';
        const documento = btn.getAttribute('data-documento') || '';

        if (inputIdUsuario) inputIdUsuario.value = idUsuario;
        if (textoNombre) textoNombre.textContent = nombre;
        if (textoDoc) textoDoc.textContent = documento;
        if (inputCorreo) inputCorreo.value = '';

        modalAsignar.modal('show');
    });

    modalAsignar.on('shown.bs.modal', function () {
        if (inputCorreo) inputCorreo.focus();
    });

    if (formAsignar) {
        formAsignar.addEventListener('submit', function (e) {
            e.preventDefault();

            const correoVal = inputCorreo.value.trim();
            if (!correoVal) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Correo obligatorio',
                        text: 'Por favor ingrese una dirección de correo válida.',
                        confirmButtonColor: '#28a745'
                    });
                }
                return;
            }

            btnGuardarCorreo.disabled = true;
            btnGuardarCorreo.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...';

            const formData = new FormData(formAsignar);

            fetch(formAsignar.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                btnGuardarCorreo.disabled = false;
                btnGuardarCorreo.innerHTML = '<i class="fas fa-save mr-1"></i> Guardar y Habilitar Envío';

                if (data.status === 'success') {
                    modalAsignar.modal('hide');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Correo asignado!',
                            text: 'El aprendiz ahora está habilitado para recibir credenciales.',
                            timer: 1300,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        window.location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error al asignar correo',
                            text: data.message || 'No se pudo guardar el correo.',
                            confirmButtonColor: '#28a745'
                        });
                    } else {
                        alert(data.message || 'Error al guardar el correo.');
                    }
                }
            })
            .catch(err => {
                btnGuardarCorreo.disabled = false;
                btnGuardarCorreo.innerHTML = '<i class="fas fa-save mr-1"></i> Guardar y Habilitar Envío';
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de comunicación',
                        text: 'No fue posible conectar con el servidor: ' + err.message,
                        confirmButtonColor: '#28a745'
                    });
                } else {
                    alert('Error de comunicación: ' + err.message);
                }
            });
        });
    }
});
</script>

<?php
$contenido = ob_get_clean();

// Render del layout principal del panel de administracion
include __DIR__ . '/../../layouts/layout_admin.php';
