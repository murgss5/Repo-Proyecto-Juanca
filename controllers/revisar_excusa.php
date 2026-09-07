<?php
/**
 * controlador: Revisar excusa medica (Instructor)
 * Permite al instructor aprobar o rechazar excusas de sus aprendices
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';

/**
 * funcion auxiliar para redirigir al listado de excusas con mensaje
 * @param string $parametro Tipo de mensaje: 'mensaje' o 'error'
 * @param string $mensaje Texto del mensaje
 */
function volverAExcusas(string $parametro, string $mensaje): void
{
    header('Location: ../views/instructor/excusas/index.php?' . $parametro . '=' . urlencode($mensaje));
    exit;
}

// Verifica que sea POST y que el usuario sea instructor (rol 2)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['fk_rol'] ?? null) != 2) {
    volverAExcusas('error', 'No tiene permiso para revisar excusas.');
}

// Verifica el token CSRF para evitar envios falsos
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    volverAExcusas('error', 'La solicitud no es valida. Intente nuevamente.');
}

// Obtiene el ID de la excusa y la decision (aprobar/rechazar)
$idExcusa = filter_input(INPUT_POST, 'id_excusa', FILTER_VALIDATE_INT);
$decision = $_POST['decision'] ?? '';

// Valida que los datos sean correctos
if ($idExcusa === false || $idExcusa === null || !in_array($decision, ['APROBADA', 'RECHAZADA'], true)) {
    volverAExcusas('error', 'Los datos de la revision no son validos.');
}

// Conecta a la base de datos
$conexion = (new Conexion())->conectar();

// Verifica que la excusa pertenezca a una ficha del instructor autenticado
// El procedimiento valida el rol, pero esta consulta impide revisar excusas de otra ficha.
$consultaPropiedad = $conexion->prepare(
    'SELECT 1
     FROM excusa
     INNER JOIN asistencia ON excusa.fk_asistencia = asistencia.id_asistencia
     INNER JOIN aprendiz ON asistencia.fk_aprendiz = aprendiz.id_aprendiz
     INNER JOIN ficha ON aprendiz.fk_ficha = ficha.id_ficha
     WHERE excusa.id_excusa = :id_excusa
       AND ficha.fk_usuario = :id_instructor
     LIMIT 1'
);
$consultaPropiedad->execute([
    ':id_excusa' => $idExcusa,
    ':id_instructor' => $_SESSION['id_usuario'],
]);

if (!$consultaPropiedad->fetchColumn()) {
    volverAExcusas('error', 'La excusa no pertenece a sus fichas.');
}

// Ejecuta el procedimiento almacenado para aprobar o rechazar
try {
    $consultaRevision = $conexion->prepare('CALL revisar_excusa(:id_excusa, :id_instructor, :decision)');
    $consultaRevision->execute([
        ':id_excusa' => $idExcusa,
        ':id_instructor' => $_SESSION['id_usuario'],
        ':decision' => $decision,
    ]);

    volverAExcusas('mensaje', 'La excusa fue ' . strtolower($decision) . ' correctamente.');
} catch (PDOException $error) {
    volverAExcusas('error', 'No fue posible revisar la excusa. Puede que ya haya sido procesada.');
}