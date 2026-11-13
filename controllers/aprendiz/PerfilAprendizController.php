<?php
/**
 * =============================================================================
 * CONTROLADOR UNIFICADO DE PERFIL APRENDIZ: PerfilAprendizController.php
 * Sistema de Control de Ingreso y Asistencia de Aprendices - SENA
 * =============================================================================
 * Unifica las operaciones de perfil y credenciales para aprendices:
 *   - accion=actualizar_perfil   : Actualiza datos personales y documento
 *   - accion=cambiar_contrasena  : Modifica la contraseña con hash Bcrypt
 *   - accion=mantener_contrasena : Confirma mantener contraseña tras invitación
 * =============================================================================
 */

require_once __DIR__ . '/../../includes/autenticacion.php';
exigirRol(3);

require_once __DIR__ . '/../../includes/constantes.php';
require_once __DIR__ . '/../../config/conexion.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 1. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido. Se requiere POST.'
    ]);
    exit;
}

// 2. Validar token CSRF estricto
$tokenRecibido = $_POST['token_csrf'] ?? $_POST['csrf_token'] ?? '';
if ($tokenRecibido === '') {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $tokenRecibido = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? '';
}

if (!is_string($tokenRecibido) || !hash_equals($_SESSION['token_csrf'] ?? '', trim($tokenRecibido))) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Token de seguridad inválido o sesión expirada.'
    ]);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

// Detección automática por compatibilidad
if ($accion === '') {
    if (isset($_POST['nueva_clave'])) {
        $accion = 'cambiar_contrasena';
    } elseif (isset($_POST['mantener']) || isset($_POST['mantener_clave'])) {
        $accion = 'mantener_contrasena';
    } elseif (isset($_POST['identificacion']) || isset($_POST['nombre'])) {
        $accion = 'actualizar_perfil';
    }
}

switch ($accion) {
    case 'actualizar_perfil':
        procesarActualizarPerfil();
        break;

    case 'cambiar_contrasena':
        procesarCambiarContrasena();
        break;

    case 'mantener_contrasena':
        procesarMantenerContrasena();
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'Acción no válida o no especificada en PerfilAprendizController.'
        ]);
        exit;
}

/**
 * =============================================================================
 * ACCIÓN 1: ACTUALIZAR DATOS PERSONALES DEL APRENDIZ
 * =============================================================================
 */
