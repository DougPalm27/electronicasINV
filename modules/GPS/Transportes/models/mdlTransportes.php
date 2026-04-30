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
            "SELECT id_transporte, nombre, contacto, telefono, activo
             FROM gps.Transportes
             ORDER BY nombre"
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

    public function crear(string $nombre, string $contacto, string $telefono): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Transportes (nombre, contacto, telefono)
             OUTPUT INSERTED.id_transporte
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$nombre, $contacto, $telefono]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(int $id, string $nombre, string $contacto, string $telefono): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Transportes
             SET nombre = ?, contacto = ?, telefono = ?
             WHERE id_transporte = ?"
        );
        $stmt->execute([$nombre, $contacto, $telefono, $id]);
    }

    public function toggleActivo(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Transportes SET activo = 1 - activo WHERE id_transporte = ?"
        );
        $stmt->execute([$id]);
    }
}
