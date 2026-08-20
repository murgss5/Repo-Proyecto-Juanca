<?php
/**
 * ============================================================
 * DATOS DE DEMOSTRACIÓN (PROTOTIPO VISUAL)
 * Sistema de Control de Ingreso de Aprendices - SENA
 * ------------------------------------------------------------
 * ¡IMPORTANTE! Los arreglos de este archivo son datos de
 * EJEMPLO 100% estáticos. NO provienen de la base de datos.
 *
 * Cuando se desarrolle la lógica real (modelos + MySQL),
 * este archivo podrá eliminarse y reemplazarse por consultas.
 * ============================================================
 */

$fichas = [
    ['id' => 1,  'numero' => '2693854', 'programa' => 'Tecnólogo en Análisis y Desarrollo de Software', 'jornada' => 'Diurna',   'instructor' => 'Carlos Gómez',   'aprendices' => 28, 'estado' => 'Activa'],
    ['id' => 2,  'numero' => '2743910', 'programa' => 'Técnico en Sistemas',                          'jornada' => 'Diurna',   'instructor' => 'Laura Martínez', 'aprendices' => 24, 'estado' => 'Activa'],
    ['id' => 3,  'numero' => '2780422', 'programa' => 'Tecnólogo en Gestión Administrativa',          'jornada' => 'Nocturna', 'instructor' => 'Andrés Pérez',   'aprendices' => 21, 'estado' => 'Activa'],
    ['id' => 4,  'numero' => '2814567', 'programa' => 'Técnico en Contabilización de Operaciones',    'jornada' => 'Diurna',   'instructor' => 'María Torres',   'aprendices' => 19, 'estado' => 'Activa'],
    ['id' => 5,  'numero' => '2653011', 'programa' => 'Tecnólogo en Producción de Multimedia',        'jornada' => 'Nocturna', 'instructor' => 'Jorge Ramírez', 'aprendices' => 16, 'estado' => 'Inactiva'],
    ['id' => 6,  'numero' => '2890345', 'programa' => 'Técnico en Programación de Software',          'jornada' => 'Diurna',   'instructor' => 'Laura Martínez', 'aprendices' => 22, 'estado' => 'Activa'],
];

$instructores = [
    ['id' => 1, 'documento' => '1032504321', 'nombre' => 'Carlos Gómez',   'correo' => 'cgomez@sena.edu.co',     'telefono' => '3101234567', 'especialidad' => 'Desarrollo de Software', 'fichas' => 2, 'estado' => 'Activo'],
    ['id' => 2, 'documento' => '1011245678', 'nombre' => 'Laura Martínez', 'correo' => 'lmartinez@sena.edu.co',  'telefono' => '3209876543', 'especialidad' => 'Bases de Datos',        'fichas' => 2, 'estado' => 'Activo'],
    ['id' => 3, 'documento' => '1022987654', 'nombre' => 'Andrés Pérez',   'correo' => 'aperez@sena.edu.co',     'telefono' => '3114567890', 'especialidad' => 'Gestión Administrativa', 'fichas' => 1, 'estado' => 'Activo'],
    ['id' => 4, 'documento' => '1065123456', 'nombre' => 'María Torres',   'correo' => 'mtorres@sena.edu.co',    'telefono' => '3001112233', 'especialidad' => 'Contabilidad',          'fichas' => 1, 'estado' => 'Activo'],
    ['id' => 5, 'documento' => '1034578901', 'nombre' => 'Jorge Ramírez', 'correo' => 'jramirez@sena.edu.co',   'telefono' => '3159876541', 'especialidad' => 'Producción Multimedia', 'fichas' => 1, 'estado' => 'Inactivo'],
];

