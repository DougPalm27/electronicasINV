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
        $sql = "SELECT u.id_usuario, u.username, u.password_hash, u.nombre, u.foto,
                       u.id_rol, r.nombre AS nombre_rol,
                       ISNULL(r.puede_ejecutar_mantenimiento, 0) AS puede_ejecutar_mantenimiento
                FROM electronicas.Usuarios u
                LEFT JOIN electronicas.Roles r ON r.id_rol = u.id_rol
                WHERE u.username = ?
                  AND u.activo   = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Devuelve las claves de módulos permitidos para un rol.
     * Retorna null si no se pasan ID (sin restricción).
     */
    public function obtenerModulosRol(int $id_rol): array
    {
        $sql = "SELECT m.clave
                FROM electronicas.RolModulos rm
                INNER JOIN electronicas.Modulos m ON m.id_modulo = rm.id_modulo
                WHERE rm.id_rol = ? AND m.activo = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_rol]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'clave');
    }
}
