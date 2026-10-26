<?php
/**
 * clase para gestionar la conexion a la base de datos usando pdo
 * centraliza la configuracion de conexion en un solo lugar
 */

class Conexion
{
    // configuracion de la base de datos
    private $servidor = "localhost";
    private $base_datos = "control_asistencia";
    private $usuario = "root";
    private $contrasena = "";

    /**
     * crea y devuelve una conexion pdo configurada
     * @return PDO Objeto de conexion listo para usar
     */
    public function conectar()
    {
        try {

            // crea la conexion pdo con los parametros configurados
            $conexion = new PDO(
                "mysql:host={$this->servidor};dbname={$this->base_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena
            );

            // configura pdo para lanzar excepciones ante errores
            $conexion->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            // configura para que los resultados vengan como arrays asociativos
            $conexion->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $conexion;

        } catch (PDOException $error) {

            // muestra mensaje generico sin exponer detalles tecnicos
            die('No fue posible conectar con la base de datos.');

        }
    }
}
