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
                    m.codigo,
                    mq.nombre                          AS maquina,
                    tm.nombre                          AS tipo,
                    ISNULL(t.nombre, 'Sin técnico')    AS tecnico,
                    m.fecha_mantenimiento,
                    m.proximo_mantenimiento,
                    m.descripcion,
                    m.anulado,
                    m.motivo_anulacion
                FROM electronicas.Mantenimientos m
                INNER JOIN electronicas.Maquinas          mq ON m.id_maquina = mq.id_maquina
                INNER JOIN electronicas.TipoMantenimiento tm ON m.id_tipo    = tm.id_tipo
                LEFT  JOIN electronicas.Usuarios           t ON m.id_tecnico = t.id_usuario
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
                    END                                         AS serie,
                    ISNULL(dv.simbolo,      dpred.simbolo)      AS divisa_simbolo,
                    ISNULL(dv.tipo_cambio,  dpred.tipo_cambio)  AS tipo_cambio
                FROM electronicas.MantenimientoRepuestos mr
                INNER JOIN electronicas.Repuestos r
                        ON mr.id_repuesto = r.id_repuesto
                LEFT  JOIN electronicas.RepuestosDetalle rd
                        ON mr.id_detalle_repuesto = rd.id_detalle_repuesto
                LEFT  JOIN electronicas.Divisas dv
                        ON dv.id_divisa = r.id_divisa
                CROSS APPLY (
                    SELECT TOP 1 simbolo, tipo_cambio FROM electronicas.Divisas
                    WHERE predeterminada = 1 AND activo = 1
                ) dpred
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
            "SELECT id_tipo, nombre, descripcion FROM electronicas.TipoMantenimiento"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTecnicos()
    {
        $stmt = $this->conn->prepare(
            "SELECT u.id_usuario, u.nombre
             FROM electronicas.Usuarios u
             INNER JOIN electronicas.Roles r ON r.id_rol = u.id_rol
             WHERE r.puede_ejecutar_mantenimiento = 1 AND u.activo = 1
             ORDER BY u.nombre"
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
                    END AS stock,
                    ISNULL(dv.simbolo,     dpred.simbolo)     AS divisa_simbolo,
                    ISNULL(dv.tipo_cambio, dpred.tipo_cambio) AS tipo_cambio
                FROM electronicas.Repuestos r
                LEFT  JOIN electronicas.Divisas dv ON dv.id_divisa = r.id_divisa
                CROSS APPLY (
                    SELECT TOP 1 simbolo, tipo_cambio FROM electronicas.Divisas
                    WHERE predeterminada = 1 AND activo = 1
                ) dpred
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
    // CATÁLOGO TAREAS SATAKE (para poblar el select del formulario)
    // ══════════════════════════════════════════════════════════════

    public function listarTareasActivasSatake(): array
    {
        return $this->conn->query(
            "SELECT t.id_tarea, t.nombre,
                    f.nombre AS frecuencia, f.orden AS frec_orden
             FROM electronicas.SatakeTareas t
             INNER JOIN electronicas.SatakeFrecuencias f ON f.id_frecuencia = t.id_frecuencia
             WHERE t.activo = 1
             ORDER BY f.orden, t.nombre"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // TAREAS DE MANTENIMIENTO
    // ══════════════════════════════════════════════════════════════

    public function guardarTareas(int $id_mantenimiento, array $tareas): void
    {
        $this->conn->prepare(
            "DELETE FROM electronicas.MantenimientoTareas WHERE id_mantenimiento = ?"
        )->execute([$id_mantenimiento]);

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.MantenimientoTareas (id_mantenimiento, id_tarea, descripcion, orden)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($tareas as $i => $tarea) {
            if (is_string($tarea)) {
                $id_tarea = null;
                $desc     = trim($tarea);
            } else {
                $tarea    = (array)$tarea;
                $id_tarea = isset($tarea['id_tarea']) && $tarea['id_tarea'] !== '' ? (int)$tarea['id_tarea'] : null;
                $desc     = trim($tarea['nombre'] ?? $tarea['descripcion'] ?? '');
            }
            if ($desc !== '') {
                $stmt->execute([$id_mantenimiento, $id_tarea, $desc, $i]);
            }
        }
    }

    public function obtenerTareas(int $id_mantenimiento): array
    {
        $stmt = $this->conn->prepare(
            "SELECT descripcion FROM electronicas.MantenimientoTareas
             WHERE id_mantenimiento = ?
             ORDER BY orden"
        );
        $stmt->execute([$id_mantenimiento]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'descripcion');
    }

    // ══════════════════════════════════════════════════════════════
    // GUARDAR MANTENIMIENTO + INSTALACIONES + RETIROS
    // (usado también por aprobarSolicitud — no modificar firma)
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
        $costo    = (float)($r->costo_unitario ?? $r->costo ?? 0);

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

        $costo = (float)($r->costo_unitario ?? $r->costo ?? 0);

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
    // ANULACIÓN — PASO 1: análisis sin modificar nada
    // ══════════════════════════════════════════════════════════════
    public function verificarAnulacion(int $id): array
    {
        $stmtM = $this->conn->prepare(
            "SELECT anulado FROM electronicas.Mantenimientos WHERE id_mantenimiento = ?"
        );
        $stmtM->execute([$id]);
        $mant = $stmtM->fetch(PDO::FETCH_ASSOC);

        if (!$mant)           throw new RuntimeException('Mantenimiento no encontrado.');
        if ($mant['anulado']) throw new RuntimeException('Este mantenimiento ya está anulado.');

        $revertibles = [];
        $conflictos  = [];

        // ── Repuestos instalados por este mantenimiento ────────
        $stmtI = $this->conn->prepare(
            "SELECT mr.id_repuesto, mr.id_detalle_repuesto, mr.cantidad,
                    r.nombre AS repuesto, r.maneja_serie
             FROM electronicas.MantenimientoRepuestos mr
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mr.id_repuesto
             WHERE mr.id_mantenimiento = ?"
        );
        $stmtI->execute([$id]);

        foreach ($stmtI->fetchAll(PDO::FETCH_ASSOC) as $inst) {
            $idDet = $inst['id_detalle_repuesto'];

            // ¿Fue retirado después por OTRO mantenimiento?
            if ($idDet) {
                $stmtChk = $this->conn->prepare(
                    "SELECT TOP 1 id_mantenimiento_retiro
                     FROM electronicas.MaquinaRepuestos
                     WHERE id_mantenimiento_instalacion = ?
                       AND id_detalle_repuesto = ?
                       AND fecha_retiro IS NOT NULL
                       AND (id_mantenimiento_retiro IS NULL OR id_mantenimiento_retiro <> ?)"
                );
                $stmtChk->execute([$id, $idDet, $id]);
            } else {
                $stmtChk = $this->conn->prepare(
                    "SELECT TOP 1 id_mantenimiento_retiro
                     FROM electronicas.MaquinaRepuestos
                     WHERE id_mantenimiento_instalacion = ?
                       AND id_repuesto = ?
                       AND id_detalle_repuesto IS NULL
                       AND fecha_retiro IS NOT NULL
                       AND (id_mantenimiento_retiro IS NULL OR id_mantenimiento_retiro <> ?)"
                );
                $stmtChk->execute([$id, $inst['id_repuesto'], $id]);
            }
            $otroMant = $stmtChk->fetchColumn();

            if ($otroMant !== false) {
                $conflictos[] = [
                    'repuesto' => $inst['repuesto'],
                    'cantidad' => $inst['cantidad'],
                    'razon'    => $otroMant
                        ? "Fue retirado en el mantenimiento #{$otroMant} — ajusta ese registro manualmente"
                        : "Fue retirado posteriormente en otro mantenimiento"
                ];
            } else {
                $info = $inst['maneja_serie']
                    ? 'La serie quedará disponible en inventario'
                    : "Se devolverán {$inst['cantidad']} unidad(es) al stock";
                $revertibles[] = ['tipo' => 'instalacion', 'repuesto' => $inst['repuesto'],
                                  'cantidad' => $inst['cantidad'], 'info' => $info];
            }
        }

        // ── Retiros registrados en este mantenimiento ──────────
        $stmtR = $this->conn->prepare(
            "SELECT maq.id_maquina_repuesto, maq.cantidad, maq.tipo_retiro,
                    r.nombre AS repuesto, r.maneja_serie, r.stock AS stock_actual
             FROM electronicas.MaquinaRepuestos maq
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = maq.id_repuesto
             WHERE maq.id_mantenimiento_retiro = ?"
        );
        $stmtR->execute([$id]);

        foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $ret) {
            $cant = (int)$ret['cantidad'];
            if ($ret['tipo_retiro'] === 'devolucion') {
                $stock = (int)$ret['stock_actual'];
                if ($stock < $cant) {
                    $conflictos[] = [
                        'repuesto' => $ret['repuesto'],
                        'cantidad' => $cant,
                        'razon'    => "Devolución no reversible: stock actual ({$stock}) < cantidad devuelta ({$cant})"
                    ];
                } else {
                    $revertibles[] = ['tipo' => 'retiro_devolucion', 'repuesto' => $ret['repuesto'],
                                      'cantidad' => $cant,
                                      'info'     => "Se descontarán {$cant} unidad(es) del stock (revierte la devolución)"];
                }
            } else {
                $info = $ret['maneja_serie']
                    ? 'La serie se restaurará como instalada en la máquina'
                    : 'La pieza se restaurará como instalada (sin mover stock)';
                $revertibles[] = ['tipo' => 'retiro_baja', 'repuesto' => $ret['repuesto'],
                                  'cantidad' => $cant, 'info' => $info];
            }
        }

        // ── Solicitud vinculada ────────────────────────────────
        $stmtSol = $this->conn->prepare(
            "SELECT TOP 1 sm.id_solicitud, sr.descripcion, sr.estado
             FROM electronicas.SolicitudesMaquinas sm
             INNER JOIN electronicas.SolicitudesRepuestos sr ON sr.id_solicitud = sm.id_solicitud
             WHERE sm.id_mantenimiento_generado = ?"
        );
        $stmtSol->execute([$id]);
        $solicitud = $stmtSol->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'revertibles' => $revertibles,
            'conflictos'  => $conflictos,
            'solicitud'   => $solicitud,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // ANULACIÓN — PASO 2: ejecutar reversión + marcar anulado
    // ══════════════════════════════════════════════════════════════
    public function ejecutarAnulacion(int $id, string $motivo): void
    {
        $this->conn->beginTransaction();
        try {
            $stmtM = $this->conn->prepare(
                "SELECT id_maquina FROM electronicas.Mantenimientos
                 WHERE id_mantenimiento = ? AND anulado = 0"
            );
            $stmtM->execute([$id]);
            if (!$stmtM->fetch()) throw new RuntimeException('Mantenimiento no encontrado o ya anulado.');

            // IDs de tipos de movimiento
            $idTipoEntrada = (int)($this->conn->query(
                "SELECT TOP 1 id_tipo_movimiento FROM electronicas.TiposMovimientoRepuesto
                 WHERE nombre = 'Entrada'"
            )->fetchColumn() ?: 1);

            $idTipoSalida = (int)($this->conn->query(
                "SELECT TOP 1 id_tipo_movimiento FROM electronicas.TiposMovimientoRepuesto
                 WHERE nombre = 'Salida'"
            )->fetchColumn() ?: 2);

            $obsBase = 'Reversión por anulación de mantenimiento #' . $id;

            // ── A. Revertir retiros primero (para no perder refs) ──
            $stmtR = $this->conn->prepare(
                "SELECT maq.id_maquina_repuesto, maq.id_repuesto, maq.id_detalle_repuesto,
                        maq.cantidad, maq.tipo_retiro, r.maneja_serie, r.stock AS stock_actual
                 FROM electronicas.MaquinaRepuestos maq
                 INNER JOIN electronicas.Repuestos r ON r.id_repuesto = maq.id_repuesto
                 WHERE maq.id_mantenimiento_retiro = ?"
            );
            $stmtR->execute([$id]);

            foreach ($stmtR->fetchAll(PDO::FETCH_ASSOC) as $ret) {
                $cant = (int)$ret['cantidad'];

                if ($ret['tipo_retiro'] === 'devolucion') {
                    $stock = (int)$ret['stock_actual'];
                    if ($stock < $cant) continue; // conflicto → saltar
                    $nuevoStock = $stock - $cant;
                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                              stock_anterior, stock_nuevo, referencia, observaciones)
                         VALUES (?, ?, ?, 0, ?, ?, 'ANULACION', ?)"
                    )->execute([$ret['id_repuesto'], $idTipoSalida, $cant, $stock, $nuevoStock, $obsBase]);
                    $this->actualizarStock($ret['id_repuesto'], $nuevoStock);

                } else {
                    // baja: restaurar serie al estado "instalado" (2)
                    if ((int)$ret['maneja_serie'] === 1 && $ret['id_detalle_repuesto']) {
                        $this->conn->prepare(
                            "UPDATE electronicas.RepuestosDetalle
                             SET id_estado_repuesto = 2,
                                 id_maquina_actual  = (
                                     SELECT id_maquina FROM electronicas.MaquinaRepuestos
                                     WHERE id_maquina_repuesto = ?
                                 )
                             WHERE id_detalle_repuesto = ?"
                        )->execute([$ret['id_maquina_repuesto'], $ret['id_detalle_repuesto']]);
                    }
                }

                // Limpiar campos de retiro: la pieza vuelve a estar instalada
                $this->conn->prepare(
                    "UPDATE electronicas.MaquinaRepuestos
                     SET fecha_retiro = NULL, tipo_retiro = NULL,
                         id_mantenimiento_retiro = NULL, observaciones_retiro = NULL
                     WHERE id_maquina_repuesto = ?"
                )->execute([$ret['id_maquina_repuesto']]);
            }

            // ── B. Revertir instalaciones ──────────────────────
            $stmtI = $this->conn->prepare(
                "SELECT mr.id_repuesto, mr.id_detalle_repuesto, mr.cantidad, r.maneja_serie
                 FROM electronicas.MantenimientoRepuestos mr
                 INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mr.id_repuesto
                 WHERE mr.id_mantenimiento = ?"
            );
            $stmtI->execute([$id]);

            foreach ($stmtI->fetchAll(PDO::FETCH_ASSOC) as $inst) {
                $idDet = $inst['id_detalle_repuesto'];

                // Buscar el registro MaquinaRepuestos reversible
                if ($idDet) {
                    $stmtMR = $this->conn->prepare(
                        "SELECT TOP 1 id_maquina_repuesto FROM electronicas.MaquinaRepuestos
                         WHERE id_mantenimiento_instalacion = ?
                           AND id_detalle_repuesto = ?
                           AND (fecha_retiro IS NULL OR id_mantenimiento_retiro = ?)"
                    );
                    $stmtMR->execute([$id, $idDet, $id]);
                } else {
                    $stmtMR = $this->conn->prepare(
                        "SELECT TOP 1 id_maquina_repuesto FROM electronicas.MaquinaRepuestos
                         WHERE id_mantenimiento_instalacion = ?
                           AND id_repuesto = ?
                           AND id_detalle_repuesto IS NULL
                           AND (fecha_retiro IS NULL OR id_mantenimiento_retiro = ?)"
                    );
                    $stmtMR->execute([$id, $inst['id_repuesto'], $id]);
                }
                $maqRepId = $stmtMR->fetchColumn();
                if (!$maqRepId) continue; // conflicto → saltar

                if ((int)$inst['maneja_serie'] === 1 && $idDet) {
                    // Serie: volver a disponible
                    $this->conn->prepare(
                        "UPDATE electronicas.RepuestosDetalle
                         SET id_estado_repuesto = 1, id_maquina_actual = NULL
                         WHERE id_detalle_repuesto = ?"
                    )->execute([$idDet]);
                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                              costo_unitario, stock_anterior, stock_nuevo, referencia, observaciones)
                         VALUES (?, ?, ?, 1, 0, 0, 0, 'ANULACION', ?)"
                    )->execute([$inst['id_repuesto'], $idDet, $idTipoEntrada, $obsBase]);
                } else {
                    // Cantidad: restaurar stock
                    $stock      = $this->obtenerStock($inst['id_repuesto']);
                    $nuevoStock = $stock + (int)$inst['cantidad'];
                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                              stock_anterior, stock_nuevo, referencia, observaciones)
                         VALUES (?, ?, ?, 0, ?, ?, 'ANULACION', ?)"
                    )->execute([$inst['id_repuesto'], $idTipoEntrada, $inst['cantidad'],
                                $stock, $nuevoStock, $obsBase]);
                    $this->actualizarStock($inst['id_repuesto'], $nuevoStock);
                }

                $this->conn->prepare(
                    "DELETE FROM electronicas.MaquinaRepuestos WHERE id_maquina_repuesto = ?"
                )->execute([$maqRepId]);
            }

            // ── C. Marcar mantenimiento como anulado ───────────
            $this->conn->prepare(
                "UPDATE electronicas.Mantenimientos
                 SET anulado = 1, motivo_anulacion = ?
                 WHERE id_mantenimiento = ?"
            )->execute([$motivo, $id]);

            // ── D. Anular la solicitud vinculada (si existe) ───
            $stmtSol = $this->conn->prepare(
                "SELECT TOP 1 sm.id_solicitud
                 FROM electronicas.SolicitudesMaquinas sm
                 WHERE sm.id_mantenimiento_generado = ?"
            );
            $stmtSol->execute([$id]);
            $idSol = $stmtSol->fetchColumn();
            if ($idSol) {
                $this->conn->prepare(
                    "UPDATE electronicas.SolicitudesRepuestos
                     SET estado = 'Anulado'
                     WHERE id_solicitud = ? AND estado = 'Aprobado'"
                )->execute([$idSol]);
            }

            $this->conn->commit();

        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ══════════════════════════════════════════════════════════════
    // OBTENER DATOS PARA EDICIÓN
    // ══════════════════════════════════════════════════════════════
    public function obtenerParaEdicion(int $id_mantenimiento): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_mantenimiento, id_maquina, id_tipo, id_tecnico,
                    fecha_mantenimiento, proximo_mantenimiento, descripcion, anulado
             FROM electronicas.Mantenimientos
             WHERE id_mantenimiento = ?"
        );
        $stmt->execute([$id_mantenimiento]);
        $mant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mant || $mant['anulado']) {
            return [];
        }

        return [
            'mantenimiento' => $mant,
            'tareas'        => $this->obtenerTareas($id_mantenimiento),
            'repuestos'     => $this->obtenerRepuestosEdicion($id_mantenimiento),
            'retiros'       => $this->obtenerRetirosEdicion($id_mantenimiento)
        ];
    }

    private function obtenerRepuestosEdicion(int $id_mantenimiento): array
    {
        $stmt = $this->conn->prepare(
            "SELECT mr.id_repuesto, mr.id_detalle_repuesto, mr.cantidad, mr.costo_unitario,
                    r.maneja_serie, r.nombre
             FROM electronicas.MantenimientoRepuestos mr
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mr.id_repuesto
             WHERE mr.id_mantenimiento = ?
             ORDER BY r.nombre"
        );
        $stmt->execute([$id_mantenimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function obtenerRetirosEdicion(int $id_mantenimiento): array
    {
        $stmt = $this->conn->prepare(
            "SELECT mq.id_maquina_repuesto, mq.id_repuesto, mq.id_detalle_repuesto,
                    mq.cantidad, mq.tipo_retiro, mq.observaciones_retiro,
                    r.nombre AS repuesto, rd.serie
             FROM electronicas.MaquinaRepuestos mq
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mq.id_repuesto
             LEFT JOIN electronicas.RepuestosDetalle rd ON rd.id_detalle_repuesto = mq.id_detalle_repuesto
             WHERE mq.id_mantenimiento_retiro = ?
             ORDER BY r.nombre"
        );
        $stmt->execute([$id_mantenimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // ACTUALIZAR MANTENIMIENTO
    // ══════════════════════════════════════════════════════════════
    public function actualizarMantenimiento(int $id_mantenimiento, $d): array
    {
        try {
            $this->conn->beginTransaction();

            // Obtener datos actuales para reversar cambios
            $stmt = $this->conn->prepare(
                "SELECT id_maquina FROM electronicas.Mantenimientos
                 WHERE id_mantenimiento = ? AND anulado = 0"
            );
            $stmt->execute([$id_mantenimiento]);
            $mant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mant) throw new Exception("Mantenimiento no encontrado o anulado.");

            // Revertir movimientos ANTES de eliminar los repuestos
            $this->revertirMovimientosRepuestos($id_mantenimiento, $mant['id_maquina']);

            // Limpiar y regenerar repuestos
            $this->conn->prepare(
                "DELETE FROM electronicas.MantenimientoRepuestos WHERE id_mantenimiento = ?"
            )->execute([$id_mantenimiento]);

            // Actualizar cabecera
            $sqlU = "UPDATE electronicas.Mantenimientos
                     SET id_tipo = ?, id_tecnico = ?, fecha_mantenimiento = ?,
                         proximo_mantenimiento = ?, descripcion = ?
                     WHERE id_mantenimiento = ?";

            $stmt = $this->conn->prepare($sqlU);
            $stmt->execute([
                $d->id_tipo,
                $d->id_tecnico ?: null,
                $d->fecha_mantenimiento,
                $d->proximo_mantenimiento ?: null,
                $d->descripcion,
                $id_mantenimiento
            ]);

            // Agregar nuevos repuestos
            if (!empty($d->repuestos)) {
                foreach ($d->repuestos as $r) {
                    $r = (object)$r;
                    if (!isset($r->id_repuesto)) continue;

                    if ((int)$r->maneja_serie === 1) {
                        $this->procesarSalidaSerieDesdeMantenimiento($id_mantenimiento, $mant['id_maquina'], $r);
                    } else {
                        $this->procesarSalidaCantidadDesdeMantenimiento($id_mantenimiento, $mant['id_maquina'], $r);
                    }
                }
            }

            // Limpiar y regenerar retiros
            $this->conn->prepare(
                "DELETE FROM electronicas.MaquinaRepuestos WHERE id_mantenimiento_retiro = ?"
            )->execute([$id_mantenimiento]);

            if (!empty($d->retiros)) {
                foreach ($d->retiros as $ret) {
                    $ret = (object)$ret;
                    $this->procesarRetiro($id_mantenimiento, $mant['id_maquina'], $ret);
                }
            }

            $this->conn->commit();
            return ["ok" => true, "id_mantenimiento" => $id_mantenimiento];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ["error" => true, "mensaje" => $e->getMessage()];
        }
    }

    private function revertirMovimientosRepuestos(int $id_mantenimiento, int $id_maquina): void
    {
        // Obtener movimientos anteriores para revertir stock
        $stmt = $this->conn->prepare(
            "SELECT mr.id_repuesto, mr.id_detalle_repuesto, mr.cantidad,
                    mov.id_tipo_movimiento, mov.stock_anterior, r.maneja_serie
             FROM electronicas.MantenimientoRepuestos mr
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mr.id_repuesto
             LEFT JOIN electronicas.MovimientosRepuestos mov
                    ON mov.id_repuesto = mr.id_repuesto
                   AND mov.referencia = 'MANTENIMIENTO'
                   AND mov.id_maquina = ?
             WHERE mr.id_mantenimiento = ?"
        );
        $stmt->execute([$id_maquina, $id_mantenimiento]);
        $repuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($repuestos as $rep) {
            if ((int)$rep['maneja_serie'] === 0 && $rep['stock_anterior'] !== null) {
                // Restaurar stock
                $this->actualizarStock($rep['id_repuesto'], (int)$rep['stock_anterior']);

                // Crear movimiento de reversión
                $this->conn->prepare(
                    "INSERT INTO electronicas.MovimientosRepuestos
                         (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                          stock_anterior, stock_nuevo, referencia, observaciones)
                     VALUES (?, 1, ?, 0, ?, ?, 'EDICION', ?)"
                )->execute([
                    $rep['id_repuesto'],
                    $rep['cantidad'],
                    (int)$rep['stock_anterior'] - (int)$rep['cantidad'],
                    (int)$rep['stock_anterior'],
                    "Reversión por edición de mantenimiento #" . $id_mantenimiento
                ]);
            }
        }
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
