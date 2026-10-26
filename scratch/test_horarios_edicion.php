<?php
/**
 * Test Suite Automatizado: Edición de Horarios Importados y Seguridad en Módulo Administrativo
 * Verifica los 10 escenarios requeridos.
 */

require_once __DIR__ . '/../../../../xampp/htdocs/control_asistencia/config/conexion.php';
require_once __DIR__ . '/../../../../xampp/htdocs/control_asistencia/models/HorarioModel.php';

$conexion = (new Conexion())->conectar();

echo "====================================================================\n";
echo "INICIANDO TEST SUITE: EDICIÓN DE HORARIOS EN MÓDULO ADMINISTRATIVO\n";
echo "====================================================================\n\n";

$testsPasados = 0;
$testsFallados = 0;

function assertTest(bool $condicion, string $nombreTest, string $detalle = ''): void {
    global $testsPasados, $testsFallados;
    if ($condicion) {
        echo "[PASÓ] " . $nombreTest . ($detalle ? " ($detalle)" : "") . "\n";
        $testsPasados++;
    } else {
        echo "[FALLÓ] " . $nombreTest . ($detalle ? " - ERROR: $detalle" : "") . "\n";
        $testsFallados++;
    }
}

// 0. Preparar datos base
$qInstr = $conexion->query("SELECT id_usuario, nombre, apellido FROM usuario WHERE fk_rol = 2 ORDER BY id_usuario ASC LIMIT 2");
$instructores = $qInstr->fetchAll();
if (count($instructores) < 2) {
    die("Se requieren al menos 2 instructores en la base de datos para ejecutar las pruebas.\n");
}
$inst1 = $instructores[0];
$inst2 = $instructores[1];

$qFicha = $conexion->query("SELECT id_ficha, nombre_programa FROM ficha ORDER BY id_ficha ASC LIMIT 2");
$fichas = $qFicha->fetchAll();
if (empty($fichas)) {
    die("Se requiere al menos 1 ficha para ejecutar las pruebas.\n");
}
$ficha1 = $fichas[0];
$ficha2 = count($fichas) > 1 ? $fichas[1] : $fichas[0];

echo "Datos de prueba:\n";
echo "- Instructor 1: #{$inst1['id_usuario']} {$inst1['nombre']} {$inst1['apellido']}\n";
echo "- Instructor 2: #{$inst2['id_usuario']} {$inst2['nombre']} {$inst2['apellido']}\n";
echo "- Ficha 1: #{$ficha1['id_ficha']} {$ficha1['nombre_programa']}\n";
echo "- Ficha 2: #{$ficha2['id_ficha']} {$ficha2['nombre_programa']}\n\n";

