<?php

class mdlMantenimientos
{
    private $conn;

    public function __construct()
    {
        require_once "../../../../config/Connection.php";
        $conexion   = new Connection();
        $this->conn = $conexion->dbConnect();
    }

    // ══════════════════════════════════════════════════════════════
    // TABLA PRINCIPAL
    // ══════════════════════════════════════════════════════════════
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

    // ══════════════════════════════════════════════════════════════
    // DETALLE: repuestos instalados + retiros de un mantenimiento
    // ══════════════════════════════════════════════════════════════
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

    public function obtenerRetirosMantenimiento($id_mantenimiento)
    {
        $sql = "SELECT
                    r.nombre           AS repuesto,
                    mq.id_maquina_repuesto,
                    mq.cantidad,
                    mq.tipo_retiro,
                    mq.observaciones_retiro,
                    rd.serie,
                    CONVERT(VARCHAR, mq.fecha_retiro, 120) AS fecha_retiro
                FROM electronicas.MaquinaRepuestos mq
                INNER JOIN electronicas.Repuestos r
                        ON r.id_repuesto = mq.id_repuesto
                LEFT  JOIN electronicas.RepuestosDetalle rd
                        ON rd.id_detalle_repuesto = mq.id_detalle_repuesto
                WHERE mq.id_mantenimiento_retiro = ?
                ORDER BY r.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_mantenimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // PIEZAS INSTALADAS ACTUALMENTE EN UNA MÁQUINA
    // ══════════════════════════════════════════════════════════════
    public function obtenerInstalados($id_maquina)
    {
        $sql = "SELECT
                    mq.id_maquina_repuesto,
                    mq.id_repuesto,
                    r.nombre                                        AS repuesto,
                    r.maneja_serie,
                    mq.id_detalle_repuesto,
                    mq.cantidad,
                    rd.serie,
                    CONVERT(VARCHAR, mq.fecha_instalacion, 23)      AS fecha_instalacion,
                    mq.id_mantenimiento_instalacion
                FROM electronicas.MaquinaRepuestos mq
                INNER JOIN electronicas.Repuestos r
                        ON r.id_repuesto = mq.id_repuesto
                LEFT  JOIN electronicas.RepuestosDetalle rd
                        ON rd.id_detalle_repuesto = mq.id_detalle_repuesto
                WHERE mq.id_maquina = ?
                  AND mq.fecha_retiro IS NULL
                ORDER BY mq.fecha_instalacion DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id_maquina]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // SELECTS
    // ══════════════════════════════════════════════════════════════
    public function listarMaquinas()
    {
        $stmt = $this->conn->prepare(
            "SELECT id_maquina, nombre FROM electronicas.Maquinas WHERE id_estado = 1"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTipos()
    {
        $stmt = $this->conn->prepare(
            "SELECT id_tipo, nombre FROM electronicas.TipoMantenimiento"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTecnicos()
    {
        $stmt = $this->conn->prepare(
            "SELECT id_tecnico, nombre FROM electronicas.Tecnicos"
        );
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
        $stmt = $this->conn->prepare(
            "SELECT id_detalle_repuesto, serie
             FROM electronicas.RepuestosDetalle
             WHERE id_repuesto        = ?
               AND id_estado_repuesto = 1
             ORDER BY serie"
        );
        $stmt->execute([$id_repuesto]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerStockPublico($id_repuesto)
    {
        return $this->obtenerStock($id_repuesto);
    }

    // ══════════════════════════════════════════════════════════════
    // GUARDAR MANTENIMIENTO + INSTALACIONES + RETIROS
    // ══════════════════════════════════════════════════════════════
    public function guardarMantenimiento($d)
    {
        try {
            $this->conn->beginTransaction();

            $sqlM = "INSERT INTO electronicas.Mantenimientos
                        (id_maquina, id_tipo, id_tecnico,
                         fecha_mantenimiento, proximo_mantenimiento, descripcion)
                     VALUES
                        (:id_maquina, :id_tipo, :id_tecnico,
                         :fecha_mantenimiento, :proximo_mantenimiento, :descripcion)";

            $stmt = $this->conn->prepare($sqlM);
            $stmt->bindValue(":id_maquina",            $d->id_maquina);
            $stmt->bindValue(":id_tipo",               $d->id_tipo);
            $stmt->bindValue(":id_tecnico",            $d->id_tecnico ?: null);
            $stmt->bindValue(":fecha_mantenimiento",   $d->fecha_mantenimiento);
            $stmt->bindValue(":proximo_mantenimiento", $d->proximo_mantenimiento ?: null);
            $stmt->bindValue(":descripcion",           $d->descripcion);
            $stmt->execute();

            $id_mantenimiento = $this->conn->lastInsertId();

            // ── Instalaciones ──────────────────────────────────────
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

            // ── Retiros ────────────────────────────────────────────
            if (!empty($d->retiros)) {
                foreach ($d->retiros as $ret) {
                    $ret = (object)$ret;
                    $this->procesarRetiro($id_mantenimiento, $d->id_maquina, $ret);
                }
            }

            $this->conn->commit();
            return ["ok" => true, "id_mantenimiento" => $id_mantenimiento];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // INSTALACIÓN — cantidad
    // ══════════════════════════════════════════════════════════════
    private function procesarSalidaCantidadDesdeMantenimiento($id_mantenimiento, $id_maquina, $r)
    {
        $cantidad = isset($r->cantidad) ? (int)$r->cantidad : 1;
        $costo    = isset($r->costo_unitario) ? (float)$r->costo_unitario : 0;

        if ($cantidad <= 0) throw new Exception("Cantidad inválida para repuesto ID {$r->id_repuesto}");

        $stockActual = $this->obtenerStock($r->id_repuesto);
        if ($stockActual < $cantidad) {
            throw new Exception("Stock insuficiente para repuesto ID {$r->id_repuesto}. Disponible: {$stockActual}");
        }

        $this->conn->prepare(
            "INSERT INTO electronicas.MantenimientoRepuestos
                 (id_mantenimiento, id_repuesto, cantidad, costo_unitario)
             VALUES (?, ?, ?, ?)"
        )->execute([$id_mantenimiento, $r->id_repuesto, $cantidad, $costo]);

        $nuevoStock = $stockActual - $cantidad;

        $this->conn->prepare(
            "INSERT INTO electronicas.MovimientosRepuestos
                 (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                  stock_anterior, stock_nuevo, id_maquina, referencia)
             VALUES (?, 2, ?, ?, ?, ?, ?, 'MANTENIMIENTO')"
        )->execute([$r->id_repuesto, $cantidad, $costo, $stockActual, $nuevoStock, $id_maquina]);

        $this->actualizarStock($r->id_repuesto, $nuevoStock);

        // Registro de pieza instalada en máquina
        $this->conn->prepare(
            "INSERT INTO electronicas.MaquinaRepuestos
                 (id_maquina, id_repuesto, cantidad, id_mantenimiento_instalacion)
             VALUES (?, ?, ?, ?)"
        )->execute([$id_maquina, $r->id_repuesto, $cantidad, $id_mantenimiento]);
    }

    // ══════════════════════════════════════════════════════════════
    // INSTALACIÓN — serie
    // ══════════════════════════════════════════════════════════════
    private function procesarSalidaSerieDesdeMantenimiento($id_mantenimiento, $id_maquina, $r)
    {
        if (empty($r->series) || !is_array($r->series)) {
            throw new Exception("Debes seleccionar al menos una serie para repuesto ID {$r->id_repuesto}");
        }

        $costo = isset($r->costo_unitario) ? (float)$r->costo_unitario : 0;

        foreach ($r->series as $id_detalle) {
            $stmtVal = $this->conn->prepare(
                "SELECT id_detalle_repuesto, serie, id_estado_repuesto
                 FROM electronicas.RepuestosDetalle
                 WHERE id_detalle_repuesto = ? AND id_repuesto = ?"
            );
            $stmtVal->execute([$id_detalle, $r->id_repuesto]);
            $detalle = $stmtVal->fetch(PDO::FETCH_ASSOC);

            if (!$detalle) throw new Exception("Serie no encontrada para repuesto ID {$r->id_repuesto}");
            if ((int)$detalle["id_estado_repuesto"] !== 1) {
                throw new Exception("La serie {$detalle['serie']} ya no está disponible");
            }

            $this->conn->prepare(
                "INSERT INTO electronicas.MantenimientoRepuestos
                     (id_mantenimiento, id_repuesto, id_detalle_repuesto, cantidad, costo_unitario)
                 VALUES (?, ?, ?, 1, ?)"
            )->execute([$id_mantenimiento, $r->id_repuesto, $id_detalle, $costo]);

            $this->conn->prepare(
                "UPDATE electronicas.RepuestosDetalle
                 SET id_estado_repuesto = 2, id_maquina_actual = ?
                 WHERE id_detalle_repuesto = ?"
            )->execute([$id_maquina, $id_detalle]);

            $this->conn->prepare(
                "INSERT INTO electronicas.MovimientosRepuestos
                     (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                      costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
                 VALUES (?, ?, 2, 1, ?, 0, 0, ?, 'MANTENIMIENTO')"
            )->execute([$r->id_repuesto, $id_detalle, $costo, $id_maquina]);

            // Registro de pieza instalada en máquina
            $this->conn->prepare(
                "INSERT INTO electronicas.MaquinaRepuestos
                     (id_maquina, id_repuesto, id_detalle_repuesto, cantidad, id_mantenimiento_instalacion)
                 VALUES (?, ?, ?, 1, ?)"
            )->execute([$id_maquina, $r->id_repuesto, $id_detalle, $id_mantenimiento]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // RETIRO (baja o devolución)
    // ══════════════════════════════════════════════════════════════
    private function procesarRetiro($id_mantenimiento, $id_maquina, $ret)
    {
        // Obtener el registro instalado
        $stmtGet = $this->conn->prepare(
            "SELECT mq.*, r.maneja_serie
             FROM electronicas.MaquinaRepuestos mq
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mq.id_repuesto
             WHERE mq.id_maquina_repuesto = ? AND mq.fecha_retiro IS NULL"
        );
        $stmtGet->execute([$ret->id_maquina_repuesto]);
        $instalado = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$instalado) {
            throw new Exception("Pieza no encontrada o ya fue retirada anteriormente");
        }

        $tipo  = $ret->tipo_retiro;   // 'baja' | 'devolucion'
        $obs   = $ret->observaciones ?? null;
        $esSerie = (int)$instalado['maneja_serie'] === 1;
        $cantidad = (int)$instalado['cantidad'];

        // IDs de tipos de movimiento (query por nombre para no depender de IDs fijos)
        $tipoMovNombre = ($tipo === 'devolucion') ? 'Retiro - Devolución' : 'Retiro - Baja';
        $stmtTipo = $this->conn->prepare(
            "SELECT id_tipo_movimiento FROM electronicas.TiposMovimientoRepuesto WHERE nombre = ?"
        );
        $stmtTipo->execute([$tipoMovNombre]);
        $idTipoMov = (int)$stmtTipo->fetchColumn();
        if (!$idTipoMov) throw new Exception("Tipo de movimiento '{$tipoMovNombre}' no encontrado. Ejecute la migración.");

        if ($esSerie) {
            // Serie: actualizar estado en RepuestosDetalle
            if ($tipo === 'devolucion') {
                $nuevoEstado = 1; // disponible
            } else {
                // Obtener id del estado "Dado de baja"
                $stmtEst = $this->conn->prepare(
                    "SELECT id_estado FROM electronicas.EstadoRepuestos WHERE nombre = 'Dado de baja'"
                );
                $stmtEst->execute();
                $nuevoEstado = (int)$stmtEst->fetchColumn();
                if (!$nuevoEstado) throw new Exception("Estado 'Dado de baja' no encontrado. Ejecute la migración.");
            }

            $this->conn->prepare(
                "UPDATE electronicas.RepuestosDetalle
                 SET id_estado_repuesto = ?, id_maquina_actual = NULL
                 WHERE id_detalle_repuesto = ?"
            )->execute([$nuevoEstado, $instalado['id_detalle_repuesto']]);

            $this->conn->prepare(
                "INSERT INTO electronicas.MovimientosRepuestos
                     (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                      costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia, observaciones)
                 VALUES (?, ?, ?, 1, 0, 0, 0, ?, 'RETIRO', ?)"
            )->execute([
                $instalado['id_repuesto'],
                $instalado['id_detalle_repuesto'],
                $idTipoMov,
                $id_maquina,
                $obs
            ]);

        } else {
            // Cantidad
            $stockActual = $this->obtenerStock($instalado['id_repuesto']);

            if ($tipo === 'devolucion') {
                $stockNuevo = $stockActual + $cantidad;
                $this->actualizarStock($instalado['id_repuesto'], $stockNuevo);
            } else {
                $stockNuevo = $stockActual; // baja no devuelve al stock
            }

            $this->conn->prepare(
                "INSERT INTO electronicas.MovimientosRepuestos
                     (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                      stock_anterior, stock_nuevo, id_maquina, referencia, observaciones)
                 VALUES (?, ?, ?, 0, ?, ?, ?, 'RETIRO', ?)"
            )->execute([
                $instalado['id_repuesto'],
                $idTipoMov,
                $cantidad,
                $stockActual,
                $stockNuevo,
                $id_maquina,
                $obs
            ]);
        }

        // Cerrar el registro en MaquinaRepuestos
        $this->conn->prepare(
            "UPDATE electronicas.MaquinaRepuestos SET
                fecha_retiro              = GETDATE(),
                tipo_retiro               = ?,
                id_mantenimiento_retiro   = ?,
                observaciones_retiro      = ?
             WHERE id_maquina_repuesto = ?"
        )->execute([$tipo, $id_mantenimiento, $obs, $ret->id_maquina_repuesto]);
    }

    // ══════════════════════════════════════════════════════════════
    // AUXILIARES
    // ══════════════════════════════════════════════════════════════
    private function obtenerStock($id_repuesto)
    {
        $stmt = $this->conn->prepare(
            "SELECT stock FROM electronicas.Repuestos WHERE id_repuesto = ?"
        );
        $stmt->execute([$id_repuesto]);
        return (int)($stmt->fetchColumn() ?? 0);
    }

    private function actualizarStock($id_repuesto, $stock)
    {
        $this->conn->prepare(
            "UPDATE electronicas.Repuestos SET stock = ? WHERE id_repuesto = ?"
        )->execute([$stock, $id_repuesto]);
    }
}
