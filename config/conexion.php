<?php
/**
 * Clase para gestionar la conexión a la base de datos usando PDO.
 * Centraliza la configuración de conexión en un solo lugar.
 */

class Conexion
{
    // Configuración de la base de datos
    private $servidor = "localhost";
    private $base_datos = "control_asistencia";
    private $usuario = "root";
    private $contrasena = "";

    /**
     * Crea y devuelve una conexión PDO configurada.
     * @return PDO Objeto de conexión listo para usar.
     */
    public function conectar()
    {
        try {

            // Crea la conexión PDO con los parámetros configurados
            $conexion = new PDO(
                "mysql:host={$this->servidor};dbname={$this->base_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena
            );

            // Configura PDO para lanzar excepciones ante errores
            $conexion->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            // Configura para que los resultados vengan como arrays asociativos
            $conexion->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $conexion;

        } catch (PDOException $error) {

            // Muestra mensaje genérico sin exponer detalles técnicos
            die('No fue posible conectar con la base de datos.');

        }
    }
}