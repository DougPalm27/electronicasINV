<?php
    require_once __DIR__ . '/env.php';

    class Connection{
        public function dbConnect(){
            try {
                $serverName = env('DB_HOST', 'localhost\SQLEXPRESS');
                $database   = env('DB_NAME', 'ElectronicasDB');
                $user       = env('DB_USER');
                $password   = env('DB_PASSWORD');

                // Instanciar la conexion con la base de datos
                $conn = new PDO("sqlsrv:server=$serverName; database=$database", $user, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Retornar la variable de conexion
                return $conn;
            } catch (PDOException $e) {
                // En caso de error
            echo "Error en conexion: " . $e->getMessage();
            }
        }
    }
?>