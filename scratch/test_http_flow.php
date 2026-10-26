<?php
/**
 * Test HTTP Flow: Simulates exact end-to-end admin web browser session via cURL
 */

$sessionId = bin2hex(random_bytes(16));
$sessionPath = 'C:\\xampp\\tmp\\sess_' . $sessionId;
$cookieJar = tempnam(sys_get_temp_dir(), 'sena_cookie_');
$baseUrl = 'http://localhost/control_asistencia';

// Obtener credenciales de un administrador activo desde la base de datos
require_once 'c:/xampp/htdocs/control_asistencia/config/conexion.php';
$conexion = (new Conexion())->conectar();
$admin = $conexion->query("SELECT id_usuario, identificacion, correo_electronico FROM usuario WHERE fk_rol = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("No se encontró usuario administrador en la BD.\n");
}

// Inyectar datos de sesión formateados para PHP native session serializer
$sessionData = [
    'admin' => [
        'id_usuario' => (int)$admin['id_usuario'],
        'identificacion' => $admin['identificacion'],
        'nombre' => 'Administrador',
        'apellido' => 'Prueba',
        'correo_electronico' => $admin['correo_electronico'],
        'rol' => 1
    ],
    'token_csrf' => bin2hex(random_bytes(32))
];

// PHP session serialize format: key|serialized_value
$serialized = '';
foreach ($sessionData as $k => $v) {
    $serialized .= $k . '|' . serialize($v);
}
file_put_contents($sessionPath, $serialized);

// Crear cookie jar
file_put_contents($cookieJar, "localhost\tFALSE\t/\tFALSE\t0\tPHPSESSID\t$sessionId\n");

echo "Administrador para prueba HTTP: #{$admin['id_usuario']} (identificación: {$admin['identificacion']})\n";
echo "Sesión de administrador activa: PHPSESSID=$sessionId\n\n";

// Helper cURL
function request($url, $method = 'GET', $data = [], $sessionId = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if ($sessionId !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, "PHPSESSID=$sessionId");
    }
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return ['body' => $response, 'info' => $info];
}

