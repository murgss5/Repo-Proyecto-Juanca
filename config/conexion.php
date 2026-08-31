<?php
class Conexion
{
    private $servidor = "localhost";
    private $base_datos = "control_asistencia";
    private $usuario = "root";
    private $contrasena = "";

    public function conectar()
    {
        try {

            $conexion = new PDO(
                "mysql:host={$this->servidor};dbname={$this->base_datos};charset=utf8mb4",
                $this->usuario,
                $this->contrasena
            );

            $conexion->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $conexion->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $conexion;

        } catch (PDOException $error) {

            die('No fue posible conectar con la base de datos.');

        }
    }
}