$aprendices = [
    ['id' => 1,  'documento' => '1002345678', 'nombre' => 'Juan David Pérez',        'correo' => 'jdperez@misena.edu.co',       'telefono' => '3001112233', 'ficha' => '2693854', 'rfid' => 'RF-2026-001', 'estado' => 'Activo'],
    ['id' => 2,  'documento' => '1010987654', 'nombre' => 'Ana Sofía Ramírez',       'correo' => 'asramirez@misena.edu.co',     'telefono' => '3102223344', 'ficha' => '2693854', 'rfid' => 'RF-2026-002', 'estado' => 'Activo'],
    ['id' => 3,  'documento' => '1023456789', 'nombre' => 'Carlos Andrés López',     'correo' => 'calopez@misena.edu.co',       'telefono' => '3203334455', 'ficha' => '2693854', 'rfid' => 'RF-2026-003', 'estado' => 'Activo'],
    ['id' => 4,  'documento' => '1034567890', 'nombre' => 'Daniela Fernanda Torres', 'correo' => 'dftorres@misena.edu.co',      'telefono' => '3114445566', 'ficha' => '2743910', 'rfid' => 'RF-2026-004', 'estado' => 'Activo'],
    ['id' => 5,  'documento' => '1045678901', 'nombre' => 'Esteban Morales',         'correo' => 'emorales@misena.edu.co',      'telefono' => '3125556677', 'ficha' => '2743910', 'rfid' => 'RF-2026-005', 'estado' => 'Suspendido'],
    ['id' => 6,  'documento' => '1056789012', 'nombre' => 'Fernanda Castro',         'correo' => 'fcastro@misena.edu.co',       'telefono' => '3136667788', 'ficha' => '2780422', 'rfid' => 'RF-2026-006', 'estado' => 'Activo'],
    ['id' => 7,  'documento' => '1067890123', 'nombre' => 'Gabriel Suárez',          'correo' => 'gsuarez@misena.edu.co',       'telefono' => '3147778899', 'ficha' => '2780422', 'rfid' => 'RF-2026-007', 'estado' => 'Activo'],
    ['id' => 8,  'documento' => '1078901234', 'nombre' => 'Helena Gómez',            'correo' => 'hgommez@misena.edu.co',       'telefono' => '3158889900', 'ficha' => '2814567', 'rfid' => 'RF-2026-008', 'estado' => 'Retirado'],
    ['id' => 9,  'documento' => '1089012345', 'nombre' => 'Iván Rodríguez',          'correo' => 'irodriguez@misena.edu.co',    'telefono' => '3169990011', 'ficha' => '2814567', 'rfid' => 'RF-2026-009', 'estado' => 'Activo'],
    ['id' => 10, 'documento' => '1090123456', 'nombre' => 'Juliana Rincón',          'correo' => 'jrincon@misena.edu.co',       'telefono' => '3170001122', 'ficha' => '2890345', 'rfid' => 'RF-2026-010', 'estado' => 'Activo'],
    ['id' => 11, 'documento' => '1101234567', 'nombre' => 'Kevin Pineda',            'correo' => 'kpineda@misena.edu.co',       'telefono' => '3181112233', 'ficha' => '2890345', 'rfid' => 'RF-2026-011', 'estado' => 'Activo'],
    ['id' => 12, 'documento' => '1112345678', 'nombre' => 'Laura Valentina Ortiz',   'correo' => 'lvortiz@misena.edu.co',       'telefono' => '3192223344', 'ficha' => '2693854', 'rfid' => 'RF-2026-012', 'estado' => 'Activo'],
];

$asistencias = [
    ['id' => 1,  'fecha' => '2026-08-20', 'aprendiz' => 'Juan David Pérez',        'ficha' => '2693854', 'entrada' => '06:55', 'salida' => '12:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-001'],
    ['id' => 2,  'fecha' => '2026-08-20', 'aprendiz' => 'Ana Sofía Ramírez',       'ficha' => '2693854', 'entrada' => '07:20', 'salida' => '12:30', 'estado' => 'Retardo',         'minutos_retardo' => 20, 'rfid' => 'RF-2026-002'],
    ['id' => 3,  'fecha' => '2026-08-20', 'aprendiz' => 'Carlos Andrés López',     'ficha' => '2693854', 'entrada' => '06:58', 'salida' => '12:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-003'],
    ['id' => 4,  'fecha' => '2026-08-20', 'aprendiz' => 'Laura Valentina Ortiz',   'ficha' => '2693854', 'entrada' => '10:30', 'salida' => '12:30', 'estado' => 'Salida temprana', 'minutos_retardo' => 0,  'rfid' => 'RF-2026-012'],
    ['id' => 5,  'fecha' => '2026-08-20', 'aprendiz' => 'Daniela Fernanda Torres', 'ficha' => '2743910', 'entrada' => '07:05', 'salida' => '12:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-004'],
    ['id' => 6,  'fecha' => '2026-08-20', 'aprendiz' => 'Esteban Morales',         'ficha' => '2743910', 'entrada' => '--',    'salida' => '--',    'estado' => 'Inasistencia',    'minutos_retardo' => 0,  'rfid' => 'RF-2026-005'],
    ['id' => 7,  'fecha' => '2026-08-20', 'aprendiz' => 'Fernanda Castro',         'ficha' => '2780422', 'entrada' => '18:05', 'salida' => '21:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-006'],
    ['id' => 8,  'fecha' => '2026-08-20', 'aprendiz' => 'Gabriel Suárez',          'ficha' => '2780422', 'entrada' => '18:22', 'salida' => '21:30', 'estado' => 'Retardo',         'minutos_retardo' => 17, 'rfid' => 'RF-2026-007'],
    ['id' => 9,  'fecha' => '2026-08-20', 'aprendiz' => 'Iván Rodríguez',          'ficha' => '2814567', 'entrada' => '07:00', 'salida' => '12:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-009'],
    ['id' => 10, 'fecha' => '2026-08-20', 'aprendiz' => 'Juliana Rincón',          'ficha' => '2890345', 'entrada' => '06:52', 'salida' => '12:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-010'],
    ['id' => 11, 'fecha' => '2026-08-19', 'aprendiz' => 'Kevin Pineda',            'ficha' => '2890345', 'entrada' => '07:30', 'salida' => '12:30', 'estado' => 'Retardo',         'minutos_retardo' => 25, 'rfid' => 'RF-2026-011'],
    ['id' => 12, 'fecha' => '2026-08-19', 'aprendiz' => 'Juliana Rincón',          'ficha' => '2890345', 'entrada' => '06:50', 'salida' => '12:30', 'estado' => 'Presente',       'minutos_retardo' => 0,  'rfid' => 'RF-2026-010'],
    ['id' => 13, 'fecha' => '2026-08-19', 'aprendiz' => 'Juan David Pérez',        'ficha' => '2693854', 'entrada' => '07:12', 'salida' => '12:30', 'estado' => 'Retardo',         'minutos_retardo' => 12, 'rfid' => 'RF-2026-001'],
    ['id' => 14, 'fecha' => '2026-08-19', 'aprendiz' => 'Helena Gómez',            'ficha' => '2814567', 'entrada' => '--',    'salida' => '--',    'estado' => 'Inasistencia',    'minutos_retardo' => 0,  'rfid' => 'RF-2026-008'],
];