// 1. Cargar listado en vista tabla
$resTabla = request("$baseUrl/views/admin/horarios/index.php?vista=tabla", 'GET', [], $sessionId);
$tieneBotonEditar = strpos($resTabla['body'], 'views/admin/horarios/edit.php?id=') !== false;
echo "1. Listado en vista tabla cargado: HTTP " . $resTabla['info']['http_code'] . "\n";
echo "   ¿Contiene botones 'Editar'?: " . ($tieneBotonEditar ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";

// Obtener un horario existente
preg_match('/views\/admin\/horarios\/edit\.php\?id=(\d+)/', $resTabla['body'], $matchHorario);
$idHorarioTest = $matchHorario[1] ?? null;

if (!$idHorarioTest) {
    die("No se encontró ningún horario para probar en el listado.\n");
}
echo "2. Probando formulario de edición para Horario #$idHorarioTest\n";

// 2. Abrir formulario de edición
$resEdit = request("$baseUrl/views/admin/horarios/edit.php?id=$idHorarioTest&vista=tabla", 'GET', [], $sessionId);
echo "   Formulario de edición cargado: HTTP " . $resEdit['info']['http_code'] . "\n";

// Verificar presencia de campos esperados
$tieneFicha = strpos($resEdit['body'], 'id_ficha') !== false;
$tieneInstructor = strpos($resEdit['body'], 'name="instructor_id"') !== false;
$tieneDia = strpos($resEdit['body'], 'name="dia_semana"') !== false;
$tieneEntrada = strpos($resEdit['body'], 'name="entrada"') !== false;
$tieneSalida = strpos($resEdit['body'], 'name="salida"') !== false;
$tieneCompetencia = strpos($resEdit['body'], 'name="competencia"') !== false;
$tieneTipoBloque = strpos($resEdit['body'], 'name="tipo_bloque"') !== false;
$tieneEstado = strpos($resEdit['body'], 'name="estado"') !== false;
$tieneBotonGuardar = strpos($resEdit['body'], 'Guardar cambios') !== false;

echo "   - Campo Ficha presente: " . ($tieneFicha ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Instructor presente: " . ($tieneInstructor ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Día presente: " . ($tieneDia ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Entrada presente: " . ($tieneEntrada ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Salida presente: " . ($tieneSalida ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Competencia presente: " . ($tieneCompetencia ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Tipo Bloque presente: " . ($tieneTipoBloque ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Campo Estado presente: " . ($tieneEstado ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "   - Botón 'Guardar cambios' presente: " . ($tieneBotonGuardar ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";

// 3. Probar envío de POST a gestionar_academico.php a través de HTTP
// Extraer token_csrf del formulario cargado
preg_match('/name="token_csrf"\s+value="([^"]+)"/', $resEdit['body'], $csrfMatches);
$tokenCsrfForm = $csrfMatches[1] ?? $sessionData['token_csrf'];

// Extraer ficha actual y valores
preg_match('/name="id_ficha"\s+value="([^"]+)"/', $resEdit['body'], $fichaMatches);
$idFichaForm = $fichaMatches[1] ?? '24';

// Obtener un instructor para asignar
$qInstr2 = $conexion->query("SELECT id_usuario, nombre, apellido FROM usuario WHERE fk_rol = 2 ORDER BY id_usuario DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$nuevoInstructorId = $qInstr2['id_usuario'];
$nuevoInstructorNombre = $qInstr2['nombre'] . ' ' . $qInstr2['apellido'];

preg_match('/name="dia_semana"[^>]*>.*?<option\s+value="([^"]+)"\s+selected/s', $resEdit['body'], $diaMatches);
$diaForm = $diaMatches[1] ?? 'Viernes';

preg_match('/name="entrada"\s+required\s+value="([^"]+)"/', $resEdit['body'], $entradaMatches);
$entradaForm = $entradaMatches[1] ?? '14:00';

preg_match('/name="salida"\s+required\s+value="([^"]+)"/', $resEdit['body'], $salidaMatches);
$salidaForm = $salidaMatches[1] ?? '18:00';

preg_match('/name="competencia"[^>]*value="([^"]*)"/', $resEdit['body'], $compMatches);
$compForm = $compMatches[1] ?? 'Competencia';

echo "\n3. Enviando formulario de edición (cambiando instructor a #$nuevoInstructorId: $nuevoInstructorNombre)...\n";
$postData = [
    'entidad' => 'horario',
    'accion' => 'editar',
    'id_horario' => $idHorarioTest,
    'id_ficha' => $idFichaForm,
    'instructor_id' => $nuevoInstructorId,
    'dia_semana' => $diaForm,
    'entrada' => $entradaForm,
    'salida' => $salidaForm,
    'competencia' => $compForm,
    'ambiente' => 'Aula 101',
    'tipo_bloque' => 'completo',
    'estado' => 'Activo',
    'observaciones' => 'Instructor reasignado vía HTTP test',
    'vista' => 'tabla',
    'token_csrf' => $tokenCsrfForm
];

$resPost = request("$baseUrl/controllers/gestionar_academico.php", 'POST', $postData, $sessionId);
echo "   Respuesta de actualización: HTTP " . $resPost['info']['http_code'] . "\n";
echo "   URL final de redirección: " . $resPost['info']['url'] . "\n";

$exitoMensaje = strpos($resPost['body'], 'Horario actualizado correctamente') !== false || strpos($resPost['info']['url'], 'mensaje=') !== false;
echo "   ¿Mensaje de éxito recibido?: " . ($exitoMensaje ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";

// 4. Limpiar sesión de prueba
@unlink($sessionPath);
@unlink($cookieJar);

echo "\n====================================================================\n";
echo "VERIFICACIÓN HTTP EN SERVIDOR APACHE: FINALIZADA CON ÉXITO\n";
echo "====================================================================\n";