function procesarActualizarPerfil(): void
{
    $usuario = usuarioActual(3);
    $idUsuario = (int)$usuario['id_usuario'];

    // Captura y sanitización individual de campos permitidos
    $tipoDocumento   = isset($_POST['tipo_documento']) ? trim((string)$_POST['tipo_documento']) : '';
    $identificacion  = isset($_POST['identificacion']) ? trim((string)$_POST['identificacion']) : '';
    $nombre          = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
    $apellido        = isset($_POST['apellido']) ? trim((string)$_POST['apellido']) : '';
    $telefono        = isset($_POST['telefono']) ? trim((string)$_POST['telefono']) : '';
    $correo          = isset($_POST['correo_electronico']) ? trim((string)$_POST['correo_electronico']) : '';
    $claveAutorizar  = isset($_POST['clave_autorizacion']) ? (string)$_POST['clave_autorizacion'] : '';

    $tiposValidos = ['CC', 'TI', 'CE', 'PEP', 'PPT', 'PAS'];
    if ($tipoDocumento !== '' && !in_array($tipoDocumento, $tiposValidos, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El tipo de documento seleccionado no es válido.']);
        exit;
    }

    if ($identificacion === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El número de documento es obligatorio.']);
        exit;
    }

    if (mb_strlen($identificacion) > 45) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El número de documento no puede exceder 45 caracteres.']);
        exit;
    }

    if ($nombre === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio.']);
        exit;
    }

    if (mb_strlen($nombre) > 45) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El nombre no puede exceder 45 caracteres.']);
        exit;
    }

    if ($apellido === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El apellido es obligatorio.']);
        exit;
    }

    if (mb_strlen($apellido) > 45) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El apellido no puede exceder 45 caracteres.']);
        exit;
    }

    if ($telefono !== '' && mb_strlen($telefono) > 45) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El teléfono celular no puede exceder 45 caracteres.']);
        exit;
    }

    if ($correo !== '') {
        if (mb_strlen($correo) > 100) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El correo electrónico no puede exceder 100 caracteres.']);
            exit;
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El formato del correo electrónico no es válido.']);
            exit;
        }
    }

    try {
        $conexion = (new Conexion())->conectar();

        $stmtActual = $conexion->prepare(
            'SELECT id_usuario, identificacion, nombre_usuario, contrasena, nombre, apellido, tipo_documento, telefono, correo_electronico 
             FROM usuario 
             WHERE id_usuario = :id AND fk_rol = 3 
             LIMIT 1'
        );
        $stmtActual->execute([':id' => $idUsuario]);
        $usuarioBD = $stmtActual->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioBD) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado en el sistema.']);
            exit;
        }

        $docActualBD    = trim((string)($usuarioBD['identificacion'] ?? ''));
        $tipoActualBD   = trim((string)($usuarioBD['tipo_documento'] ?? ''));
        $nomActualBD    = trim((string)($usuarioBD['nombre'] ?? ''));
        $apeActualBD    = trim((string)($usuarioBD['apellido'] ?? ''));
        $telActualBD    = trim((string)($usuarioBD['telefono'] ?? ''));
        $corActualBD    = trim((string)($usuarioBD['correo_electronico'] ?? ''));

        $docModificado  = ($identificacion !== $docActualBD);
        $tipoModificado = ($tipoDocumento !== $tipoActualBD);
        $nomModificado  = ($nombre !== $nomActualBD);
        $apeModificado  = ($apellido !== $apeActualBD);
        $telModificado  = ($telefono !== $telActualBD);
        $corModificado  = ($correo !== $corActualBD);

        $hayCambios = ($docModificado || $tipoModificado || $nomModificado || $apeModificado || $telModificado || $corModificado);

        if (!$hayCambios) {
            http_response_code(200);
            echo json_encode([
                'success'     => true,
                'sin_cambios' => true,
                'message'     => 'No hay cambios para guardar.'
            ]);
            exit;
        }

        // Si se modifica el número de documento: validación de contraseña actual y unicidad
        if ($docModificado) {
            if ($claveAutorizar === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Para modificar tu número de documento debes ingresar tu contraseña actual.'
                ]);
                exit;
            }

            if (!password_verify($claveAutorizar, $usuarioBD['contrasena'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'La contraseña actual ingresada es incorrecta. No se modificó el documento.'
                ]);
                exit;
            }

            $stmtCheckDoc = $conexion->prepare(
                'SELECT id_usuario FROM usuario WHERE identificacion = :doc AND id_usuario <> :id LIMIT 1'
            );
            $stmtCheckDoc->execute([':doc' => $identificacion, ':id' => $idUsuario]);
            if ($stmtCheckDoc->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'El número de documento ingresado ya se encuentra registrado por otro usuario.'
                ]);
                exit;
            }

            $stmtCheckAmb = $conexion->prepare(
                'SELECT id_usuario FROM usuario WHERE nombre_usuario = :doc AND id_usuario <> :id LIMIT 1'
            );
            $stmtCheckAmb->execute([':doc' => $identificacion, ':id' => $idUsuario]);
            if ($stmtCheckAmb->fetch()) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'El número de documento coincide con el identificador de usuario de otra cuenta y no puede ser asignado.'
                ]);
                exit;
            }
        }

        $conexion->beginTransaction();

        $stmtUpdate = $conexion->prepare(
            'UPDATE usuario 
             SET tipo_documento = :tipo_doc,
                 identificacion = :identificacion,
                 nombre = :nombre,
                 apellido = :apellido,
                 telefono = :telefono,
                 correo_electronico = :correo
             WHERE id_usuario = :id AND fk_rol = 3'
        );
        $stmtUpdate->execute([
            ':tipo_doc'       => $tipoDocumento !== '' ? $tipoDocumento : null,
            ':identificacion' => $identificacion,
            ':nombre'         => $nombre,
            ':apellido'       => $apellido,
            ':telefono'       => $telefono !== '' ? $telefono : null,
            ':correo'         => $correo !== '' ? $correo : null,
            ':id'             => $idUsuario
        ]);

        if ($corModificado) {
            try {
                $stmtApr = $conexion->prepare('UPDATE aprendiz SET correo_enviado = 0 WHERE fk_usuario = :id');
                $stmtApr->execute([':id' => $idUsuario]);
            } catch (PDOException $eCol) {}
        }

        $conexion->commit();

        if (isset($_SESSION['aprendiz']) && is_array($_SESSION['aprendiz'])) {
            $_SESSION['aprendiz']['nombre'] = $nombre;
            $_SESSION['aprendiz']['apellido'] = $apellido;
            $nombreCompleto = trim($nombre . ' ' . $apellido);
            $_SESSION['aprendiz']['nombre_completo'] = $nombreCompleto;
            if ($docModificado || isset($_SESSION['aprendiz']['identificacion'])) {
                $_SESSION['aprendiz']['identificacion'] = $identificacion;
            }
            if (isset($_SESSION['aprendiz']['telefono'])) {
                $_SESSION['aprendiz']['telefono'] = $telefono !== '' ? $telefono : null;
            }
            $_SESSION['aprendiz']['correo_electronico'] = $correo !== '' ? $correo : null;
        }

        $mensaje = $docModificado
            ? 'Datos actualizados. En tu próximo inicio de sesión utiliza tu nuevo número de documento y tu contraseña actual.'
            : 'Datos personales actualizados correctamente.';

        http_response_code(200);
        echo json_encode([
            'success'             => true,
            'sin_cambios'         => false,
            'documento_cambiado'  => $docModificado,
            'message'             => $mensaje,
            'datos'               => [
                'tipo_documento'     => $tipoDocumento,
                'identificacion'     => $identificacion,
                'documento'          => $identificacion,
                'nombre'             => $nombre,
                'apellido'           => $apellido,
                'nombre_completo'    => $nombreCompleto,
                'telefono'           => $telefono,
                'correo_electronico' => $correo
            ]
        ]);
        exit;

    } catch (PDOException $e) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        $codigoMySQL = (int)($e->errorInfo[1] ?? 0);
        if ((string)$e->getCode() === '23000' && $codigoMySQL === 1062) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El número de documento ya está asignado a otra cuenta registrada.']);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error interno en el servidor al actualizar los datos personales. Inténtelo más tarde.']);
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 2: CAMBIAR CONTRASEÑA DEL APRENDIZ
 * =============================================================================
 */
