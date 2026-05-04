<?php
class mdlPlataformas
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
            "SELECT p.id_plataforma, p.nombre, p.url_base, p.activo,
                    p.fecha_creacion, u.nombre AS creado_por_nombre
             FROM gps.Plataformas p
             LEFT JOIN electronicas.Usuarios u ON u.id_usuario = p.creado_por
             ORDER BY p.nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarActivas(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_plataforma, nombre, url_base FROM gps.Plataformas
             WHERE activo = 1 ORDER BY nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(string $nombre, string $url_base, int $creado_por): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Plataformas (nombre, url_base, creado_por)
             OUTPUT INSERTED.id_plataforma VALUES (?, ?, ?)"
        );
        $stmt->execute([$nombre, $url_base ?: null, $creado_por]);
        return (int)$stmt->fetchColumn();
    }

    public function editar(int $id, string $nombre, string $url_base): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Plataformas SET nombre = ?, url_base = ?
             WHERE id_plataforma = ?"
        );
        $stmt->execute([$nombre, $url_base ?: null, $id]);
    }

    public function toggleActivo(int $id): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Plataformas SET activo = 1 - activo WHERE id_plataforma = ?"
        );
        $stmt->execute([$id]);
    }
}
