<?php

class mdlRepuestos
{
    private $conn;

    public function __construct()
    {
        require_once "../../../../config/connection.php";

        $conexion = new Connection();
        $this->conn = $conexion->dbConnect();
    }
    //////////////////////////////////////////////////////////
    // 📊 LISTAR REPUESTOS
    //////////////////////////////////////////////////////////
    public function listarRepuestos()
    {
        $sql = "SELECT 
                r.id_repuesto,
                r.nombre,
                r.numero_parte,
                CASE 
                WHEN r.maneja_serie = 1 THEN 
                    (SELECT COUNT(*) 
                        FROM electronicas.RepuestosDetalle d 
                        WHERE d.id_repuesto = r.id_repuesto 
                        AND d.id_estado_repuesto = 1)
                    ELSE r.stock
                END AS stock,
                r.costo_promedio,
                p.nombre AS proveedor,
                r.id_tipo,
                r.id_marca,
                r.id_modelo,
                r.maneja_serie,
                r.id_proveedor,
                r.comentarios,
                t.nombre AS tipo,
                m.nombre AS marca,
                mo.nombre AS modelo

            FROM electronicas.Repuestos r
            INNER JOIN electronicas.Proveedores p
                ON r.id_proveedor = p.id_proveedor
            LEFT JOIN electronicas.TiposRepuesto t
                ON r.id_tipo = t.id_tipo
            LEFT JOIN electronicas.Marcas m
                ON r.id_marca = m.id_marca
            LEFT JOIN electronicas.Modelos mo
                ON r.id_modelo = mo.id_modelo";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // 💾 GUARDAR REPUESTO
    //////////////////////////////////////////////////////////
    public function guardarRepuesto($data)
    {
        $sql = "INSERT INTO electronicas.Repuestos
            (nombre, numero_parte, id_proveedor, costo_promedio, comentarios, id_tipo, id_marca, id_modelo, maneja_serie, stock)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["nombre"],
            $data["numero_parte"],
            $data["id_proveedor"],
            $data["costo"],
            $data["comentarios"],
            $data["id_tipo"],
            $data["id_marca"],
            $data["id_modelo"],
            $data["maneja_serie"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // ✏️ EDITAR REPUESTO
    //////////////////////////////////////////////////////////
    public function editarRepuesto($data)
    {
        // 🔥 obtener tipo actual
        $sql = "SELECT maneja_serie, stock FROM electronicas.Repuestos WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$data["id_repuesto"]]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);

        // si cambia el tipo
        if ($actual["maneja_serie"] != $data["maneja_serie"]) {

            // 🟢 validar stock
            if ($actual["stock"] > 0) {
                return [
                    "error" => true,
                    "mensaje" => "No puedes cambiar el tipo con stock existente"
                ];
            }

            // 🔵 validar series
            $sqlDet = "SELECT COUNT(*) FROM electronicas.RepuestosDetalle WHERE id_repuesto = ?";
            $stmtDet = $this->conn->prepare($sqlDet);
            $stmtDet->execute([$data["id_repuesto"]]);

            if ($stmtDet->fetchColumn() > 0) {
                return [
                    "error" => true,
                    "mensaje" => "No puedes cambiar el tipo porque ya existen series registradas"
                ];
            }
        }

        //UPDATE normal
        $sql = "UPDATE electronicas.Repuestos SET
        nombre = ?,
        numero_parte = ?,
        id_proveedor = ?,
        costo_promedio = ?,
        comentarios = ?,
        id_tipo = ?,
        id_marca = ?,
        id_modelo = ?,
        maneja_serie = ?
            WHERE id_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["nombre"],
            $data["numero_parte"],
            $data["id_proveedor"],
            $data["costo"],
            $data["comentarios"],
            $data["id_tipo"],
            $data["id_marca"],
            $data["id_modelo"],
            $data["maneja_serie"],
            $data["id_repuesto"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // 📥 ENTRADA (KARDEX)
    //////////////////////////////////////////////////////////
    public function entradaRepuesto($data)
    {
        $stockActual = $this->obtenerStock($data["id_repuesto"]);
        $nuevoStock = $stockActual + $data["cantidad"];

        // Kardex
        $sql = "INSERT INTO electronicas.MovimientosRepuestos
                (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario, stock_anterior, stock_nuevo, referencia)
                VALUES (?, 1, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data["id_repuesto"],
            $data["cantidad"],
            $data["costo"],
            $stockActual,
            $nuevoStock,
            $data["referencia"]
        ]);

        // actualizar stock
        $this->actualizarStock($data["id_repuesto"], $nuevoStock);

        return true;
    }

    //////////////////////////////////////////////////////////
    // 📊 KARDEX
    //////////////////////////////////////////////////////////
    public function obtenerKardex($id)
    {
        $sql = "SELECT 
                    m.fecha_movimiento,
                    t.nombre AS tipo,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    m.costo_unitario,
                    m.referencia
                FROM electronicas.MovimientosRepuestos m
                INNER JOIN electronicas.TiposMovimientoRepuesto t
                    ON m.id_tipo_movimiento = t.id_tipo_movimiento
                WHERE m.id_repuesto = ?
                ORDER BY m.fecha_movimiento DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // 📦 DETALLE POR SERIE
    //////////////////////////////////////////////////////////
    public function obtenerDetalle($id_repuesto)
    {
        $sql = "SELECT 
                    d.id_detalle_repuesto,
                    d.serie,
                    d.id_estado_repuesto,
                    e.nombre AS estado,
                    d.id_maquina_actual
                FROM electronicas.RepuestosDetalle d
                INNER JOIN electronicas.EstadoRepuestos e
                    ON d.id_estado_repuesto = e.id_estado
                WHERE d.id_repuesto = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_repuesto]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // ✏️ EDITAR DETALLE
    //////////////////////////////////////////////////////////
    public function editarDetalle($data)
    {
        $sql = "UPDATE electronicas.RepuestosDetalle SET
                    serie = ?,
                    id_estado_repuesto = ?,
                    id_maquina_actual = ?
                WHERE id_detalle_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["serie"],
            $data["estado"],
            $data["maquina"],
            $data["id"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // 🔄 CAMBIAR ESTADO
    //////////////////////////////////////////////////////////
    public function cambiarEstadoDetalle($data)
    {
        $sql = "UPDATE electronicas.RepuestosDetalle SET
                    id_estado_repuesto = ?,
                    id_maquina_actual = ?
                WHERE id_detalle_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["estado"],
            $data["maquina"],
            $data["id"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // 🔧 AUXILIARES
    //////////////////////////////////////////////////////////

    public function obtenerStock($id)
    {
        $sql = "SELECT stock FROM electronicas.Repuestos WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetchColumn() ?? 0;
    }

    public function actualizarStock($id, $stock)
    {
        $sql = "UPDATE electronicas.Repuestos SET stock = ? WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$stock, $id]);
    }
    //////////////////////////////////////////////////////////
    // 📦 LISTAR PROVEEDORES
    //////////////////////////////////////////////////////////
    public function listarProveedores()
    {
        $sql = "SELECT id_proveedor, nombre 
            FROM electronicas.Proveedores
            WHERE estado = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listarTipos()
    {
        $sql = "SELECT id_tipo, nombre FROM electronicas.TiposRepuesto";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listarMarcas()
    {
        $sql = "SELECT id_marca, nombre FROM electronicas.Marcas";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listarModelos($id_marca, $id_tipo_modelo)
    {
        $sql = "SELECT id_modelo, nombre
            FROM electronicas.Modelos
            WHERE id_marca = ?
              AND id_tipo_modelo = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_marca, $id_tipo_modelo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function entradaSerie($id_repuesto, $series)
    {
        $errores = [];
        $insertadas = 0;

        foreach ($series as $serie) {

            $serie = trim($serie);
            if ($serie == "") continue;

            // validar duplicado
            $sqlCheck = "SELECT COUNT(*) FROM electronicas.RepuestosDetalle WHERE serie = ?";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([$serie]);

            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = $serie;
                continue;
            }

            // insertar
            $sql = "INSERT INTO electronicas.RepuestosDetalle
                (id_repuesto, serie, id_estado_repuesto)
                VALUES (?, ?, 1)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_repuesto, $serie]);

            $insertadas++;
        }

        return [
            "ok" => true,
            "insertadas" => $insertadas,
            "duplicadas" => $errores
        ];
    }
    public function eliminarRepuesto($id)
    {
        $sql = "UPDATE electronicas.Repuestos
            SET id_estado_repuesto = 5 -- Desechado
            WHERE id_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([$id]);
    }
    public function obtenerSeriesDisponibles($id_repuesto)
    {
        $sql = "SELECT 
                id_detalle_repuesto,
                serie
            FROM electronicas.RepuestosDetalle
            WHERE id_repuesto = ?
              AND id_estado_repuesto = 1
            ORDER BY serie";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_repuesto]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function salidaRepuesto($data)
    {
        $stockActual = $this->obtenerStock($data["id_repuesto"]);
        $cantidad = (int)$data["cantidad"];

        if ($cantidad <= 0) {
            return [
                "error" => true,
                "mensaje" => "Cantidad inválida"
            ];
        }

        if ($stockActual < $cantidad) {
            return [
                "error" => true,
                "mensaje" => "Stock insuficiente"
            ];
        }

        $nuevoStock = $stockActual - $cantidad;

        $sql = "INSERT INTO electronicas.MovimientosRepuestos
            (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
            VALUES (?, 2, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $data["id_repuesto"],
            $cantidad,
            $data["costo"],
            $stockActual,
            $nuevoStock,
            $data["id_maquina"],
            $data["referencia"]
        ]);

        $this->actualizarStock($data["id_repuesto"], $nuevoStock);

        return [
            "ok" => true,
            "stock_anterior" => $stockActual,
            "stock_nuevo" => $nuevoStock
        ];
    }
    public function salidaSerie($id_repuesto, $id_maquina, $series, $referencia = 'MANTENIMIENTO')
    {
        $procesadas = 0;
        $errores = [];

        foreach ($series as $id_detalle) {

            $sqlVal = "SELECT 
                        id_detalle_repuesto,
                        serie,
                        id_estado_repuesto
                   FROM electronicas.RepuestosDetalle
                   WHERE id_detalle_repuesto = ?
                     AND id_repuesto = ?";
            $stmtVal = $this->conn->prepare($sqlVal);
            $stmtVal->execute([$id_detalle, $id_repuesto]);
            $detalle = $stmtVal->fetch(PDO::FETCH_ASSOC);

            if (!$detalle) {
                $errores[] = "Detalle no encontrado: " . $id_detalle;
                continue;
            }

            if ((int)$detalle["id_estado_repuesto"] !== 1) {
                $errores[] = "La serie no está disponible: " . $detalle["serie"];
                continue;
            }

            $sqlUpd = "UPDATE electronicas.RepuestosDetalle
                   SET id_estado_repuesto = 2,
                       id_maquina_actual = ?
                   WHERE id_detalle_repuesto = ?";
            $stmtUpd = $this->conn->prepare($sqlUpd);
            $stmtUpd->execute([$id_maquina, $id_detalle]);

            $sqlMov = "INSERT INTO electronicas.MovimientosRepuestos
                   (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad, costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
                   VALUES (?, ?, 2, 1, 0, 0, 0, ?, ?)";
            $stmtMov = $this->conn->prepare($sqlMov);
            $stmtMov->execute([
                $id_repuesto,
                $id_detalle,
                $id_maquina,
                $referencia
            ]);

            $procesadas++;
        }

        if ($procesadas === 0) {
            return [
                "error" => true,
                "mensaje" => "No se pudo procesar ninguna serie",
                "errores" => $errores
            ];
        }

        return [
            "ok" => true,
            "procesadas" => $procesadas,
            "errores" => $errores
        ];
    }
    
}
