<?php
/**
 * controlador: procesar archivo excel de horarios, validar y crear previsualizacion
 * fase 2: solo validacion en memoria, sin guardar en base de datos
 */

session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/ExcelReader.php';
require_once __DIR__ . '/../includes/HorarioValidator.php';

// validar post y rol admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SESSION['fk_rol'] ?? null) != 1) {
    header('Location: ../views/auth/login.php');
    exit;
}

// validar csrf
if (!hash_equals($_SESSION['token_csrf'] ?? '', $_POST['token_csrf'] ?? '')) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'Token CSRF invalido'];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

// validar archivo
if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => 'No se recibio un archivo valido'];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

$archivo = $_FILES['archivo_excel'];

// leer con excelreader
$resultado = ExcelReader::readUploadedFile($archivo, 'Horarios');

if (!$resultado['success']) {
    $_SESSION['flash'] = ['tipo' => 'error', 'mensaje' => $resultado['message']];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

$headers = $resultado['headers'];
$filasDatos = $resultado['data'];

// validar encabezados
$valEnc = HorarioValidator::validarEncabezados($headers);
if (!$valEnc['ok']) {
    $_SESSION['flash'] = [
        'tipo' => 'error',
        'mensaje' => 'Encabezados faltantes: ' . implode(', ', $valEnc['faltantes'])
    ];
    header('Location: ../views/admin/horarios/importar.php');
    exit;
}

// conectar a bd para validaciones (solo select)
$conexion = (new Conexion())->conectar();

// cache de consultas para evitar repetir
$cacheInstructores = [];
$cacheFichas = [];
$cacheHorariosExistentes = [];

// obtener todos los horarios actuales para validaciones
$q = $conexion->query("
    SELECT h.id_horario, h.fk_ficha, h.dia_semana, h.entrada, h.salida, f.fk_usuario as instructor_id
    FROM horario h
    INNER JOIN ficha f ON f.id_ficha = h.fk_ficha
");
$horariosBD = $q->fetchAll();
foreach ($horariosBD as $h) {
    $clave = $h['fk_ficha'] . '|' . $h['dia_semana'] . '|' . substr($h['entrada'], 0, 5) . '|' . substr($h['salida'], 0, 5);
    $cacheHorariosExistentes[$clave] = $h;
}

// mapear horarios por ficha y dia para cruces
$horariosPorFichaDia = [];
$horariosPorInstructorDia = [];
foreach ($horariosBD as $h) {
    $fichaDia = $h['fk_ficha'] . '|' . $h['dia_semana'];
    $instDia = $h['instructor_id'] . '|' . $h['dia_semana'];
    $horariosPorFichaDia[$fichaDia][] = $h;
    $horariosPorInstructorDia[$instDia][] = $h;
}

// procesar filas
$preview = [];
$filasVacias = 0;
$filasProcesadas = 0;

// para detectar duplicados internos
$vistasEnArchivo = [];

foreach ($filasDatos as $idx => $fila) {
    $numFila = $idx + 2; // +2 por encabezado e indice base 1

    // normalizar claves de fila
    $filaNorm = [];
    foreach ($fila as $k => $v) {
        $filaNorm[HorarioValidator::normalizarClave($k)] = $v;
    }

    // ignorar fila completamente vacia
    if (HorarioValidator::esFilaVacia($filaNorm, $headers)) {
        $filasVacias++;
        continue;
    }

    $filasProcesadas++;

    // extraer valores
    $diaRaw = $filaNorm['dia'] ?? '';
    $horaIniRaw = $filaNorm['hora_inicio'] ?? '';
    $horaFinRaw = $filaNorm['hora_fin'] ?? '';
    $identInstr = trim((string)($filaNorm['identificacion_instructor'] ?? ''));
    $ambiente = trim((string)($filaNorm['ambiente'] ?? ''));
    $idFichaRaw = $filaNorm['id_ficha'] ?? '';
    $numFichaRaw = $filaNorm['numero_ficha'] ?? '';
    $nombreInstr = trim((string)($filaNorm['nombre_instructor'] ?? ''));
    $programa = trim((string)($filaNorm['programa'] ?? ''));
    $area = trim((string)($filaNorm['area_o_competencia'] ?? ''));
    $obs = trim((string)($filaNorm['observacion'] ?? ''));

    // validaciones
    $estado = 'VALIDA';
    $detalles = [];
    $advertencias = [];
    $errores = [];

    // dia
    $dia = HorarioValidator::normalizarDia($diaRaw);
    if (!$dia) {
        $estado = 'ERROR';
        $errores[] = "Dia invalido: '$diaRaw' (validos: Lunes, Martes, Miércoles, Jueves, Viernes, Sábado, Domingo)";
    }

    // horas
    $horaIni = HorarioValidator::normalizarHora($horaIniRaw);
    $horaFin = HorarioValidator::normalizarHora($horaFinRaw);

    if (!$horaIni) {
        $estado = 'ERROR';
        $errores[] = "Hora de inicio invalida: '$horaIniRaw' (formato HH:MM)";
    }
    if (!$horaFin) {
        $estado = 'ERROR';
        $errores[] = "Hora de fin invalida: '$horaFinRaw' (formato HH:MM)";
    }
    if ($horaIni && $horaFin && !HorarioValidator::validarOrdenHoras($horaIni, $horaFin)) {
        $estado = 'ERROR';
        $errores[] = "Hora de fin ($horaFin) debe ser posterior a hora de inicio ($horaIni)";
    }

    // instructor
    $instructor = null;
    if ($identInstr !== '') {
        if (!isset($cacheInstructores[$identInstr])) {
            $q = $conexion->prepare("SELECT id_usuario, nombre, apellido, identificacion FROM usuario WHERE identificacion = ? AND fk_rol = 2 LIMIT 1");
            $q->execute([$identInstr]);
            $cacheInstructores[$identInstr] = $q->fetch();
        }
        $instructor = $cacheInstructores[$identInstr];
    }
    if (!$instructor) {
        $estado = 'ERROR';
        $errores[] = "Instructor no encontrado o no tiene rol Instructor (identificacion: $identInstr)";
    }

    // ficha
    $ficha = null;
    $fichaId = null;
    $pendienteFicha = false;
    $fichasDelInstructor = [];

    if ($instructor) {
        // obtener fichas del instructor para selector si hace falta
        $q = $conexion->prepare("SELECT id_ficha, nombre_programa FROM ficha WHERE fk_usuario = ? ORDER BY id_ficha");
        $q->execute([$instructor['id_usuario']]);
        $fichasDelInstructor = $q->fetchAll();
    }

    $tieneIdFicha = ($idFichaRaw !== '' && $idFichaRaw !== null);
    $tieneNumFicha = ($numFichaRaw !== '' && $numFichaRaw !== null);

    if ($tieneIdFicha) {
        $idFicha = (int)$idFichaRaw;
        if (!isset($cacheFichas[$idFicha])) {
            $q = $conexion->prepare("SELECT id_ficha, nombre_programa, fk_usuario FROM ficha WHERE id_ficha = ? LIMIT 1");
            $q->execute([$idFicha]);
            $cacheFichas[$idFicha] = $q->fetch();
        }
        $ficha = $cacheFichas[$idFicha] ?? null;
    } elseif ($tieneNumFicha) {
        // verificar si existe columna numero_ficha en ficha
        $q = $conexion->query("SHOW COLUMNS FROM ficha LIKE 'numero_ficha'");
        if ($q->fetch()) {
            $numFicha = trim((string)$numFichaRaw);
            if (!isset($cacheFichas['num_' . $numFicha])) {
                $q = $conexion->prepare("SELECT id_ficha, nombre_programa, fk_usuario FROM ficha WHERE numero_ficha = ? LIMIT 1");
                $q->execute([$numFicha]);
                $cacheFichas['num_' . $numFicha] = $q->fetch();
            }
            $ficha = $cacheFichas['num_' . $numFicha] ?? null;
        } else {
            $advertencias[] = "numero_ficha no esta disponible en la base actual; seleccione ficha manualmente";
            $pendienteFicha = true;
        }
    } else {
        $pendienteFicha = true;
    }

    if ($pendienteFicha) {
        $estado = 'PENDIENTE_FICHA';
    } elseif (!$ficha) {
        $estado = 'ERROR';
        $errores[] = "Ficha no encontrada" . ($tieneIdFicha ? " (id_ficha: $idFichaRaw)" : " (numero_ficha: $numFichaRaw)");
    } elseif ($instructor && $ficha && (int)$ficha['fk_usuario'] !== (int)$instructor['id_usuario']) {
        $estado = 'ERROR';
        $errores[] = "La ficha #{$ficha['id_ficha']} pertenece a otro instructor (fk_usuario={$ficha['fk_usuario']}, esperado={$instructor['id_usuario']})";
    }

    // ambiente - advertencia mientras no exista campo
    $advertenciaAmbiente = false;
    if ($ambiente !== '') {
        $advertenciaAmbiente = true;
        $advertencias[] = "Validacion de ambiente pendiente: ambiente_principal aun no esta configurado";
    }

    // duplicado dentro del archivo
    $claveArchivo = "";
    if ($ficha && $dia && $horaIni) {
        $claveArchivo = $ficha['id_ficha'] . '|' . $dia . '|' . $horaIni;
        if (isset($vistasEnArchivo[$claveArchivo])) {
            $estado = 'ERROR';
            $errores[] = "Fila duplicada en el archivo (misma ficha, dia y hora_inicio)";
        } else {
            $vistasEnArchivo[$claveArchivo] = true;
        }
    }

    // duplicado contra bd y cruces
    $fichaId = $ficha ? (int)$ficha['id_ficha'] : 0;
    $instrId = $instructor ? (int)$instructor['id_usuario'] : 0;

    if ($fichaId && $dia && $horaIni && $horaFin) {
        // duplicado identico en bd
        $claveBD = $fichaId . '|' . $dia . '|' . $horaIni . '|' . $horaFin;
        if (isset($cacheHorariosExistentes[$claveBD])) {
            if ($estado === 'VALIDA') $estado = 'ADVERTENCIA';
            $advertencias[] = "Duplicado: se omitira en una futura importacion (horario identico ya existe en BD)";
        }

        // cruces ficha (bd)
        $fichaDia = $fichaId . '|' . $dia;
        if (isset($horariosPorFichaDia[$fichaDia])) {
            foreach ($horariosPorFichaDia[$fichaDia] as $hExist) {
                $hIni = substr($hExist['entrada'], 0, 5);
                $hFin = substr($hExist['salida'], 0, 5);
                if (HorarioValidator::haySolapamiento($horaIni, $horaFin, $hIni, $hFin)) {
                    $estado = 'ERROR';
                    $errores[] = "Cruce con horario existente de la misma ficha #{$fichaId} el $dia ($hIni-$hFin)";
                    break;
                }
            }
        }

        // cruces instructor (bd)
        if ($instrId) {
            $instDia = $instrId . '|' . $dia;
            if (isset($horariosPorInstructorDia[$instDia])) {
                foreach ($horariosPorInstructorDia[$instDia] as $hExist) {
                    if ((int)$hExist['fk_ficha'] === $fichaId) continue; // ya validado arriba
                    $hIni = substr($hExist['entrada'], 0, 5);
                    $hFin = substr($hExist['salida'], 0, 5);
                    if (HorarioValidator::haySolapamiento($horaIni, $horaFin, $hIni, $hFin)) {
                        $estado = 'ERROR';
                        $errores[] = "Cruce con horario existente de otra ficha del mismo instructor el $dia ($hIni-$hFin, ficha #{$hExist['fk_ficha']})";
                        break;
                    }
                }
            }
        }

        // cruces dentro del archivo (mismo instructor)
        if ($instrId) {
            foreach ($preview as $p) {
                if ($p['estado'] === 'ERROR') continue;
                if ($p['instructor_id'] === $instrId && $p['dia'] === $dia) {
                    if (HorarioValidator::haySolapamiento($horaIni, $horaFin, $p['hora_inicio'], $p['hora_fin'])) {
                        $estado = 'ERROR';
                        $errores[] = "Cruce dentro del archivo con otra fila del mismo instructor el $dia ({$p['hora_inicio']}-{$p['hora_fin']})";
                        break;
                    }
                }
            }
        }
    }

    // consolidar detalles
    if (!empty($advertencias)) {
        $detalles = array_merge($detalles, $advertencias);
    }
    if (!empty($errores)) {
        $detalles = array_merge($detalles, $errores);
    }

    $preview[] = [
        'fila' => $numFila,
        'dia' => $dia,
        'hora_inicio' => $horaIni,
        'hora_fin' => $horaFin,
        'identificacion_instructor' => $identInstr,
        'nombre_instructor' => $nombreInstr,
        'instructor_id' => $instrId,
        'instructor_nombre' => $instructor ? $instructor['nombre'] . ' ' . $instructor['apellido'] : '',
        'ficha_id' => $fichaId,
        'ficha_nombre' => $ficha ? $ficha['nombre_programa'] : '',
        'pendiente_ficha' => $pendienteFicha,
        'fichas_disponibles' => $fichasDelInstructor,
        'ambiente' => $ambiente,
        'programa' => $programa,
        'area_o_competencia' => $area,
        'observacion' => $obs,
        'estado' => $estado,
        'detalle' => implode('; ', $detalles),
        'seleccionable' => in_array($estado, ['VALIDA', 'ADVERTENCIA', 'PENDIENTE_FICHA'])
    ];
}

// resumen
$resumen = [
    'total_filas' => count($filasDatos),
    'filas_vacias' => $filasVacias,
    'filas_procesadas' => $filasProcesadas,
    'validas' => 0,
    'advertencias' => 0,
    'pendientes' => 0,
    'errores' => 0
];

foreach ($preview as $p) {
    switch ($p['estado']) {
        case 'VALIDA': $resumen['validas']++; break;
        case 'ADVERTENCIA': $resumen['advertencias']++; break;
        case 'PENDIENTE_FICHA': $resumen['pendientes']++; break;
        case 'ERROR': $resumen['errores']++; break;
    }
}

// guardar en sesion
$_SESSION['import_preview'] = [
    'filas' => $preview,
    'resumen' => $resumen,
    'timestamp' => time()
];

// redirigir a previsualizacion
header('Location: ../views/admin/horarios/importar_preview.php');
exit;