function procesarCambiarContrasena(): void
{
    $usuario = usuarioActual(3);
    $idUsuario = (int)$usuario['id_usuario'];

    $claveActual    = $_POST['clave_actual'] ?? null;
    $nuevaClave     = $_POST['nueva_clave'] ?? null;
    $confirmarClave = $_POST['confirmar_clave'] ?? null;

    if (!is_string($claveActual) || !is_string($nuevaClave) || !is_string($confirmarClave)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Los campos de contraseña deben ser cadenas de texto válidas.']);
        exit;
    }

    if ($claveActual === '' || $nuevaClave === '' || $confirmarClave === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Todos los campos de contraseña son obligatorios.']);
        exit;
    }

    if (mb_strlen($nuevaClave) < 8) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La nueva contraseña debe tener al menos 8 caracteres.']);
        exit;
    }

    if (strlen($nuevaClave) > 72) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La nueva contraseña excede el límite seguro de 72 bytes para el algoritmo de cifrado.']);
        exit;
    }

    if ($nuevaClave !== $confirmarClave) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La nueva contraseña y su confirmación no coinciden.']);
        exit;
    }

    try {
        $conexion = (new Conexion())->conectar();

        $stmtUser = $conexion->prepare(
            'SELECT id_usuario, contrasena, identificacion, nombre_usuario, estado_invitacion_clave, fecha_resolucion_invitacion 
             FROM usuario 
             WHERE id_usuario = :id AND fk_rol = 3 
             LIMIT 1'
        );
        $stmtUser->execute([':id' => $idUsuario]);
        $usuarioBD = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioBD) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado en el sistema.']);
            exit;
        }

        if (!password_verify($claveActual, $usuarioBD['contrasena'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La contraseña actual ingresada es incorrecta.']);
            exit;
        }

        if (password_verify($nuevaClave, $usuarioBD['contrasena'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La nueva contraseña no puede ser igual a la contraseña actual.']);
            exit;
        }

        $docIdentidad = trim((string)($usuarioBD['identificacion'] ?? ''));
        $nombreUsuario = trim((string)($usuarioBD['nombre_usuario'] ?? ''));

        if ($docIdentidad !== '' && strtolower($nuevaClave) === strtolower($docIdentidad)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La nueva contraseña no puede ser igual a su número de documento.']);
            exit;
        }

        if ($nombreUsuario !== '' && strtolower($nuevaClave) === strtolower($nombreUsuario)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La nueva contraseña no puede ser igual a su nombre de usuario.']);
            exit;
        }

        $nuevoHash = password_hash($nuevaClave, PASSWORD_DEFAULT);

        $conexion->beginTransaction();

        $stmtUpdate = $conexion->prepare(
            "UPDATE usuario 
             SET contrasena = :nuevo_hash,
                 estado_invitacion_clave = 'CAMBIADA',
                 fecha_resolucion_invitacion = COALESCE(fecha_resolucion_invitacion, NOW()),
                 fecha_ultimo_cambio_clave = NOW()
             WHERE id_usuario = :id 
               AND fk_rol = 3"
        );
        $stmtUpdate->execute([
            ':nuevo_hash' => $nuevoHash,
            ':id'         => $idUsuario
        ]);

        $conexion->commit();

        session_regenerate_id(true);

        echo json_encode([
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.',
            'fecha'   => date('d/m/Y h:i a')
        ]);
        exit;

    } catch (Exception $e) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error en el servidor al actualizar la contraseña. Inténtelo de nuevo.']);
        exit;
    }
}

/**
 * =============================================================================
 * ACCIÓN 3: MANTENER CONTRASEÑA TRAS INVITACIÓN INICIAL
 * =============================================================================
 */
function procesarMantenerContrasena(): void
{
    $usuario = usuarioActual(3);
    $idUsuario = (int)$usuario['id_usuario'];

    try {
        $conexion = (new Conexion())->conectar();

        $stmtCheck = $conexion->prepare(
            'SELECT estado_invitacion_clave, fecha_resolucion_invitacion 
             FROM usuario 
             WHERE id_usuario = :id AND fk_rol = 3 
             LIMIT 1'
        );
        $stmtCheck->execute([':id' => $idUsuario]);
        $estadoActual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$estadoActual) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Usuario no encontrado en el sistema.']);
            exit;
        }

        if ($estadoActual['estado_invitacion_clave'] !== 'PENDIENTE') {
            echo json_encode([
                'success' => true,
                'message' => 'La preferencia ya se encuentra registrada en su cuenta.',
                'estado'  => $estadoActual['estado_invitacion_clave']
            ]);
            exit;
        }

        $stmtUpdate = $conexion->prepare(
            "UPDATE usuario 
             SET estado_invitacion_clave = 'MANTENIDA',
                 fecha_resolucion_invitacion = NOW()
             WHERE id_usuario = :id 
               AND fk_rol = 3 
               AND estado_invitacion_clave = 'PENDIENTE'"
        );
        $stmtUpdate->execute([':id' => $idUsuario]);

        if ($stmtUpdate->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Preferencia guardada. Puedes cambiar tu contraseña en cualquier momento desde Mi perfil.',
                'estado'  => 'MANTENIDA'
            ]);
            exit;
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Preferencia registrada previamente.',
                'estado'  => 'MANTENIDA'
            ]);
            exit;
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error en el servidor al guardar la preferencia. Inténtelo más tarde.']);
        exit;
    }
}
