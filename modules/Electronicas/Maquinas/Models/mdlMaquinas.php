<?php


class mdlMaquinas extends Connection
{
    private $conn;

    public function __construct()
    {
        $database = new Connection();
        $this->conn = $database->dbConnect();
    }

    // Valida costo y año; devuelve un mensaje de error o null si todo está bien.
    private function validarDatos($losDatos)
    {
        if ((float) ($losDatos->costo ?? 0) < 0) {
            return "El costo no puede ser negativo.";
        }

        $anio = trim((string) ($losDatos->anio ?? ''));
        if ($anio !== '') {
            $anioNum = (int) $anio;
            $maxAnio = (int) date('Y') + 1;
            if ($anioNum < 1900 || $anioNum > $maxAnio) {
                return "El año de fabricación debe estar entre 1900 y $maxAnio.";
            }
        }

        return null;
    }

    public function guardarMaquina($losDatos)
    {
        header('Content-Type: application/json');

        $error = $this->validarDatos($losDatos);
        if ($error !== null) {
            echo json_encode([["status" => "400", "mensaje" => $error]]);
            exit;
        }

        $sql = "INSERT INTO electronicas.Maquinas
            (
                nombre,
                id_modelo,
                serie,
                comentarios,
                id_estado,
                costo,
                anio,
                ubicacion
            )
            VALUES
            (
                :nombre,
                :id_modelo,
                :serie,
                :comentarios,
                :id_estado,
                :costo,
                :anio,
                :ubicacion
            )";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":nombre", $losDatos->nombre);
        $stmt->bindParam(":id_modelo", $losDatos->id_modelo);
        $stmt->bindParam(":serie", $losDatos->serie);
        $stmt->bindParam(":comentarios", $losDatos->comentarios);
        $stmt->bindParam(":id_estado", $losDatos->id_estado);
        $stmt->bindParam(":costo", $losDatos->costo);
        $stmt->bindParam(":anio", $losDatos->anio);
        $stmt->bindParam(":ubicacion", $losDatos->ubicacion);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina registrada correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function editarMaquina($losDatos)
    {
        header('Content-Type: application/json');

        $error = $this->validarDatos($losDatos);
        if ($error !== null) {
            echo json_encode([["status" => "400", "mensaje" => $error]]);
            exit;
        }

        $sql = "UPDATE electronicas.Maquinas
            SET nombre = :nombre,
                id_modelo = :id_modelo,
                serie = :serie,
                comentarios = :comentarios,
                id_estado = :id_estado,
                costo = :costo,
                anio = :anio,
                ubicacion = :ubicacion
            WHERE id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $losDatos->id_maquina);
        $stmt->bindParam(":nombre", $losDatos->nombre);
        $stmt->bindParam(":id_modelo", $losDatos->id_modelo);
        $stmt->bindParam(":serie", $losDatos->serie);
        $stmt->bindParam(":comentarios", $losDatos->comentarios);
        $stmt->bindParam(":id_estado", $losDatos->id_estado);
        $stmt->bindParam(":costo", $losDatos->costo);
        $stmt->bindParam(":anio", $losDatos->anio);
        $stmt->bindParam(":ubicacion", $losDatos->ubicacion);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina actualizada correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function listarMaquinas()
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                mq.id_maquina,
                mq.nombre,
                mq.serie,
                mq.comentarios,
                mq.id_modelo,
                mq.id_estado,
                mq.costo,
                mq.anio,
                mq.ubicacion,
                mo.nombre AS modelo,
                mo.imagen AS modelo_imagen,
                tm.nombre AS tipo_modelo,
                ma.nombre AS marca,
                em.nombre AS estado
            FROM electronicas.Maquinas mq
            INNER JOIN electronicas.Modelos mo
                ON mq.id_modelo = mo.id_modelo
            INNER JOIN electronicas.Marcas ma
                ON mo.id_marca = ma.id_marca
            INNER JOIN electronicas.EstadoMaquina em
                ON mq.id_estado = em.id_estado
            LEFT JOIN electronicas.TiposModelo tm
                ON mo.id_tipo_modelo = tm.id_tipo_modelo
            ORDER BY mq.id_maquina DESC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function obtenerMaquina($id_maquina)
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                mq.id_maquina,
                mq.nombre,
                mq.id_modelo,
                mo.id_marca,
                mq.serie,
                mq.comentarios,
                mq.id_estado,
                mq.costo,
                mq.anio,
                mq.ubicacion
            FROM electronicas.Maquinas mq
            INNER JOIN electronicas.Modelos mo
                ON mq.id_modelo = mo.id_modelo
            WHERE mq.id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }
    public function cambiarEstadoMaquina($id_maquina, $id_estado)
    {
        header('Content-Type: application/json');

        $sql = "UPDATE electronicas.Maquinas
                SET id_estado = :id_estado
                WHERE id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);
        $stmt->bindParam(":id_estado", $id_estado);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Estado actualizado correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarMarcas()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_marca, nombre
                FROM electronicas.Marcas
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarModelos($id_marca)
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_modelo, nombre
                FROM electronicas.Modelos
                WHERE id_marca = :id_marca
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_marca", $id_marca);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarEstadosMaquina()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_estado, nombre
                FROM electronicas.EstadoMaquina
                ORDER BY id_estado ASC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }
    public function obtenerRepuestosMaquina($id_maquina)
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                r.id_repuesto,
                r.nombre,
                r.costo,
                ISNULL(dv.simbolo, 'L.') AS divisa_simbolo,
                e.nombre AS estado,
                FORMAT(MAX(m.fecha_mantenimiento), 'dd/MM/yyyy') AS ultima_fecha
            FROM electronicas.Repuestos r
            INNER JOIN electronicas.EstadoRepuestos e
                ON r.id_estado = e.id_estado
            LEFT JOIN electronicas.Divisas dv
                ON dv.id_divisa = r.id_divisa
            LEFT JOIN electronicas.MantenimientoRepuestos mr
                ON r.id_repuesto = mr.id_repuesto
            LEFT JOIN electronicas.Mantenimientos m
                ON mr.id_mantenimiento = m.id_mantenimiento
               AND m.anulado = 0
            WHERE r.id_maquina_actual = :id_maquina
            GROUP BY
                r.id_repuesto,
                r.nombre,
                r.costo,
                dv.simbolo,
                e.nombre
            ORDER BY r.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }
}
