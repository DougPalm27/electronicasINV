<?php

class mdlUsuarios
{
    private $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ── Listar ─────────────────────────────────────────────
    public function listar(): array
    {
        $sql = "SELECT u.id_usuario, u.username, u.nombre, u.email, u.activo,
                       FORMAT(u.fecha_registro,'dd/MM/yyyy') AS fecha_registro,
                       u.id_rol, r.nombre AS nombre_rol
                FROM electronicas.Usuarios u
                LEFT JOIN electronicas.Roles r ON r.id_rol = u.id_rol
                ORDER BY u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Listar roles disponibles (para select) ─────────────
    public function listarRoles(): array
    {
        $sql = "SELECT id_rol, nombre FROM electronicas.Roles
                WHERE activo = 1 ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Crear ──────────────────────────────────────────────
    public function crear(string $username, string $password, string $nombre, ?int $id_rol = null, ?string $email = null): void
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.Usuarios WHERE username = ?"
        );
        $chk->execute([$username]);
        if ((int)$chk->fetchColumn() > 0) {
            throw new RuntimeException("El usuario '$username' ya existe.");
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.Usuarios (username, password_hash, nombre, id_rol, email)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $hash, $nombre, $id_rol, $email ?: null]);
    }

    // ── Editar usuario (nombre + rol + email) ─────────────
    public function editarNombre(int $id, string $nombre, ?int $id_rol = null, ?string $email = null): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET nombre = ?, id_rol = ?, email = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$nombre, $id_rol, $email ?: null, $id]);
    }

    // ── Asignar rol ────────────────────────────────────────
    public function asignarRol(int $id_usuario, ?int $id_rol): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET id_rol = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$id_rol, $id_usuario]);
    }

    // ── Resetear contraseña (admin) ────────────────────────
    public function resetPassword(int $id, string $nuevaPassword): void
    {
        $hash = password_hash($nuevaPassword, PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET password_hash = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$hash, $id]);
    }

    // ── Activar / Desactivar ───────────────────────────────
    public function toggleActivo(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET activo = 1 - activo WHERE id_usuario = ?"
        );
        $stmt->execute([$id]);
    }

    // ── Cambiar propia contraseña ──────────────────────────
    public function cambiarPassword(int $id, string $actual, string $nueva): void
    {
        $stmt = $this->conn->prepare(
            "SELECT password_hash FROM electronicas.Usuarios WHERE id_usuario = ?"
        );
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($actual, $hash)) {
            throw new RuntimeException('La contraseña actual es incorrecta.');
        }

        $nuevo_hash = password_hash($nueva, PASSWORD_BCRYPT);
        $upd = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET password_hash = ? WHERE id_usuario = ?"
        );
        $upd->execute([$nuevo_hash, $id]);
    }
}
