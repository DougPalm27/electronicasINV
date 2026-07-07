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
                p.nombre  AS proveedor,
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
                r.id_ubicacion,
                u.nombre  AS ubicacion,
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
            LEFT JOIN electronicas.Ubicaciones u
                ON u.id_ubicacion = r.id_ubicacion
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
                    (nombre, numero_parte, id_proveedor, costo_promedio, stock_minimo,
                     comentarios, id_tipo, id_marca, id_modelo, maneja_serie, id_divisa,
                     id_ubicacion, stock)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";

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
            $data["id_divisa"]      ?: null,
            $data["id_ubicacion"]   ?: null
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
                    id_divisa      = ?,
                    id_ubicacion   = ?
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
            $data["id_divisa"]    ?: null,
            $data["id_ubicacion"] ?: null,
            $data["id_repuesto"]
        ]);
    }

    //////////////////////////////////////////////////////////
    // ELIMINAR (soft delete)
    //////////////////////////////////////////////////////////
    public function eliminarRepuesto($id)
    {
        $rep = $this->obtenerInfoRepuesto($id);
        if (!$rep) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }

        // Bloquear si aún hay existencias
        if ((int)$rep["maneja_serie"] === 1) {
            $stmt = $this->conn->prepare(
                "SELECT COUNT(*) FROM electronicas.RepuestosDetalle
                 WHERE id_repuesto = ? AND id_estado_repuesto = 1"
            );
            $stmt->execute([$id]);
            $disponibles = (int)$stmt->fetchColumn();
            if ($disponibles > 0) {
                return ["error" => true, "mensaje" => "No se puede desechar: tiene $disponibles serie(s) disponibles en inventario"];
            }
        } elseif ((int)$rep["stock"] > 0) {
            return ["error" => true, "mensaje" => "No se puede desechar: tiene stock existente ({$rep['stock']} unidades)"];
        }

        // Bloquear si está en solicitudes de repuestos pendientes
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*)
             FROM electronicas.SolicitudesDetalle sd
             INNER JOIN electronicas.SolicitudesMaquinas sm
                 ON sm.id_solicitud_maquina = sd.id_solicitud_maquina
             INNER JOIN electronicas.SolicitudesRepuestos sr
                 ON sr.id_solicitud = sm.id_solicitud
             WHERE sd.id_repuesto = ? AND sr.estado = 'Pendiente'"
        );
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede desechar: está incluido en solicitudes de repuestos pendientes"];
        }

        // Bloquear si está en solicitudes de compra activas
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*)
             FROM electronicas.SolicitudesCompraDetalle det
             INNER JOIN electronicas.SolicitudesCompra sc
                 ON sc.id_solicitud_compra = det.id_solicitud_compra
             WHERE det.id_repuesto = ?
               AND sc.estado IN ('Borrador','Pendiente','Aprobada','Ordenada','Recibida parcial')"
        );
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ["error" => true, "mensaje" => "No se puede desechar: está incluido en solicitudes de compra activas"];
        }

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

        $cantidad = (int)$data["cantidad"];
        $costo    = (float)$data["costo"];

        $this->conn->beginTransaction();
        try {
            // Incremento atómico: evita perder movimientos concurrentes.
            // Si la entrada trae costo, recalcula el promedio ponderado sobre
            // los valores vigentes de la fila (mismo criterio que la recepción de OC)
            if ($costo > 0) {
                $stmtUpd = $this->conn->prepare(
                    "UPDATE electronicas.Repuestos
                     SET costo_promedio = CASE
                             WHEN stock > 0 THEN ROUND(
                                 (stock * costo_promedio + CAST(? AS INT) * CAST(? AS DECIMAL(18,6)))
                                 / (stock + CAST(? AS INT)), 4)
                             ELSE CAST(? AS DECIMAL(18,6))
                         END,
                         stock = stock + CAST(? AS INT)
                     OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                     WHERE id_repuesto = ?"
                );
                $stmtUpd->execute([
                    $cantidad, $costo, $cantidad,
                    $costo, $cantidad, $data["id_repuesto"]
                ]);
            } else {
                $stmtUpd = $this->conn->prepare(
                    "UPDATE electronicas.Repuestos
                     SET stock = stock + ?
                     OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                     WHERE id_repuesto = ?"
                );
                $stmtUpd->execute([$cantidad, $data["id_repuesto"]]);
            }
            $stocks = $stmtUpd->fetch(PDO::FETCH_ASSOC);
            if (!$stocks) {
                throw new RuntimeException("Repuesto no encontrado");
            }

            $sql = "INSERT INTO electronicas.MovimientosRepuestos
                        (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                         stock_anterior, stock_nuevo, referencia, id_proveedor, tipo_entrada)
                    VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data["id_repuesto"],
                $cantidad,
                $data["costo"],
                $stocks["stock_anterior"],
                $stocks["stock_nuevo"],
                $data["referencia"],
                $data["id_proveedor"] ?: null,
                $data["tipo_entrada"]  ?? 'Compra',
            ]);

            $this->conn->commit();
            return true;

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    //////////////////////////////////////////////////////////
    // ENTRADA POR SERIE (solo para repuestos por serie)
    //////////////////////////////////////////////////////////
    public function entradaSerie($id_repuesto, $series, $id_proveedor = null, $tipo_entrada = 'Compra')
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

        $this->conn->beginTransaction();
        try {
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
                        OUTPUT INSERTED.id_detalle_repuesto
                        VALUES (?, ?, 1)";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$id_repuesto, $serie]);
                $id_det_rep = (int)$stmt->fetchColumn();

                // Saldo corrido: conteo de series disponibles tras el ingreso
                $saldoNuevo = $this->contarSeriesDisponibles($id_repuesto);

                // Movimiento de entrada en el kardex (una fila por serie)
                $this->conn->prepare(
                    "INSERT INTO electronicas.MovimientosRepuestos
                        (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                         costo_unitario, stock_anterior, stock_nuevo, referencia,
                         id_proveedor, tipo_entrada)
                     VALUES (?, ?, 1, 1, 0, ?, ?, 'ENTRADA SERIE', ?, ?)"
                )->execute([
                    $id_repuesto, $id_det_rep,
                    $saldoNuevo - 1, $saldoNuevo,
                    $id_proveedor ?: null, $tipo_entrada
                ]);

                $insertadas++;
            }

            $this->conn->commit();

            return [
                "ok"         => true,
                "insertadas" => $insertadas,
                "duplicadas" => $errores
            ];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
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

        $cantidad = (int)$data["cantidad"];

        if ($cantidad <= 0) {
            return ["error" => true, "mensaje" => "Cantidad inválida"];
        }

        $this->conn->beginTransaction();
        try {
            // Descuento atómico: la condición stock >= cantidad evita stock negativo
            // ante operaciones concurrentes. La salida se valora al costo promedio
            // vigente (método de promedio ponderado), no a un costo digitado.
            $stmtUpd = $this->conn->prepare(
                "UPDATE electronicas.Repuestos
                 SET stock = stock - ?
                 OUTPUT DELETED.stock          AS stock_anterior,
                        INSERTED.stock         AS stock_nuevo,
                        DELETED.costo_promedio AS costo_promedio
                 WHERE id_repuesto = ? AND stock >= ?"
            );
            $stmtUpd->execute([$cantidad, $data["id_repuesto"], $cantidad]);
            $stocks = $stmtUpd->fetch(PDO::FETCH_ASSOC);

            if (!$stocks) {
                $this->conn->rollBack();
                $disponible = (int)$this->obtenerStock($data["id_repuesto"]);
                return ["error" => true, "mensaje" => "Stock insuficiente (disponible: $disponible)"];
            }

            $sql = "INSERT INTO electronicas.MovimientosRepuestos
                        (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                         stock_anterior, stock_nuevo, id_maquina, referencia)
                    VALUES (?, 2, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $data["id_repuesto"],
                $cantidad,
                $stocks["costo_promedio"],
                $stocks["stock_anterior"],
                $stocks["stock_nuevo"],
                $data["id_maquina"],
                $data["referencia"]
            ]);

            $this->conn->commit();

            return [
                "ok"             => true,
                "stock_anterior" => (int)$stocks["stock_anterior"],
                "stock_nuevo"    => (int)$stocks["stock_nuevo"]
            ];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    //////////////////////////////////////////////////////////
    // SALIDA POR SERIE (solo para repuestos por serie)
    //////////////////////////////////////////////////////////
    public function salidaSerie($id_repuesto, $id_maquina, $series, $referencia = 'SALIDA MANUAL')
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

        // Toda salida se valora al costo promedio vigente del repuesto
        $costoSalida = (float)($rep['costo_promedio'] ?? 0);

        $this->conn->beginTransaction();
        try {
            foreach ($series as $id_detalle) {

                // Cambio de estado atómico: solo procesa si la serie sigue disponible,
                // evita que dos salidas concurrentes tomen la misma serie
                $sqlUpd = "UPDATE electronicas.RepuestosDetalle
                           SET id_estado_repuesto = 2, id_maquina_actual = ?
                           OUTPUT INSERTED.serie
                           WHERE id_detalle_repuesto = ? AND id_repuesto = ?
                             AND id_estado_repuesto = 1";
                $stmtUpd = $this->conn->prepare($sqlUpd);
                $stmtUpd->execute([$id_maquina, $id_detalle, $id_repuesto]);
                $fila = $stmtUpd->fetch(PDO::FETCH_ASSOC);

                if (!$fila) {
                    $errores[] = "Serie no encontrada o no disponible: " . $id_detalle;
                    continue;
                }

                // Saldo corrido: conteo de series disponibles tras la salida
                $saldoNuevo = $this->contarSeriesDisponibles($id_repuesto);

                $sqlMov = "INSERT INTO electronicas.MovimientosRepuestos
                               (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                                costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
                           VALUES (?, ?, 2, 1, ?, ?, ?, ?, ?)";
                $stmtMov = $this->conn->prepare($sqlMov);
                $stmtMov->execute([
                    $id_repuesto, $id_detalle, $costoSalida,
                    $saldoNuevo + 1, $saldoNuevo,
                    $id_maquina, $referencia
                ]);

                $procesadas++;
            }

            if ($procesadas === 0) {
                $this->conn->rollBack();
                return ["error" => true, "mensaje" => "No se pudo procesar ninguna serie", "errores" => $errores];
            }

            $this->conn->commit();
            return ["ok" => true, "procesadas" => $procesadas, "errores" => $errores];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    //////////////////////////////////////////////////////////
    // AJUSTE NEGATIVO (merma, pérdida, daño, conteo físico)
    // Salida sin máquina destino; requiere motivo.
    //////////////////////////////////////////////////////////
    public function ajusteNegativo($id_repuesto, $cantidad, $motivo)
    {
        $rep = $this->obtenerInfoRepuesto($id_repuesto);
        if (!$rep) {
            return ["error" => true, "mensaje" => "Repuesto no encontrado"];
        }
        if ((int)$rep["maneja_serie"] === 1) {
            return ["error" => true, "mensaje" => "Este repuesto se controla por serie. Da de baja la serie desde 'Ver series'"];
        }

        $cantidad = (int)$cantidad;
        if ($cantidad <= 0) {
            return ["error" => true, "mensaje" => "Cantidad inválida"];
        }

        $this->conn->beginTransaction();
        try {
            $stmtUpd = $this->conn->prepare(
                "UPDATE electronicas.Repuestos
                 SET stock = stock - ?
                 OUTPUT DELETED.stock          AS stock_anterior,
                        INSERTED.stock         AS stock_nuevo,
                        DELETED.costo_promedio AS costo_promedio
                 WHERE id_repuesto = ? AND stock >= ?"
            );
            $stmtUpd->execute([$cantidad, $id_repuesto, $cantidad]);
            $stocks = $stmtUpd->fetch(PDO::FETCH_ASSOC);

            if (!$stocks) {
                $this->conn->rollBack();
                $disponible = (int)$this->obtenerStock($id_repuesto);
                return ["error" => true, "mensaje" => "Stock insuficiente (disponible: $disponible)"];
            }

            $this->conn->prepare(
                "INSERT INTO electronicas.MovimientosRepuestos
                    (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                     stock_anterior, stock_nuevo, id_maquina, referencia, observaciones)
                 VALUES (?, 2, ?, ?, ?, ?, NULL, 'AJUSTE INVENTARIO', ?)"
            )->execute([
                $id_repuesto,
                $cantidad,
                $stocks["costo_promedio"],
                $stocks["stock_anterior"],
                $stocks["stock_nuevo"],
                $motivo
            ]);

            $this->conn->commit();

            return [
                "ok"          => true,
                "stock_nuevo" => (int)$stocks["stock_nuevo"]
            ];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    //////////////////////////////////////////////////////////
    // KARDEX
    //////////////////////////////////////////////////////////
    public function obtenerKardex($id, $desde = null, $hasta = null)
    {
        // Saldo inicial del período: último saldo registrado antes de la fecha de inicio
        $saldoInicial = 0;
        if ($desde) {
            $stmtSI = $this->conn->prepare(
                "SELECT TOP 1 stock_nuevo
                 FROM electronicas.MovimientosRepuestos
                 WHERE id_repuesto = ? AND fecha_movimiento < ?
                 ORDER BY fecha_movimiento DESC, id_movimiento DESC"
            );
            $stmtSI->execute([$id, $desde]);
            $saldoInicial = (int)($stmtSI->fetchColumn() ?: 0);
        }

        $sql = "SELECT
                    m.id_movimiento,
                    FORMAT(m.fecha_movimiento, 'dd/MM/yyyy HH:mm') AS fecha_movimiento,
                    t.nombre                           AS tipo,
                    m.id_tipo_movimiento,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    m.costo_unitario,
                    m.referencia,
                    ISNULL(m.observaciones, '')        AS observaciones,
                    m.anulado,
                    ISNULL(m.tipo_entrada, '')         AS tipo_entrada,
                    ISNULL(p.nombre, '')               AS proveedor,
                    ISNULL(u.nombre, '')               AS usuario
                FROM electronicas.MovimientosRepuestos m
                INNER JOIN electronicas.TiposMovimientoRepuesto t
                    ON m.id_tipo_movimiento = t.id_tipo_movimiento
                LEFT  JOIN electronicas.Proveedores p
                    ON p.id_proveedor = m.id_proveedor
                LEFT  JOIN electronicas.Usuarios u
                    ON u.id_usuario = m.id_usuario
                WHERE m.id_repuesto = ?";

        $params = [$id];

        if ($desde) {
            $sql .= " AND m.fecha_movimiento >= ?";
            $params[] = $desde;
        }
        if ($hasta) {
            // Inclusivo: hasta el final del día indicado
            $sql .= " AND m.fecha_movimiento < DATEADD(DAY, 1, CAST(? AS DATE))";
            $params[] = $hasta;
        }

        $sql .= " ORDER BY m.fecha_movimiento ASC, m.id_movimiento ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return [
            "saldo_inicial" => $saldoInicial,
            "movimientos"   => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    //////////////////////////////////////////////////////////
    // ANULAR MOVIMIENTO
    //////////////////////////////////////////////////////////
    public function anularMovimiento(int $id_movimiento): array
    {
        // 1. Obtener el movimiento original
        $sqlGet = "SELECT m.id_movimiento, m.id_repuesto, m.id_tipo_movimiento,
                          m.cantidad, m.costo_unitario, m.anulado, m.referencia,
                          r.stock, r.maneja_serie
                   FROM electronicas.MovimientosRepuestos m
                   INNER JOIN electronicas.Repuestos r ON r.id_repuesto = m.id_repuesto
                   WHERE m.id_movimiento = ?";
        $stmt = $this->conn->prepare($sqlGet);
        $stmt->execute([$id_movimiento]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) {
            return ['error' => true, 'mensaje' => 'Movimiento no encontrado.'];
        }
        if ((int)$mov['anulado'] === 1) {
            return ['error' => true, 'mensaje' => 'Este movimiento ya fue anulado.'];
        }
        // Solo se permite anular entradas (1) y salidas (2)
        if (!in_array((int)$mov['id_tipo_movimiento'], [1, 2])) {
            return ['error' => true, 'mensaje' => 'Solo se pueden anular movimientos de Entrada o Salida.'];
        }
        if ((int)$mov['maneja_serie'] === 1) {
            return ['error' => true, 'mensaje' => 'Los movimientos por serie no se pueden anular desde aquí.'];
        }

        $referencia = (string)($mov['referencia'] ?? '');

        // Las salidas de solicitudes aprobadas generan mantenimiento + instalación:
        // deben revertirse anulando el mantenimiento, no desde el kardex
        if (strpos($referencia, 'SOL-') === 0) {
            return [
                'error'   => true,
                'mensaje' => 'Este movimiento proviene de una solicitud aprobada. Para revertirlo, anula el mantenimiento generado desde el módulo de Mantenimientos.'
            ];
        }

        $cantidad     = (int)$mov['cantidad'];
        $id_repuesto  = (int)$mov['id_repuesto'];
        $esEntrada    = (int)$mov['id_tipo_movimiento'] === 1;
        $tipoInverso  = $esEntrada ? 2 : 1;

        $this->conn->beginTransaction();
        try {
            // 2. Marcar original como anulado — la condición anulado = 0 evita
            //    que dos anulaciones concurrentes del mismo movimiento pasen ambas
            $sqlAnul = "UPDATE electronicas.MovimientosRepuestos
                        SET anulado = 1
                        WHERE id_movimiento = ? AND anulado = 0";
            $stmtAnul = $this->conn->prepare($sqlAnul);
            $stmtAnul->execute([$id_movimiento]);
            if ($stmtAnul->rowCount() === 0) {
                throw new RuntimeException('Este movimiento ya fue anulado.');
            }

            // 3. Revertir stock de forma atómica
            if ($esEntrada) {
                // Anular una entrada = restar del stock (sin dejarlo negativo)
                $sqlStock = "UPDATE electronicas.Repuestos
                             SET stock = stock - ?
                             OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                             WHERE id_repuesto = ? AND stock >= ?";
                $params = [$cantidad, $id_repuesto, $cantidad];
            } else {
                // Anular una salida = sumar al stock
                $sqlStock = "UPDATE electronicas.Repuestos
                             SET stock = stock + ?
                             OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                             WHERE id_repuesto = ?";
                $params = [$cantidad, $id_repuesto];
            }
            $stmtStock = $this->conn->prepare($sqlStock);
            $stmtStock->execute($params);
            $stocks = $stmtStock->fetch(PDO::FETCH_ASSOC);

            if (!$stocks) {
                $disponible = (int)$this->obtenerStock($id_repuesto);
                throw new RuntimeException(
                    "No se puede anular: el stock actual ($disponible) es menor que la cantidad del movimiento ($cantidad)."
                );
            }

            // 4. Si la entrada proviene de una recepción de OC, revertir también
            //    lo recibido en la solicitud de compra (mantiene ambos módulos en sincronía)
            if ($esEntrada && preg_match('/^OC-0*(\d+)/', $referencia, $mOC)) {
                $id_sc = (int)$mOC[1];

                $stmtDet = $this->conn->prepare(
                    "SELECT TOP 1 id_detalle
                     FROM electronicas.SolicitudesCompraDetalle
                     WHERE id_solicitud_compra = ? AND id_repuesto = ?
                       AND cantidad_recibida >= ?
                     ORDER BY id_detalle"
                );
                $stmtDet->execute([$id_sc, $id_repuesto, $cantidad]);
                $id_det = $stmtDet->fetchColumn();

                if (!$id_det) {
                    throw new RuntimeException(
                        "No se encontró el ítem de la orden de compra #$id_sc con cantidad recibida suficiente para revertir."
                    );
                }

                $this->conn->prepare(
                    "UPDATE electronicas.SolicitudesCompraDetalle
                     SET cantidad_recibida = cantidad_recibida - ?
                     WHERE id_detalle = ?"
                )->execute([$cantidad, $id_det]);

                // La solicitud vuelve a tener pendientes
                $this->conn->prepare(
                    "UPDATE electronicas.SolicitudesCompra
                     SET estado = 'Recibida parcial'
                     WHERE id_solicitud_compra = ? AND estado = 'Recibida'"
                )->execute([$id_sc]);
            }

            // 5. Insertar movimiento inverso
            $observaciones = 'Anulación de ' . ($esEntrada ? 'entrada' : 'salida')
                . " #$id_movimiento" . ($referencia !== '' ? " (ref: $referencia)" : '');

            $sqlInv = "INSERT INTO electronicas.MovimientosRepuestos
                           (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                            stock_anterior, stock_nuevo, referencia, observaciones, anulado)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
            $this->conn->prepare($sqlInv)->execute([
                $id_repuesto,
                $tipoInverso,
                $cantidad,
                $mov['costo_unitario'],
                $stocks['stock_anterior'],
                $stocks['stock_nuevo'],
                'ANULACION #' . $id_movimiento,
                $observaciones,
            ]);

            $this->conn->commit();
            return ['ok' => true, 'stock_nuevo' => (int)$stocks['stock_nuevo']];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ['error' => true, 'mensaje' => $e->getMessage()];
        }
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
    // EDITAR / CAMBIAR ESTADO DETALLE
    // Si el cambio de estado altera la disponibilidad de la
    // serie, queda registrado como ajuste en el kardex.
    //////////////////////////////////////////////////////////
    public function editarDetalle($data)
    {
        return $this->actualizarDetalleConKardex($data, true);
    }

    public function cambiarEstadoDetalle($data)
    {
        return $this->actualizarDetalleConKardex($data, false);
    }

    private function actualizarDetalleConKardex(array $data, bool $incluirSerie)
    {
        // Estado actual de la serie
        $stmt = $this->conn->prepare(
            "SELECT id_repuesto, id_estado_repuesto, serie
             FROM electronicas.RepuestosDetalle
             WHERE id_detalle_repuesto = ?"
        );
        $stmt->execute([$data["id"]]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$actual) {
            return ["error" => true, "mensaje" => "Serie no encontrada"];
        }

        // Validar serie duplicada si se está renombrando
        if ($incluirSerie && $data["serie"] !== $actual["serie"]) {
            $chk = $this->conn->prepare(
                "SELECT COUNT(*) FROM electronicas.RepuestosDetalle
                 WHERE serie = ? AND id_detalle_repuesto != ?"
            );
            $chk->execute([$data["serie"], $data["id"]]);
            if ((int)$chk->fetchColumn() > 0) {
                return ["error" => true, "mensaje" => "La serie '{$data['serie']}' ya existe en otro registro"];
            }
        }

        $estadoAnt = (int)$actual["id_estado_repuesto"];
        $estadoNvo = (int)$data["estado"];

        $this->conn->beginTransaction();
        try {
            if ($incluirSerie) {
                $this->conn->prepare(
                    "UPDATE electronicas.RepuestosDetalle SET
                        serie              = ?,
                        id_estado_repuesto = ?,
                        id_maquina_actual  = ?
                     WHERE id_detalle_repuesto = ?"
                )->execute([$data["serie"], $estadoNvo, $data["maquina"] ?: null, $data["id"]]);
            } else {
                $this->conn->prepare(
                    "UPDATE electronicas.RepuestosDetalle SET
                        id_estado_repuesto = ?,
                        id_maquina_actual  = ?
                     WHERE id_detalle_repuesto = ?"
                )->execute([$estadoNvo, $data["maquina"] ?: null, $data["id"]]);
            }

            // Si el cambio altera la disponibilidad, dejar rastro en el kardex
            $eraDisponible = $estadoAnt === 1;
            $esDisponible  = $estadoNvo === 1;

            if ($eraDisponible !== $esDisponible) {
                $saldoNuevo = $this->contarSeriesDisponibles($actual["id_repuesto"]);
                $tipoMov    = $esDisponible ? 1 : 2;
                $saldoAnt   = $esDisponible ? $saldoNuevo - 1 : $saldoNuevo + 1;

                $stmtN = $this->conn->prepare(
                    "SELECT id_estado, nombre FROM electronicas.EstadoRepuestos
                     WHERE id_estado IN (?, ?)"
                );
                $stmtN->execute([$estadoAnt, $estadoNvo]);
                $nombres = $stmtN->fetchAll(PDO::FETCH_KEY_PAIR);

                $obs = "Cambio de estado manual de la serie '{$actual['serie']}': "
                     . ($nombres[$estadoAnt] ?? $estadoAnt) . " → " . ($nombres[$estadoNvo] ?? $estadoNvo);

                $this->conn->prepare(
                    "INSERT INTO electronicas.MovimientosRepuestos
                        (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                         costo_unitario, stock_anterior, stock_nuevo, id_maquina,
                         referencia, observaciones)
                     VALUES (?, ?, ?, 1, 0, ?, ?, ?, 'AJUSTE SERIE', ?)"
                )->execute([
                    $actual["id_repuesto"], $data["id"], $tipoMov,
                    $saldoAnt, $saldoNuevo,
                    $data["maquina"] ?: null, $obs
                ]);
            }

            $this->conn->commit();
            return true;

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    //////////////////////////////////////////////////////////
    // CATÁLOGOS
    //////////////////////////////////////////////////////////
    public function listarUbicaciones()
    {
        $stmt = $this->conn->query(
            "SELECT id_ubicacion, nombre FROM electronicas.Ubicaciones
             WHERE activo = 1 ORDER BY nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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
        $sql = "SELECT id_repuesto, maneja_serie, stock, costo_promedio
                FROM electronicas.Repuestos WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function contarSeriesDisponibles($id_repuesto): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.RepuestosDetalle
             WHERE id_repuesto = ? AND id_estado_repuesto = 1"
        );
        $stmt->execute([$id_repuesto]);
        return (int)$stmt->fetchColumn();
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
