<?php

class mdlTransportes
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
            "SELECT t.id_transporte, t.nombre, t.contacto, t.telefono, t.activo,
                    t.fecha_creacion, t.fecha_actualizacion,
                    uc.nombre AS creado_por_nombre,
                    ua.nombre AS actualizado_por_nombre
             FROM gps.Transportes t
             LEFT JOIN electronicas.Usuarios uc ON uc.id_usuario = t.creado_por
             LEFT JOIN electronicas.Usuarios ua ON ua.id_usuario = t.actualizado_por
             ORDER BY t.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarActivos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_transporte, nombre
             FROM gps.Transportes
             WHERE activo = 1
             ORDER BY nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre, string $contacto, string $telefono, int $creado_por): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Transportes (nombre, contacto, telefono, creado_por)
             OUTPUT INSERTED.id_transporte
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$nombre, $contacto, $telefono, $creado_por]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(int $id, string $nombre, string $contacto, string $telefono, int $actualizado_por): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Transportes
             SET nombre = ?, contacto = ?, telefono = ?,
                 fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_transporte = ?"
        );
        $stmt->execute([$nombre, $contacto, $telefono, $actualizado_por, $id]);
    }

    public function toggleActivo(int $id, int $uid): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Transportes
             SET activo = 1 - activo, fecha_actualizacion = GETDATE(), actualizado_por = ?
             WHERE id_transporte = ?"
        );
        $stmt->execute([$uid, $id]);
    }
}
