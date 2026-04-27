<?php

class mdlRoles
{
    private $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ══════════════════════════════════════════════════════
    // ROLES
    // ══════════════════════════════════════════════════════

    public function listarRoles(): array
    {
        $sql = "SELECT r.id_rol, r.nombre, r.descripcion, r.activo,
                       COUNT(rm.id_modulo) AS total_modulos
                FROM electronicas.Roles r
                LEFT JOIN electronicas.RolModulos rm ON rm.id_rol = r.id_rol
                GROUP BY r.id_rol, r.nombre, r.descripcion, r.activo
                ORDER BY r.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearRol(string $nombre, string $descripcion): int
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.Roles WHERE nombre = ?"
        );
        $chk->execute([$nombre]);
        if ((int)$chk->fetchColumn() > 0)
            throw new RuntimeException("El rol '$nombre' ya existe.");

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.Roles (nombre, descripcion)
             OUTPUT INSERTED.id_rol
             VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $descripcion]);
        return (int)$stmt->fetchColumn();
    }

    public function editarRol(int $id, string $nombre, string $descripcion): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Roles
             SET nombre = ?, descripcion = ?
             WHERE id_rol = ?"
        );
        $stmt->execute([$nombre, $descripcion, $id]);
    }

    public function toggleActivoRol(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Roles SET activo = 1 - activo WHERE id_rol = ?"
        );
        $stmt->execute([$id]);
    }

    // ══════════════════════════════════════════════════════
    // MÓDULOS
    // ══════════════════════════════════════════════════════

    public function listarModulos(): array
    {
        $sql = "SELECT id_modulo, clave, nombre, icono, grupo, orden
                FROM electronicas.Modulos
                WHERE activo = 1
                ORDER BY orden";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════
    // PERMISOS (pivot RolModulos)
    // ══════════════════════════════════════════════════════

    public function obtenerPermisosRol(int $id_rol): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_modulo FROM electronicas.RolModulos WHERE id_rol = ?"
        );
        $stmt->execute([$id_rol]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id_modulo');
    }

    public function guardarPermisosRol(int $id_rol, array $ids_modulos): void
    {
        $del = $this->conn->prepare(
            "DELETE FROM electronicas.RolModulos WHERE id_rol = ?"
        );
        $del->execute([$id_rol]);

        if (empty($ids_modulos)) return;

        $ins = $this->conn->prepare(
            "INSERT INTO electronicas.RolModulos (id_rol, id_modulo) VALUES (?, ?)"
        );
        foreach ($ids_modulos as $id_modulo) {
            $ins->execute([$id_rol, (int)$id_modulo]);
        }
    }

    // ── Para sesión: claves de módulos permitidos por rol ──
    public function obtenerClavesModulosRol(int $id_rol): array
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
