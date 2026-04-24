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
    // LISTAR REPUESTOS
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
                r.stock_minimo,
                r.costo_promedio,
                p.nombre AS proveedor,
                r.id_tipo,
                r.id_marca,
                r.id_modelo,
                r.maneja_serie,
                r.id_proveedor,
                r.comentarios,
                t.nombre  AS tipo,
                m.nombre  AS marca,
                mo.nombre AS modelo,
                r.id_divisa,
                ISNULL(dv.simbolo, dpred.simbolo) AS divisa_simbolo,
                ISNULL(dv.codigo,  dpred.codigo)  AS divisa_codigo
            FROM electronicas.Repuestos r
            INNER JOIN electronicas.Proveedores p
                ON r.id_proveedor = p.id_proveedor
            LEFT JOIN electronicas.TiposRepuesto t
                ON r.id_tipo = t.id_tipo
            LEFT JOIN electronicas.Marcas m
                ON r.id_marca = m.id_marca
            LEFT JOIN electronicas.Modelos mo
                ON r.id_modelo = mo.id_modelo
            LEFT JOIN electronicas.Divisas dv
                ON dv.id_divisa = r.id_divisa
            CROSS APPLY (
                SELECT TOP 1 simbolo, codigo
                FROM electronicas.Divisas
                WHERE predeterminada = 1 AND activo = 1
            ) dpred
            WHERE r.id_estado != 5";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // GUARDAR REPUESTO
    //////////////////////////////////////////////////////////
    public function guardarRepuesto($data)
    {
        // Validar numero_parte duplicado (solo si se ingresó)
        if (!empty($data["numero_parte"])) {
            $sqlCheck = "SELECT COUNT(*) FROM electronicas.Repuestos
                         WHERE numero_parte = ? AND id_estado != 5";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([$data["numero_parte"]]);

            if ($stmtCheck->fetchColumn() > 0) {
                return ["error" => true, "mensaje" => "El número de parte '{$data['numero_parte']}' ya existe en otro repuesto"];
            }
        }

        $sql = "INSERT INTO electronicas.Repuestos
                    (nombre, numero_parte, id_proveedor, costo_promedio, stock_minimo, comentarios,
                     id_tipo, id_marca, id_modelo, maneja_serie, id_divisa, stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["nombre"],
            $data["numero_parte"],
            $data["id_proveedor"],
            $data["costo"],
            $data["stock_minimo"],
            $data["comentarios"],
            $data["id_tipo"],
            $data["id_marca"],
            $data["id_modelo"],
            $data["maneja_serie"],
            $data["id_divisa"] ?: null
        ]);
    }

    //////////////////////////////////////////////////////////
    // EDITAR REPUESTO
    //////////////////////////////////////////////////////////
    public function editarRepuesto($data)
    {
        $sql = "SELECT maneja_serie, stock FROM electronicas.Repuestos WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$data["id_repuesto"]]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$actual) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }

        // Validar numero_parte duplicado en otros repuestos
        if (!empty($data["numero_parte"])) {
            $sqlCheck = "SELECT COUNT(*) FROM electronicas.Repuestos
                         WHERE numero_parte = ? AND id_repuesto != ? AND id_estado != 5";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([$data["numero_parte"], $data["id_repuesto"]]);

            if ($stmtCheck->fetchColumn() > 0) {
                return ["error" => true, "mensaje" => "El número de parte '{$data['numero_parte']}' ya existe en otro repuesto"];
            }
        }

        // Validar cambio de tipo de control
        if ($actual["maneja_serie"] != $data["maneja_serie"]) {

            if ($actual["stock"] > 0) {
                return ["error" => true, "mensaje" => "No puedes cambiar el tipo de control con stock existente"];
            }

            $sqlDet = "SELECT COUNT(*) FROM electronicas.RepuestosDetalle WHERE id_repuesto = ?";
            $stmtDet = $this->conn->prepare($sqlDet);
            $stmtDet->execute([$data["id_repuesto"]]);

            if ($stmtDet->fetchColumn() > 0) {
                return ["error" => true, "mensaje" => "No puedes cambiar el tipo de control porque ya existen series registradas"];
            }
        }

        $sql = "UPDATE electronicas.Repuestos SET
                    nombre         = ?,
                    numero_parte   = ?,
                    id_proveedor   = ?,
                    costo_promedio = ?,
                    stock_minimo   = ?,
                    comentarios    = ?,
                    id_tipo        = ?,
                    id_marca       = ?,
                    id_modelo      = ?,
                    maneja_serie   = ?,
                    id_divisa      = ?
                WHERE id_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["nombre"],
            $data["numero_parte"],
            $data["id_proveedor"],
            $data["costo"],
            $data["stock_minimo"],
            $data["comentarios"],
            $data["id_tipo"],
            $data["id_marca"],
            $data["id_modelo"],
            $data["maneja_serie"],
            $data["id_divisa"] ?: null,
            $data["id_repuesto"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // ELIMINAR (soft delete)
    //////////////////////////////////////////////////////////
    public function eliminarRepuesto($id)
    {
        $sql = "UPDATE electronicas.Repuestos SET id_estado = 5 WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$id]);
    }

    //////////////////////////////////////////////////////////
    // ENTRADA (solo para repuestos por cantidad)
    //////////////////////////////////////////////////////////
    public function entradaRepuesto($data)
    {
        // Bloquear si el repuesto maneja serie
        $rep = $this->obtenerInfoRepuesto($data["id_repuesto"]);
        if (!$rep) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }
        if ((int)$rep["maneja_serie"] === 1) {
            return ["error" => true, "mensaje" => "Este repuesto se controla por serie. Usa 'Entrada por serie'"];
        }

        $stockActual = (int)$rep["stock"];
        $nuevoStock  = $stockActual + (int)$data["cantidad"];

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

        $this->actualizarStock($data["id_repuesto"], $nuevoStock);

        return true;
    }

    //////////////////////////////////////////////////////////
    // ENTRADA POR SERIE (solo para repuestos por serie)
    //////////////////////////////////////////////////////////
    public function entradaSerie($id_repuesto, $series)
    {
        // Bloquear si el repuesto NO maneja serie
        $rep = $this->obtenerInfoRepuesto($id_repuesto);
        if (!$rep) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }
        if ((int)$rep["maneja_serie"] === 0) {
            return ["error" => true, "mensaje" => "Este repuesto se controla por cantidad. Usa 'Entrada por cantidad'"];
        }

        $errores    = [];
        $insertadas = 0;

        foreach ($series as $serie) {

            $serie = trim($serie);
            if ($serie === "") continue;

            $sqlCheck = "SELECT COUNT(*) FROM electronicas.RepuestosDetalle WHERE serie = ?";
            $stmtCheck = $this->conn->prepare($sqlCheck);
            $stmtCheck->execute([$serie]);

            if ($stmtCheck->fetchColumn() > 0) {
                $errores[] = $serie;
                continue;
            }

            $sql = "INSERT INTO electronicas.RepuestosDetalle
                        (id_repuesto, serie, id_estado_repuesto)
                    VALUES (?, ?, 1)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id_repuesto, $serie]);

            $insertadas++;
        }

        return [
            "ok"         => true,
            "insertadas" => $insertadas,
            "duplicadas" => $errores
        ];
    }

    //////////////////////////////////////////////////////////
    // SALIDA (solo para repuestos por cantidad)
    //////////////////////////////////////////////////////////
    public function salidaRepuesto($data)
    {
        // Bloquear si el repuesto maneja serie
        $rep = $this->obtenerInfoRepuesto($data["id_repuesto"]);
        if (!$rep) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }
        if ((int)$rep["maneja_serie"] === 1) {
            return ["error" => true, "mensaje" => "Este repuesto se controla por serie. Usa 'Salida por serie'"];
        }

        $stockActual = (int)$rep["stock"];
        $cantidad    = (int)$data["cantidad"];

        if ($cantidad <= 0) {
            return ["error" => true, "mensaje" => "Cantidad inválida"];
        }

        if ($stockActual < $cantidad) {
            return ["error" => true, "mensaje" => "Stock insuficiente (disponible: $stockActual)"];
        }

        $nuevoStock = $stockActual - $cantidad;

        $sql = "INSERT INTO electronicas.MovimientosRepuestos
                    (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                     stock_anterior, stock_nuevo, id_maquina, referencia)
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
            "ok"             => true,
            "stock_anterior" => $stockActual,
            "stock_nuevo"    => $nuevoStock
        ];
    }

    //////////////////////////////////////////////////////////
    // SALIDA POR SERIE (solo para repuestos por serie)
    //////////////////////////////////////////////////////////
    public function salidaSerie($id_repuesto, $id_maquina, $series, $referencia = 'MANTENIMIENTO')
    {
        // Bloquear si el repuesto NO maneja serie
        $rep = $this->obtenerInfoRepuesto($id_repuesto);
        if (!$rep) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }
        if ((int)$rep["maneja_serie"] === 0) {
            return ["error" => true, "mensaje" => "Este repuesto se controla por cantidad. Usa 'Salida por cantidad'"];
        }

        $procesadas = 0;
        $errores    = [];

        foreach ($series as $id_detalle) {

            $sqlVal = "SELECT id_detalle_repuesto, serie, id_estado_repuesto
                       FROM electronicas.RepuestosDetalle
                       WHERE id_detalle_repuesto = ? AND id_repuesto = ?";
            $stmtVal = $this->conn->prepare($sqlVal);
            $stmtVal->execute([$id_detalle, $id_repuesto]);
            $detalle = $stmtVal->fetch(PDO::FETCH_ASSOC);

            if (!$detalle) {
                $errores[] = "Serie no encontrada: " . $id_detalle;
                continue;
            }

            if ((int)$detalle["id_estado_repuesto"] !== 1) {
                $errores[] = "La serie no está disponible: " . $detalle["serie"];
                continue;
            }

            $sqlUpd = "UPDATE electronicas.RepuestosDetalle
                       SET id_estado_repuesto = 2, id_maquina_actual = ?
                       WHERE id_detalle_repuesto = ?";
            $stmtUpd = $this->conn->prepare($sqlUpd);
            $stmtUpd->execute([$id_maquina, $id_detalle]);

            $sqlMov = "INSERT INTO electronicas.MovimientosRepuestos
                           (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                            costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
                       VALUES (?, ?, 2, 1, 0, 0, 0, ?, ?)";
            $stmtMov = $this->conn->prepare($sqlMov);
            $stmtMov->execute([$id_repuesto, $id_detalle, $id_maquina, $referencia]);

            $procesadas++;
        }

        if ($procesadas === 0) {
            return ["error" => true, "mensaje" => "No se pudo procesar ninguna serie", "errores" => $errores];
        }

        return ["ok" => true, "procesadas" => $procesadas, "errores" => $errores];
    }

    //////////////////////////////////////////////////////////
    // KARDEX
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
    // DETALLE POR SERIE
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
    // EDITAR DETALLE
    //////////////////////////////////////////////////////////
    public function editarDetalle($data)
    {
        $sql = "UPDATE electronicas.RepuestosDetalle SET
                    serie              = ?,
                    id_estado_repuesto = ?,
                    id_maquina_actual  = ?
                WHERE id_detalle_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["serie"],
            $data["estado"],
            $data["maquina"] ?: null,
            $data["id"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // CAMBIAR ESTADO DETALLE
    //////////////////////////////////////////////////////////
    public function cambiarEstadoDetalle($data)
    {
        $sql = "UPDATE electronicas.RepuestosDetalle SET
                    id_estado_repuesto = ?,
                    id_maquina_actual  = ?
                WHERE id_detalle_repuesto = ?";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            $data["estado"],
            $data["maquina"] ?: null,
            $data["id"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // CATÁLOGOS
    //////////////////////////////////////////////////////////
    public function listarProveedores()
    {
        $sql = "SELECT id_proveedor, nombre FROM electronicas.Proveedores WHERE estado = 1";
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
                WHERE id_marca = ? AND id_tipo_modelo = ?";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_marca, $id_tipo_modelo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarEstados()
    {
        $sql = "SELECT id_estado, nombre FROM electronicas.EstadoRepuestos ORDER BY id_estado";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerSeriesDisponibles($id_repuesto)
    {
        $sql = "SELECT id_detalle_repuesto, serie
                FROM electronicas.RepuestosDetalle
                WHERE id_repuesto = ? AND id_estado_repuesto = 1
                ORDER BY serie";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_repuesto]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // AUXILIARES
    //////////////////////////////////////////////////////////
    public function obtenerInfoRepuesto($id)
    {
        $sql = "SELECT id_repuesto, maneja_serie, stock FROM electronicas.Repuestos WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

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
}
