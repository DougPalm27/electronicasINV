<?php

class mdlMantenimientos
{
    private $conn;

    public function __construct()
    {
        require_once "../../../../config/Connection.php";

        $conexion    = new Connection();
        $this->conn  = $conexion->dbConnect();
    }

    //////////////////////////////////////////////////////////
    // TABLA PRINCIPAL
    //////////////////////////////////////////////////////////
    public function listarMantenimientos()
    {
        $sql = "SELECT
                    m.id_mantenimiento,
                    mq.nombre                          AS maquina,
                    tm.nombre                          AS tipo,
                    ISNULL(t.nombre, 'Sin técnico')    AS tecnico,
                    m.fecha_mantenimiento,
                    m.proximo_mantenimiento,
                    m.descripcion
                FROM electronicas.Mantenimientos m
                INNER JOIN electronicas.Maquinas          mq ON m.id_maquina = mq.id_maquina
                INNER JOIN electronicas.TipoMantenimiento tm ON m.id_tipo    = tm.id_tipo
                LEFT  JOIN electronicas.Tecnicos           t ON m.id_tecnico = t.id_tecnico
                ORDER BY m.fecha_mantenimiento DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // DETALLE: repuestos usados en un mantenimiento
    //////////////////////////////////////////////////////////
    public function obtenerDetalleMantenimiento($id_mantenimiento)
    {
        $sql = "SELECT
                    r.nombre                                    AS repuesto,
                    mr.cantidad,
                    mr.costo_unitario,
                    (mr.cantidad * mr.costo_unitario)           AS subtotal,
                    CASE
                        WHEN r.maneja_serie = 1 THEN rd.serie
                        ELSE NULL
                    END                                         AS serie
                FROM electronicas.MantenimientoRepuestos mr
                INNER JOIN electronicas.Repuestos r
                        ON mr.id_repuesto = r.id_repuesto
                LEFT  JOIN electronicas.RepuestosDetalle rd
                        ON mr.id_detalle_repuesto = rd.id_detalle_repuesto
                WHERE mr.id_mantenimiento = ?
                ORDER BY r.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_mantenimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // SELECTS
    //////////////////////////////////////////////////////////
    public function listarMaquinas()
    {
        $sql = "SELECT id_maquina, nombre
                FROM electronicas.Maquinas
                WHERE id_estado = 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTipos()
    {
        $sql = "SELECT id_tipo, nombre
                FROM electronicas.TipoMantenimiento";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTecnicos()
    {
        $sql = "SELECT id_tecnico, nombre
                FROM electronicas.Tecnicos";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarRepuestosDisponibles()
    {
        $sql = "SELECT
                    r.id_repuesto,
                    r.nombre,
                    r.costo_promedio AS costo,
                    r.maneja_serie,
                    CASE
                        WHEN r.maneja_serie = 1 THEN (
                            SELECT COUNT(*)
                            FROM electronicas.RepuestosDetalle d
                            WHERE d.id_repuesto        = r.id_repuesto
                              AND d.id_estado_repuesto = 1
                        )
                        ELSE r.stock
                    END AS stock
                FROM electronicas.Repuestos r
                WHERE r.id_estado = 1
                ORDER BY r.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerSeriesDisponibles($id_repuesto)
    {
        $sql = "SELECT id_detalle_repuesto, serie
                FROM electronicas.RepuestosDetalle
                WHERE id_repuesto        = ?
                  AND id_estado_repuesto = 1
                ORDER BY serie";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_repuesto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //////////////////////////////////////////////////////////
    // STOCK PÚBLICO (para validación en frontend)
    //////////////////////////////////////////////////////////
    public function obtenerStockPublico($id_repuesto)
    {
        return $this->obtenerStock($id_repuesto);
    }

    //////////////////////////////////////////////////////////
    // GUARDAR MANTENIMIENTO + CONSUMO DE INVENTARIO
    //////////////////////////////////////////////////////////
    public function guardarMantenimiento($d)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO electronicas.Mantenimientos
                        (id_maquina, id_tipo, id_tecnico,
                         fecha_mantenimiento, proximo_mantenimiento, descripcion)
                    VALUES
                        (:id_maquina, :id_tipo, :id_tecnico,
                         :fecha_mantenimiento, :proximo_mantenimiento, :descripcion)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(":id_maquina",             $d->id_maquina);
            $stmt->bindValue(":id_tipo",                $d->id_tipo);
            $stmt->bindValue(":id_tecnico",             $d->id_tecnico ?: null);
            $stmt->bindValue(":fecha_mantenimiento",    $d->fecha_mantenimiento);
            $stmt->bindValue(":proximo_mantenimiento",  $d->proximo_mantenimiento ?: null);
            $stmt->bindValue(":descripcion",            $d->descripcion);
            $stmt->execute();

            $id_mantenimiento = $this->conn->lastInsertId();

            if (!empty($d->repuestos)) {
                foreach ($d->repuestos as $r) {
                    $r = (object)$r;
                    if (!isset($r->id_repuesto)) continue;

                    if ((int)$r->maneja_serie === 1) {
                        $this->procesarSalidaSerieDesdeMantenimiento($id_mantenimiento, $d->id_maquina, $r);
                    } else {
                        $this->procesarSalidaCantidadDesdeMantenimiento($id_mantenimiento, $d->id_maquina, $r);
                    }
                }
            }

            $this->conn->commit();
            return ["ok" => true, "id_mantenimiento" => $id_mantenimiento];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    //////////////////////////////////////////////////////////
    // SALIDA POR CANTIDAD
    //////////////////////////////////////////////////////////
    private function procesarSalidaCantidadDesdeMantenimiento($id_mantenimiento, $id_maquina, $r)
    {
        $cantidad = isset($r->cantidad) ? (int)$r->cantidad : 1;
        $costo    = isset($r->costo_unitario) ? (float)$r->costo_unitario : 0;

        if ($cantidad <= 0) {
            throw new Exception("Cantidad inválida para el repuesto ID {$r->id_repuesto}");
        }

        $stockActual = $this->obtenerStock($r->id_repuesto);

        if ($stockActual < $cantidad) {
            throw new Exception("Stock insuficiente para el repuesto ID {$r->id_repuesto}. Disponible: {$stockActual}");
        }

        $sqlDet = "INSERT INTO electronicas.MantenimientoRepuestos
                       (id_mantenimiento, id_repuesto, cantidad, costo_unitario)
                   VALUES
                       (:id_mantenimiento, :id_repuesto, :cantidad, :costo_unitario)";

        $stmtDet = $this->conn->prepare($sqlDet);
        $stmtDet->execute([
            ":id_mantenimiento" => $id_mantenimiento,
            ":id_repuesto"      => $r->id_repuesto,
            ":cantidad"         => $cantidad,
            ":costo_unitario"   => $costo
        ]);

        $nuevoStock = $stockActual - $cantidad;

        $sqlMov = "INSERT INTO electronicas.MovimientosRepuestos
                       (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                        stock_anterior, stock_nuevo, id_maquina, referencia)
                   VALUES
                       (:id_repuesto, 2, :cantidad, :costo_unitario,
                        :stock_anterior, :stock_nuevo, :id_maquina, 'MANTENIMIENTO')";

        $stmtMov = $this->conn->prepare($sqlMov);
        $stmtMov->execute([
            ":id_repuesto"    => $r->id_repuesto,
            ":cantidad"       => $cantidad,
            ":costo_unitario" => $costo,
            ":stock_anterior" => $stockActual,
            ":stock_nuevo"    => $nuevoStock,
            ":id_maquina"     => $id_maquina
        ]);

        $this->actualizarStock($r->id_repuesto, $nuevoStock);
    }

    //////////////////////////////////////////////////////////
    // SALIDA POR SERIE
    //////////////////////////////////////////////////////////
    private function procesarSalidaSerieDesdeMantenimiento($id_mantenimiento, $id_maquina, $r)
    {
        if (empty($r->series) || !is_array($r->series)) {
            throw new Exception("Debes seleccionar al menos una serie para el repuesto ID {$r->id_repuesto}");
        }

        $costo = isset($r->costo_unitario) ? (float)$r->costo_unitario : 0;

        foreach ($r->series as $id_detalle) {
            $sqlVal = "SELECT id_detalle_repuesto, serie, id_estado_repuesto
                       FROM electronicas.RepuestosDetalle
                       WHERE id_detalle_repuesto = ? AND id_repuesto = ?";
            $stmtVal = $this->conn->prepare($sqlVal);
            $stmtVal->execute([$id_detalle, $r->id_repuesto]);
            $detalle = $stmtVal->fetch(PDO::FETCH_ASSOC);

            if (!$detalle) {
                throw new Exception("Serie no encontrada para el repuesto ID {$r->id_repuesto}");
            }

            if ((int)$detalle["id_estado_repuesto"] !== 1) {
                throw new Exception("La serie {$detalle['serie']} ya no está disponible");
            }

            $sqlDet = "INSERT INTO electronicas.MantenimientoRepuestos
                           (id_mantenimiento, id_repuesto, id_detalle_repuesto, cantidad, costo_unitario)
                       VALUES
                           (:id_mantenimiento, :id_repuesto, :id_detalle_repuesto, 1, :costo_unitario)";

            $stmtDet = $this->conn->prepare($sqlDet);
            $stmtDet->execute([
                ":id_mantenimiento"   => $id_mantenimiento,
                ":id_repuesto"        => $r->id_repuesto,
                ":id_detalle_repuesto"=> $id_detalle,
                ":costo_unitario"     => $costo
            ]);

            $sqlUpd = "UPDATE electronicas.RepuestosDetalle
                       SET id_estado_repuesto = 2, id_maquina_actual = ?
                       WHERE id_detalle_repuesto = ?";
            $stmtUpd = $this->conn->prepare($sqlUpd);
            $stmtUpd->execute([$id_maquina, $id_detalle]);

            $sqlMov = "INSERT INTO electronicas.MovimientosRepuestos
                           (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                            costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
                       VALUES
                           (:id_repuesto, :id_detalle_repuesto, 2, 1,
                            :costo_unitario, 0, 0, :id_maquina, 'MANTENIMIENTO')";

            $stmtMov = $this->conn->prepare($sqlMov);
            $stmtMov->execute([
                ":id_repuesto"         => $r->id_repuesto,
                ":id_detalle_repuesto" => $id_detalle,
                ":costo_unitario"      => $costo,
                ":id_maquina"          => $id_maquina
            ]);
        }
    }

    //////////////////////////////////////////////////////////
    // AUXILIARES INVENTARIO
    //////////////////////////////////////////////////////////
    private function obtenerStock($id_repuesto)
    {
        $sql  = "SELECT stock FROM electronicas.Repuestos WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_repuesto]);
        return (int)($stmt->fetchColumn() ?? 0);
    }

    private function actualizarStock($id_repuesto, $stock)
    {
        $sql  = "UPDATE electronicas.Repuestos SET stock = ? WHERE id_repuesto = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$stock, $id_repuesto]);
    }
}
