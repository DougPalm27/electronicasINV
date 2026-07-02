<?php

class mdlCustodios
{
    private $conn;

    public function __construct()
    {
        require_once '../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    public function listar(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT c.id_custodio, c.nombre, c.telefono, c.activo,
                    c.fecha_creacion, c.fecha_actualizacion,
                    uc.nombre AS creado_por_nombre,
                    ua.nombre AS actualizado_por_nombre
             FROM gps.Custodios c
             LEFT JOIN electronicas.Usuarios uc ON uc.id_usuario = c.creado_por
             LEFT JOIN electronicas.Usuarios ua ON ua.id_usuario = c.actualizado_por
             ORDER BY c.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre, string $telefono, int $creado_por): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Custodios (nombre, telefono, creado_por)
             OUTPUT INSERTED.id_custodio
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$nombre, $telefono ?: null, $creado_por]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(int $id, string $nombre, string $telefono, int $actualizado_por): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Custodios
             SET nombre = ?, telefono = ?,
                 fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_custodio = ?"
        );
        $stmt->execute([$nombre, $telefono ?: null, $actualizado_por, $id]);
    }

    public function toggleActivo(int $id, int $uid): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Custodios
             SET activo = 1 - activo,
                 fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_custodio = ?"
        );
        $stmt->execute([$uid, $id]);
    }
}