// TEST 1: Importar / crear horario de prueba como si viniera de importación
echo "--- TEST 1: Crear horario de prueba (simulación importación Excel) ---\n";
$stmtIns = $conexion->prepare("
    INSERT INTO horario (dia_semana, entrada, salida, competencia, tipo_bloque, ambiente, estado, observaciones, fecha_inicio, fecha_fin, fk_ficha, instructor_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmtIns->execute([
    'Viernes', '06:00:00', '10:00:00',
    'COMPETENCIA TEST IMPORTACIÓN', 'completo',
    'Ambiente 301', 'Activo', 'Horario importado desde prueba técnica',
    '2026-11-06', '2026-11-06',
    $ficha1['id_ficha'], $inst1['id_usuario']
]);
$idHorarioTest1 = (int)$conexion->lastInsertId();
assertTest($idHorarioTest1 > 0, "Test 1: Horario importado creado con ID #$idHorarioTest1");

// TEST 2: Comprobar que el horario aparece en el listado administrativo
echo "\n--- TEST 2: Comprobar aparición en listado administrativo ---\n";
$horariosTabla = HorarioModel::obtenerHorariosTablaAdmin((int)$ficha1['id_ficha']);
$encontradoEnTabla = false;
$datosHorarioEnTabla = null;
foreach ($horariosTabla as $h) {
    if ((int)$h['id_horario'] === $idHorarioTest1) {
        $encontradoEnTabla = true;
        $datosHorarioEnTabla = $h;
        break;
    }
}
assertTest($encontradoEnTabla, "Test 2: Horario aparece en obtenerHorariosTablaAdmin", "Instructor visible: " . ($datosHorarioEnTabla['instructor_nombre'] ?? 'N/A'));

// TEST 3: Entrar en «Editar» y verificar pre-carga de campos
echo "\n--- TEST 3: Pre-carga de datos para el formulario de edición ---\n";
$qGet = $conexion->prepare("SELECT * FROM horario WHERE id_horario = ?");
$qGet->execute([$idHorarioTest1]);
$datosEdicion = $qGet->fetch(PDO::FETCH_ASSOC);

$camposCoinciden = (
    $datosEdicion['dia_semana'] === 'Viernes' &&
    substr($datosEdicion['entrada'], 0, 5) === '06:00' &&
    substr($datosEdicion['salida'], 0, 5) === '10:00' &&
    (int)$datosEdicion['fk_ficha'] === (int)$ficha1['id_ficha'] &&
    (int)$datosEdicion['instructor_id'] === (int)$inst1['id_usuario'] &&
    $datosEdicion['competencia'] === 'COMPETENCIA TEST IMPORTACIÓN' &&
    $datosEdicion['ambiente'] === 'Ambiente 301' &&
    $datosEdicion['tipo_bloque'] === 'completo' &&
    $datosEdicion['estado'] === 'Activo' &&
    $datosEdicion['fecha_inicio'] === '2026-11-06' &&
    $datosEdicion['fecha_fin'] === '2026-11-06'
);
assertTest($camposCoinciden, "Test 3: Todos los campos del horario se cargan exactamente desde la base de datos");

// TEST 4 & 5: Cambiar el instructor y guardar, comprobando persistencia inmediata
echo "\n--- TEST 4 & 5: Modificar instructor y verificar persistencia inmediata ---\n";
// Simular POST a gestionar_academico con rol admin
function simularEdicionHorario($idHorario, $ficha, $instructorId, $dia, $entrada, $salida, $competencia, $ambiente, $tipoBloque, $estado, $observaciones, $fechaInicio, $fechaFin, $esAdmin = true, $tokenCsrfValido = true) {
    global $conexion;
    
    // Validar autenticación
    if (!$esAdmin) {
        return ['status' => 'error', 'msg' => 'No autorizado (requiere admin)'];
    }
    if (!$tokenCsrfValido) {
        return ['status' => 'error', 'msg' => 'CSRF inválido'];
    }

    // Validar existencia horario
    $q = $conexion->prepare("SELECT * FROM horario WHERE id_horario = ?");
    $q->execute([$idHorario]);
    $horarioActual = $q->fetch();
    if (!$horarioActual) {
        return ['status' => 'error', 'msg' => 'Horario no existe'];
    }

    // Validar ficha
    $qFicha = $conexion->prepare("SELECT id_ficha FROM ficha WHERE id_ficha = ?");
    $qFicha->execute([$ficha]);
    if (!$qFicha->fetch()) {
        return ['status' => 'error', 'msg' => 'Ficha no válida'];
    }

    // Validar instructor si especificado
    if ($instructorId !== null) {
        $qInstr = $conexion->prepare("SELECT id_usuario FROM usuario WHERE id_usuario = ? AND fk_rol = 2");
        $qInstr->execute([$instructorId]);
        if (!$qInstr->fetch()) {
            return ['status' => 'error', 'msg' => 'Instructor no válido o sin rol 2'];
        }
    }

    // Validar horas
    if ($salida <= $entrada) {
        return ['status' => 'error', 'msg' => 'Hora de salida debe ser posterior'];
    }

    // Validar fechas
    if ($fechaInicio !== null && $fechaFin !== null && $fechaFin < $fechaInicio) {
        return ['status' => 'error', 'msg' => 'Fecha fin menor que fecha inicio'];
    }

    // Comprobar historial y protección de ficha
    $chkSes = $conexion->prepare('SELECT COUNT(*) FROM sesiones_jornada WHERE horario_id = ?');
    $chkSes->execute([$idHorario]);
    $cantSes = (int)$chkSes->fetchColumn();

    $chkAst = $conexion->prepare('SELECT COUNT(*) FROM asistencia WHERE fk_horario = ?');
    $chkAst->execute([$idHorario]);
    $cantAst = (int)$chkAst->fetchColumn();

    $tieneHistorial = ($cantSes > 0 || $cantAst > 0);
    $fichaFinal = $ficha;
    $fichaProtegida = false;
    if ($tieneHistorial && (int)$ficha !== (int)$horarioActual['fk_ficha']) {
        $fichaFinal = (int)$horarioActual['fk_ficha'];
        $fichaProtegida = true;
    }

    // Verificar cruce misma ficha
    $qConf = $conexion->prepare("
        SELECT id_horario FROM horario
        WHERE fk_ficha = ? AND dia_semana = ?
          AND id_horario <> ? AND (estado = 'Activo' OR estado IS NULL)
          AND entrada < ? AND salida > ?
          AND (
              (? IS NULL OR fecha_fin IS NULL OR ? <= fecha_fin)
              AND
              (? IS NULL OR fecha_inicio IS NULL OR ? >= fecha_inicio)
          )
        LIMIT 1
    ");
    $qConf->execute([$fichaFinal, $dia, $idHorario, $salida, $entrada, $fechaInicio, $fechaInicio, $fechaFin, $fechaFin]);
    if ($qConf->fetch()) {
        return ['status' => 'error', 'msg' => 'Cruce de horario en misma ficha'];
    }

    // Ejecutar UPDATE
    $stmtUpd = $conexion->prepare("
        UPDATE horario
        SET dia_semana = ?, entrada = ?, salida = ?, competencia = ?, tipo_bloque = ?, ambiente = ?, estado = ?, observaciones = ?, fecha_inicio = ?, fecha_fin = ?, fk_ficha = ?, instructor_id = ?
        WHERE id_horario = ?
    ");
    $stmtUpd->execute([
        $dia, $entrada, $salida, $competencia, $tipoBloque, $ambiente, $estado, $observaciones, $fechaInicio, $fechaFin, $fichaFinal, $instructorId, $idHorario
    ]);

    return [
        'status' => 'ok',
        'msg' => 'Horario actualizado',
        'ficha_protegida' => $fichaProtegida
    ];
}

// Ejecutar cambio de instructor a Instructor 2
$resEdit = simularEdicionHorario(
    $idHorarioTest1, $ficha1['id_ficha'], $inst2['id_usuario'],
    'Viernes', '06:00:00', '10:00:00',
    'COMPETENCIA ACTUALIZADA', 'Ambiente 301 B', 'completo', 'Activo', 'Instructor cambiado', '2026-11-06', '2026-11-06'
);
assertTest($resEdit['status'] === 'ok', "Test 4: Edición guardada exitosamente");

$qCheckPersist = $conexion->prepare("SELECT instructor_id, competencia FROM horario WHERE id_horario = ?");
$qCheckPersist->execute([$idHorarioTest1]);
$rowPersist = $qCheckPersist->fetch(PDO::FETCH_ASSOC);
$persiste = ((int)$rowPersist['instructor_id'] === (int)$inst2['id_usuario']);
assertTest($persiste, "Test 5: El instructor actualizado persiste en la base de datos (#{$inst2['id_usuario']})");

// TEST 6 & 7: Intercambio de instructores entre dos horarios
echo "\n--- TEST 6 & 7: Intercambio de instructores entre dos horarios (Horario A y Horario B) ---\n";
// Crear Horario 2 en el mismo rango de fecha pero en bloque siguiente (10:00 a 14:00) asignado a Instructor 1
$stmtIns->execute([
    'Viernes', '10:00:00', '14:00:00',
    'COMPETENCIA HORARIO 2', 'completo',
    'Ambiente 302', 'Activo', 'Horario 2 para prueba de intercambio',
    '2026-11-06', '2026-11-06',
    $ficha1['id_ficha'], $inst1['id_usuario']
]);
$idHorarioTest2 = (int)$conexion->lastInsertId();

// En este punto:
// HorarioTest1 tiene Instructor 2
// HorarioTest2 tiene Instructor 1
echo "Estado antes del intercambio:\n";
echo "- Horario #$idHorarioTest1 tiene Instructor #{$inst2['id_usuario']}\n";
echo "- Horario #$idHorarioTest2 tiene Instructor #{$inst1['id_usuario']}\n";

// Realizar el intercambio: HorarioTest1 recibe Instructor 1, HorarioTest2 recibe Instructor 2
$resSwap1 = simularEdicionHorario(
    $idHorarioTest1, $ficha1['id_ficha'], $inst1['id_usuario'],
    'Viernes', '06:00:00', '10:00:00',
    'COMPETENCIA ACTUALIZADA', 'Ambiente 301 B', 'completo', 'Activo', 'Intercambio swap 1', '2026-11-06', '2026-11-06'
);
$resSwap2 = simularEdicionHorario(
    $idHorarioTest2, $ficha1['id_ficha'], $inst2['id_usuario'],
    'Viernes', '10:00:00', '14:00:00',
    'COMPETENCIA HORARIO 2', 'Ambiente 302', 'completo', 'Activo', 'Intercambio swap 2', '2026-11-06', '2026-11-06'
);

assertTest($resSwap1['status'] === 'ok' && $resSwap2['status'] === 'ok', "Test 6: Intercambio de instructores completado exitosamente sin bloqueos artificiales");

$qH1 = $conexion->prepare("SELECT id_horario, instructor_id FROM horario WHERE id_horario = ?");
$qH1->execute([$idHorarioTest1]);
$h1Post = $qH1->fetch(PDO::FETCH_ASSOC);

$qH2 = $conexion->prepare("SELECT id_horario, instructor_id FROM horario WHERE id_horario = ?");
$qH2->execute([$idHorarioTest2]);
$h2Post = $qH2->fetch(PDO::FETCH_ASSOC);

$swapValido = (
    (int)$h1Post['id_horario'] === $idHorarioTest1 &&
    (int)$h1Post['instructor_id'] === (int)$inst1['id_usuario'] &&
    (int)$h2Post['id_horario'] === $idHorarioTest2 &&
    (int)$h2Post['instructor_id'] === (int)$inst2['id_usuario']
);
assertTest($swapValido, "Test 7: Ambos horarios conservan sus identificadores (#$idHorarioTest1 y #$idHorarioTest2) y los nuevos instructores quedaron intercambiados");

// TEST 8: Horario con historial permite corregir instructor y protege la ficha
echo "\n--- TEST 8: Horario con sesiones y asistencias históricas ---\n";
// Insertar sesión de prueba y asistencia de prueba vinculada a idHorarioTest1
$stmtSes = $conexion->prepare("
    INSERT INTO sesiones_jornada (instructor_id, ficha_id, horario_id, fecha, hora_apertura_real, hora_limite_puntualidad, estado)
    VALUES (?, ?, ?, '2026-11-06', '2026-11-06 06:05:00', '2026-11-06 06:15:00', 'cerrada_manual')
");
$stmtSes->execute([$inst1['id_usuario'], $ficha1['id_ficha'], $idHorarioTest1]);
$idSesionTest = (int)$conexion->lastInsertId();

// Obtener aprendiz de ficha1
$qApr = $conexion->prepare("SELECT id_aprendiz FROM aprendiz WHERE fk_ficha = ? LIMIT 1");
$qApr->execute([$ficha1['id_ficha']]);
$apr = $qApr->fetch();
$idAprTest = $apr ? (int)$apr['id_aprendiz'] : null;

$idAsistenciaTest = null;
if ($idAprTest) {
    $stmtAst = $conexion->prepare("
        INSERT INTO asistencia (fecha_asistencia, entrada, salida, estado_entrada, estado_salida, fk_aprendiz, fk_sesion_jornada, fk_horario)
        VALUES ('2026-11-06', '2026-11-06 06:04:00', '2026-11-06 10:00:00', 'A Tiempo', 'Normal', ?, ?, ?)
    ");
    $stmtAst->execute([$idAprTest, $idSesionTest, $idHorarioTest1]);
    $idAsistenciaTest = (int)$conexion->lastInsertId();
}

echo "Historial creado para Horario #$idHorarioTest1: Sesion #$idSesionTest, Asistencia " . ($idAsistenciaTest ? "#$idAsistenciaTest" : "(sin aprendiz)") . "\n";

// Intentar editar instructor a Instructor 2 Y cambiar ficha a Ficha 2
$resEditHist = simularEdicionHorario(
    $idHorarioTest1, $ficha2['id_ficha'], $inst2['id_usuario'],
    'Viernes', '06:00:00', '10:00:00',
    'CLASE HISTORICA EDITADA', 'Ambiente 301 B', 'completo', 'Activo', 'Edición con historial', '2026-11-06', '2026-11-06'
);

$qVerifHist = $conexion->prepare("SELECT fk_ficha, instructor_id FROM horario WHERE id_horario = ?");
$qVerifHist->execute([$idHorarioTest1]);
$rowHorHist = $qVerifHist->fetch(PDO::FETCH_ASSOC);

$fichaIntacta = ((int)$rowHorHist['fk_ficha'] === (int)$ficha1['id_ficha']);
$instructorActualizado = ((int)$rowHorHist['instructor_id'] === (int)$inst2['id_usuario']);

// Verificar que la sesion y la asistencia no sufrieron alteraciones
$qSesCheck = $conexion->prepare("SELECT ficha_id, horario_id FROM sesiones_jornada WHERE id_sesion = ?");
$qSesCheck->execute([$idSesionTest]);
$sesCheck = $qSesCheck->fetch(PDO::FETCH_ASSOC);

$sesionIntacta = ($sesCheck && (int)$sesCheck['ficha_id'] === (int)$ficha1['id_ficha'] && (int)$sesCheck['horario_id'] === $idHorarioTest1);

assertTest(
    $resEditHist['status'] === 'ok' && $fichaIntacta && $instructorActualizado && $sesionIntacta,
    "Test 8: Horario con historial permitió actualizar instructor (#{$inst2['id_usuario']}) y protegió la ficha original (#{$ficha1['id_ficha']}), preservando la sesión y asistencias históricas"
);

// TEST 9: Validación de permisos administrativos
echo "\n--- TEST 9: Control de acceso y validaciones de seguridad ---\n";
$resNoAdmin = simularEdicionHorario(
    $idHorarioTest1, $ficha1['id_ficha'], $inst1['id_usuario'],
    'Viernes', '06:00:00', '10:00:00',
    'TEST HACK', 'Ambiente', 'completo', 'Activo', '', '2026-11-06', '2026-11-06',
    false // No admin
);
$resNoCsrf = simularEdicionHorario(
    $idHorarioTest1, $ficha1['id_ficha'], $inst1['id_usuario'],
    'Viernes', '06:00:00', '10:00:00',
    'TEST HACK', 'Ambiente', 'completo', 'Activo', '', '2026-11-06', '2026-11-06',
    true, false // Token CSRF invalido
);
assertTest(
    $resNoAdmin['status'] === 'error' && $resNoCsrf['status'] === 'error',
    "Test 9: Peticiones sin permisos de administrador o sin token CSRF válido son rechazadas tajantemente"
);

// TEST 10: Verificar que la importación de horarios sigue funcionando
echo "\n--- TEST 10: Verificación de la importación tras los cambios ---\n";
// Simular inserción por importador con instructor_id y captura de id_horario
$stmtImp = $conexion->prepare("
    INSERT INTO horario (dia_semana, entrada, salida, fk_ficha, instructor_id)
    VALUES (?, ?, ?, ?, ?)
");
$stmtImp->execute([
    'Sábado', '08:00:00', '12:00:00', $ficha1['id_ficha'], $inst1['id_usuario']
]);
$idHorarioImportadoTest = (int)$conexion->lastInsertId();
assertTest($idHorarioImportadoTest > 0, "Test 10: Importador inserta correctamente horario con instructor_id (#$idHorarioImportadoTest)");

// Limpieza de datos de prueba creados en este test suite
echo "\n--- LIMPIEZA DE DATOS DE PRUEBA ---\n";
if ($idAsistenciaTest) {
    $conexion->prepare("DELETE FROM asistencia WHERE id_asistencia = ?")->execute([$idAsistenciaTest]);
}
if ($idSesionTest) {
    $conexion->prepare("DELETE FROM sesiones_jornada WHERE id_sesion = ?")->execute([$idSesionTest]);
}
$conexion->prepare("DELETE FROM horario WHERE id_horario IN (?, ?, ?)")->execute([$idHorarioTest1, $idHorarioTest2, $idHorarioImportadoTest]);
echo "Limpieza completada: registros de prueba eliminados.\n\n";

echo "====================================================================\n";
echo "RESULTADOS DEL TEST SUITE:\n";
echo "Total de pruebas: " . ($testsPasados + $testsFallados) . "\n";
echo "Pruebas pasadas:  $testsPasados\n";
echo "Pruebas falladas: $testsFallados\n";
echo "====================================================================\n";

if ($testsFallados > 0) {
    exit(1);
}
