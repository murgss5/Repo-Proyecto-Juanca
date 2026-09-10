<?php

// carga la validacion de sesion y roles
require_once dirname(__DIR__, 3) . '/includes/autenticacion.php';

// permite el acceso solo al administrador
exigirRol(1);

// carga las constantes generales del proyecto
require_once dirname(__DIR__, 3) . '/includes/constantes.php';

// carga la clase de conexion pdo
require_once dirname(__DIR__, 3) . '/config/conexion.php';

// crea una instancia de la clase de conexion
$conexionBaseDatos = new Conexion();

// obtiene la conexion pdo para realizar consultas
$conexion = $conexionBaseDatos->conectar();

// obtiene el id del aprendiz enviado por la url
$idAprendiz = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// valida que se haya enviado un id valido
if (!$idAprendiz) {
  // redirige al listado cuando el id no es valido
  header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=aprendiz_invalido');

  // finaliza la ejecucion
  exit;
}

try {
  // prepara la consulta para obtener los datos del aprendiz
  $consultaAprendiz = $conexion->prepare(
    'SELECT
            aprendiz.id_aprendiz,
            aprendiz.codigo_rfid,
            usuario.identificacion,
            usuario.nombre,
            usuario.apellido,
            usuario.telefono,
            usuario.nombre_usuario,
            ficha.id_ficha,
            ficha.nombre_programa,
            ficha.jornada
        FROM aprendiz
        INNER JOIN usuario
            ON usuario.id_usuario = aprendiz.fk_usuario
        INNER JOIN ficha
            ON ficha.id_ficha = aprendiz.fk_ficha
        WHERE aprendiz.id_aprendiz = :id_aprendiz
        LIMIT 1'
  );

  // ejecuta la consulta con el id del aprendiz
  $consultaAprendiz->execute([
    ':id_aprendiz' => $idAprendiz,
  ]);

  // obtiene los datos del aprendiz
  $aprendiz = $consultaAprendiz->fetch();

  // valida que el aprendiz exista
  if (!$aprendiz) {
    // redirige al listado si el aprendiz no existe
    header('Location: ' . RUTA_BASE . 'views/admin/aprendices/index.php?error=aprendiz_no_encontrado');

    // finaliza la ejecucion
    exit;
  }

  // prepara la consulta para obtener las asistencias recientes
  $consultaAsistencias = $conexion->prepare(
    'SELECT
            asistencia.fecha_asistencia,
            asistencia.entrada,
            asistencia.salida,
            asistencia.estado_entrada,
            asistencia.estado_salida,
            asistencia.minutos_retardo,
            asistencia.minutos_salida_anticipada
        FROM asistencia
        WHERE asistencia.fk_aprendiz = :id_aprendiz
        ORDER BY asistencia.fecha_asistencia DESC, asistencia.entrada DESC
        LIMIT 10'
  );

  // ejecuta la consulta de asistencias
  $consultaAsistencias->execute([
    ':id_aprendiz' => $idAprendiz,
  ]);

  // obtiene las asistencias del aprendiz
  $asistencias = $consultaAsistencias->fetchAll();
} catch (PDOException $error) {
  // detiene la vista si ocurre un error de consulta
  die('No fue posible consultar el detalle del aprendiz.');
}

// crea el nombre completo del aprendiz
$nombreCompleto = trim($aprendiz['nombre'] . ' ' . $aprendiz['apellido']);

// define el titulo de la pagina
$paginaTitulo = 'Detalle del aprendiz';

// define el modulo activo del menu
$menuActivo = 'aprendices';

// define las migas de pan de la vista
$miga = [
  [
    'label' => 'Inicio',
    'url' => RUTA_BASE . 'views/admin/dashboard.php',
  ],
  [
    'label' => 'Aprendices',
    'url' => RUTA_BASE . 'views/admin/aprendices/index.php',
  ],
  [
    'label' => 'Detalle',
  ],
];

// inicia la captura del contenido de la pagina
ob_start();
?>