$excusas = [
    ['id' => 1,  'aprendiz' => 'Carlos Andrés López',     'ficha' => '2693854', 'fecha_inasistencia' => '2026-08-18', 'archivo' => 'excusa_carlos_lopez.pdf', 'fecha_envio' => '2026-08-19', 'estado' => 'Pendiente', 'observaciones' => ''],
    ['id' => 2,  'aprendiz' => 'Esteban Morales',         'ficha' => '2743910', 'fecha_inasistencia' => '2026-08-18', 'archivo' => 'excusa_esteban_morales.jpg', 'fecha_envio' => '2026-08-19', 'estado' => 'Aprobada',   'observaciones' => 'Cita médica verificada correctamente.'],
    ['id' => 3,  'aprendiz' => 'Helena Gómez',            'ficha' => '2814567', 'fecha_inasistencia' => '2026-08-17', 'archivo' => 'excusa_helena_gomez.pdf', 'fecha_envio' => '2026-08-18', 'estado' => 'Rechazada',  'observaciones' => 'El documento no es legible, se solicita enviarlo nuevamente.'],
    ['id' => 4,  'aprendiz' => 'Daniela Fernanda Torres', 'ficha' => '2743910', 'fecha_inasistencia' => '2026-08-15', 'archivo' => 'excusa_daniela_torres.pdf', 'fecha_envio' => '2026-08-16', 'estado' => 'Aprobada',   'observaciones' => 'Aprobada por incapacidad médica.'],
    ['id' => 5,  'aprendiz' => 'Kevin Pineda',            'ficha' => '2890345', 'fecha_inasistencia' => '2026-08-14', 'archivo' => 'excusa_kevin_pineda.jpg', 'fecha_envio' => '2026-08-15', 'estado' => 'Pendiente', 'observaciones' => ''],
    ['id' => 6,  'aprendiz' => 'Laura Valentina Ortiz',   'ficha' => '2693854', 'fecha_inasistencia' => '2026-08-12', 'archivo' => 'excusa_laura_ortiz.pdf', 'fecha_envio' => '2026-08-13', 'estado' => 'Aprobada',   'observaciones' => 'Excusa aceptada por calamidad familiar.'],
];

