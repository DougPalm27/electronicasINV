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
        $sql = "SELECT id_usuario, username, nombre, activo,
                       FORMAT(fecha_registro,'dd/MM/yyyy') AS fecha_registro
                FROM electronicas.Usuarios
                ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Crear ──────────────────────────────────────────────
    public function crear(string $username, string $password, string $nombre): void
    {
        // Verificar username único
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.Usuarios WHERE username = ?"
        );
        $chk->execute([$username]);
        if ((int)$chk->fetchColumn() > 0) {
            throw new RuntimeException("El usuario '$username' ya existe.");
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.Usuarios (username, password_hash, nombre)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$username, $hash, $nombre]);
    }

    // ── Editar nombre ──────────────────────────────────────
    public function editarNombre(int $id, string $nombre): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET nombre = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$nombre, $id]);
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
