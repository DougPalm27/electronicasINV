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
    // La columna nivel_delvis llega con BD/migration_nivel_delvis.sql
    private function hayNivelDelvis(): bool
    {
        return (bool)$this->conn->query("SELECT COL_LENGTH('electronicas.Usuarios', 'nivel_delvis')")->fetchColumn();
    }

    public function listar(): array
    {
        $nivel = $this->hayNivelDelvis() ? 'ISNULL(u.nivel_delvis, 0)' : '0';
        $sql = "SELECT u.id_usuario, u.username, u.nombre, u.email, u.activo,
                       FORMAT(u.fecha_registro,'dd/MM/yyyy') AS fecha_registro,
                       u.id_rol, r.nombre AS nombre_rol,
                       CASE WHEN u.pin_bloqueo IS NOT NULL THEN 1 ELSE 0 END AS tiene_pin,
                       $nivel AS nivel_delvis
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

    // ── Foto de perfil ─────────────────────────────────────
    public function obtenerFoto(int $id): ?string
    {
        $stmt = $this->conn->prepare(
            "SELECT foto FROM electronicas.Usuarios WHERE id_usuario = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetchColumn() ?: null;
    }

    public function actualizarFoto(int $id, ?string $ruta): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET foto = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$ruta, $id]);
    }

    // ── Código de bloqueo (PIN del kiosco de planta) ───────
    public function asignarPin(int $id, string $pin): void
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.Usuarios WHERE pin_bloqueo = ? AND id_usuario <> ?"
        );
        $chk->execute([$pin, $id]);
        if ((int)$chk->fetchColumn() > 0) {
            throw new RuntimeException('Ese código ya está asignado a otro usuario.');
        }

        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET pin_bloqueo = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$pin, $id]);
    }

    // Nivel de Delvis que el candado escribe por este usuario (0 = ninguno)
    public function asignarNivelDelvis(int $id, int $nivel): void
    {
        if (!$this->hayNivelDelvis()) {
            throw new RuntimeException('Falta correr BD/migration_nivel_delvis.sql en la base de datos.');
        }
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET nivel_delvis = ? WHERE id_usuario = ?"
        );
        $stmt->execute([$nivel ?: null, $id]);
    }

    // ── Contraseñas de nivel de Delvis (iguales en todas las máquinas) ──
    // Solo se guardan cifradas; la pantalla nunca las vuelve a mostrar, solo si están puestas.
    private function hayClavesDelvis(): bool
    {
        return (bool)$this->conn->query("SELECT CASE WHEN OBJECT_ID('electronicas.DelvisClaves') IS NULL THEN 0 ELSE 1 END")->fetchColumn();
    }

    public function estadoClavesDelvis(): ?array
    {
        if (!$this->hayClavesDelvis()) return null;
        $stmt = $this->conn->query(
            "SELECT c.nivel, FORMAT(c.actualizado, 'dd/MM/yyyy HH:mm') AS actualizado, u.nombre AS por
             FROM electronicas.DelvisClaves c
             LEFT JOIN electronicas.Usuarios u ON u.id_usuario = c.actualizado_por
             ORDER BY c.nivel"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // $claves: nivel => contraseña ('' = quitar ese nivel)
    public function guardarClavesDelvis(array $claves, int $porUsuario): void
    {
        if (!$this->hayClavesDelvis()) {
            throw new RuntimeException('Falta correr BD/migration_nivel_delvis.sql en la base de datos.');
        }
        require_once __DIR__ . '/../../../config/kioscoCifrado.php';
        foreach ($claves as $nivel => $clave) {
            if ($clave === '') {
                $this->conn->prepare("DELETE FROM electronicas.DelvisClaves WHERE nivel = ?")->execute([$nivel]);
                continue;
            }
            $cif = kioscoCifrar($clave, 'delvis-bd');
            $stmt = $this->conn->prepare(
                "MERGE electronicas.DelvisClaves AS t
                 USING (SELECT ? AS nivel) AS s ON t.nivel = s.nivel
                 WHEN MATCHED THEN UPDATE SET clave_cifrada = ?, actualizado = GETDATE(), actualizado_por = ?
                 WHEN NOT MATCHED THEN INSERT (nivel, clave_cifrada, actualizado_por) VALUES (?, ?, ?);"
            );
            $stmt->execute([$nivel, $cif, $porUsuario ?: null, $nivel, $cif, $porUsuario ?: null]);
        }
    }

    public function quitarPin(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.Usuarios SET pin_bloqueo = NULL WHERE id_usuario = ?"
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