<div class="row">
  <div class="col-md-4 mb-3">
    <div class="card">
      <div class="card-body text-center">
        <img src="<?= RUTA_BASE ?>assets/img/avatar.png" class="avatar-grande img-circle mb-3" alt="Foto del aprendiz">

        <h4><?= htmlspecialchars($nombreCompleto) ?></h4>

        <p class="text-muted mb-2">
          <?= htmlspecialchars($aprendiz['nombre_programa']) ?>
        </p>

        <span class="badge badge-estado badge-success mb-3">
          Activo
        </span>

        <hr>

        <p class="mb-1">
          <i class="fas fa-id-card text-sena"></i>
          <strong>Documento:</strong>
          <?= htmlspecialchars($aprendiz['identificacion']) ?>
        </p>

        <p class="mb-1">
          <i class="fas fa-rss text-sena"></i>
          <strong>Código RFID:</strong>
          <code><?= htmlspecialchars($aprendiz['codigo_rfid']) ?></code>
        </p>

        <p class="mb-1">
          <i class="fas fa-layer-group text-sena"></i>
          <strong>Ficha:</strong>
          <?= htmlspecialchars($aprendiz['id_ficha']) ?>
        </p>

        <div class="mt-3">
          <a href="<?= RUTA_BASE ?>views/admin/aprendices/edit.php?id=<?= urlencode($aprendiz['id_aprendiz']) ?>"
            class="btn btn-warning btn-sm">
            <i class="fas fa-edit"></i>
            Editar
          </a>

          <a href="<?= RUTA_BASE ?>views/admin/aprendices/index.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i>
            Volver
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-8 mb-3">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-info-circle text-sena"></i>
          Información de contacto
        </h3>
      </div>

      <div class="card-body">
        <table class="table table-bordered">
          <tbody>
            <tr>
              <th style="width: 200px;">Usuario</th>
              <td><?= htmlspecialchars($aprendiz['nombre_usuario']) ?></td>
            </tr>

            <tr>
              <th>Teléfono</th>
              <td><?= htmlspecialchars($aprendiz['telefono'] ?: 'No registrado') ?></td>
            </tr>

            <tr>
              <th>Jornada</th>
              <td><?= htmlspecialchars($aprendiz['jornada']) ?></td>
            </tr>

            <tr>
              <th>Programa</th>
              <td><?= htmlspecialchars($aprendiz['nombre_programa']) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">
          <i class="fas fa-clipboard-check text-sena"></i>
          Asistencia reciente
        </h3>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Estado</th>
              </tr>
            </thead>

            <tbody>
              <?php if (empty($asistencias)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">
                    El aprendiz no tiene asistencias registradas.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($asistencias as $asistencia): ?>
                  <?php
                  // toma el estado de entrada o un texto por defecto
                  $estado = $asistencia['estado_entrada'] ?? 'SIN REGISTRO';

                  // define la clase visual inicial del estado
                  $claseBadge = 'badge-secondary';

                  // asigna verde a los registros puntuales
                  if ($estado === 'PUNTUAL') {
                    $claseBadge = 'badge-success';
                  }

                  // asigna amarillo a los retardos
                  if ($estado === 'RETARDO') {
                    $claseBadge = 'badge-warning';
                  }

                  // asigna rojo a las inasistencias
                  if ($estado === 'INASISTENCIA') {
                    $claseBadge = 'badge-danger';
                  }

                  // asigna azul a las salidas anticipadas
                  if (($asistencia['estado_salida'] ?? '') === 'SALIDA_ANTICIPADA') {
                    $claseBadge = 'badge-info';
                  }

                  // define el texto cuando no existe entrada
                  $horaEntrada = $asistencia['entrada'] ?: 'Sin entrada';

                  // define el texto cuando no existe salida
                  $horaSalida = $asistencia['salida'] ?: 'Sin salida';
                  ?>

                  <tr>
                    <td>
                      <?= htmlspecialchars($asistencia['fecha_asistencia']) ?>
                    </td>

                    <td>
                      <?= htmlspecialchars($horaEntrada) ?>
                    </td>

                    <td>
                      <?= htmlspecialchars($horaSalida) ?>
                    </td>

                    <td>
                      <span class="badge badge-estado <?= $claseBadge ?>">
                        <?= htmlspecialchars(ucfirst(strtolower($estado))) ?>
                      </span>

                      <?php if ((int) ($asistencia['minutos_retardo'] ?? 0) > 0): ?>
                        <small class="text-muted">
                          (<?= (int) $asistencia['minutos_retardo'] ?> min)
                        </small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php

// obtiene todo el contenido capturado
$contenido = ob_get_clean();

// carga el layout principal del administrador
include dirname(__DIR__, 2) . '/layouts/layout_admin.php';