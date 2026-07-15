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
            "INSERT INTO electronicas.MantenimientoTareas
                (id_mantenimiento, id_tarea_catalogo, descripcion, orden)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($tareas as $i => $tarea) {
            if (is_string($tarea)) {
                $id_tarea_catalogo = null;
                $desc              = trim($tarea);
            } else {
                $tarea             = (array)$tarea;
                $id_tarea_catalogo = isset($tarea['id_tarea']) && $tarea['id_tarea'] !== ''
                                   ? (int)$tarea['id_tarea']
                                   : null;
                $desc              = trim($tarea['nombre'] ?? $tarea['descripcion'] ?? '');
            }
            if ($desc !== '') {
                $stmt->execute([$id_mantenimiento, $id_tarea_catalogo, $desc, $i]);
            }
        }
    }

    public function obtenerTareas(int $id_mantenimiento): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_mantenimiento_tarea, id_tarea_catalogo, descripcion
             FROM electronicas.MantenimientoTareas
             WHERE id_mantenimiento = ?
             ORDER BY orden"
        );
        $stmt->execute([$id_mantenimiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTareasDescripcion(int $id_mantenimiento): array
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

            // ── Repuestos desde solicitud aprobada ─────────────────
            if (!empty($d->id_solicitud_maquina)) {
                $seriesPorRepuesto = isset($d->series_solicitud)
                    ? (array)$d->series_solicitud
                    : [];
                $this->consumirSolicitud(
                    $id_mantenimiento,
                    (int)$d->id_maquina,
                    (int)$d->id_solicitud_maquina,
                    $seriesPorRepuesto
                );
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
    private function procesarSalidaCantidadDesdeMantenimiento($id_mantenimiento, $id_maquina, $r, $referencia = 'MANTENIMIENTO')
    {
        $cantidad = isset($r->cantidad) ? (int)$r->cantidad : 1;

        if ($cantidad <= 0) throw new Exception("Cantidad inválida para repuesto ID {$r->id_repuesto}");

        // Descuento atómico: la condición stock >= cantidad evita stock negativo
        // ante operaciones concurrentes. La salida se valora al costo promedio
        // vigente del repuesto, no al costo que envíe el front.
        $stmtStock = $this->conn->prepare(
            "UPDATE electronicas.Repuestos
             SET stock = stock - ?
             OUTPUT DELETED.stock          AS stock_anterior,
                    INSERTED.stock         AS stock_nuevo,
                    DELETED.costo_promedio AS costo_promedio
             WHERE id_repuesto = ? AND stock >= ?"
        );
        $stmtStock->execute([$cantidad, $r->id_repuesto, $cantidad]);
        $stocks = $stmtStock->fetch(PDO::FETCH_ASSOC);

        if (!$stocks) {
            $disponible = $this->obtenerStock($r->id_repuesto);
            throw new Exception("Stock insuficiente para repuesto ID {$r->id_repuesto}. Disponible: {$disponible}");
        }

        $costo = (float)$stocks['costo_promedio'];

        $this->conn->prepare(
            "INSERT INTO electronicas.MantenimientoRepuestos
                 (id_mantenimiento, id_repuesto, cantidad, costo_unitario)
             VALUES (?, ?, ?, ?)"
        )->execute([$id_mantenimiento, $r->id_repuesto, $cantidad, $costo]);

        $this->conn->prepare(
            "INSERT INTO electronicas.MovimientosRepuestos
                 (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                  stock_anterior, stock_nuevo, id_maquina, referencia)
             VALUES (?, 2, ?, ?, ?, ?, ?, ?)"
        )->execute([$r->id_repuesto, $cantidad, $costo, $stocks['stock_anterior'], $stocks['stock_nuevo'], $id_maquina, $referencia]);

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
    private function procesarSalidaSerieDesdeMantenimiento($id_mantenimiento, $id_maquina, $r, $referencia = 'MANTENIMIENTO')
    {
        if (empty($r->series) || !is_array($r->series)) {
            throw new Exception("Debes seleccionar al menos una serie para repuesto ID {$r->id_repuesto}");
        }

        // La salida se valora al costo promedio vigente del repuesto
        $stmtCP = $this->conn->prepare(
            "SELECT costo_promedio FROM electronicas.Repuestos WHERE id_repuesto = ?"
        );
        $stmtCP->execute([$r->id_repuesto]);
        $costo = (float)$stmtCP->fetchColumn();

        foreach ($r->series as $id_detalle) {
            // Cambio de estado atómico: solo procesa si la serie sigue disponible
            $stmtUpd = $this->conn->prepare(
                "UPDATE electronicas.RepuestosDetalle
                 SET id_estado_repuesto = 2, id_maquina_actual = ?
                 OUTPUT INSERTED.serie
                 WHERE id_detalle_repuesto = ? AND id_repuesto = ?
                   AND id_estado_repuesto = 1"
            );
            $stmtUpd->execute([$id_maquina, $id_detalle, $r->id_repuesto]);
            if (!$stmtUpd->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Serie no encontrada o no disponible (repuesto ID {$r->id_repuesto})");
            }

            $this->conn->prepare(
                "INSERT INTO electronicas.MantenimientoRepuestos
                     (id_mantenimiento, id_repuesto, id_detalle_repuesto, cantidad, costo_unitario)
                 VALUES (?, ?, ?, 1, ?)"
            )->execute([$id_mantenimiento, $r->id_repuesto, $id_detalle, $costo]);

            // Saldo corrido: conteo de series disponibles tras la salida
            $saldoNuevo = $this->contarSeriesDisponibles($r->id_repuesto);

            $this->conn->prepare(
                "INSERT INTO electronicas.MovimientosRepuestos
                     (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                      costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia)
                 VALUES (?, ?, 2, 1, ?, ?, ?, ?, ?)"
            )->execute([
                $r->id_repuesto, $id_detalle, $costo,
                $saldoNuevo + 1, $saldoNuevo, $id_maquina, $referencia
            ]);

            // Registro de pieza instalada en máquina
            $this->conn->prepare(
                "INSERT INTO electronicas.MaquinaRepuestos
                     (id_maquina, id_repuesto, id_detalle_repuesto, cantidad, id_mantenimiento_instalacion)
                 VALUES (?, ?, ?, 1, ?)"
            )->execute([$id_maquina, $r->id_repuesto, $id_detalle, $id_mantenimiento]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // SOLICITUDES DE REPUESTOS APROBADAS (disponibles para usar)
    // ══════════════════════════════════════════════════════════════
    public function listarSolicitudesDisponibles(int $id_maquina): array
    {
        $stmt = $this->conn->prepare(
            "SELECT sm.id_solicitud_maquina,
                    sr.id_solicitud,
                    sr.codigo,
                    sr.descripcion,
                    sm.descripcion                              AS descripcion_maquina,
                    FORMAT(sr.fecha_programada, 'dd/MM/yyyy')   AS fecha_programada,
                    sr.id_tipo,
                    sr.id_tecnico,
                    tm.nombre                                   AS tipo,
                    u.nombre                                    AS solicitante
             FROM electronicas.SolicitudesMaquinas sm
             INNER JOIN electronicas.SolicitudesRepuestos sr ON sr.id_solicitud = sm.id_solicitud
             INNER JOIN electronicas.TipoMantenimiento    tm ON tm.id_tipo      = sr.id_tipo
             INNER JOIN electronicas.Usuarios             u  ON u.id_usuario    = sr.id_usuario
             WHERE sm.id_maquina = ?
               AND sr.estado = 'Aprobado'
               AND sm.id_mantenimiento_generado IS NULL
             ORDER BY sr.id_solicitud DESC"
        );
        $stmt->execute([$id_maquina]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerRepuestosSolicitudMaquina(int $id_solicitud_maquina): array
    {
        $stmt = $this->conn->prepare(
            "SELECT sd.id_repuesto,
                    r.nombre AS repuesto,
                    sd.cantidad,
                    r.maneja_serie,
                    CASE WHEN r.maneja_serie = 1
                        THEN (SELECT COUNT(*) FROM electronicas.RepuestosDetalle rd
                              WHERE rd.id_repuesto = r.id_repuesto AND rd.id_estado_repuesto = 1)
                        ELSE r.stock END AS stock_actual
             FROM electronicas.SolicitudesDetalle sd
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = sd.id_repuesto
             WHERE sd.id_solicitud_maquina = ?
             ORDER BY r.nombre"
        );
        $stmt->execute([$id_solicitud_maquina]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // CONSUMIR SOLICITUD — descuenta los repuestos de la solicitud
    // aprobada y la enlaza al mantenimiento. Se ejecuta dentro de la
    // transacción de guardarMantenimiento.
    // ══════════════════════════════════════════════════════════════
    private function consumirSolicitud(int $id_mantenimiento, int $id_maquina, int $id_solicitud_maquina, array $seriesPorRepuesto): void
    {
        // Normalizar claves a entero (el JSON llega con claves string)
        $seriesNorm = [];
        foreach ($seriesPorRepuesto as $k => $v) {
            $seriesNorm[(int)$k] = (array)$v;
        }
        $seriesPorRepuesto = $seriesNorm;

        // Validar que la línea pertenezca a una solicitud aprobada
        // para esta máquina y siga sin usarse
        $stmtV = $this->conn->prepare(
            "SELECT sm.id_solicitud, sm.id_maquina, sm.id_mantenimiento_generado, sr.estado
             FROM electronicas.SolicitudesMaquinas sm
             INNER JOIN electronicas.SolicitudesRepuestos sr ON sr.id_solicitud = sm.id_solicitud
             WHERE sm.id_solicitud_maquina = ?"
        );
        $stmtV->execute([$id_solicitud_maquina]);
        $sol = $stmtV->fetch(PDO::FETCH_ASSOC);

        if (!$sol)                                   throw new Exception('Solicitud no encontrada.');
        if ($sol['estado'] !== 'Aprobado')           throw new Exception('La solicitud no está en estado Aprobado.');
        if ((int)$sol['id_maquina'] !== $id_maquina) throw new Exception('La solicitud no corresponde a la máquina seleccionada.');

        // Reclamo atómico: evita que dos mantenimientos usen la misma línea
        $stmtC = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesMaquinas
             SET id_mantenimiento_generado = ?
             WHERE id_solicitud_maquina = ? AND id_mantenimiento_generado IS NULL"
        );
        $stmtC->execute([$id_mantenimiento, $id_solicitud_maquina]);
        if ($stmtC->rowCount() === 0) {
            throw new Exception('Esta solicitud ya fue usada en otro mantenimiento.');
        }

        $referencia = 'SOL-' . str_pad((int)$sol['id_solicitud'], 5, '0', STR_PAD_LEFT);

        // Consumir cada repuesto de la solicitud
        $stmtD = $this->conn->prepare(
            "SELECT sd.id_repuesto, sd.cantidad, r.nombre, r.maneja_serie
             FROM electronicas.SolicitudesDetalle sd
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = sd.id_repuesto
             WHERE sd.id_solicitud_maquina = ?"
        );
        $stmtD->execute([$id_solicitud_maquina]);

        foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $rep) {
            $cantidad = (int)$rep['cantidad'];

            if ((int)$rep['maneja_serie'] === 1) {
                // Series elegidas por el usuario para este repuesto
                $series = array_values(array_filter(array_map(
                    'intval',
                    $seriesPorRepuesto[(int)$rep['id_repuesto']] ?? []
                )));
                if (count($series) !== $cantidad) {
                    throw new Exception(
                        "Debes seleccionar exactamente {$cantidad} serie(s) para \"{$rep['nombre']}\"."
                    );
                }
                $r = (object)[
                    'id_repuesto' => $rep['id_repuesto'],
                    'series'      => $series
                ];
                $this->procesarSalidaSerieDesdeMantenimiento($id_mantenimiento, $id_maquina, $r, $referencia);
            } else {
                $r = (object)[
                    'id_repuesto' => $rep['id_repuesto'],
                    'cantidad'    => $cantidad
                ];
                $this->procesarSalidaCantidadDesdeMantenimiento($id_mantenimiento, $id_maquina, $r, $referencia);
            }
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

            // Saldo corrido: la devolución suma una serie disponible; la baja no
            $saldoNuevo = $this->contarSeriesDisponibles($instalado['id_repuesto']);
            $saldoAnt   = ($tipo === 'devolucion') ? $saldoNuevo - 1 : $saldoNuevo;

            $this->conn->prepare(
                "INSERT INTO electronicas.MovimientosRepuestos
                     (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                      costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia, observaciones)
                 VALUES (?, ?, ?, 1, 0, ?, ?, ?, 'RETIRO', ?)"
            )->execute([
                $instalado['id_repuesto'],
                $instalado['id_detalle_repuesto'],
                $idTipoMov,
                $saldoAnt,
                $saldoNuevo,
                $id_maquina,
                $obs
            ]);

        } else {
            // Cantidad
            if ($tipo === 'devolucion') {
                // Devolución al stock: incremento atómico
                $stmtStock = $this->conn->prepare(
                    "UPDATE electronicas.Repuestos
                     SET stock = stock + ?
                     OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                     WHERE id_repuesto = ?"
                );
                $stmtStock->execute([$cantidad, $instalado['id_repuesto']]);
                $stocks = $stmtStock->fetch(PDO::FETCH_ASSOC);
                if (!$stocks) throw new Exception("Repuesto ID {$instalado['id_repuesto']} no encontrado.");
                $stockActual = (int)$stocks['stock_anterior'];
                $stockNuevo  = (int)$stocks['stock_nuevo'];
            } else {
                // Baja: no devuelve al stock, solo registra el movimiento
                $stockActual = $this->obtenerStock($instalado['id_repuesto']);
                $stockNuevo  = $stockActual;
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
                    // Descuento atómico; si el stock ya no alcanza → conflicto, saltar
                    $stmtStock = $this->conn->prepare(
                        "UPDATE electronicas.Repuestos
                         SET stock = stock - ?
                         OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                         WHERE id_repuesto = ? AND stock >= ?"
                    );
                    $stmtStock->execute([$cant, $ret['id_repuesto'], $cant]);
                    $stocks = $stmtStock->fetch(PDO::FETCH_ASSOC);
                    if (!$stocks) continue; // conflicto → saltar

                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                              stock_anterior, stock_nuevo, referencia, observaciones)
                         VALUES (?, ?, ?, 0, ?, ?, 'ANULACION', ?)"
                    )->execute([$ret['id_repuesto'], $idTipoSalida, $cant,
                                $stocks['stock_anterior'], $stocks['stock_nuevo'], $obsBase]);

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

                    // Saldo corrido: conteo de series disponibles tras la reversión
                    $saldoNuevo = $this->contarSeriesDisponibles($inst['id_repuesto']);

                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                              costo_unitario, stock_anterior, stock_nuevo, referencia, observaciones)
                         VALUES (?, ?, ?, 1, 0, ?, ?, 'ANULACION', ?)"
                    )->execute([
                        $inst['id_repuesto'], $idDet, $idTipoEntrada,
                        $saldoNuevo - 1, $saldoNuevo, $obsBase
                    ]);
                } else {
                    // Cantidad: restaurar stock con incremento atómico
                    $stmtStock = $this->conn->prepare(
                        "UPDATE electronicas.Repuestos
                         SET stock = stock + ?
                         OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                         WHERE id_repuesto = ?"
                    );
                    $stmtStock->execute([(int)$inst['cantidad'], $inst['id_repuesto']]);
                    $stocks = $stmtStock->fetch(PDO::FETCH_ASSOC);
                    if (!$stocks) continue;

                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                              stock_anterior, stock_nuevo, referencia, observaciones)
                         VALUES (?, ?, ?, 0, ?, ?, 'ANULACION', ?)"
                    )->execute([$inst['id_repuesto'], $idTipoEntrada, $inst['cantidad'],
                                $stocks['stock_anterior'], $stocks['stock_nuevo'], $obsBase]);
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

            // ── D. Liberar la solicitud vinculada (si existe) ──
            // El stock ya se revirtió con los contra-movimientos, así
            // que la línea de solicitud vuelve a quedar disponible
            // para usarse en otro mantenimiento.
            $this->conn->prepare(
                "UPDATE electronicas.SolicitudesMaquinas
                 SET id_mantenimiento_generado = NULL
                 WHERE id_mantenimiento_generado = ?"
            )->execute([$id]);

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

            // Los repuestos instalados provienen de la solicitud usada:
            // no se modifican en la edición. Solo cabecera, tareas y retiros.

            // Revertir los retiros previos sin borrar historial (se reprocesan abajo)
            $this->revertirRetirosPrevios($id_mantenimiento);

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

            // Reprocesar retiros (los previos ya fueron revertidos al inicio)
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

    /**
     * Revierte los retiros registrados por este mantenimiento sin borrar el
     * historial: deshace el efecto de stock/serie de cada retiro (misma lógica
     * que ejecutarAnulacion paso A) y reabre la fila de MaquinaRepuestos para
     * que el retiro pueda reprocesarse. La fila pertenece a la instalación
     * original (otro mantenimiento), por eso nunca debe eliminarse.
     */
    private function revertirRetirosPrevios(int $id_mantenimiento): void
    {
        $stmtR = $this->conn->prepare(
            "SELECT maq.id_maquina_repuesto, maq.id_maquina, maq.id_repuesto,
                    maq.id_detalle_repuesto, maq.cantidad, maq.tipo_retiro,
                    r.maneja_serie, r.nombre AS repuesto
             FROM electronicas.MaquinaRepuestos maq
             INNER JOIN electronicas.Repuestos r ON r.id_repuesto = maq.id_repuesto
             WHERE maq.id_mantenimiento_retiro = ?"
        );
        $stmtR->execute([$id_mantenimiento]);
        $retiros = $stmtR->fetchAll(PDO::FETCH_ASSOC);
        if (!$retiros) return;

        $idTipoSalida = (int)($this->conn->query(
            "SELECT TOP 1 id_tipo_movimiento FROM electronicas.TiposMovimientoRepuesto
             WHERE nombre = 'Salida'"
        )->fetchColumn() ?: 2);

        $obsBase = 'Reversión de retiro por edición de mantenimiento #' . $id_mantenimiento;

        foreach ($retiros as $ret) {
            $cant    = (int)$ret['cantidad'];
            $esSerie = (int)$ret['maneja_serie'] === 1 && $ret['id_detalle_repuesto'];

            if ($ret['tipo_retiro'] === 'devolucion') {
                if ($esSerie) {
                    // La devolución dejó la serie "disponible"; volverla a "instalada"
                    // solo si nadie la tomó después (guarda atómica)
                    $stmtSerie = $this->conn->prepare(
                        "UPDATE electronicas.RepuestosDetalle
                         SET id_estado_repuesto = 2, id_maquina_actual = ?
                         WHERE id_detalle_repuesto = ? AND id_estado_repuesto = 1"
                    );
                    $stmtSerie->execute([$ret['id_maquina'], $ret['id_detalle_repuesto']]);
                    if ($stmtSerie->rowCount() === 0) {
                        throw new Exception(
                            "No se puede editar: la serie devuelta de '{$ret['repuesto']}' ya no está disponible en inventario."
                        );
                    }

                    $saldoNuevo = $this->contarSeriesDisponibles($ret['id_repuesto']);

                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_detalle_repuesto, id_tipo_movimiento, cantidad,
                              costo_unitario, stock_anterior, stock_nuevo, id_maquina, referencia, observaciones)
                         VALUES (?, ?, ?, 1, 0, ?, ?, ?, 'EDICION', ?)"
                    )->execute([
                        $ret['id_repuesto'], $ret['id_detalle_repuesto'], $idTipoSalida,
                        $saldoNuevo + 1, $saldoNuevo, $ret['id_maquina'], $obsBase
                    ]);
                } else {
                    // Descontar el stock que la devolución había sumado (atómico)
                    $stmtStock = $this->conn->prepare(
                        "UPDATE electronicas.Repuestos
                         SET stock = stock - ?
                         OUTPUT DELETED.stock AS stock_anterior, INSERTED.stock AS stock_nuevo
                         WHERE id_repuesto = ? AND stock >= ?"
                    );
                    $stmtStock->execute([$cant, $ret['id_repuesto'], $cant]);
                    $stocks = $stmtStock->fetch(PDO::FETCH_ASSOC);
                    if (!$stocks) {
                        throw new Exception(
                            "No se puede editar: el stock actual de '{$ret['repuesto']}' no alcanza para revertir la devolución de {$cant} unidad(es)."
                        );
                    }

                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                             (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                              stock_anterior, stock_nuevo, id_maquina, referencia, observaciones)
                         VALUES (?, ?, ?, 0, ?, ?, ?, 'EDICION', ?)"
                    )->execute([
                        $ret['id_repuesto'], $idTipoSalida, $cant,
                        $stocks['stock_anterior'], $stocks['stock_nuevo'],
                        $ret['id_maquina'], $obsBase
                    ]);
                }
            } elseif ($esSerie) {
                // Baja de serie: restaurarla como instalada (sin mover stock)
                $this->conn->prepare(
                    "UPDATE electronicas.RepuestosDetalle
                     SET id_estado_repuesto = 2, id_maquina_actual = ?
                     WHERE id_detalle_repuesto = ?"
                )->execute([$ret['id_maquina'], $ret['id_detalle_repuesto']]);
            }
            // Baja por cantidad: no movió stock, nada que revertir

            // Reabrir el registro: la pieza vuelve a estar instalada
            $this->conn->prepare(
                "UPDATE electronicas.MaquinaRepuestos
                 SET fecha_retiro = NULL, tipo_retiro = NULL,
                     id_mantenimiento_retiro = NULL, observaciones_retiro = NULL
                 WHERE id_maquina_repuesto = ?"
            )->execute([$ret['id_maquina_repuesto']]);
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

    private function contarSeriesDisponibles($id_repuesto): int
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.RepuestosDetalle
             WHERE id_repuesto = ? AND id_estado_repuesto = 1"
        );
        $stmt->execute([$id_repuesto]);
        return (int)$stmt->fetchColumn();
    }
}
