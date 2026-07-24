<?php
    require_once __DIR__ . '/env.php';

    class Connection{
        public function dbConnect(){
            try {
                $serverName = env('DB_HOST', 'localhost\SQLEXPRESS');
                $database   = env('DB_NAME', 'ElectronicasDB');
                $user       = env('DB_USER');
                $password   = env('DB_PASSWORD');
                $encrypt    = env('DB_ENCRYPT', '0');
                $trustCert  = env('DB_TRUST_SERVER_CERTIFICATE', '1');

                // Instanciar la conexion con la base de datos
                $conn = new PDO(
                    "sqlsrv:server=$serverName;database=$database;Encrypt=$encrypt;TrustServerCertificate=$trustCert",
                    $user,
                    $password
                );
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Publicar el usuario logueado en el contexto de la sesión SQL:
                // la columna MovimientosRepuestos.id_usuario lo toma por DEFAULT
                // (ver BD/migration_usuario_movimientos.sql)
                if (!empty($_SESSION['id_usuario'])) {
                    $stmt = $conn->prepare("EXEC sp_set_session_context @key = N'id_usuario', @value = ?");
                    $stmt->execute([(int)$_SESSION['id_usuario']]);
                }

                // Retornar la variable de conexion
                return $conn;
            } catch (PDOException $e) {
                // En caso de error
            echo "Error en conexion: " . $e->getMessage();
            }
        }
    }
?>
