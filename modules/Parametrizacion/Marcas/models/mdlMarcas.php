<?php

class mdlMarcas
{
    private $conn;

    public function __construct()
    {
        require_once "../../../../config/connection.php";
        $conexion = new Connection();
        $this->conn = $conexion->dbConnect();
    }

    public function listar()
    {
        $sql = "SELECT
                    m.id_marca,
                    m.nombre,
                    COUNT(DISTINCT mo.id_modelo) AS total_modelos
                FROM electronicas.Marcas m
                LEFT JOIN electronicas.Modelos mo ON mo.id_marca = m.id_marca
                GROUP BY m.id_marca, m.nombre
                ORDER BY m.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($nombre)
    {
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Marcas WHERE nombre = ?";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$nombre]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe una marca con ese nombre"];
        }

        $sql = "INSERT INTO electronicas.Marcas (nombre) VALUES (?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$nombre]);

        return ["ok" => true, "id" => $this->conn->lastInsertId()];
    }

    public function editar($id, $nombre)
    {
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Marcas WHERE nombre = ? AND id_marca != ?";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$nombre, $id]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe una marca con ese nombre"];
        }

        $sql = "UPDATE electronicas.Marcas SET nombre = ? WHERE id_marca = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$nombre, $id]);

        return ["ok" => true];
    }

    public function eliminar($id)
    {
        // Verificar si tiene modelos asociados
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Modelos WHERE id_marca = ?";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$id]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede eliminar: la marca tiene modelos registrados"];
        }

        $sql = "DELETE FROM electronicas.Marcas WHERE id_marca = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return ["ok" => true];
    }
}
