<?php

class mdlLogin
{
    private $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    /**
     * Busca un usuario activo por su username.
     * Devuelve el array con id_usuario, username, password_hash y nombre,
     * o null si no existe / está inactivo.
     */
    public function buscarUsuario(string $username): ?array
    {
        $sql = "SELECT id_usuario, username, password_hash, nombre
                FROM electronicas.Usuarios
                WHERE username = ?
                  AND activo   = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
