<?php
    class Connection{
        public function dbConnect(){
            try {
                // recio
                
                $serverName = "(localdb)\DOUGLAS";
                $database = "ElectronicasDB"; 
                $user = 'dpalma';
                $password = 'dpalma2729';
                $port ="1443";

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