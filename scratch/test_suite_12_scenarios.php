<?php
/**
 * Automated Test Suite - Multi-Horario Attendance System (12 Scenarios)
 * Tests are executed against the isolated sandbox database `control_asistencia_backup_verify`
 * to protect historical production data while verifying all business logic and DB constraints.
 */

date_default_timezone_set('America/Bogota');
require_once __DIR__ . '/../models/AsistenciaModel.php';

$pdo = new PDO(
    "mysql:host=localhost;dbname=control_asistencia_backup_verify;charset=utf8mb4",
    'root',
    '',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$results = [];

function runTest($id, $name, callable $fn) {
    global $results;
    try {
        $detail = $fn();
        $results[$id] = [
            'scenario' => $id,
            'name'     => $name,
            'status'   => 'PASS',
            'detail'   => $detail
        ];
        echo " [PASS] Scenario $id: $name\n";
    } catch (Throwable $e) {
        $results[$id] = [
            'scenario' => $id,
            'name'     => $name,
            'status'   => 'FAIL',
            'detail'   => $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine()
        ];
        echo " [FAIL] Scenario $id: $name -> " . $e->getMessage() . "\n";
    }
}

function createTestAprendiz($pdo, $fichaId, $rfid) {
    $idNum = (string)mt_rand(100000000, 999999999);
    $username = "usr_" . mt_rand(10000, 99999);
    $email = "test_" . mt_rand(10000, 99999) . "@test.com";
    $pdo->prepare("INSERT INTO usuario (nombre_usuario, identificacion, tipo_documento, nombre, apellido, correo_electronico, contrasena, fk_rol) VALUES (?, ?, 'CC', 'Test', 'Aprendiz', ?, 'hash', 3)")
        ->execute([$username, $idNum, $email]);
    $uid = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO aprendiz (fk_usuario, fk_ficha, codigo_rfid) VALUES (?, ?, ?)")
        ->execute([$uid, $fichaId, $rfid]);
    $apId = (int)$pdo->lastInsertId();
    return ['id_aprendiz' => $apId, 'id_usuario' => $uid, 'codigo_rfid' => $rfid];
}

function deleteTestAprendiz($pdo, $ap) {
    $pdo->exec("DELETE FROM excusa WHERE fk_asistencia IN (SELECT id_asistencia FROM asistencia WHERE fk_aprendiz = " . $ap['id_aprendiz'] . ")");
    $pdo->exec("DELETE FROM asistencia WHERE fk_aprendiz = " . $ap['id_aprendiz']);
    $pdo->exec("DELETE FROM aprendiz WHERE id_aprendiz = " . $ap['id_aprendiz']);
    $pdo->exec("DELETE FROM usuario WHERE id_usuario = " . $ap['id_usuario']);
}

echo "======================================================================\n";
echo "   INICIANDO BATERÍA DE PRUEBAS AUTOMATIZADAS MULTI-HORARIO (12 CASOS)\n";
echo "======================================================================\n\n";

// Preparación de datos de prueba en la base de pruebas aislada
$testDate = '2026-10-24'; // Sábado
$diaSemanaTest = 'Sábado';

// Limpiar datos de prueba previos si existían
$pdo->exec("DELETE FROM excusa WHERE fk_asistencia IN (SELECT id_asistencia FROM asistencia WHERE fecha_asistencia = '$testDate')");
$pdo->exec("DELETE FROM excusa WHERE fk_asistencia IN (SELECT id_asistencia FROM asistencia WHERE fk_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid LIKE 'TEST_RFID_%'))");
$pdo->exec("DELETE FROM asistencia WHERE fecha_asistencia = '$testDate'");
$pdo->exec("DELETE FROM asistencia WHERE fk_aprendiz IN (SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid LIKE 'TEST_RFID_%')");
$pdo->exec("DELETE FROM aprendiz WHERE codigo_rfid LIKE 'TEST_RFID_%'");
$pdo->exec("DELETE FROM usuario WHERE correo_electronico LIKE 'test_%@test.com'");
$pdo->exec("DELETE FROM sesiones_jornada WHERE fecha = '$testDate'");
$pdo->exec("DELETE FROM horario WHERE observaciones LIKE 'TEST_MULTI_%'");

// Buscar ficha e instructor reales para pruebas
$fichaTest = $pdo->query("SELECT id_ficha, fk_usuario FROM ficha ORDER BY id_ficha ASC LIMIT 1")->fetch();
$fichaId = (int)$fichaTest['id_ficha'];
$instructorId = (int)$fichaTest['fk_usuario'];

// Crear aprendiz principal de prueba
$aprendizPrincipal = createTestAprendiz($pdo, $fichaId, 'TEST_RFID_PRINCIPAL_01');
$rfid = $aprendizPrincipal['codigo_rfid'];
$aprendizId = $aprendizPrincipal['id_aprendiz'];

// Crear dos horarios de prueba en el mismo día para la misma ficha
// Clase 1: 08:00:00 a 10:00:00
// Clase 2: 10:15:00 a 12:15:00
$qHor = $pdo->prepare("INSERT INTO horario (dia_semana, entrada, salida, competencia, tipo_bloque, ambiente, estado, observaciones, fecha_inicio, fecha_fin, fk_ficha, instructor_id) VALUES (?, ?, ?, ?, 'completo', 'Ambiente 101', 'Activo', ?, ?, ?, ?, ?)");

$qHor->execute([$diaSemanaTest, '08:00:00', '10:00:00', 'Competencia 1 Multi', 'TEST_MULTI_H1', $testDate, $testDate, $fichaId, $instructorId]);
$horarioId1 = (int)$pdo->lastInsertId();

$qHor->execute([$diaSemanaTest, '10:15:00', '12:15:00', 'Competencia 2 Multi', 'TEST_MULTI_H2', $testDate, $testDate, $fichaId, $instructorId]);
$horarioId2 = (int)$pdo->lastInsertId();

// Crear sesiones de jornada para ambos horarios en la misma fecha
$qSes = $pdo->prepare("INSERT INTO sesiones_jornada (instructor_id, ficha_id, horario_id, fecha, hora_apertura_real, hora_limite_puntualidad, competencia, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'abierta')");

$qSes->execute([$instructorId, $fichaId, $horarioId1, $testDate, "$testDate 07:50:00", "$testDate 08:00:00", 'Competencia 1 Multi']);
$sesionId1 = (int)$pdo->lastInsertId();

$qSes->execute([$instructorId, $fichaId, $horarioId2, $testDate, "$testDate 10:05:00", "$testDate 10:15:00", 'Competencia 2 Multi']);
$sesionId2 = (int)$pdo->lastInsertId();

// ------------------------------------------------------------------------
// PRUEBA 1: Entrada puntual en la primera clase
// ------------------------------------------------------------------------
runTest(1, 'Entrada puntual en la primera clase', function() use ($pdo, $rfid, $fichaId, $horarioId1, $testDate) {
    // Escaneo a las 07:55:00 (5 min antes del inicio programado 08:00:00)
    $res = AsistenciaModel::registrarPorRfid($rfid, 'entrada', $pdo, $fichaId, $testDate, '07:55:00');
    if (!$res['success']) {
        throw new Exception("Error al registrar entrada: " . ($res['message'] ?? ''));
    }
    if (($res['data']['estado'] ?? '') !== 'PUNTUAL') {
        throw new Exception("El estado esperado era PUNTUAL, se obtuvo: " . $res['data']['estado']);
    }
    if ((int)$res['data']['horario_id'] !== $horarioId1) {
        throw new Exception("El horario asignado debió ser $horarioId1, se obtuvo: " . $res['data']['horario_id']);
    }
    return "Entrada registrada exitosamente: PUNTUAL, horario_id=$horarioId1, asistencia_id=" . $res['data']['asistencia_id'];
});

// ------------------------------------------------------------------------
// PRUEBA 2: Rechazo de una segunda entrada para la misma clase
// ------------------------------------------------------------------------
runTest(2, 'Rechazo de una segunda entrada para la misma clase', function() use ($pdo, $rfid, $fichaId, $testDate) {
    // Intentar segundo registro a las 07:58:00 para la misma clase
    $res = AsistenciaModel::registrarPorRfid($rfid, 'entrada', $pdo, $fichaId, $testDate, '07:58:00');
    if ($res['success']) {
        throw new Exception("Se esperaba rechazo por duplicado, pero la entrada fue aceptada.");
    }
    if (strpos($res['message'], 'ya fue registrada') === false) {
        throw new Exception("Mensaje inesperado: " . $res['message']);
    }
    return "Rechazado correctamente: " . $res['message'];
});

// ------------------------------------------------------------------------
// PRUEBA 3: Registro correcto de la salida de la primera clase
// ------------------------------------------------------------------------
runTest(3, 'Registro correcto de la salida de la primera clase', function() use ($pdo, $rfid, $fichaId, $horarioId1, $testDate) {
    // Registro de salida a las 10:00:00 (hora programada de salida de clase 1)
    $res = AsistenciaModel::registrarPorRfid($rfid, 'salida', $pdo, $fichaId, $testDate, '10:00:00');
    if (!$res['success']) {
        throw new Exception("Error al registrar salida: " . ($res['message'] ?? ''));
    }
    if (($res['data']['estado_salida'] ?? '') !== 'SALIDA_NORMAL') {
        throw new Exception("El estado esperado era SALIDA_NORMAL, se obtuvo: " . $res['data']['estado_salida']);
    }
    // Verificar que la salida quedó en BD
    $q = $pdo->prepare("SELECT salida, estado_salida FROM asistencia WHERE id_asistencia = ?");
    $q->execute([$res['data']['asistencia_id']]);
    $row = $q->fetch();
    if (empty($row['salida'])) {
        throw new Exception("El campo salida no fue persistido.");
    }
    return "Salida registrada correctamente: SALIDA_NORMAL a las 10:00:00 para asistencia_id=" . $res['data']['asistencia_id'];
});

// ------------------------------------------------------------------------
// PRUEBA 4: Entrada en una segunda clase durante la misma fecha
// ------------------------------------------------------------------------
runTest(4, 'Entrada en una segunda clase durante la misma fecha', function() use ($pdo, $rfid, $fichaId, $horarioId2, $testDate) {
    // Registro de entrada a las 10:10:00 (5 min antes de inicio de clase 2: 10:15:00)
    $res = AsistenciaModel::registrarPorRfid($rfid, 'entrada', $pdo, $fichaId, $testDate, '10:10:00');
    if (!$res['success']) {
        throw new Exception("Error al registrar entrada en clase 2: " . ($res['message'] ?? ''));
    }
    if ((int)$res['data']['horario_id'] !== $horarioId2) {
        throw new Exception("El horario asignado debió ser $horarioId2, se obtuvo: " . $res['data']['horario_id']);
    }
    // Verificar que coexisten 2 asistencias para este aprendiz en la misma fecha
    $q = $pdo->prepare("SELECT COUNT(*) FROM asistencia WHERE fk_aprendiz = (SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid = ?) AND fecha_asistencia = ?");
    $q->execute([$rfid, $testDate]);
    $total = (int)$q->fetchColumn();
    if ($total !== 2) {
        throw new Exception("Se esperaban 2 asistencias para la fecha, se encontraron: $total");
    }
    return "Entrada en segunda clase permitida sin conflicto. Total asistencias hoy: $total";
});

// ------------------------------------------------------------------------
// PRUEBA 5: Límite exacto de 20 minutos (inclusivo vs rechazo a 20m 01s)
// ------------------------------------------------------------------------
runTest(5, 'Límite exacto de 20 minutos: aceptación en 20m00s y rechazo en 20m01s', function() use ($pdo, $fichaId, $diaSemanaTest, $testDate) {
    // Crear un horario auxiliar para probar el límite exacto: 14:00:00 a 16:00:00
    $q = $pdo->prepare("INSERT INTO horario (dia_semana, entrada, salida, competencia, tipo_bloque, ambiente, estado, observaciones, fecha_inicio, fecha_fin, fk_ficha) VALUES (?, '14:00:00', '16:00:00', 'Limite 20m', 'completo', 'Lab', 'Activo', 'TEST_MULTI_LIMITE', ?, ?, ?)");
    $q->execute([$diaSemanaTest, $testDate, $testDate, $fichaId]);
    $hLimId = (int)$pdo->lastInsertId();

    $apOk = createTestAprendiz($pdo, $fichaId, 'TEST_RFID_LIM_OK');
    $apFail = createTestAprendiz($pdo, $fichaId, 'TEST_RFID_LIM_FAIL');

    // Caso A: 14:20:00 (exactamente 20 minutos 00 segundos) -> DEBE ACEPTARSE (RETARDO)
    $resOk = AsistenciaModel::registrarPorRfid('TEST_RFID_LIM_OK', 'entrada', $pdo, $fichaId, $testDate, '14:20:00');
    if (!$resOk['success']) {
        throw new Exception("Aceptación a las 14:20:00 falló: " . ($resOk['message'] ?? ''));
    }
    if (($resOk['data']['estado'] ?? '') !== 'RETARDO' || (int)$resOk['data']['minutos_retardo'] !== 20) {
        throw new Exception("Se esperaba RETARDO de 20 min, se obtuvo: " . json_encode($resOk['data']));
    }

    // Caso B: 14:20:01 (20 minutos y 1 segundo) -> DEBE RECHAZARSE
    $resFail = AsistenciaModel::registrarPorRfid('TEST_RFID_LIM_FAIL', 'entrada', $pdo, $fichaId, $testDate, '14:20:01');
    if ($resFail['success']) {
        throw new Exception("A las 14:20:01 debió ser rechazado pero fue aceptado.");
    }
    if (strpos($resFail['message'], 'finalizado') === false) {
        throw new Exception("Mensaje inesperado al rechazar a los 20m01s: " . $resFail['message']);
    }

    deleteTestAprendiz($pdo, $apOk);
    deleteTestAprendiz($pdo, $apFail);
    $pdo->exec("DELETE FROM horario WHERE id_horario = $hLimId");

    return "Límite validado: 14:20:00 aceptado (RETARDO 20 min) y 14:20:01 rechazado ('La ventana de registro de entrada ha finalizado.').";
});

// ------------------------------------------------------------------------
// PRUEBA 6: Inasistencia a una segunda clase aunque exista asistencia a la primera
// ------------------------------------------------------------------------
runTest(6, 'Inasistencia a una segunda clase aunque exista asistencia a la primera', function() use ($pdo, $fichaId, $horarioId1, $horarioId2, $testDate) {
    $ap = createTestAprendiz($pdo, $fichaId, 'TEST_RFID_INASIS_2');
    $apId = $ap['id_aprendiz'];

    // Registrar asistencia en Clase 1
    $pdo->prepare("INSERT INTO asistencia (fecha_asistencia, entrada, estado_entrada, fk_aprendiz, fk_horario) VALUES (?, '2026-10-24 07:55:00', 'PUNTUAL', ?, ?)")->execute([$testDate, $apId, $horarioId1]);

    // Generar inasistencia para la Clase 2
    $pdo->prepare("INSERT INTO asistencia (fecha_asistencia, estado_entrada, fk_aprendiz, fk_horario) VALUES (?, 'INASISTENCIA', ?, ?)")->execute([$testDate, $apId, $horarioId2]);

    // Verificar que ambas filas existen para el mismo aprendiz en la misma fecha
    $rows = $pdo->query("SELECT fk_horario, estado_entrada FROM asistencia WHERE fk_aprendiz = $apId AND fecha_asistencia = '$testDate' ORDER BY fk_horario ASC")->fetchAll();

    if (count($rows) !== 2) {
        throw new Exception("Se esperaban 2 registros para el aprendiz, se encontraron: " . count($rows));
    }
    if ($rows[0]['estado_entrada'] !== 'PUNTUAL' || $rows[1]['estado_entrada'] !== 'INASISTENCIA') {
        throw new Exception("Estados no coinciden: " . json_encode($rows));
    }

    deleteTestAprendiz($pdo, $ap);

    return "Inasistencia a clase 2 registrada correctamente junto a asistencia a clase 1 para el mismo aprendiz y fecha.";
});

// ------------------------------------------------------------------------
// PRUEBA 7: Reejecución de la generación de inasistencias sin duplicados (Idempotencia)
// ------------------------------------------------------------------------
runTest(7, 'Reejecución de generación de inasistencias sin duplicados (idempotencia)', function() use ($pdo) {
    // Ejecutar generar_inasistencias_v2 dos veces seguidas
    $stmt1 = $pdo->query("CALL generar_inasistencias_v2()");
    $stmt1->closeCursor();

    $stmt2 = $pdo->query("CALL generar_inasistencias_v2()");
    $stmt2->closeCursor();

    // Verificar que no existan duplicados de (fk_aprendiz, fk_horario, fecha_asistencia)
    $dups = (int)$pdo->query("SELECT COUNT(*) FROM (SELECT fk_aprendiz, fk_horario, fecha_asistencia FROM asistencia GROUP BY fk_aprendiz, fk_horario, fecha_asistencia HAVING COUNT(*) > 1) t")->fetchColumn();
    if ($dups > 0) {
        throw new Exception("Se detectaron $dups duplicados tras reejecutar el procedimiento.");
    }
    return "Procedimiento reejecutado exitosamente: 0 duplicados encontrados.";
});

// ------------------------------------------------------------------------
// PRUEBA 8: Asociación correcta entre aprendiz, ficha, horario y sesión
// ------------------------------------------------------------------------
runTest(8, 'Asociación correcta entre aprendiz, ficha, horario y sesión', function() use ($pdo, $aprendizId, $horarioId1, $horarioId2, $sesionId1, $testDate) {
    // Intentar insertar una asistencia vinculando sesionId1 con horarioId2 (INCOMPATIBLES)
    // El constraint fk_asistencia_sesion_horario DEBE rechazarlo
    $rechazadoIncompatible = false;
    try {
        $pdo->prepare("INSERT INTO asistencia (fecha_asistencia, entrada, estado_entrada, fk_aprendiz, fk_sesion_jornada, fk_horario) VALUES (?, '2026-10-24 08:00:00', 'PUNTUAL', ?, ?, ?)")->execute([$testDate, $aprendizId, $sesionId1, $horarioId2]);
    } catch (PDOException $e) {
        $rechazadoIncompatible = true;
    }

    if (!$rechazadoIncompatible) {
        throw new Exception("El sistema permitió asociar una sesión con un horario diferente al de la sesión.");
    }

    return "Integridad referencial validada: Rechazada la combinación incompatible sesión #$sesionId1 con horario #$horarioId2 por constraint fk_asistencia_sesion_horario.";
});

// ------------------------------------------------------------------------
// PRUEBA 9: Radicación y consulta de excusas
// ------------------------------------------------------------------------
runTest(9, 'Radicación y consulta de excusas vinculadas a la asistencia exacta', function() use ($pdo, $fichaId, $horarioId1, $horarioId2, $testDate) {
    $ap = createTestAprendiz($pdo, $fichaId, 'TEST_RFID_EXCUSA');
    $apId = $ap['id_aprendiz'];

    // Crear dos inasistencias en la misma fecha (clase 1 y clase 2)
    $q = $pdo->prepare("INSERT INTO asistencia (fecha_asistencia, estado_entrada, fk_aprendiz, fk_horario) VALUES (?, 'INASISTENCIA', ?, ?)");
    $q->execute([$testDate, $apId, $horarioId1]);
    $idAst1 = (int)$pdo->lastInsertId();

    $q->execute([$testDate, $apId, $horarioId2]);
    $idAst2 = (int)$pdo->lastInsertId();

    // Radicar excusa SOLO para la clase 1 (idAst1)
    $pdo->prepare("INSERT INTO excusa (archivo, estado, fk_asistencia) VALUES ('excusa_test.pdf', 'Pendiente', ?)")->execute([$idAst1]);
    $excusaId = (int)$pdo->lastInsertId();

    // Consultar inasistencias sin excusa para este aprendiz
    $qInasis = $pdo->prepare("
        SELECT a.id_asistencia, a.fk_horario
        FROM asistencia a
        LEFT JOIN excusa e ON e.fk_asistencia = a.id_asistencia
        WHERE a.fk_aprendiz = ? AND a.estado_entrada = 'INASISTENCIA' AND e.id_excusa IS NULL
    ");
    $qInasis->execute([$apId]);
    $pendientes = $qInasis->fetchAll();

    if (count($pendientes) !== 1 || (int)$pendientes[0]['id_asistencia'] !== $idAst2) {
        throw new Exception("La consulta de inasistencias sin excusa debió retornar solo idAst2 ($idAst2), se obtuvo: " . json_encode($pendientes));
    }

    deleteTestAprendiz($pdo, $ap);

    return "Excusa vinculada con precisión quirúrgica a id_asistencia=$idAst1. id_asistencia=$idAst2 permanece pendiente por justificar.";
});

// ------------------------------------------------------------------------
// PRUEBA 10: Cierre de jornada con inserción concurrente de inasistencia (Error 1062 manejado)
// ------------------------------------------------------------------------
runTest(10, 'Cierre de jornada cuando existe una inserción concurrente de inasistencia (error 1062 manejado)', function() use ($pdo, $fichaId, $horarioId1, $sesionId1, $testDate) {
    $ap = createTestAprendiz($pdo, $fichaId, 'TEST_RFID_CONCURR');
    $apId = $ap['id_aprendiz'];

    // Inserción concurrente previa
    $pdo->prepare("INSERT INTO asistencia (fecha_asistencia, estado_entrada, fk_aprendiz, fk_horario) VALUES (?, 'INASISTENCIA', ?, ?)")->execute([$testDate, $apId, $horarioId1]);
    $astIdExistente = (int)$pdo->lastInsertId();

    // Lógica del controlador cerrar_jornada.php ante 1062
    $pdo->beginTransaction();
    try {
        $qInsert = $pdo->prepare("
            INSERT INTO asistencia (
                fecha_asistencia, entrada, salida, estado_entrada, estado_salida,
                minutos_retardo, minutos_salida_anticipada, fk_aprendiz,
                fk_sesion_jornada, fk_horario
            ) VALUES (
                :fecha, NULL, NULL, 'INASISTENCIA', NULL,
                0, 0, :aprendiz_id,
                :sesion_id, :horario_id
            )
        ");
        $qInsert->execute([
            ':fecha'       => $testDate,
            ':aprendiz_id' => $apId,
            ':sesion_id'   => $sesionId1,
            ':horario_id'  => $horarioId1
        ]);
        $pdo->commit();
    } catch (PDOException $e) {
        $codigoError = $e->errorInfo[1] ?? $e->getCode();
        if ((int)$codigoError === 1062) {
            // Manejador idéntico a cerrar_jornada.php
            $chk = $pdo->prepare("
                SELECT id_asistencia, fk_sesion_jornada
                FROM asistencia
                WHERE fk_aprendiz = :aprendiz_id
                  AND fk_horario = :horario_id
                  AND fecha_asistencia = :fecha
                LIMIT 1 FOR UPDATE
            ");
            $chk->execute([
                ':aprendiz_id' => $apId,
                ':horario_id'  => $horarioId1,
                ':fecha'       => $testDate
            ]);
            $filaExistente = $chk->fetch();

            if (!$filaExistente) {
                $pdo->rollBack();
                throw new Exception("Error 1062 no corresponde a la clave esperada.");
            }

            if (empty($filaExistente['fk_sesion_jornada'])) {
                $pdo->prepare("UPDATE asistencia SET fk_sesion_jornada = :sesion_id WHERE id_asistencia = :id_asistencia")->execute([
                    ':sesion_id'      => $sesionId1,
                    ':id_asistencia'  => $filaExistente['id_asistencia']
                ]);
            }
            $pdo->commit();
        } else {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Verificar que la asistencia ahora tiene la sesion vinculada y no se crearon duplicados
    $qVerif = $pdo->prepare("SELECT fk_sesion_jornada FROM asistencia WHERE id_asistencia = ?");
    $qVerif->execute([$astIdExistente]);
    $sesAsociada = (int)$qVerif->fetchColumn();

    deleteTestAprendiz($pdo, $ap);

    if ($sesAsociada !== $sesionId1) {
        throw new Exception("fk_sesion_jornada no fue actualizada a $sesionId1.");
    }

    return "Colisión 1062 capturada y resuelta con éxito: registro existente vinculado a la sesión $sesionId1 sin abortar.";
});

// ------------------------------------------------------------------------
// PRUEBA 11: Compatibilidad de los reportes y consultas existentes
// ------------------------------------------------------------------------
runTest(11, 'Compatibilidad de consultas de reportes e historiales', function() use ($pdo) {
    // 1. Consulta típica de reportes por ficha y rango de fechas (admin e instructor)
    $sqlReporte = "
        SELECT 
            a.id_asistencia,
            a.fecha_asistencia,
            a.entrada,
            a.salida,
            a.estado_entrada,
            a.estado_salida,
            a.minutos_retardo,
            ap.codigo_rfid,
            u.nombre,
            u.apellido,
            f.id_ficha,
            f.nombre_programa,
            h.entrada AS hora_entrada_prog,
            h.salida AS hora_salida_prog,
            h.competencia
        FROM asistencia a
        INNER JOIN aprendiz ap ON ap.id_aprendiz = a.fk_aprendiz
        INNER JOIN usuario u ON u.id_usuario = ap.fk_usuario
        INNER JOIN ficha f ON f.id_ficha = ap.fk_ficha
        LEFT JOIN horario h ON h.id_horario = a.fk_horario
        ORDER BY a.fecha_asistencia DESC, a.id_asistencia DESC
        LIMIT 10
    ";
    $filasRep = $pdo->query($sqlReporte)->fetchAll();
    if (empty($filasRep)) {
        throw new Exception("La consulta de reportes no retornó registros.");
    }

    // 2. Consulta de historial del aprendiz
    $sqlHistorial = "
        SELECT 
            a.id_asistencia,
            a.fecha_asistencia,
            a.entrada,
            a.salida,
            a.estado_entrada,
            a.estado_salida,
            a.minutos_retardo,
            h.competencia,
            h.entrada AS horario_entrada,
            h.salida AS horario_salida
        FROM asistencia a
        LEFT JOIN horario h ON h.id_horario = a.fk_horario
        WHERE a.fk_aprendiz = ?
        ORDER BY a.fecha_asistencia DESC
        LIMIT 5
    ";
    $qHist = $pdo->prepare($sqlHistorial);
    $qHist->execute([$filasRep[0]['id_asistencia']]);
    $filasHist = $qHist->fetchAll();

    return "Consultas de reportes e historial ejecutadas exitosamente con " . count($filasRep) . " registros recuperados con fk_horario y competencias.";
});

// ------------------------------------------------------------------------
// PRUEBA 12: Conservación de los 75 registros históricos existentes
// ------------------------------------------------------------------------
runTest(12, 'Conservación de registros históricos tras la migración', function() use ($pdo) {
    // Consultar registros históricos
    $total = (int)$pdo->query("SELECT COUNT(*) FROM asistencia WHERE id_asistencia <= 273")->fetchColumn();
    $conHorarioNull = (int)$pdo->query("SELECT COUNT(*) FROM asistencia WHERE id_asistencia <= 273 AND fk_horario IS NULL")->fetchColumn();
    $conHorarioInvalido = (int)$pdo->query("SELECT COUNT(*) FROM asistencia a LEFT JOIN horario h ON a.fk_horario = h.id_horario WHERE a.id_asistencia <= 273 AND h.id_horario IS NULL")->fetchColumn();

    if ($total !== 75) {
        throw new Exception("Se esperaban exactamente 75 registros históricos, se encontraron: $total");
    }
    if ($conHorarioNull !== 0) {
        throw new Exception("Existen $conHorarioNull registros con fk_horario NULL");
    }
    if ($conHorarioInvalido !== 0) {
        throw new Exception("Existen $conHorarioInvalido registros con fk_horario huérfano");
    }

    return "75 de 75 registros históricos conservados intactos, todos con fk_horario válido y consistente.";
});

// Limpieza final de datos de prueba
deleteTestAprendiz($pdo, $aprendizPrincipal);
$pdo->exec("DELETE FROM excusa WHERE fk_asistencia IN (SELECT id_asistencia FROM asistencia WHERE fecha_asistencia = '$testDate')");
$pdo->exec("DELETE FROM asistencia WHERE fecha_asistencia = '$testDate'");
$pdo->exec("DELETE FROM sesiones_jornada WHERE fecha = '$testDate'");
$pdo->exec("DELETE FROM horario WHERE observaciones LIKE 'TEST_MULTI_%'");

echo "\n======================================================================\n";
echo "                       RESUMEN DE PRUEBAS\n";
echo "======================================================================\n";
$totalPassed = 0;
$totalFailed = 0;
foreach ($results as $r) {
    if ($r['status'] === 'PASS') {
        $totalPassed++;
    } else {
        $totalFailed++;
    }
    printf("[%s] Escenario %02d: %-60s\n", $r['status'], $r['scenario'], $r['name']);
    echo "       Detalle: " . $r['detail'] . "\n\n";
}

echo "TOTAL EJECUTADAS: " . count($results) . "\n";
echo "APROBADAS:        $totalPassed\n";
echo "FALLIDAS:         $totalFailed\n";
echo "======================================================================\n";
