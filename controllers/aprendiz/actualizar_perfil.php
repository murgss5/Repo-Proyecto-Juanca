<?php
/**
 * Controlador: Actualizar datos personales del Aprendiz
 * Permite modificar tipo de documento, número de documento, nombres, apellidos,
 * teléfono y correo electrónico. Exige la contraseña actual si se modifica el documento.
 */

require_once __DIR__ . '/../../includes/constantes.php';
require_once __DIR__ . '/../../includes/autenticacion.php';
require_once __DIR__ . '/../../config/conexion.php';

asegurarSesionIniciada();

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

// 2. Validar rol Aprendiz (rol 3) y sesión activa
$usuario = usuarioActual();
if (!$usuario || (int)$usuario['fk_rol'] !== 3) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error'   => 'Acceso denegado. Se requiere sesión activa con rol Aprendiz.'
    ]);
    exit;
}

// 3. Validar token CSRF estricto
$tokenRecibido = $_POST['token_csrf'] ?? '';
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

$idUsuario = (int)$usuario['id_usuario'];

// 4. Captura y sanitización individual de los 6 campos permitidos (sin asignación masiva)
$tipoDocumento   = isset($_POST['tipo_documento']) ? trim((string)$_POST['tipo_documento']) : '';
$identificacion  = isset($_POST['identificacion']) ? trim((string)$_POST['identificacion']) : '';
$nombre          = isset($_POST['nombre']) ? trim((string)$_POST['nombre']) : '';
$apellido        = isset($_POST['apellido']) ? trim((string)$_POST['apellido']) : '';
$telefono        = isset($_POST['telefono']) ? trim((string)$_POST['telefono']) : '';
$correo          = isset($_POST['correo_electronico']) ? trim((string)$_POST['correo_electronico']) : '';
$claveAutorizar  = isset($_POST['clave_autorizacion']) ? (string)$_POST['clave_autorizacion'] : '';

// 5. Validaciones de esquema y obligatoriedad
$tiposValidos = ['CC', 'TI', 'CE', 'PEP', 'PPT', 'PAS'];
if ($tipoDocumento !== '' && !in_array($tipoDocumento, $tiposValidos, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El tipo de documento seleccionado no es válido.'
    ]);
    exit;
}

if ($identificacion === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El número de documento es obligatorio.'
    ]);
    exit;
}

if (mb_strlen($identificacion) > 45) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El número de documento no puede exceder 45 caracteres.'
    ]);
    exit;
}

if ($nombre === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El nombre es obligatorio.'
    ]);
    exit;
}

if (mb_strlen($nombre) > 45) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El nombre no puede exceder 45 caracteres.'
    ]);
    exit;
}

if ($apellido === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El apellido es obligatorio.'
    ]);
    exit;
}

if (mb_strlen($apellido) > 45) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El apellido no puede exceder 45 caracteres.'
    ]);
    exit;
}

if ($telefono !== '' && mb_strlen($telefono) > 45) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'El teléfono celular no puede exceder 45 caracteres.'
    ]);
    exit;
}

if ($correo !== '') {
    if (mb_strlen($correo) > 100) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'El correo electrónico no puede exceder 100 caracteres.'
        ]);
        exit;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'El formato del correo electrónico no es válido.'
        ]);
        exit;
    }
}

try {
    $conexion = (new Conexion())->conectar();

    // 6. Consultar registro actual del usuario en la base de datos
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
        echo json_encode([
            'success' => false,
            'error'   => 'Usuario no encontrado en el sistema.'
        ]);
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

    // 7. Si se modifica el número de documento: validación estricta de autorización y unicidad
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

        // Comprobar que el documento nuevo no esté registrado en otra cuenta
        $stmtCheckDoc = $conexion->prepare(
            'SELECT id_usuario FROM usuario WHERE identificacion = :doc AND id_usuario <> :id LIMIT 1'
        );
        $stmtCheckDoc->execute([
            ':doc' => $identificacion,
            ':id'  => $idUsuario
        ]);
        if ($stmtCheckDoc->fetch()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'El número de documento ingresado ya se encuentra registrado por otro usuario.'
            ]);
            exit;
        }

        // Comprobar que no genere ambigüedad con el nombre de usuario de otra cuenta
        $stmtCheckAmb = $conexion->prepare(
            'SELECT id_usuario FROM usuario WHERE nombre_usuario = :doc AND id_usuario <> :id LIMIT 1'
        );
        $stmtCheckAmb->execute([
            ':doc' => $identificacion,
            ':id'  => $idUsuario
        ]);
        if ($stmtCheckAmb->fetch()) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'El número de documento coincide con el identificador de usuario de otra cuenta y no puede ser asignado.'
            ]);
            exit;
        }
    }

    // 8. Actualizar datos en transacción atómica
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

    // Si el aprendiz registró o modificó su correo, se reinicia el indicador para el envío de credenciales
    if ($corModificado) {
        try {
            $stmtApr = $conexion->prepare('UPDATE aprendiz SET correo_enviado = 0 WHERE fk_usuario = :id');
            $stmtApr->execute([':id' => $idUsuario]);
        } catch (PDOException $eCol) {
            // Ignorar si la columna no existe aún
        }
    }

    $conexion->commit();

    // 9. Actualizar datos de sesión en memoria (namespace aprendiz)
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

    // 10. Mensaje de respuesta según si el documento cambió
    if ($docModificado) {
        $mensaje = 'Datos actualizados. En tu próximo inicio de sesión utiliza tu nuevo número de documento y tu contraseña actual.';
    } else {
        $mensaje = 'Datos personales actualizados correctamente.';
    }

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
    // Captura específica de clave duplicada en MySQL (SQLSTATE 23000 con código de error MySQL 1062)
    $codigoMySQL = (int)($e->errorInfo[1] ?? 0);
    if ((string)$e->getCode() === '23000' && $codigoMySQL === 1062) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error'   => 'El número de documento ya está asignado a otra cuenta registrada.'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Error interno en el servidor al actualizar los datos personales. Inténtelo más tarde.'
    ]);
    exit;
}
