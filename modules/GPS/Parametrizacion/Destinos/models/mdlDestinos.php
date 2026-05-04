<?php
class mdlDestinos
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
            "SELECT d.id_destino, d.nombre, d.activo,
                    d.fecha_creacion, u.nombre AS creado_por_nombre
             FROM gps.Destinos d
             LEFT JOIN electronicas.Usuarios u ON u.id_usuario = d.creado_por
             ORDER BY d.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarActivos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_destino, nombre FROM gps.Destinos
             WHERE activo = 1 ORDER BY nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre, int $creado_por): int
    {
        $chk = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.Destinos WHERE nombre = ?"
        );
        $chk->execute([$nombre]);
        if ((int)$chk->fetchColumn() > 0)
            throw new RuntimeException("El destino '$nombre' ya existe.");

        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Destinos (nombre, creado_por)
             OUTPUT INSERTED.id_destino VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $creado_por]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(int $id, string $nombre): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Destinos SET nombre = ? WHERE id_destino = ?"
        );
        $stmt->execute([$nombre, $id]);
    }

    public function toggleActivo(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Destinos SET activo = 1 - activo WHERE id_destino = ?"
        );
        $stmt->execute([$id]);
    }
}
