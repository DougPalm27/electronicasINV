<?php
class mdlTiposVehiculo
{
    private $conn;

    public function __construct()
    {
        require_once '../../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    public function listar(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT tv.id_tipo_vehiculo, tv.nombre, tv.activo,
                    tv.fecha_creacion, u.nombre AS creado_por_nombre
             FROM gps.TiposVehiculo tv
             LEFT JOIN electronicas.Usuarios u ON u.id_usuario = tv.creado_por
             ORDER BY tv.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarActivos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_tipo_vehiculo, nombre FROM gps.TiposVehiculo
             WHERE activo = 1 ORDER BY nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre, int $creado_por): int
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.TiposVehiculo WHERE nombre = ?"
        );
        $chk->execute([$nombre]);
        if ((int)$chk->fetchColumn() > 0)
            throw new RuntimeException("El tipo '$nombre' ya existe.");

        $stmt = $this->conn->prepare(
            "INSERT INTO gps.TiposVehiculo (nombre, creado_por)
             OUTPUT INSERTED.id_tipo_vehiculo VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $creado_por]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(int $id, string $nombre): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.TiposVehiculo SET nombre = ? WHERE id_tipo_vehiculo = ?"
        );
        $stmt->execute([$nombre, $id]);
    }

    public function toggleActivo(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.TiposVehiculo SET activo = 1 - activo WHERE id_tipo_vehiculo = ?"
        );
        $stmt->execute([$id]);
    }
}
