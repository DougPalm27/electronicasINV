<?php

class mdlSolicitudesCompra
{
    private $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ══════════════════════════════════════════════════════
    // CATÁLOGOS
    // ══════════════════════════════════════════════════════

    public function listarProveedores(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_proveedor, nombre FROM electronicas.Proveedores
             WHERE estado = 1 ORDER BY nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDivisas(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_divisa, nombre, simbolo, codigo, tipo_cambio, predeterminada
             FROM electronicas.Divisas
             WHERE activo = 1 ORDER BY predeterminada DESC, nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarRepuestos(): array
    {
        $stmt = $this->conn->query(
            "SELECT r.id_repuesto, r.nombre, r.maneja_serie, r.stock_minimo,
                    r.costo_promedio,
                    CASE WHEN r.maneja_serie = 1
                         THEN (SELECT COUNT(*) FROM electronicas.RepuestosDetalle d
                               WHERE d.id_repuesto = r.id_repuesto
                                 AND d.id_estado_repuesto = 1)
                         ELSE r.stock
                    END AS stock,
                    ma.nombre AS marca,
                    mo.nombre AS modelo
             FROM electronicas.Repuestos r
             LEFT JOIN electronicas.Marcas  ma ON ma.id_marca  = r.id_marca
             LEFT JOIN electronicas.Modelos mo ON mo.id_modelo = r.id_modelo
             WHERE r.id_estado = 1
             ORDER BY ma.nombre, mo.nombre, r.nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════
    // LISTAR SOLICITUDES
    // ══════════════════════════════════════════════════════

    public function listarSolicitudes(?int $id_usuario = null): array
    {
        $where = $id_usuario ? "WHERE sc.id_usuario = $id_usuario" : '';

        $sql = "SELECT sc.id_solicitud_compra,
                       sc.codigo,
                       sc.descripcion,
                       sc.estado,
                       FORMAT(sc.fecha_solicitud, 'dd/MM/yyyy') AS fecha_solicitud,
                       u.nombre                                  AS solicitante,
                       ISNULL(p.nombre, '—')                    AS proveedor,
                       d.simbolo                                 AS divisa_simbolo,
                       d.codigo                                  AS divisa_codigo,
                       COUNT(det.id_detalle)                     AS total_items,
                       SUM(det.cantidad_solicitada * det.costo_unitario) AS total_estimado,
                       sc.numero_orden
                FROM electronicas.SolicitudesCompra sc
                INNER JOIN electronicas.Usuarios   u   ON u.id_usuario   = sc.id_usuario
                INNER JOIN electronicas.Divisas    d   ON d.id_divisa    = sc.id_divisa
                LEFT  JOIN electronicas.Proveedores p  ON p.id_proveedor = sc.id_proveedor
                LEFT  JOIN electronicas.SolicitudesCompraDetalle det
                       ON det.id_solicitud_compra = sc.id_solicitud_compra
                $where
                GROUP BY sc.id_solicitud_compra, sc.codigo, sc.descripcion, sc.estado,
                         sc.fecha_solicitud, u.nombre, p.nombre,
                         d.simbolo, d.codigo, sc.numero_orden
                ORDER BY sc.codigo DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════
    // DETALLE COMPLETO
    // ══════════════════════════════════════════════════════

    public function obtenerDetalle(int $id): array
    {
        $stmtH = $this->conn->prepare(
            "SELECT sc.*,
                    u.nombre                                   AS solicitante,
                    ISNULL(p.nombre, '—')                     AS proveedor_nombre,
                    d.nombre                                   AS divisa_nombre,
                    d.simbolo                                  AS divisa_simbolo,
                    d.tipo_cambio                              AS divisa_tc,
                    ISNULL(ap.nombre, '—')                    AS aprobador_nombre,
                    ISNULL(rc.nombre, '—')                    AS receptor_nombre,
                    FORMAT(sc.fecha_solicitud,  'dd/MM/yyyy HH:mm') AS fecha_sol_fmt,
                    FORMAT(sc.fecha_aprobacion, 'dd/MM/yyyy HH:mm') AS fecha_apr_fmt,
                    FORMAT(sc.fecha_orden,      'dd/MM/yyyy HH:mm') AS fecha_ord_fmt,
                    FORMAT(sc.fecha_recepcion,  'dd/MM/yyyy HH:mm') AS fecha_rec_fmt
             FROM electronicas.SolicitudesCompra sc
             INNER JOIN electronicas.Usuarios   u   ON u.id_usuario   = sc.id_usuario
             INNER JOIN electronicas.Divisas    d   ON d.id_divisa    = sc.id_divisa
             LEFT  JOIN electronicas.Proveedores p  ON p.id_proveedor = sc.id_proveedor
             LEFT  JOIN electronicas.Usuarios   ap  ON ap.id_usuario  = sc.id_aprobador
             LEFT  JOIN electronicas.Usuarios   rc  ON rc.id_usuario  = sc.id_receptor
             WHERE sc.id_solicitud_compra = ?"
        );
        $stmtH->execute([$id]);
        $cab = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$cab) return [];

        $stmtD = $this->conn->prepare(
            "SELECT det.id_detalle,
                    r.id_repuesto,
                    ISNULL(r.nombre, det.nombre_externo) AS repuesto,
                    ISNULL(r.maneja_serie, 0)            AS maneja_serie,
                    ma.nombre  AS marca,
                    mo.nombre  AS modelo,
                    det.nombre_externo,
                    det.enlace_externo,
                    det.id_proveedor,
                    pv.nombre  AS proveedor_item,
                    det.cantidad_solicitada,
                    det.cantidad_recibida,
                    det.costo_unitario,
                    det.costo_recibido,
                    det.fecha_entrega_est,
                    FORMAT(det.fecha_entrega_est, 'dd/MM/yyyy') AS fecha_ent_fmt,
                    CASE
                        WHEN r.id_repuesto IS NULL  THEN NULL
                        WHEN r.maneja_serie = 1
                             THEN (SELECT COUNT(*) FROM electronicas.RepuestosDetalle rd
                                   WHERE rd.id_repuesto = r.id_repuesto
                                     AND rd.id_estado_repuesto = 1)
                        ELSE r.stock
                    END AS stock_actual
             FROM electronicas.SolicitudesCompraDetalle det
             LEFT  JOIN electronicas.Repuestos  r   ON r.id_repuesto  = det.id_repuesto
             LEFT  JOIN electronicas.Marcas     ma  ON ma.id_marca    = r.id_marca
             LEFT  JOIN electronicas.Modelos    mo  ON mo.id_modelo   = r.id_modelo
             LEFT  JOIN electronicas.Proveedores pv ON pv.id_proveedor = det.id_proveedor
             WHERE det.id_solicitud_compra = ?
             ORDER BY repuesto"
        );
        $stmtD->execute([$id]);
        $cab['items'] = $stmtD->fetchAll(PDO::FETCH_ASSOC);
        return $cab;
    }

    // ══════════════════════════════════════════════════════
    // CREAR / ACTUALIZAR BORRADOR
    // ══════════════════════════════════════════════════════

    public function guardarBorrador(array $d, ?int $id_existente = null): int
    {
        $this->conn->beginTransaction();
        try {
            if ($id_existente) {
                // Solo se puede editar si está en Borrador
                $chk = $this->conn->prepare(
                    "SELECT estado FROM electronicas.SolicitudesCompra
                     WHERE id_solicitud_compra = ? AND id_usuario = ?"
                );
                $chk->execute([$id_existente, $d['id_usuario']]);
                $row = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$row || $row['estado'] !== 'Borrador') {
                    throw new RuntimeException('Solo se puede editar una solicitud en estado Borrador.');
                }

                $this->conn->prepare(
                    "UPDATE electronicas.SolicitudesCompra
                     SET id_proveedor = ?, id_divisa = ?, descripcion = ?, notas = ?
                     WHERE id_solicitud_compra = ?"
                )->execute([
                    $d['id_proveedor'] ?: null,
                    $d['id_divisa'],
                    $d['descripcion'],
                    $d['notas'] ?: null,
                    $id_existente
                ]);

                // Borrar ítems anteriores y reinsertar
                $this->conn->prepare(
                    "DELETE FROM electronicas.SolicitudesCompraDetalle
                     WHERE id_solicitud_compra = ?"
                )->execute([$id_existente]);

                $id = $id_existente;

            } else {
                $stmt = $this->conn->prepare(
                    "INSERT INTO electronicas.SolicitudesCompra
                        (id_usuario, id_proveedor, id_divisa, descripcion, notas, estado)
                     OUTPUT INSERTED.id_solicitud_compra
                     VALUES (?, ?, ?, ?, ?, 'Borrador')"
                );
                $stmt->execute([
                    $d['id_usuario'],
                    $d['id_proveedor'] ?: null,
                    $d['id_divisa'],
                    $d['descripcion'],
                    $d['notas'] ?: null,
                ]);
                $id = (int)$stmt->fetchColumn();
            }

            // Insertar ítems (catálogo o externo)
            $ins = $this->conn->prepare(
                "INSERT INTO electronicas.SolicitudesCompraDetalle
                    (id_solicitud_compra, id_repuesto, nombre_externo, enlace_externo,
                     id_proveedor, cantidad_solicitada, costo_unitario)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($d['items'] as $it) {
                $idRep   = !empty($it['id_repuesto'])    ? (int)$it['id_repuesto']    : null;
                $nomExt  = !empty($it['nombre_externo']) ? trim($it['nombre_externo']) : null;
                $enlace  = !empty($it['enlace_externo']) ? trim($it['enlace_externo']) : null;
                $idProv  = !empty($it['id_proveedor'])   ? (int)$it['id_proveedor']   : null;

                // Debe tener al menos uno de los dos
                if (!$idRep && !$nomExt) continue;

                $ins->execute([
                    $id,
                    $idRep,
                    $nomExt,
                    $enlace,
                    $idProv,
                    max(1, (int)$it['cantidad']),
                    max(0, (float)$it['costo_unitario'])
                ]);
            }

            $this->conn->commit();
            return $id;

        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ══════════════════════════════════════════════════════
    // ENVIAR (Borrador → Pendiente)
    // ══════════════════════════════════════════════════════

    public function enviarSolicitud(int $id, int $id_usuario): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesCompra
             SET estado = 'Pendiente'
             WHERE id_solicitud_compra = ?
               AND id_usuario = ?
               AND estado = 'Borrador'"
        );
        $stmt->execute([$id, $id_usuario]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('No se pudo enviar la solicitud. Verifica que sea un borrador tuyo.');
        }
    }

    // ══════════════════════════════════════════════════════
    // APROBAR (Pendiente → Aprobada)
    // ══════════════════════════════════════════════════════

    public function aprobarSolicitud(int $id, int $id_aprobador): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesCompra
             SET estado = 'Aprobada', id_aprobador = ?, fecha_aprobacion = GETDATE()
             WHERE id_solicitud_compra = ? AND estado = 'Pendiente'"
        );
        $stmt->execute([$id_aprobador, $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('La solicitud no está en estado Pendiente.');
        }
    }

    // ══════════════════════════════════════════════════════
    // RECHAZAR (Pendiente → Rechazada)
    // ══════════════════════════════════════════════════════

    public function rechazarSolicitud(int $id, int $id_aprobador, string $motivo): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesCompra
             SET estado = 'Rechazada', id_aprobador = ?, fecha_aprobacion = GETDATE(),
                 motivo_rechazo = ?
             WHERE id_solicitud_compra = ? AND estado = 'Pendiente'"
        );
        $stmt->execute([$id_aprobador, $motivo, $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('La solicitud no está en estado Pendiente.');
        }
    }

    // ══════════════════════════════════════════════════════
    // REGISTRAR ORDEN (Aprobada → Ordenada)
    // ══════════════════════════════════════════════════════

    public function registrarOrden(int $id, string $numero_orden, array $fechas_items): void
    {
        $this->conn->beginTransaction();
        try {
            // Actualizar cabecera
            $stmt = $this->conn->prepare(
                "UPDATE electronicas.SolicitudesCompra
                 SET estado       = 'Ordenada',
                     numero_orden = ?,
                     fecha_orden  = GETDATE()
                 WHERE id_solicitud_compra = ? AND estado = 'Aprobada'"
            );
            $stmt->execute([$numero_orden ?: null, $id]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('La solicitud no está en estado Aprobada.');
            }

            // Actualizar fecha estimada por ítem
            $stmtD = $this->conn->prepare(
                "UPDATE electronicas.SolicitudesCompraDetalle
                 SET fecha_entrega_est = ?
                 WHERE id_detalle = ? AND id_solicitud_compra = ?"
            );
            foreach ($fechas_items as $item) {
                $idDet = (int)($item['id_detalle'] ?? 0);
                $fecha = trim($item['fecha_entrega_est'] ?? '');
                if (!$idDet) continue;
                $stmtD->execute([$fecha ?: null, $idDet, $id]);
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ══════════════════════════════════════════════════════
    // REGISTRAR RECEPCIÓN (Ordenada | Recibida parcial → Recibida parcial | Recibida)
    // Soporta entregas parciales: acumula cantidades y determina
    // el estado final según si todos los ítems quedaron completos.
    // ══════════════════════════════════════════════════════

    public function registrarRecepcion(int $id, int $id_receptor, array $recepciones): array
    {
        $this->conn->beginTransaction();
        try {
            // Verificar estado — acepta Ordenada y Recibida parcial
            $stmtH = $this->conn->prepare(
                "SELECT sc.estado, d.tipo_cambio, sc.id_proveedor
                 FROM electronicas.SolicitudesCompra sc
                 INNER JOIN electronicas.Divisas d ON d.id_divisa = sc.id_divisa
                 WHERE sc.id_solicitud_compra = ?"
            );
            $stmtH->execute([$id]);
            $sol = $stmtH->fetch(PDO::FETCH_ASSOC);

            if (!$sol || !in_array($sol['estado'], ['Ordenada', 'Recibida parcial'])) {
                throw new RuntimeException('La solicitud debe estar en estado Ordenada o Recibida parcial.');
            }

            $tc          = (float)($sol['tipo_cambio'] ?? 1);
            $id_proveedor = $sol['id_proveedor'] ?: null;
            $referencia  = 'OC-' . str_pad($id, 6, '0', STR_PAD_LEFT);
            $warnings    = [];

            foreach ($recepciones as $rec) {
                $id_detalle  = (int)$rec['id_detalle'];
                $maneja_serie = (int)$rec['maneja_serie'];

                // Obtener detalle original
                $stmtD = $this->conn->prepare(
                    "SELECT det.id_repuesto, det.nombre_externo,
                            det.cantidad_solicitada,
                            det.id_proveedor            AS id_proveedor_item,
                            ISNULL(r.stock, 0)          AS stock,
                            ISNULL(r.costo_promedio, 0) AS costo_promedio,
                            ISNULL(r.maneja_serie, 0)   AS maneja_serie
                     FROM electronicas.SolicitudesCompraDetalle det
                     LEFT JOIN electronicas.Repuestos r ON r.id_repuesto = det.id_repuesto
                     WHERE det.id_detalle = ? AND det.id_solicitud_compra = ?"
                );
                $stmtD->execute([$id_detalle, $id]);
                $det = $stmtD->fetch(PDO::FETCH_ASSOC);
                if (!$det) continue;

                $id_repuesto = !empty($det['id_repuesto']) ? (int)$det['id_repuesto'] : null;

                // ── Ítem externo (sin repuesto en catálogo) ──
                if (!$id_repuesto) {
                    $cant_recibida = max(0, (int)($rec['cantidad_recibida'] ?? 0));
                    if ($cant_recibida === 0) continue;
                    $this->conn->prepare(
                        "UPDATE electronicas.SolicitudesCompraDetalle
                         SET cantidad_recibida = cantidad_recibida + ?,
                             costo_recibido    = ?
                         WHERE id_detalle = ?"
                    )->execute([$cant_recibida, (float)($rec['costo_recibido'] ?? 0), $id_detalle]);
                    $warnings[] = 'Ítem externo "' . ($det['nombre_externo'] ?? $id_detalle) . '" marcado como recibido. Agrégalo al catálogo para actualizar inventario.';
                    continue;
                }

                if ($maneja_serie === 0) {
                    // ── Repuesto por cantidad ──────────────────────
                    $cant_recibida = max(0, (int)$rec['cantidad_recibida']);
                    if ($cant_recibida === 0) continue;

                    $costo_unitario = (float)$rec['costo_recibido'];
                    $costo_lps      = $costo_unitario * $tc;

                    $stock_anterior  = (int)$det['stock'];
                    $nuevo_stock     = $stock_anterior + $cant_recibida;

                    // Promedio ponderado en Lempiras
                    $cp_anterior = (float)$det['costo_promedio'];
                    $nuevo_cp    = $stock_anterior > 0
                        ? ($stock_anterior * $cp_anterior + $cant_recibida * $costo_lps) / $nuevo_stock
                        : $costo_lps;

                    // Actualizar Repuestos
                    $this->conn->prepare(
                        "UPDATE electronicas.Repuestos
                         SET stock = ?, costo_promedio = ?
                         WHERE id_repuesto = ?"
                    )->execute([$nuevo_stock, round($nuevo_cp, 4), $id_repuesto]);

                    // Proveedor: usa el del ítem si tiene, si no el de la cabecera
                    $id_prov_mov = !empty($det['id_proveedor_item'])
                        ? (int)$det['id_proveedor_item']
                        : $id_proveedor;

                    // Movimiento de entrada
                    $this->conn->prepare(
                        "INSERT INTO electronicas.MovimientosRepuestos
                            (id_repuesto, id_tipo_movimiento, cantidad, costo_unitario,
                             stock_anterior, stock_nuevo, referencia, id_proveedor, tipo_entrada)
                         VALUES (?, 1, ?, ?, ?, ?, ?, ?, 'Orden de Compra')"
                    )->execute([
                        $id_repuesto, $cant_recibida, $costo_lps,
                        $stock_anterior, $nuevo_stock, $referencia, $id_prov_mov
                    ]);

                    // Acumular cantidad_recibida (no reemplazar — soporta entregas parciales)
                    $this->conn->prepare(
                        "UPDATE electronicas.SolicitudesCompraDetalle
                         SET cantidad_recibida = cantidad_recibida + ?,
                             costo_recibido    = ?
                         WHERE id_detalle = ?"
                    )->execute([$cant_recibida, $costo_unitario, $id_detalle]);

                } else {
                    // ── Repuesto por serie ─────────────────────────
                    $series    = array_filter(
                        array_map('trim', (array)($rec['series'] ?? [])),
                        fn($s) => $s !== ''
                    );
                    if (empty($series)) continue;

                    $insertadas  = 0;
                    $duplicadas  = [];

                    foreach ($series as $serie) {
                        // Verificar duplicado global
                        $chk = $this->conn->prepare(
                            "SELECT COUNT(*) FROM electronicas.RepuestosDetalle WHERE serie = ?"
                        );
                        $chk->execute([$serie]);
                        if ((int)$chk->fetchColumn() > 0) {
                            $duplicadas[] = $serie;
                            continue;
                        }

                        $this->conn->prepare(
                            "INSERT INTO electronicas.RepuestosDetalle
                                (id_repuesto, serie, id_estado_repuesto)
                             VALUES (?, ?, 1)"
                        )->execute([$id_repuesto, $serie]);

                        $insertadas++;
                    }

                    if (!empty($duplicadas)) {
                        $warnings[] = 'Series duplicadas (ignoradas): ' . implode(', ', $duplicadas);
                    }

                    if ($insertadas > 0) {
                        // Acumular series recibidas
                        $this->conn->prepare(
                            "UPDATE electronicas.SolicitudesCompraDetalle
                             SET cantidad_recibida = cantidad_recibida + ?
                             WHERE id_detalle = ?"
                        )->execute([$insertadas, $id_detalle]);
                    }
                }
            }

            // Determinar si todos los ítems quedaron completos
            $stmtPend = $this->conn->prepare(
                "SELECT COUNT(*) FROM electronicas.SolicitudesCompraDetalle
                 WHERE id_solicitud_compra = ?
                   AND cantidad_recibida < cantidad_solicitada"
            );
            $stmtPend->execute([$id]);
            $pendientes  = (int)$stmtPend->fetchColumn();
            $nuevoEstado = ($pendientes === 0) ? 'Recibida' : 'Recibida parcial';

            $this->conn->prepare(
                "UPDATE electronicas.SolicitudesCompra
                 SET estado           = ?,
                     id_receptor      = ?,
                     fecha_recepcion  = GETDATE()
                 WHERE id_solicitud_compra = ?"
            )->execute([$nuevoEstado, $id_receptor, $id]);

            $this->conn->commit();
            return ['ok' => true, 'estado' => $nuevoEstado, 'warnings' => $warnings];

        } catch (Throwable $e) {
            $this->conn->rollBack();
            return ['error' => true, 'mensaje' => $e->getMessage()];
        }
    }

    // ══════════════════════════════════════════════════════
    // CERRAR RECEPCIÓN PARCIAL (Recibida parcial → Recibida)
    // Fuerza el cierre aunque queden ítems pendientes.
    // No deshace el inventario ya actualizado.
    // ══════════════════════════════════════════════════════

    public function cerrarRecepcion(int $id, int $id_receptor): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesCompra
             SET estado          = 'Recibida',
                 id_receptor     = ?,
                 fecha_recepcion = GETDATE()
             WHERE id_solicitud_compra = ? AND estado = 'Recibida parcial'"
        );
        $stmt->execute([$id_receptor, $id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('La solicitud no está en estado Recibida parcial.');
        }
    }

    // ══════════════════════════════════════════════════════
    // CANCELAR
    // ══════════════════════════════════════════════════════

    public function cancelarSolicitud(int $id, int $id_usuario, bool $esAdmin = false): void
    {
        // Admin puede cancelar hasta Recibida parcial; técnico solo sus Borrador/Pendiente
        $estadosPermitidos = $esAdmin
            ? "('Borrador','Pendiente','Aprobada','Recibida parcial')"
            : "('Borrador','Pendiente')";

        $condUsuario = $esAdmin ? '' : "AND id_usuario = $id_usuario";

        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesCompra
             SET estado = 'Cancelada'
             WHERE id_solicitud_compra = ?
               AND estado IN $estadosPermitidos
               $condUsuario"
        );
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('No se puede cancelar esta solicitud en su estado actual.');
        }
    }

    public function obtenerEmailSolicitante(int $id): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.nombre, u.email
             FROM electronicas.SolicitudesCompra s
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = s.id_usuario
             WHERE s.id_solicitud_compra = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConn(): \PDO { return $this->conn; }
}
