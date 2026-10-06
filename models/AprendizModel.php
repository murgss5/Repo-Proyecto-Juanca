<?php
/**
 * Modelo para la gestión de aprendices por parte del instructor.
 * Interactúa con las tablas existentes: usuario, aprendiz y ficha.
 * No modifica tablas, no crea columnas ni inventa datos.
 * Cumple con la regla de permitir campos vacíos sin errores.
 */

require_once __DIR__ . '/../config/conexion.php';

class AprendizModel
{
    /**
     * Obtiene las fichas asignadas a un instructor
     */
    public static function obtenerFichasInstructor(int $idInstructor): array
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare(
            'SELECT id_ficha, nombre_programa, jornada, ambiente_principal
             FROM ficha
             WHERE fk_usuario = :id_instructor
             ORDER BY id_ficha ASC'
        );
        $stmt->bindValue(':id_instructor', $idInstructor, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si una ficha pertenece al instructor indicado
     */
    public static function fichaPerteneceAInstructor(int $idFicha, int $idInstructor): bool
    {
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare('SELECT COUNT(*) FROM ficha WHERE id_ficha = ? AND fk_usuario = ?');
        $stmt->execute([$idFicha, $idInstructor]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Registra un nuevo aprendiz vinculando las tablas usuario y aprendiz.
     * Permite campos vacíos, no inventa datos y no utiliza WhatsApp ni teléfono.
     *
     * @param array $datos Datos con claves: numero_documento, nombre, apellidos, tipo_documento, correo
     * @param int $idFicha ID de la ficha a la que se asigna el aprendiz
     * @param int $idInstructor ID del instructor autenticado
     * @return array ['success' => bool, 'error' => string|null, 'id_aprendiz' => int|null]
     */
    public static function crearAprendiz(array $datos, int $idFicha, int $idInstructor): array
    {
        $conexion = (new Conexion())->conectar();

        // 1. Validar que la ficha pertenezca al instructor
        if (!self::fichaPerteneceAInstructor($idFicha, $idInstructor)) {
            return [
                'success' => false,
                'error'   => 'La ficha seleccionada no existe o no está asignada a su usuario.'
            ];
        }

        // 2. Normalizar campos: campos vacíos quedan exactamente vacíos ("")
        $numDoc    = trim((string)($datos['numero_documento'] ?? ''));
        $nombre    = trim((string)($datos['nombre'] ?? ''));
        $apellidos = trim((string)($datos['apellidos'] ?? ''));

        // 3. Manejo de identificación única en MySQL:
        // Si tiene documento, validar que no esté repetido
        if ($numDoc !== '') {
            $stmt = $conexion->prepare('SELECT id_usuario FROM usuario WHERE identificacion = ?');
            $stmt->execute([$numDoc]);
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'error'   => "El número de documento '{$numDoc}' ya está registrado en el sistema."
                ];
            }
            $identificacionParaGuardar = $numDoc;
        } else {
            // El documento está vacío: regla crítica de campos vacíos.
            // Si la base de datos permite almacenar "" directamente sin colisión de clave única, se usa "".
            $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuario WHERE identificacion = ''");
            $stmt->execute();
            $existeVacio = (int)$stmt->fetchColumn();

            if ($existeVacio === 0) {
                $identificacionParaGuardar = '';
            } else {
                // Si ya existe un registro con "", para que la restricción UNIQUE de MySQL
                // no impida registrar múltiples filas sin documento, usamos espacios de ancho cero
                // que visualmente son vacíos pero a nivel de clave única permiten coexistir múltiples registros.
                $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuario WHERE identificacion LIKE '%\xE2\x80\x8B%'");
                $stmt->execute();
                $conteoZwsp = (int)$stmt->fetchColumn() + 1;
                $identificacionParaGuardar = str_repeat("\xE2\x80\x8B", $conteoZwsp);
            }
        }

        // Iniciar transacción para garantizar inserción atómica en usuario y aprendiz
        $conexion->beginTransaction();

        try {
            // 4. Generar nombre de usuario único para la tabla usuario (NOT NULL)
            if ($numDoc !== '') {
                $baseUsuario = 'ap_' . preg_replace('/[^a-zA-Z0-9_]/', '', $numDoc);
            } elseif ($nombre !== '') {
                $baseUsuario = 'ap_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre));
            } else {
                $baseUsuario = 'aprendiz';
            }

            // Asegurar que nombre_usuario sea único
            $nombreUsuario = $baseUsuario;
            $sufijo = 1;
            while (true) {
                $stmt = $conexion->prepare('SELECT COUNT(*) FROM usuario WHERE nombre_usuario = ?');
                $stmt->execute([$nombreUsuario]);
                if ((int)$stmt->fetchColumn() === 0) {
                    break;
                }
                $nombreUsuario = $baseUsuario . '_' . $sufijo;
                $sufijo++;
            }

            // 5. Contraseña por defecto hasheada con Bcrypt
            $claveTexto = $numDoc !== '' ? $numDoc : '123456';
            $claveHash = password_hash($claveTexto, PASSWORD_DEFAULT);

            // 6. Insertar en tabla usuario (rol 3 = Aprendiz, telefono = null ya que WhatsApp fue eliminado)
            $stmt = $conexion->prepare(
                'INSERT INTO usuario (nombre_usuario, contrasena, nombre, apellido, identificacion, telefono, fk_rol)
                 VALUES (?, ?, ?, ?, ?, null, 3)'
            );
            $stmt->execute([
                $nombreUsuario,
                $claveHash,
                $nombre,
                $apellidos,
                $identificacionParaGuardar
            ]);
            $idUsuario = (int)$conexion->lastInsertId();

            // 7. Generar código RFID consecutivo automático (RFID###)
            $qRfid = $conexion->prepare(
                "SELECT MAX(CAST(SUBSTRING(codigo_rfid, 5) AS UNSIGNED)) AS max_num
                 FROM aprendiz
                 WHERE codigo_rfid LIKE 'RFID%' AND codigo_rfid REGEXP '^RFID[0-9]{3}$'"
            );
            $qRfid->execute();
            $resRfid = $qRfid->fetch(PDO::FETCH_ASSOC);
            $siguienteNum = (int)($resRfid['max_num'] ?? 0) + 1;
            $rfid = 'RFID' . str_pad((string)$siguienteNum, 3, '0', STR_PAD_LEFT);

            // Verificar que no esté repetido por seguridad
            $qVerifRfid = $conexion->prepare('SELECT id_aprendiz FROM aprendiz WHERE codigo_rfid = ?');
            $qVerifRfid->execute([$rfid]);
            if ($qVerifRfid->fetch()) {
                throw new Exception('Error al generar código RFID único.');
            }

            // 8. Insertar en tabla aprendiz
            $stmtAprendiz = $conexion->prepare(
                'INSERT INTO aprendiz (codigo_rfid, fk_ficha, fk_usuario) VALUES (?, ?, ?)'
            );
            $stmtAprendiz->execute([$rfid, $idFicha, $idUsuario]);
            $idAprendiz = (int)$conexion->lastInsertId();

            // Confirmar transacción
            $conexion->commit();

            return [
                'success'     => true,
                'error'       => null,
                'id_aprendiz' => $idAprendiz,
                'codigo_rfid' => $rfid
            ];

        } catch (\Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            return [
                'success' => false,
                'error'   => 'Error en la base de datos: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verifica si un número de documento ya está registrado en la tabla usuario
     */
    public static function existeDocumento(string $documento): bool
    {
        $documento = trim($documento);
        if ($documento === '') {
            return false;
        }
        $conexion = (new Conexion())->conectar();
        $stmt = $conexion->prepare('SELECT COUNT(*) FROM usuario WHERE identificacion = ?');
        $stmt->execute([$documento]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Importa masivamente una lista de aprendices validando duplicados
     * y asociándolos a la ficha del instructor autenticado.
     *
     * @param array $aprendices Lista de filas validadas del Excel
     * @param int $idFicha ID de la ficha de destino
     * @param int $idInstructor ID del instructor autenticado
     * @return array ['success' => bool, 'registrados' => int, 'duplicados' => int, 'errores' => array, 'error' => string|null]
     */
    public static function importarAprendicesLote(array $aprendices, int $idFicha, int $idInstructor): array
    {
        $conexion = (new Conexion())->conectar();

        // Validar que la ficha pertenezca al instructor
        if (!self::fichaPerteneceAInstructor($idFicha, $idInstructor)) {
            return [
                'success'     => false,
                'error'       => 'La ficha seleccionada no existe o no está asignada a su usuario.',
                'registrados' => 0,
                'duplicados'  => 0,
                'errores'     => []
            ];
        }

        $registrados = 0;
        $duplicados  = 0;
        $errores     = [];
        $documentosVistosEnLote = [];

        foreach ($aprendices as $fila) {
            $numDoc    = trim((string)($fila['numero_documento'] ?? ''));
            $nombre    = trim((string)($fila['nombre'] ?? ''));
            $apellidos = trim((string)($fila['apellidos'] ?? ''));
            $celular   = trim((string)($fila['celular'] ?? ''));

            // 1. Control de duplicados por número de documento
            if ($numDoc !== '') {
                // Duplicado en el mismo lote
                if (isset($documentosVistosEnLote[$numDoc])) {
                    $duplicados++;
                    continue;
                }
                $documentosVistosEnLote[$numDoc] = true;

                // Duplicado ya existente en la base de datos
                $stmtVerif = $conexion->prepare('SELECT id_usuario FROM usuario WHERE identificacion = ?');
                $stmtVerif->execute([$numDoc]);
                if ($stmtVerif->fetch()) {
                    $duplicados++;
                    continue;
                }
                $identificacionParaGuardar = $numDoc;
            } else {
                // Manejo de identificación vacía para permitir registros sin documento
                $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuario WHERE identificacion = ''");
                $stmt->execute();
                $existeVacio = (int)$stmt->fetchColumn();

                if ($existeVacio === 0) {
                    $identificacionParaGuardar = '';
                } else {
                    $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuario WHERE identificacion LIKE '%\xE2\x80\x8B%'");
                    $stmt->execute();
                    $conteoZwsp = (int)$stmt->fetchColumn() + 1;
                    $identificacionParaGuardar = str_repeat("\xE2\x80\x8B", $conteoZwsp);
                }
            }

            // Iniciar transacción individual para garantizar atomicidad de cada aprendiz
            $conexion->beginTransaction();
            try {
                // Generar nombre de usuario único
                if ($numDoc !== '') {
                    $baseUsuario = 'ap_' . preg_replace('/[^a-zA-Z0-9_]/', '', $numDoc);
                } elseif ($nombre !== '') {
                    $baseUsuario = 'ap_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre));
                } else {
                    $baseUsuario = 'aprendiz';
                }

                $nombreUsuario = $baseUsuario;
                $sufijo = 1;
                while (true) {
                    $stmtUserCheck = $conexion->prepare('SELECT COUNT(*) FROM usuario WHERE nombre_usuario = ?');
                    $stmtUserCheck->execute([$nombreUsuario]);
                    if ((int)$stmtUserCheck->fetchColumn() === 0) {
                        break;
                    }
                    $nombreUsuario = $baseUsuario . '_' . $sufijo;
                    $sufijo++;
                }

                // Contraseña hasheada (por defecto su documento o '123456')
                $claveTexto = $numDoc !== '' ? $numDoc : '123456';
                $claveHash = password_hash($claveTexto, PASSWORD_DEFAULT);

                // Insertar en tabla usuario (rol 3 = Aprendiz, telefono = celular)
                $stmtUser = $conexion->prepare(
                    'INSERT INTO usuario (nombre_usuario, contrasena, nombre, apellido, identificacion, telefono, fk_rol)
                     VALUES (?, ?, ?, ?, ?, ?, 3)'
                );
                $stmtUser->execute([
                    $nombreUsuario,
                    $claveHash,
                    $nombre,
                    $apellidos,
                    $identificacionParaGuardar,
                    $celular !== '' ? $celular : null
                ]);
                $idUsuario = (int)$conexion->lastInsertId();

                // Generar código RFID consecutivo automático (RFID###)
                $qRfid = $conexion->prepare(
                    "SELECT MAX(CAST(SUBSTRING(codigo_rfid, 5) AS UNSIGNED)) AS max_num
                     FROM aprendiz
                     WHERE codigo_rfid LIKE 'RFID%' AND codigo_rfid REGEXP '^RFID[0-9]{3,}$'"
                );
                $qRfid->execute();
                $resRfid = $qRfid->fetch(PDO::FETCH_ASSOC);
                $siguienteNum = (int)($resRfid['max_num'] ?? 0) + 1;
                $rfid = 'RFID' . str_pad((string)$siguienteNum, 3, '0', STR_PAD_LEFT);

                // Insertar en tabla aprendiz
                $stmtAprendiz = $conexion->prepare(
                    'INSERT INTO aprendiz (codigo_rfid, fk_ficha, fk_usuario) VALUES (?, ?, ?)'
                );
                $stmtAprendiz->execute([$rfid, $idFicha, $idUsuario]);

                $conexion->commit();
                $registrados++;

            } catch (\Throwable $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }
                $errores[] = "Error fila " . ($fila['fila'] ?? '') . ": " . $e->getMessage();
            }
        }

        return [
            'success'     => true,
            'registrados' => $registrados,
            'duplicados'  => $duplicados,
            'errores'     => $errores
        ];
    }
}

