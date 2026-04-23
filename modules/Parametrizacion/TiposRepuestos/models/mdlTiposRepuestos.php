<?php

class mdlTiposRepuestos
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
                    t.id_tipo,
                    t.nombre,
                    COUNT(r.id_repuesto) AS total_repuestos
                FROM electronicas.TiposRepuesto t
                LEFT JOIN electronicas.Repuestos r
                    ON r.id_tipo = t.id_tipo
                    AND r.id_estado != 5
                GROUP BY t.id_tipo, t.nombre
                ORDER BY t.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($data)
    {
        $stmtCheck = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.TiposRepuesto WHERE nombre = ?"
        );
        $stmtCheck->execute([$data["nombre"]]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe un tipo de repuesto con ese nombre"];
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.TiposRepuesto (nombre) VALUES (?)"
        );
        $stmt->execute([$data["nombre"]]);

        return ["ok" => true, "id" => $this->conn->lastInsertId()];
    }

    public function editar($data)
    {
        $stmtCheck = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.TiposRepuesto
             WHERE nombre = ? AND id_tipo != ?"
        );
        $stmtCheck->execute([$data["nombre"], $data["id_tipo"]]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe otro tipo con ese nombre"];
        }

        $stmt = $this->conn->prepare(
            "UPDATE electronicas.TiposRepuesto SET nombre = ? WHERE id_tipo = ?"
        );
        $stmt->execute([$data["nombre"], $data["id_tipo"]]);

        return ["ok" => true];
    }

    public function eliminar($id)
    {
        $stmtCheck = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.Repuestos
             WHERE id_tipo = ? AND id_estado != 5"
        );
        $stmtCheck->execute([$id]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede eliminar: el tipo tiene repuestos activos asociados"];
        }

        $stmt = $this->conn->prepare(
            "DELETE FROM electronicas.TiposRepuesto WHERE id_tipo = ?"
        );
        $stmt->execute([$id]);

        return ["ok" => true];
    }
}
