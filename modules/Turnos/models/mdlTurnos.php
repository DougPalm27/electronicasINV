<?php

class mdlTurnos
{
    private $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ── Kiosco: identificar por PIN ────────────────────────
    public function buscarPorPin(string $pin): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario, nombre, foto
             FROM electronicas.Usuarios
             WHERE pin_bloqueo = ? AND activo = 1"
        );
        $stmt->execute([$pin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Kiosco: sesión abierta actualmente en una estación ─
    public function sesionActivaEn(string $estacion): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT TOP 1 ts.id_sesion, ts.inicio, u.id_usuario, u.nombre, u.foto
             FROM electronicas.TurnoSesiones ts
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
             WHERE ts.estacion = ? AND ts.fin IS NULL
             ORDER BY ts.inicio DESC"
        );
        $stmt->execute([$estacion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Kiosco: abrir turno (cierra cualquier sesión huérfana
    //    que haya quedado abierta en la misma estación) ─────
    public function abrirSesion(int $id_usuario, string $estacion): int
    {
        $this->conn->prepare(
            "UPDATE electronicas.TurnoSesiones SET fin = GETDATE()
             WHERE estacion = ? AND fin IS NULL"
        )->execute([$estacion]);

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.TurnoSesiones (id_usuario, estacion)
             OUTPUT INSERTED.id_sesion
             VALUES (?, ?)"
        );
        $stmt->execute([$id_usuario, $estacion]);
        return (int)$stmt->fetchColumn();
    }

    // ── Kiosco: bloquear (cierra la sesión activa de la estación) ─
    public function cerrarSesionEnEstacion(string $estacion): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.TurnoSesiones SET fin = GETDATE()
             WHERE estacion = ? AND fin IS NULL"
        )->execute([$estacion]);
    }

    // ── Admin: estaciones con turno abierto ahora mismo ────
    public function activos(): array
    {
        $sql = "SELECT ts.id_sesion, ts.estacion, ts.inicio, u.nombre
                FROM electronicas.TurnoSesiones ts
                INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
                WHERE ts.fin IS NULL
                ORDER BY ts.estacion";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Admin: historial completo ──────────────────────────
    public function historial(): array
    {
        $sql = "SELECT ts.id_sesion, ts.estacion, u.nombre,
                       FORMAT(ts.inicio,'dd/MM/yyyy HH:mm') AS inicio,
                       FORMAT(ts.fin,   'dd/MM/yyyy HH:mm') AS fin,
                       CASE WHEN ts.fin IS NULL THEN NULL
                            ELSE DATEDIFF(MINUTE, ts.inicio, ts.fin) END AS minutos
                FROM electronicas.TurnoSesiones ts
                INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
                ORDER BY ts.inicio DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
