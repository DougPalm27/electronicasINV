<?php

class mdlProveedores
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
                    p.id_proveedor,
                    p.nombre,
                    p.telefono,
                    p.correo,
                    p.direccion,
                    p.fecha_registro,
                    COUNT(r.id_repuesto) AS total_repuestos
                FROM electronicas.Proveedores p
                LEFT JOIN electronicas.Repuestos r
                    ON r.id_proveedor = p.id_proveedor
                    AND r.id_estado != 5
                WHERE p.estado = 1
                GROUP BY p.id_proveedor, p.nombre, p.telefono, p.correo, p.direccion, p.fecha_registro
                ORDER BY p.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar($data)
    {
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Proveedores WHERE nombre = ? AND estado = 1";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$data["nombre"]]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe un proveedor con ese nombre"];
        }

        $sql = "INSERT INTO electronicas.Proveedores (nombre, telefono, correo, direccion)
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data["nombre"],
            $data["telefono"] ?: null,
            $data["correo"]   ?: null,
            $data["direccion"]?: null,
        ]);

        return ["ok" => true, "id" => $this->conn->lastInsertId()];
    }

    public function editar($data)
    {
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Proveedores
                     WHERE nombre = ? AND estado = 1 AND id_proveedor != ?";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$data["nombre"], $data["id_proveedor"]]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "Ya existe un proveedor con ese nombre"];
        }

        $sql = "UPDATE electronicas.Proveedores SET
                    nombre    = ?,
                    telefono  = ?,
                    correo    = ?,
                    direccion = ?
                WHERE id_proveedor = ? AND estado = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data["nombre"],
            $data["telefono"] ?: null,
            $data["correo"]   ?: null,
            $data["direccion"]?: null,
            $data["id_proveedor"],
        ]);

        return ["ok" => true];
    }

    public function eliminar($id)
    {
        $sqlCheck = "SELECT COUNT(*) FROM electronicas.Repuestos
                     WHERE id_proveedor = ? AND id_estado != 5";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([$id]);

        if ($stmtCheck->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede eliminar: el proveedor tiene repuestos activos asociados"];
        }

        $sql = "UPDATE electronicas.Proveedores SET estado = 0 WHERE id_proveedor = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return ["ok" => true];
    }
}
