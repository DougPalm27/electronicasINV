<?php

class mdlGPS
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
            "SELECT g.id_gps, g.placa, g.tipo_vehiculo, g.plataforma,
                    g.destino, g.usuario, g.contrasena, g.estado,
                    g.id_transporte, t.nombre AS transporte
             FROM gps.GPS g
             LEFT JOIN gps.Transportes t ON t.id_transporte = g.id_transporte
             ORDER BY g.placa"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.GPS (id_transporte, tipo_vehiculo, placa, plataforma, destino, usuario, contrasena)
             OUTPUT INSERTED.id_gps
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $d['id_transporte'] ?: null,
            $d['tipo_vehiculo'],
            $d['placa'],
            $d['plataforma'],
            $d['destino'],
            $d['usuario'],
            $d['contrasena'],
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(array $d): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.GPS
             SET id_transporte = ?, tipo_vehiculo = ?, placa = ?,
                 plataforma = ?, destino = ?, usuario = ?, contrasena = ?,
                 updated_at = GETDATE()
             WHERE id_gps = ?"
        );
        $stmt->execute([
            $d['id_transporte'] ?: null,
            $d['tipo_vehiculo'],
            $d['placa'],
            $d['plataforma'],
            $d['destino'],
            $d['usuario'],
            $d['contrasena'],
            $d['id_gps'],
        ]);
    }

    public function toggleEstado(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.GPS SET estado = 1 - estado WHERE id_gps = ?"
        );
        $stmt->execute([$id]);
    }
}
