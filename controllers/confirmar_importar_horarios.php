<?php
/**
 * controlador: confirmar importacion de horarios desde previsualizacion
 * fase 3: inserta horarios validos en transaccion
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/HorarioValidator.php';

// validar post y rol admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['admin']['id_usuario'])) {
    header('Location: ../views/auth/login.php');
    exit;
}

// validar csrf
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Token CSRF invalido'];
    header('Location: ../views/admin/horarios/importar_preview.php');
    exit;
}

// recuperar preview de sesion
if (empty($_SESSION['import_preview'])) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No hay previsualizacion para confirmar'];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

$preview = $_SESSION['import_preview'];
$filas = $preview['filas'] ?? [];

$filasSeleccionadas = $_POST['filas_seleccionadas'] ?? [];
$fichasManual = $_POST['ficha_manual'] ?? [];

if (empty($filasSeleccionadas)) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No se seleccionaron filas para importar'];
    header('Location: ../views/admin/horarios/importar_preview.php');
    exit;
}

// conectar a bd
$conexion = (new Conexion())->conectar();

try {
    $conexion->beginTransaction();

    // cache para validaciones
    $cacheInstructores = [];
    $cacheFichas = [];
    $cacheHorariosExistentes = [];
    $cacheHorariosPorAmbienteDia = [];

    // obtener horarios existentes con ambiente_principal
    $q = $conexion->query("
        SELECT h.fk_ficha, h.dia_semana, h.entrada, h.salida, f.ambiente_principal
        FROM horario h
        INNER JOIN ficha f ON f.id_ficha = h.fk_ficha
    ");
    $horariosBD = $q->fetchAll();
    foreach ($horariosBD as $h) {
        $clave = $h['fk_ficha'] . '|' . $h['dia_semana'] . '|' . substr($h['entrada'], 0, 5) . '|' . substr($h['salida'], 0, 5);
        $cacheHorariosExistentes[$clave] = true;
        // para cruces de ambiente
        $amb = trim((string)($h['ambiente_principal'] ?? ''));
        if ($amb !== '') {
            $ambDia = HorarioValidator::normalizarAmbiente($amb) . '|' . $h['dia_semana'];
            $cacheHorariosPorAmbienteDia[$ambDia][] = $h;
        }
    }

    $resultados = [];
    $creados = 0;
    $omitidos = 0;
    $rechazados = 0;

    foreach ($filasSeleccionadas as $idxStr) {
        $idx = (int)$idxStr;
        if (!isset($filas[$idx])) {
            continue; // ignorar indices invalidos
        }

        $f = $filas[$idx];

        // solo filas VALIDA, ADVERTENCIA, PENDIENTE_FICHA
        if (!in_array($f['estado'], ['VALIDA', 'ADVERTENCIA', 'PENDIENTE_FICHA'], true)) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => $f['ficha_id'] ?? ($fichasManual[$idx] ?? '—'),
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Estado no importable: ' . $f['estado']
            ];
            $rechazados++;
            continue;
        }

        // validar instructor
        $identInstr = $f['identificacion_instructor'];
        if (!isset($cacheInstructores[$identInstr])) {
            $q = $conexion->prepare("SELECT id_usuario, nombre, apellido FROM usuario WHERE identificacion = ? AND fk_rol = 2 LIMIT 1");
            $q->execute([$identInstr]);
            $cacheInstructores[$identInstr] = $q->fetch();
        }
        $instructor = $cacheInstructores[$identInstr];
        if (!$instructor) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '—',
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Instructor no encontrado o sin rol Instructor'
            ];
            $rechazados++;
            continue;
        }

        // resolver ficha
        $fichaId = $f['ficha_id'];
        if ($f['pendiente_ficha']) {
            $fichaId = isset($fichasManual[$idx]) ? (int)$fichasManual[$idx] : 0;
        }

        if (!$fichaId) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '—',
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Ficha no seleccionada'
            ];
            $rechazados++;
            continue;
        }

        // validar ficha
        if (!isset($cacheFichas[$fichaId])) {
            $q = $conexion->prepare("SELECT id_ficha, nombre_programa, fk_usuario, ambiente_principal FROM ficha WHERE id_ficha = ? LIMIT 1");
            $q->execute([$fichaId]);
            $cacheFichas[$fichaId] = $q->fetch();
        }
        $ficha = $cacheFichas[$fichaId];
        if (!$ficha) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $fichaId,
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Ficha no existe'
            ];
            $rechazados++;
            continue;
        }

        // validar pertenencia ficha-instructor
        if ((int)$ficha['fk_usuario'] !== (int)$instructor['id_usuario']) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'],
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'La ficha no pertenece al instructor indicado'
            ];
            $rechazados++;
            continue;
        }

        // validar ambiente: Excel vs ficha.ambiente_principal
        $ambienteExcel = trim((string)($f['ambiente'] ?? ''));
        $ambienteFicha = trim((string)($ficha['ambiente_principal'] ?? ''));
        if ($ambienteExcel !== '') {
            if ($ambienteFicha !== '') {
                if (HorarioValidator::normalizarAmbiente($ambienteExcel) !== HorarioValidator::normalizarAmbiente($ambienteFicha)) {
                    $resultados[] = [
                        'fila' => $f['fila'],
                        'instructor' => $f['instructor_nombre'],
                        'ficha' => '#' . $ficha['id_ficha'],
                        'dia' => $f['dia'],
                        'inicio' => $f['hora_inicio'],
                        'fin' => $f['hora_fin'],
                        'resultado' => 'RECHAZADA',
                        'detalle' => "Ambiente no coincide: Excel='$ambienteExcel', ficha principal='$ambienteFicha'"
                    ];
                    $rechazados++;
                    continue;
                }
            }
            // si ficha no tiene ambiente_principal, solo advertencia (no bloquea)
        }

        // normalizar dia y horas
        $dia = HorarioValidator::normalizarDia($f['dia']);
        $horaIni = HorarioValidator::normalizarHora($f['hora_inicio']);
        $horaFin = HorarioValidator::normalizarHora($f['hora_fin']);

        if (!$dia || !$horaIni || !$horaFin || !HorarioValidator::validarOrdenHoras($horaIni, $horaFin)) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'],
                'dia' => $f['dia'],
                'inicio' => $f['hora_inicio'],
                'fin' => $f['hora_fin'],
                'resultado' => 'RECHAZADA',
                'detalle' => 'Dia u horas invalidas'
            ];
            $rechazados++;
            continue;
        }

        // comprobar duplicado exacto en bd
        $claveBD = (int)$ficha['id_ficha'] . '|' . $dia . '|' . $horaIni . '|' . $horaFin;
        if (isset($cacheHorariosExistentes[$claveBD])) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'resultado' => 'OMITIDA_DUPLICADO',
                'detalle' => 'Horario identico ya existe en BD'
            ];
            $omitidos++;
            // marcar en cache para que siguientes filas no intenten insertar lo mismo
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'ambiente' => $ambienteFicha,
                'resultado' => 'OMITIDA_DUPLICADO',
                'detalle' => 'Horario identico ya existe en BD'
            ];
            continue;
        }

        // cruces de ambiente contra BD (fichas distintas con mismo ambiente, mismo dia, horas solapadas)
        if ($ambienteFicha !== '') {
            $ambDia = HorarioValidator::normalizarAmbiente($ambienteFicha) . '|' . $dia;
            if (isset($cacheHorariosPorAmbienteDia[$ambDia])) {
                foreach ($cacheHorariosPorAmbienteDia[$ambDia] as $hExist) {
                    if ((int)$hExist['fk_ficha'] === $ficha['id_ficha']) continue;
                    $hIni = substr($hExist['entrada'], 0, 5);
                    $hFin = substr($hExist['salida'], 0, 5);
                    if (HorarioValidator::haySolapamientoSeguro($horaIni, $horaFin, $hIni, $hFin)) {
                        $resultados[] = [
                            'fila' => $f['fila'],
                            'instructor' => $f['instructor_nombre'],
                            'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                            'dia' => $dia,
                            'inicio' => $horaIni,
                            'fin' => $horaFin,
                            'resultado' => 'RECHAZADA',
                            'detalle' => "Cruce de ambiente: ficha #{$hExist['fk_ficha']} usa mismo ambiente '$ambienteFicha' el $dia ($hIni-$hFin)"
                        ];
                        $rechazados++;
                        continue 2; // salta al siguiente foreach principal
                    }
                }
            }
        }

        // cruces de ambiente dentro de la misma transaccion (filas previas de esta importacion)
        if ($ambienteFicha !== '') {
            $ambienteNorm = HorarioValidator::normalizarAmbiente($ambienteFicha);
            foreach ($resultados as $r) {
                if ($r['resultado'] === 'RECHAZADA') continue;
                if ($r['ficha'] === '#' . $ficha['id_ficha']) continue;
                if ($r['dia'] !== $dia) continue;
                $rAmbNorm = HorarioValidator::normalizarAmbiente($r['ambiente'] ?? '');
                if ($rAmbNorm !== $ambienteNorm) continue;
                if (HorarioValidator::haySolapamientoSeguro($horaIni, $horaFin, $r['inicio'], $r['fin'])) {
                    $resultados[] = [
                        'fila' => $f['fila'],
                        'instructor' => $f['instructor_nombre'],
                        'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                        'dia' => $dia,
                        'inicio' => $horaIni,
                        'fin' => $horaFin,
                        'resultado' => 'RECHAZADA',
                        'detalle' => "Cruce de ambiente dentro de la importacion: ficha {$r['ficha']} usa mismo ambiente '$ambienteFicha' el $dia ({$r['inicio']}-{$r['fin']})"
                    ];
                    $rechazados++;
                    continue 2;
                }
            }
        }

        // insertar horario
        $stmt = $conexion->prepare("INSERT INTO horario (dia_semana, entrada, salida, fk_ficha) VALUES (?, ?, ?, ?)");
        $stmt->execute([$dia, $horaIni . ':00', $horaFin . ':00', $ficha['id_ficha']]);

        if ($stmt->rowCount() > 0) {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'] . ' - ' . $ficha['nombre_programa'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'ambiente' => $ambienteFicha,
                'resultado' => 'CREADA',
                'detalle' => 'Horario insertado correctamente'
            ];
            $creados++;
            // actualizar cache para evitar duplicados en misma transaccion
            $cacheHorariosExistentes[$claveBD] = true;
        } else {
            $resultados[] = [
                'fila' => $f['fila'],
                'instructor' => $f['instructor_nombre'],
                'ficha' => '#' . $ficha['id_ficha'],
                'dia' => $dia,
                'inicio' => $horaIni,
                'fin' => $horaFin,
                'resultado' => 'RECHAZADA',
                'detalle' => 'No se pudo insertar (rowCount=0)'
            ];
            $rechazados++;
        }
    }

    $conexion->commit();

    // limpiar preview de sesion
    unset($_SESSION['import_preview']);

    // guardar resultado en flash para la vista de resultado
    $_SESSION['import_resultado'] = [
        'total_seleccionadas' => count($filasSeleccionadas),
        'creadas' => $creados,
        'omitidas' => $omitidos,
        'rechazadas' => $rechazados,
        'detalles' => $resultados,
        'timestamp' => time()
    ];

    header('Location: ../views/admin/horarios/importar_resultado.php');
    exit;

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Error al importar: ' . $e->getMessage()];
    header('Location: ../views/admin/horarios/importar_preview.php');
    exit;
}