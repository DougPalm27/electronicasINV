<?php

class mdlSolicitudes
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

    public function listarMaquinas(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_maquina, nombre,
                    ISNULL(serie,    '') AS serie,
                    ISNULL(ubicacion,'') AS ubicacion
             FROM electronicas.Maquinas
             WHERE id_estado = 1 ORDER BY nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTipos(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_tipo, nombre FROM electronicas.TipoMantenimiento ORDER BY nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarTecnicos(): array
    {
        // Devuelve usuarios que tengan el rol "Técnico" activo
        $stmt = $this->conn->query(
            "SELECT u.id_usuario AS id_tecnico, u.nombre
             FROM electronicas.Usuarios u
             INNER JOIN electronicas.Roles r ON r.id_rol = u.id_rol
             WHERE r.nombre = 'Técnico' AND u.activo = 1
             ORDER BY u.nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarRepuestos(): array
    {
        $sql = "SELECT
                    r.id_repuesto,
                    r.nombre,
                    r.maneja_serie,
                    CASE
                        WHEN r.maneja_serie = 1 THEN
                            (SELECT COUNT(*) FROM electronicas.RepuestosDetalle d
                             WHERE d.id_repuesto = r.id_repuesto AND d.id_estado_repuesto = 1)
                        ELSE r.stock
                    END AS stock,
                    r.costo_promedio,
                    ma.nombre  AS marca,
                    mo.nombre  AS modelo,
                    ISNULL(dv.simbolo, dpred.simbolo) AS divisa_simbolo,
                    ISNULL(dv.tipo_cambio, dpred.tipo_cambio) AS tipo_cambio,
                    (SELECT ISNULL(SUM(sd.cantidad), 0)
                     FROM electronicas.SolicitudesDetalle sd
                     INNER JOIN electronicas.SolicitudesMaquinas sm
                         ON sm.id_solicitud_maquina = sd.id_solicitud_maquina
                     INNER JOIN electronicas.SolicitudesRepuestos sr
                         ON sr.id_solicitud = sm.id_solicitud
                     WHERE sd.id_repuesto = r.id_repuesto
                       AND (sr.estado = 'Pendiente'
                            OR (sr.estado = 'Aprobado'
                                AND sm.id_mantenimiento_generado IS NULL))) AS comprometido
                FROM electronicas.Repuestos r
                LEFT JOIN electronicas.Marcas   ma ON ma.id_marca  = r.id_marca
                LEFT JOIN electronicas.Modelos  mo ON mo.id_modelo = r.id_modelo
                LEFT JOIN electronicas.Divisas  dv ON dv.id_divisa = r.id_divisa
                CROSS APPLY (
                    SELECT TOP 1 simbolo, tipo_cambio FROM electronicas.Divisas
                    WHERE predeterminada = 1 AND activo = 1
                ) dpred
                WHERE (r.stock > 0 OR r.maneja_serie = 1)
                  AND r.id_estado != 5
                ORDER BY ma.nombre, mo.nombre, r.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════
    // LISTAR SOLICITUDES
    // ══════════════════════════════════════════════════════

    public function listarSolicitudes(?int $id_usuario = null): array
    {
        $where  = $id_usuario ? "WHERE sr.id_usuario = ?" : '';
        $params = $id_usuario ? [$id_usuario] : [];

        $sql = "SELECT
                    sr.id_solicitud,
                    sr.codigo,
                    sr.descripcion,
                    sr.estado,
                    FORMAT(sr.fecha_programada, 'dd/MM/yyyy')  AS fecha_programada,
                    FORMAT(sr.fecha_solicitud,  'dd/MM/yyyy')  AS fecha_solicitud,
                    u.nombre                                   AS solicitante,
                    tm.nombre                                  AS tipo,
                    ISNULL(t.nombre, '—')                      AS tecnico,
                    COUNT(sm.id_solicitud_maquina)             AS total_maquinas,
                    SUM(CASE WHEN sm.id_mantenimiento_generado IS NOT NULL
                             THEN 1 ELSE 0 END)                AS maquinas_usadas,
                    sr.motivo_rechazo
                FROM electronicas.SolicitudesRepuestos sr
                INNER JOIN electronicas.Usuarios            u  ON u.id_usuario  = sr.id_usuario
                INNER JOIN electronicas.TipoMantenimiento   tm ON tm.id_tipo    = sr.id_tipo
                LEFT  JOIN electronicas.Usuarios            t  ON t.id_usuario  = sr.id_tecnico
                LEFT  JOIN electronicas.SolicitudesMaquinas sm ON sm.id_solicitud = sr.id_solicitud
                $where
                GROUP BY sr.id_solicitud, sr.codigo, sr.descripcion, sr.estado,
                         sr.fecha_programada, sr.fecha_solicitud,
                         u.nombre, tm.nombre, t.nombre, sr.motivo_rechazo
                ORDER BY sr.codigo DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════
    // DETALLE COMPLETO (para el modal de revisión)
    // ══════════════════════════════════════════════════════

    public function obtenerDetalle(int $id_solicitud): array
    {
        // Cabecera
        $stmtH = $this->conn->prepare(
            "SELECT sr.*, u.nombre AS solicitante, tm.nombre AS tipo_nombre,
                    ISNULL(t.nombre,'—') AS tecnico_nombre,
                    ru.nombre AS revisor_nombre,
                    FORMAT(sr.fecha_programada,'dd/MM/yyyy') AS fecha_prog_fmt,
                    FORMAT(sr.fecha_solicitud, 'dd/MM/yyyy HH:mm') AS fecha_sol_fmt,
                    FORMAT(sr.fecha_revision,  'dd/MM/yyyy HH:mm') AS fecha_rev_fmt
             FROM electronicas.SolicitudesRepuestos sr
             INNER JOIN electronicas.Usuarios          u  ON u.id_usuario  = sr.id_usuario
             INNER JOIN electronicas.TipoMantenimiento tm ON tm.id_tipo    = sr.id_tipo
             LEFT  JOIN electronicas.Usuarios          t  ON t.id_usuario  = sr.id_tecnico
             LEFT  JOIN electronicas.Usuarios          ru ON ru.id_usuario = sr.id_revisor
             WHERE sr.id_solicitud = ?"
        );
        $stmtH->execute([$id_solicitud]);
        $cabecera = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$cabecera) return [];

        // Máquinas + sus repuestos
        $stmtM = $this->conn->prepare(
            "SELECT sm.id_solicitud_maquina, sm.id_maquina, mq.nombre AS maquina,
                    sm.descripcion, sm.id_mantenimiento_generado
             FROM electronicas.SolicitudesMaquinas sm
             INNER JOIN electronicas.Maquinas mq ON mq.id_maquina = sm.id_maquina
             WHERE sm.id_solicitud = ?
             ORDER BY sm.id_solicitud_maquina"
        );
        $stmtM->execute([$id_solicitud]);
        $maquinas = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        foreach ($maquinas as &$maq) {
            $stmtD = $this->conn->prepare(
                "SELECT sd.id_detalle, sd.id_repuesto, r.nombre AS repuesto,
                        sd.cantidad, r.maneja_serie,
                        CASE WHEN r.maneja_serie = 1
                            THEN (SELECT COUNT(*) FROM electronicas.RepuestosDetalle d
                                  WHERE d.id_repuesto = r.id_repuesto AND d.id_estado_repuesto = 1)
                            ELSE r.stock END AS stock_actual,
                        (SELECT ISNULL(SUM(sd2.cantidad), 0)
                         FROM electronicas.SolicitudesDetalle sd2
                         INNER JOIN electronicas.SolicitudesMaquinas sm2
                             ON sm2.id_solicitud_maquina = sd2.id_solicitud_maquina
                         INNER JOIN electronicas.SolicitudesRepuestos sr2
                             ON sr2.id_solicitud = sm2.id_solicitud
                         WHERE sd2.id_repuesto = sd.id_repuesto
                           AND (sr2.estado = 'Pendiente'
                                OR (sr2.estado = 'Aprobado'
                                    AND sm2.id_mantenimiento_generado IS NULL))
                           AND sr2.id_solicitud != ?) AS comprometido_otras
                 FROM electronicas.SolicitudesDetalle sd
                 INNER JOIN electronicas.Repuestos r ON r.id_repuesto = sd.id_repuesto
                 WHERE sd.id_solicitud_maquina = ?
                 ORDER BY r.nombre"
            );
            $stmtD->execute([$id_solicitud, $maq['id_solicitud_maquina']]);
            $maq['repuestos'] = $stmtD->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($maq);

        $cabecera['maquinas'] = $maquinas;
        return $cabecera;
    }

    // ══════════════════════════════════════════════════════
    // CREAR SOLICITUD
    // ══════════════════════════════════════════════════════

    public function crearSolicitud(array $d): int
    {
        // Validar stock antes de abrir la transacción
        foreach ($d['maquinas'] as $maq) {
            foreach ($maq['repuestos'] ?? [] as $rep) {
                $stmtV = $this->conn->prepare(
                    "SELECT nombre, maneja_serie,
                            CASE WHEN maneja_serie = 1
                                THEN (SELECT COUNT(*) FROM electronicas.RepuestosDetalle rd
                                      WHERE rd.id_repuesto = r.id_repuesto
                                        AND rd.id_estado_repuesto = 1)
                                ELSE stock
                            END AS stock_disponible
                     FROM electronicas.Repuestos r
                     WHERE id_repuesto = ? AND id_estado != 5"
                );
                $stmtV->execute([$rep['id_repuesto']]);
                $r = $stmtV->fetch(PDO::FETCH_ASSOC);
                if (!$r) throw new RuntimeException("Repuesto no encontrado (id: {$rep['id_repuesto']}).");
                if ((int)$r['maneja_serie'] === 0 && (int)$r['stock_disponible'] < (int)$rep['cantidad']) {
                    throw new RuntimeException(
                        "Stock insuficiente para \"{$r['nombre']}\". " .
                        "Disponible: {$r['stock_disponible']}, solicitado: {$rep['cantidad']}."
                    );
                }
            }
        }

        $this->conn->beginTransaction();
        try {
            // Cabecera
            $stmt = $this->conn->prepare(
                "INSERT INTO electronicas.SolicitudesRepuestos
                    (id_usuario, id_tecnico, id_tipo, descripcion, fecha_programada)
                 OUTPUT INSERTED.id_solicitud
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $d['id_usuario'],
                $d['id_tecnico'] ?: null,
                $d['id_tipo'],
                $d['descripcion'],
                $d['fecha_programada']
            ]);
            $id_sol = (int)$stmt->fetchColumn();

            // Máquinas
            foreach ($d['maquinas'] as $maq) {
                $stmtM = $this->conn->prepare(
                    "INSERT INTO electronicas.SolicitudesMaquinas
                        (id_solicitud, id_maquina, descripcion)
                     OUTPUT INSERTED.id_solicitud_maquina
                     VALUES (?, ?, ?)"
                );
                $stmtM->execute([$id_sol, $maq['id_maquina'], $maq['descripcion'] ?? null]);
                $id_maq = (int)$stmtM->fetchColumn();

                // Repuestos de esta máquina
                foreach (($maq['repuestos'] ?? []) as $rep) {
                    $this->conn->prepare(
                        "INSERT INTO electronicas.SolicitudesDetalle
                            (id_solicitud_maquina, id_repuesto, cantidad)
                         VALUES (?, ?, ?)"
                    )->execute([$id_maq, $rep['id_repuesto'], max(1, (int)$rep['cantidad'])]);
                }
            }

            $this->conn->commit();
            return $id_sol;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    // ══════════════════════════════════════════════════════
    // APROBAR  →  solo cambia el estado. Los repuestos se
    // consumen cuando la solicitud se usa en un mantenimiento.
    // ══════════════════════════════════════════════════════

    public function aprobarSolicitud(int $id_solicitud, int $id_revisor): array
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesRepuestos
             SET estado = 'Aprobado', id_revisor = ?, fecha_revision = GETDATE()
             WHERE id_solicitud = ? AND estado = 'Pendiente'"
        );
        $stmt->execute([$id_revisor, $id_solicitud]);

        if ($stmt->rowCount() === 0) {
            return ['error' => true, 'mensaje' => 'La solicitud no existe o ya fue procesada.'];
        }
        return ['ok' => true];
    }

    // ══════════════════════════════════════════════════════
    // RECHAZAR
    // ══════════════════════════════════════════════════════

    public function rechazarSolicitud(int $id_solicitud, int $id_revisor, string $motivo): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesRepuestos
             SET estado = 'Rechazado', id_revisor = ?, fecha_revision = GETDATE(),
                 motivo_rechazo = ?
             WHERE id_solicitud = ? AND estado = 'Pendiente'"
        );
        $stmt->execute([$id_revisor, $motivo, $id_solicitud]);
        if ($stmt->rowCount() === 0)
            throw new RuntimeException('La solicitud no existe o ya fue procesada.');
    }

    // ══════════════════════════════════════════════════════
    // CANCELAR
    // - Técnico: solo sus propias solicitudes en Pendiente
    // - Admin: Pendiente o Aprobado sin máquinas usadas en
    //   mantenimiento (libera el stock comprometido)
    // ══════════════════════════════════════════════════════

    public function cancelarSolicitud(int $id_solicitud, int $id_usuario, bool $esAdmin = false): void
    {
        if ($esAdmin) {
            $stmt = $this->conn->prepare(
                "UPDATE sr
                 SET sr.estado = 'Cancelado'
                 FROM electronicas.SolicitudesRepuestos sr
                 WHERE sr.id_solicitud = ?
                   AND sr.estado IN ('Pendiente', 'Aprobado')
                   AND NOT EXISTS (
                       SELECT 1 FROM electronicas.SolicitudesMaquinas sm
                       WHERE sm.id_solicitud = sr.id_solicitud
                         AND sm.id_mantenimiento_generado IS NOT NULL
                   )"
            );
            $stmt->execute([$id_solicitud]);
            if ($stmt->rowCount() === 0)
                throw new RuntimeException(
                    'No se puede cancelar: la solicitud ya fue usada en un mantenimiento o su estado no lo permite.'
                );
            return;
        }

        $stmt = $this->conn->prepare(
            "UPDATE electronicas.SolicitudesRepuestos
             SET estado = 'Cancelado'
             WHERE id_solicitud = ? AND id_usuario = ? AND estado = 'Pendiente'"
        );
        $stmt->execute([$id_solicitud, $id_usuario]);
        if ($stmt->rowCount() === 0)
            throw new RuntimeException('No puedes cancelar esta solicitud.');
    }

    public function obtenerEmailSolicitante(int $id_solicitud): array
    {
        $stmt = $this->conn->prepare(
            "SELECT u.nombre, u.email
             FROM electronicas.SolicitudesRepuestos s
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = s.id_usuario
             WHERE s.id_solicitud = ?"
        );
        $stmt->execute([$id_solicitud]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConn(): \PDO { return $this->conn; }
}