$horarios = [
    ['id' => 1, 'ficha' => '2693854', 'dia' => 'Lunes',     'hora_inicio' => '07:00', 'hora_fin' => '12:30', 'ambiente' => 'Aula 204', 'instructor' => 'Carlos Gómez'],
    ['id' => 2, 'ficha' => '2693854', 'dia' => 'Martes',    'hora_inicio' => '07:00', 'hora_fin' => '12:30', 'ambiente' => 'Sala 3',   'instructor' => 'Laura Martínez'],
    ['id' => 3, 'ficha' => '2693854', 'dia' => 'Miércoles', 'hora_inicio' => '07:00', 'hora_fin' => '12:30', 'ambiente' => 'Aula 204', 'instructor' => 'Carlos Gómez'],
    ['id' => 4, 'ficha' => '2743910', 'dia' => 'Lunes',     'hora_inicio' => '07:00', 'hora_fin' => '12:30', 'ambiente' => 'Sala 5',   'instructor' => 'Laura Martínez'],
    ['id' => 5, 'ficha' => '2743910', 'dia' => 'Jueves',    'hora_inicio' => '07:00', 'hora_fin' => '12:30', 'ambiente' => 'Sala 5',   'instructor' => 'Laura Martínez'],
    ['id' => 6, 'ficha' => '2780422', 'dia' => 'Lunes',     'hora_inicio' => '18:00', 'hora_fin' => '21:30', 'ambiente' => 'Aula 110', 'instructor' => 'Andrés Pérez'],
    ['id' => 7, 'ficha' => '2780422', 'dia' => 'Miércoles', 'hora_inicio' => '18:00', 'hora_fin' => '21:30', 'ambiente' => 'Aula 110', 'instructor' => 'Andrés Pérez'],
    ['id' => 8, 'ficha' => '2814567', 'dia' => 'Viernes',   'hora_inicio' => '07:00', 'hora_fin' => '12:30', 'ambiente' => 'Aula 12',  'instructor' => 'María Torres'],
];

$ultimosIngresos = [
    ['hora' => '07:20', 'aprendiz' => 'Ana Sofía Ramírez',       'ficha' => '2693854', 'rfid' => 'RF-2026-002'],
    ['hora' => '07:05', 'aprendiz' => 'Daniela Fernanda Torres', 'ficha' => '2743910', 'rfid' => 'RF-2026-004'],
    ['hora' => '06:58', 'aprendiz' => 'Carlos Andrés López',     'ficha' => '2693854', 'rfid' => 'RF-2026-003'],
    ['hora' => '06:55', 'aprendiz' => 'Juan David Pérez',        'ficha' => '2693854', 'rfid' => 'RF-2026-001'],
    ['hora' => '06:52', 'aprendiz' => 'Juliana Rincón',          'ficha' => '2890345', 'rfid' => 'RF-2026-010'],
];

$retardosRecientes = [
    ['hora' => '07:20', 'aprendiz' => 'Ana Sofía Ramírez',     'ficha' => '2693854', 'minutos' => 20],
    ['hora' => '18:22', 'aprendiz' => 'Gabriel Suárez',        'ficha' => '2780422', 'minutos' => 17],
    ['hora' => '07:12', 'aprendiz' => 'Juan David Pérez',      'ficha' => '2693854', 'minutos' => 12],
];

/**
 * Resumen para la gráfica de asistencia de la semana (datos de ejemplo)
 * Claves: asistencias_puntuales, retardos, inasistencias, salidas_tempranas
 */
$asistenciaSemana = [
    ['dia' => 'Lunes',     'presentes' => 32, 'retardos' => 4,  'inasistencias' => 3],
    ['dia' => 'Martes',    'presentes' => 30, 'retardos' => 6,  'inasistencias' => 5],
    ['dia' => 'Miércoles', 'presentes' => 34, 'retardos' => 3,  'inasistencias' => 2],
    ['dia' => 'Jueves',    'presentes' => 28, 'retardos' => 8,  'inasistencias' => 6],
    ['dia' => 'Viernes',   'presentes' => 31, 'retardos' => 5,  'inasistencias' => 4],
    ['dia' => 'Sábado',    'presentes' => 25, 'retardos' => 9,  'inasistencias' => 7],
];

/**
 * Historial personal del aprendiz (para el panel del aprendiz)
 */
$historialPersonal = [
    ['fecha' => '2026-08-20', 'entrada' => '06:55', 'salida' => '12:30', 'estado' => 'Presente'],
    ['fecha' => '2026-08-19', 'entrada' => '07:12', 'salida' => '12:30', 'estado' => 'Retardo'],
    ['fecha' => '2026-08-18', 'entrada' => '06:58', 'salida' => '12:30', 'estado' => 'Presente'],
    ['fecha' => '2026-08-15', 'entrada' => '--',    'salida' => '--',    'estado' => 'Inasistencia'],
    ['fecha' => '2026-08-14', 'entrada' => '10:30', 'salida' => '12:30', 'estado' => 'Salida temprana'],
    ['fecha' => '2026-08-13', 'entrada' => '06:50', 'salida' => '12:30', 'estado' => 'Presente'],
];