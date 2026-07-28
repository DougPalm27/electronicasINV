<?php
/**
 * Modelo de Despachos — listas de seguimiento temporales.
 *
 * Un despacho agrupa los carros que salen en una operación. Se arma cuando se
 * necesita, se le suman/quitan carros, y se cierra (queda como historial).
 * El mapa gira en torno a los despachos ACTIVOS.
 *
 * Las posiciones viven en gps.Posiciones (clave id_cuenta + imei) y se cruzan
 * con los vehículos del despacho por esa misma clave.
 */
class mdlDespachos
{
    private $conn;
    private $tablaTramosExiste = null;

    public function __construct()
    {
        require_once '../../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ── CRUD de despachos ───────────────────────────────────────
    public function crear(string $nombre, int $uid): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO gps.Despachos (nombre, creado_por)
             OUTPUT INSERTED.id_despacho VALUES (?, ?)"
        );
        $stmt->execute([$nombre, $uid ?: null]);
        return (int)$stmt->fetchColumn();
    }

    public function cerrar(int $id_despacho): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Despachos SET estado = 'cerrado', fecha_cierre = GETDATE()
             WHERE id_despacho = ? AND estado = 'activo'"
        );
        $stmt->execute([$id_despacho]);
    }

    public function reabrir(int $id_despacho): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE gps.Despachos SET estado = 'activo', fecha_cierre = NULL WHERE id_despacho = ?"
        );
        $stmt->execute([$id_despacho]);
    }

    /** Despachos por estado ('activo' | 'cerrado'), con conteo de carros. */
    public function listar(string $estado = 'activo'): array
    {
        $soloActivos = $estado === 'activo' ? 'AND dv.activo = 1' : '';
        $stmt = $this->conn->prepare(
            "SELECT d.id_despacho, d.nombre, d.estado,
                    CONVERT(varchar(19), d.fecha_apertura, 120) AS fecha_apertura,
                    CONVERT(varchar(19), d.fecha_cierre,   120) AS fecha_cierre,
                    (SELECT COUNT(*) FROM gps.DespachoVehiculos dv
                     WHERE dv.id_despacho = d.id_despacho $soloActivos) AS carros
             FROM gps.Despachos d
             WHERE d.estado = ?
             ORDER BY d.fecha_apertura DESC"
        );
        $stmt->execute([$estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function registrarHeartbeat(string $worker, string $estado = 'ok', ?string $detalle = null): void
    {
        if (!$this->tablaExiste('gps.WorkerHeartbeats')) return;
        $stmt = $this->conn->prepare(
            "MERGE gps.WorkerHeartbeats AS t
             USING (SELECT ? AS worker) AS s ON t.worker = s.worker
             WHEN MATCHED THEN UPDATE SET
                estado = ?, detalle = ?, pid = ?, host = ?, fecha_latido = GETDATE()
             WHEN NOT MATCHED THEN INSERT
                (worker, estado, detalle, pid, host, fecha_latido, fecha_inicio)
                VALUES (?, ?, ?, ?, ?, GETDATE(), GETDATE());"
        );
        $pid = function_exists('getmypid') ? getmypid() : null;
        $host = gethostname() ?: null;
        $stmt->execute([$worker, $estado, $detalle, $pid, $host, $worker, $estado, $detalle, $pid, $host]);
    }

    public function estadoHeartbeat(string $worker, int $maxEdadSeg = 90): array
    {
        if (!$this->tablaExiste('gps.WorkerHeartbeats')) {
            return ['worker' => $worker, 'ok' => false, 'estado' => 'sin_tabla', 'detalle' => 'Falta aplicar la migracion de heartbeats.'];
        }
        $stmt = $this->conn->prepare(
            "SELECT worker, estado, detalle, pid, host,
                    CONVERT(varchar(19), fecha_latido, 120) AS fecha_latido,
                    CONVERT(varchar(19), fecha_inicio, 120) AS fecha_inicio,
                    DATEDIFF(second, fecha_latido, GETDATE()) AS edad_seg
             FROM gps.WorkerHeartbeats
             WHERE worker = ?"
        );
        $stmt->execute([$worker]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            return ['worker' => $worker, 'ok' => false, 'estado' => 'sin_latido', 'detalle' => 'El worker aun no ha registrado latido.'];
        }
        $edad = (int)($r['edad_seg'] ?? 999999);
        $r['edad_seg'] = $edad;
        $r['ok'] = ($r['estado'] === 'ok' && $edad <= $maxEdadSeg);
        return $r;
    }

    // ── Vehículos del despacho ──────────────────────────────────
    /**
     * Agrega carros a un despacho. $items = [['id_cuenta','placa','imei','dispositivo'], ...]
     * Omite los que ya estén activos en ESE despacho (misma placa o imei en la cuenta).
     */
    public function agregarVehiculos(int $id_despacho, array $items, int $uid): array
    {
        // Duplicado: si el equipo tiene IMEI, se compara por IMEI (único).
        // Solo si NO hay IMEI se compara por placa (varios equipos pueden llamarse
        // igual, p.ej. "gps" sin configurar → placas repetidas, imeis distintos).
        $existe = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.DespachoVehiculos
             WHERE id_despacho = ? AND activo = 1 AND id_cuenta = ?
               AND ( (? <> '' AND imei = ?) OR (? = '' AND placa = ?) )"
        );
        $insert = $this->conn->prepare(
            "INSERT INTO gps.DespachoVehiculos (id_despacho, id_cuenta, placa, imei, dispositivo, agregado_por)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $agregados = 0; $omitidos = 0;
        $this->conn->beginTransaction();
        try {
            foreach ($items as $it) {
                $id_cuenta = (int)($it['id_cuenta'] ?? 0);
                $placa = strtoupper(trim($it['placa'] ?? ''));
                $imei  = trim($it['imei'] ?? '');
                if (!$id_cuenta || $placa === '') { $omitidos++; continue; }

                $existe->execute([$id_despacho, $id_cuenta, $imei, $imei, $imei, $placa]);
                if ((int)$existe->fetchColumn() > 0) { $omitidos++; continue; }

                $insert->execute([$id_despacho, $id_cuenta, $placa, $imei ?: null,
                                  trim($it['dispositivo'] ?? '') ?: null, $uid ?: null]);
                $agregados++;
            }
            $this->conn->commit();
        } catch (Throwable $e) { $this->conn->rollBack(); throw $e; }
        return ['agregados' => $agregados, 'omitidos' => $omitidos];
    }

    /**
     * Quita un carro del despacho (deja de seguirlo). No borra: soft-delete.
     * $motivo: 'removido' (salió de ruta / cumplió) | 'error' (se agregó por equivocación).
     */
    public function quitarVehiculo(int $id_dv, string $motivo = 'removido'): void
    {
        $motivo = $motivo === 'error' ? 'error' : 'removido';
        $col = $this->columnaExiste('gps.DespachoVehiculos', 'motivo_remocion');
        $sql = $col
            ? "UPDATE gps.DespachoVehiculos SET activo = 0, fecha_removido = GETDATE(), motivo_remocion = ?
               WHERE id_dv = ? AND activo = 1"
            : "UPDATE gps.DespachoVehiculos SET activo = 0, fecha_removido = GETDATE()
               WHERE id_dv = ? AND activo = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($col ? [$motivo, $id_dv] : [$id_dv]);
    }

    /** Placas/imeis ya activos en un despacho (para marcar en el selector). */
    public function vinculadosDeDespacho(int $id_despacho): array
    {
        $stmt = $this->conn->prepare(
            "SELECT placa, imei, id_cuenta FROM gps.DespachoVehiculos
             WHERE id_despacho = ? AND activo = 1"
        );
        $stmt->execute([$id_despacho]);
        $placas = []; $imeis = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $placas[$r['id_cuenta'] . '|' . strtoupper(trim($r['placa']))] = true;
            if (!empty($r['imei'])) $imeis[$r['id_cuenta'] . '|' . trim($r['imei'])] = true;
        }
        return ['placas' => $placas, 'imeis' => $imeis];
    }

    /**
     * Vehículos (activos) de uno o todos los despachos activos, con su última
     * posición en caché. Si $id_despacho es null → todos los despachos activos.
     */
    public function vehiculos(?int $id_despacho, bool $historico = false): array
    {
        $where = $historico ? "d.estado = 'cerrado'" : "d.estado = 'activo' AND dv.activo = 1";
        $params = [];
        if ($id_despacho !== null) {
            $where = $historico ? "dv.id_despacho = ?" : "dv.id_despacho = ? AND dv.activo = 1";
            $params = [$id_despacho];
        }
        // Los carros descartados por error no salen ni en históricos.
        if ($this->columnaExiste('gps.DespachoVehiculos', 'motivo_remocion')) {
            $where .= " AND ISNULL(dv.motivo_remocion, '') <> 'error'";
        }

        $posJoin = "LEFT  JOIN gps.Posiciones pos ON pos.id_cuenta = dv.id_cuenta AND pos.imei = dv.imei";
        $posPrefix = "pos";
        if ($historico) {
            $posJoin = "OUTER APPLY (
                    SELECT TOP 1 r.lat, r.lng, r.velocidad, r.rumbo, r.encendido, r.direccion, r.fecha_posicion
                    FROM gps.DespachoRecorridos r
                    WHERE r.id_dv = dv.id_dv
                    ORDER BY ISNULL(r.fecha_posicion, r.fecha_captura) DESC, r.id_recorrido DESC
                ) pos";
            $posPrefix = "pos";
        }
        $tramosOk = $this->tablaExiste('gps.DespachoVehiculoTramos');
        $tramoSelect = $tramosOk
            ? "tr.id_tramo, tr.estado AS estado_tramo,
                    CONVERT(varchar(19), tr.fecha_inicio, 120) AS fecha_inicio_tramo,
                    CONVERT(varchar(19), tr.fecha_fin, 120) AS fecha_fin_tramo,
                    tr.duracion_minutos"
            : "CAST(NULL AS INT) AS id_tramo, CAST(NULL AS NVARCHAR(12)) AS estado_tramo,
                    CAST(NULL AS varchar(19)) AS fecha_inicio_tramo,
                    CAST(NULL AS varchar(19)) AS fecha_fin_tramo,
                    CAST(NULL AS INT) AS duracion_minutos";
        $tramoJoin = $tramosOk
            ? "OUTER APPLY (
                SELECT TOP 1 t2.id_tramo, t2.estado, t2.fecha_inicio, t2.fecha_fin, t2.duracion_minutos
                FROM gps.DespachoVehiculoTramos t2
                WHERE t2.id_dv = dv.id_dv
                ORDER BY CASE WHEN t2.estado = 'en_ruta' THEN 0 ELSE 1 END,
                         ISNULL(t2.fecha_fin, t2.fecha_inicio) DESC,
                         t2.id_tramo DESC
             ) tr"
            : "";
        $incOk = $this->tablaExiste('gps.DespachoVehiculoIncidencias');
        $incSelect = $incOk
            ? "ISNULL(inc.incidencias_total, 0) AS incidencias_total,
                    ISNULL(inc.incidencias_abiertas, 0) AS incidencias_abiertas"
            : "CAST(0 AS INT) AS incidencias_total,
                    CAST(0 AS INT) AS incidencias_abiertas";
        $incJoin = $incOk
            ? "OUTER APPLY (
                SELECT COUNT(*) AS incidencias_total,
                       SUM(CASE WHEN i.estado <> 'cerrada' THEN 1 ELSE 0 END) AS incidencias_abiertas
                FROM gps.DespachoVehiculoIncidencias i
                WHERE i.id_dv = dv.id_dv
             ) inc"
            : "";

        $stmt = $this->conn->prepare(
            "SELECT dv.id_dv, dv.id_despacho, d.nombre AS despacho,
                    dv.placa, dv.imei, dv.id_cuenta, dv.dispositivo,
                    d.estado AS estado_despacho,
                    CONVERT(varchar(19), d.fecha_apertura, 120) AS fecha_apertura,
                    CONVERT(varchar(19), d.fecha_cierre, 120) AS fecha_cierre,
                    p.nombre AS plataforma, p.tipo_integracion, t.nombre AS transporte, c.usuario,
                    CAST($posPrefix.lat AS FLOAT) AS lat, CAST($posPrefix.lng AS FLOAT) AS lng,
                    $posPrefix.velocidad, $posPrefix.rumbo, $posPrefix.encendido, $posPrefix.direccion,
                    CONVERT(varchar(19), $posPrefix.fecha_posicion, 120) AS fecha,
                    $tramoSelect,
                    $incSelect
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.Despachos    d ON d.id_despacho  = dv.id_despacho
             INNER JOIN gps.CuentasGPS   c ON c.id_cuenta    = dv.id_cuenta
             INNER JOIN gps.Plataformas  p ON p.id_plataforma = c.id_plataforma
             INNER JOIN gps.Transportes  t ON t.id_transporte = c.id_transporte
             $posJoin
             $tramoJoin
             $incJoin
             WHERE $where
             ORDER BY dv.placa"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function vehiculoConPosicion(int $id_dv): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT TOP 1 dv.id_dv, dv.id_despacho, dv.id_cuenta, dv.placa, dv.imei,
                    d.estado AS estado_despacho,
                    CAST(pos.lat AS FLOAT) AS lat, CAST(pos.lng AS FLOAT) AS lng,
                    pos.velocidad, pos.rumbo, pos.encendido, pos.direccion,
                    CONVERT(varchar(19), pos.fecha_posicion, 120) AS fecha
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.Despachos d ON d.id_despacho = dv.id_despacho
             LEFT JOIN gps.Posiciones pos ON pos.id_cuenta = dv.id_cuenta AND pos.imei = dv.imei
             WHERE dv.id_dv = ?"
        );
        $stmt->execute([$id_dv]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function iniciarTramo(int $id_dv, int $uid): array
    {
        if (!$this->tablaExiste('gps.DespachoVehiculoTramos')) {
            throw new RuntimeException('Falta aplicar la migracion de tramos de despacho.');
        }
        $v = $this->vehiculoConPosicion($id_dv);
        if (!$v) throw new RuntimeException('Vehiculo no encontrado.');
        if (($v['estado_despacho'] ?? '') !== 'activo') throw new RuntimeException('El despacho no esta activo.');

        $abierto = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.DespachoVehiculoTramos WHERE id_dv = ? AND estado = 'en_ruta'"
        );
        $abierto->execute([$id_dv]);
        if ((int)$abierto->fetchColumn() > 0) throw new RuntimeException('Este vehiculo ya tiene una ruta iniciada.');

        $stmt = $this->conn->prepare(
            "INSERT INTO gps.DespachoVehiculoTramos
                (id_despacho, id_dv, placa, lat_inicio, lng_inicio, direccion_inicio, iniciado_por)
             OUTPUT INSERTED.id_tramo,
                    CONVERT(varchar(19), INSERTED.fecha_inicio, 120) AS fecha_inicio
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$v['id_despacho'],
            (int)$v['id_dv'],
            $v['placa'],
            $v['lat'] === null ? null : (float)$v['lat'],
            $v['lng'] === null ? null : (float)$v['lng'],
            $v['direccion'] ?? null,
            $uid ?: null,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function finalizarTramo(int $id_dv, int $uid): array
    {
        if (!$this->tablaExiste('gps.DespachoVehiculoTramos')) {
            throw new RuntimeException('Falta aplicar la migracion de tramos de despacho.');
        }
        $v = $this->vehiculoConPosicion($id_dv);
        if (!$v) throw new RuntimeException('Vehiculo no encontrado.');

        $stmt = $this->conn->prepare(
            "UPDATE gps.DespachoVehiculoTramos
             SET estado = 'finalizado',
                 fecha_fin = GETDATE(),
                 lat_fin = ?,
                 lng_fin = ?,
                 direccion_fin = ?,
                 duracion_minutos = DATEDIFF(minute, fecha_inicio, GETDATE()),
                 finalizado_por = ?
             OUTPUT INSERTED.id_tramo,
                    CONVERT(varchar(19), INSERTED.fecha_inicio, 120) AS fecha_inicio,
                    CONVERT(varchar(19), INSERTED.fecha_fin, 120) AS fecha_fin,
                    INSERTED.duracion_minutos
             WHERE id_dv = ? AND estado = 'en_ruta'"
        );
        $stmt->execute([
            $v['lat'] === null ? null : (float)$v['lat'],
            $v['lng'] === null ? null : (float)$v['lng'],
            $v['direccion'] ?? null,
            $uid ?: null,
            $id_dv,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Este vehiculo no tiene una ruta iniciada.');
        return $row;
    }

    public function tramosAbiertosDespacho(int $id_despacho): array
    {
        if (!$this->tablaExiste('gps.DespachoVehiculoTramos')) return [];
        $stmt = $this->conn->prepare(
            "SELECT tr.id_tramo, tr.id_dv, tr.placa,
                    CONVERT(varchar(19), tr.fecha_inicio, 120) AS fecha_inicio
             FROM gps.DespachoVehiculoTramos tr
             WHERE tr.id_despacho = ? AND tr.estado = 'en_ruta'
             ORDER BY tr.fecha_inicio"
        );
        $stmt->execute([$id_despacho]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function finalizarTramosAbiertosDespacho(int $id_despacho, int $uid): int
    {
        $abiertos = $this->tramosAbiertosDespacho($id_despacho);
        $n = 0;
        foreach ($abiertos as $t) {
            $this->finalizarTramo((int)$t['id_dv'], $uid);
            $n++;
        }
        return $n;
    }

    public function crearIncidencia(int $id_dv, string $tipo, string $severidad, ?string $descripcion, int $uid): array
    {
        if (!$this->tablaExiste('gps.DespachoVehiculoIncidencias')) {
            throw new RuntimeException('Falta aplicar la migracion de incidencias de despacho.');
        }
        $tipo = trim($tipo);
        $severidad = strtolower(trim($severidad));
        if ($tipo === '') throw new RuntimeException('Selecciona el tipo de incidencia.');
        if (!in_array($severidad, ['baja', 'media', 'alta'], true)) $severidad = 'media';

        $tramoSelect = $this->tablaExiste('gps.DespachoVehiculoTramos')
            ? "tr.id_tramo"
            : "CAST(NULL AS INT) AS id_tramo";
        $tramoJoin = $this->tablaExiste('gps.DespachoVehiculoTramos')
            ? "OUTER APPLY (
                SELECT TOP 1 id_tramo
                FROM gps.DespachoVehiculoTramos
                WHERE id_dv = dv.id_dv AND estado = 'en_ruta'
                ORDER BY fecha_inicio DESC, id_tramo DESC
             ) tr"
            : "";
        $stmt = $this->conn->prepare(
            "SELECT TOP 1 dv.id_dv, dv.id_despacho, dv.id_cuenta, dv.placa, dv.imei,
                    d.estado AS estado_despacho,
                    CAST(pos.lat AS FLOAT) AS lat, CAST(pos.lng AS FLOAT) AS lng,
                    pos.direccion,
                    $tramoSelect
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.Despachos d ON d.id_despacho = dv.id_despacho
             LEFT JOIN gps.Posiciones pos ON pos.id_cuenta = dv.id_cuenta AND pos.imei = dv.imei
             $tramoJoin
             WHERE dv.id_dv = ?"
        );
        $stmt->execute([$id_dv]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new RuntimeException('Vehiculo no encontrado.');
        if (($v['estado_despacho'] ?? '') !== 'activo') throw new RuntimeException('El despacho ya esta cerrado.');

        $ins = $this->conn->prepare(
            "INSERT INTO gps.DespachoVehiculoIncidencias
                (id_despacho, id_dv, id_tramo, id_cuenta, placa, imei, tipo, severidad,
                 descripcion, lat, lng, direccion, creado_por)
             OUTPUT INSERTED.id_incidencia,
                    CONVERT(varchar(19), INSERTED.fecha_incidencia, 120) AS fecha_incidencia
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([
            (int)$v['id_despacho'],
            (int)$v['id_dv'],
            $v['id_tramo'] === null ? null : (int)$v['id_tramo'],
            (int)$v['id_cuenta'],
            $v['placa'],
            $v['imei'] ?? null,
            $tipo,
            $severidad,
            trim((string)$descripcion) ?: null,
            $v['lat'] === null ? null : (float)$v['lat'],
            $v['lng'] === null ? null : (float)$v['lng'],
            $v['direccion'] ?? null,
            $uid ?: null,
        ]);
        return $ins->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function cerrarIncidencia(int $id_incidencia, int $uid): void
    {
        if (!$this->tablaExiste('gps.DespachoVehiculoIncidencias')) {
            throw new RuntimeException('Falta aplicar la migracion de incidencias de despacho.');
        }
        $stmt = $this->conn->prepare(
            "UPDATE gps.DespachoVehiculoIncidencias
             SET estado = 'cerrada', fecha_cierre = GETDATE(), cerrado_por = ?
             WHERE id_incidencia = ? AND estado <> 'cerrada'"
        );
        $stmt->execute([$uid ?: null, $id_incidencia]);
    }

    public function incidenciasVehiculo(int $id_dv): array
    {
        if (!$this->tablaExiste('gps.DespachoVehiculoIncidencias')) {
            return ['actuales' => [], 'historial' => []];
        }
        $actuales = $this->conn->prepare(
            "SELECT i.id_incidencia, i.id_despacho, i.id_dv, i.id_tramo, i.tipo, i.severidad,
                    i.descripcion, i.estado, CAST(i.lat AS FLOAT) AS lat, CAST(i.lng AS FLOAT) AS lng,
                    i.direccion,
                    CONVERT(varchar(19), i.fecha_incidencia, 120) AS fecha_incidencia,
                    CONVERT(varchar(19), i.fecha_cierre, 120) AS fecha_cierre
             FROM gps.DespachoVehiculoIncidencias i
             WHERE i.id_dv = ?
             ORDER BY CASE WHEN i.estado = 'cerrada' THEN 1 ELSE 0 END,
                      i.fecha_incidencia DESC"
        );
        $actuales->execute([$id_dv]);

        $base = $this->conn->prepare(
            "SELECT id_cuenta, placa, imei FROM gps.DespachoVehiculos WHERE id_dv = ?"
        );
        $base->execute([$id_dv]);
        $v = $base->fetch(PDO::FETCH_ASSOC);
        if (!$v) throw new RuntimeException('Vehiculo no encontrado.');

        $porImei = trim((string)($v['imei'] ?? '')) !== '';
        $where = $porImei ? "i.id_cuenta = ? AND i.imei = ?" : "i.id_cuenta = ? AND i.placa = ?";
        $params = $porImei ? [(int)$v['id_cuenta'], $v['imei']] : [(int)$v['id_cuenta'], $v['placa']];
        $hist = $this->conn->prepare(
            "SELECT TOP 50 i.id_incidencia, i.id_despacho, d.nombre AS despacho, i.placa, i.imei,
                    i.tipo, i.severidad, i.descripcion, i.estado,
                    CONVERT(varchar(19), i.fecha_incidencia, 120) AS fecha_incidencia,
                    CONVERT(varchar(19), i.fecha_cierre, 120) AS fecha_cierre
             FROM gps.DespachoVehiculoIncidencias i
             INNER JOIN gps.Despachos d ON d.id_despacho = i.id_despacho
             WHERE $where
             ORDER BY i.fecha_incidencia DESC, i.id_incidencia DESC"
        );
        $hist->execute($params);
        return [
            'actuales' => $actuales->fetchAll(PDO::FETCH_ASSOC),
            'historial' => $hist->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function reporteDespacho(int $id_despacho): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_despacho, nombre, estado,
                    CONVERT(varchar(19), fecha_apertura, 120) AS fecha_apertura,
                    CONVERT(varchar(19), fecha_cierre, 120) AS fecha_cierre
             FROM gps.Despachos
             WHERE id_despacho = ?"
        );
        $stmt->execute([$id_despacho]);
        $despacho = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$despacho) throw new RuntimeException('Despacho no encontrado.');

        $tramosOk = $this->tablaExiste('gps.DespachoVehiculoTramos');
        $tramoSelect = $tramosOk
            ? "tr.id_tramo, tr.estado AS estado_tramo,
               CONVERT(varchar(19), tr.fecha_inicio, 120) AS fecha_inicio_tramo,
               CONVERT(varchar(19), tr.fecha_fin, 120) AS fecha_fin_tramo,
               tr.duracion_minutos,
               CAST(tr.lat_inicio AS FLOAT) AS lat_inicio, CAST(tr.lng_inicio AS FLOAT) AS lng_inicio,
               CAST(tr.lat_fin AS FLOAT) AS lat_fin, CAST(tr.lng_fin AS FLOAT) AS lng_fin,
               tr.direccion_inicio, tr.direccion_fin"
            : "CAST(NULL AS INT) AS id_tramo, CAST(NULL AS NVARCHAR(12)) AS estado_tramo,
               CAST(NULL AS varchar(19)) AS fecha_inicio_tramo,
               CAST(NULL AS varchar(19)) AS fecha_fin_tramo,
               CAST(NULL AS INT) AS duracion_minutos,
               CAST(NULL AS FLOAT) AS lat_inicio, CAST(NULL AS FLOAT) AS lng_inicio,
               CAST(NULL AS FLOAT) AS lat_fin, CAST(NULL AS FLOAT) AS lng_fin,
               CAST(NULL AS NVARCHAR(500)) AS direccion_inicio,
               CAST(NULL AS NVARCHAR(500)) AS direccion_fin";
        $tramoJoin = $tramosOk
            ? "OUTER APPLY (
                SELECT TOP 1 t2.id_tramo, t2.estado, t2.fecha_inicio, t2.fecha_fin,
                       t2.duracion_minutos, t2.lat_inicio, t2.lng_inicio, t2.lat_fin, t2.lng_fin,
                       t2.direccion_inicio, t2.direccion_fin
                FROM gps.DespachoVehiculoTramos t2
                WHERE t2.id_dv = dv.id_dv
                ORDER BY CASE WHEN t2.estado = 'en_ruta' THEN 0 ELSE 1 END,
                         ISNULL(t2.fecha_fin, t2.fecha_inicio) DESC,
                         t2.id_tramo DESC
             ) tr"
            : "";

        $stmt = $this->conn->prepare(
            "SELECT dv.id_dv, dv.placa, dv.imei, dv.dispositivo, dv.activo,
                    " . ($this->columnaExiste('gps.DespachoVehiculos', 'motivo_remocion')
                        ? "dv.motivo_remocion" : "CAST(NULL AS NVARCHAR(15)) AS motivo_remocion") . ",
                    CONVERT(varchar(19), dv.fecha_agregado, 120) AS fecha_agregado,
                    CONVERT(varchar(19), dv.fecha_removido, 120) AS fecha_removido,
                    p.nombre AS plataforma, p.tipo_integracion, t.nombre AS transporte, c.usuario,
                    CAST(pos.lat AS FLOAT) AS lat_actual, CAST(pos.lng AS FLOAT) AS lng_actual,
                    pos.velocidad AS velocidad_actual, pos.direccion AS direccion_actual,
                    CONVERT(varchar(19), pos.fecha_posicion, 120) AS fecha_posicion_actual,
                    $tramoSelect
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.CuentasGPS   c ON c.id_cuenta = dv.id_cuenta
             INNER JOIN gps.Plataformas  p ON p.id_plataforma = c.id_plataforma
             INNER JOIN gps.Transportes  t ON t.id_transporte = c.id_transporte
             LEFT  JOIN gps.Posiciones pos ON pos.id_cuenta = dv.id_cuenta AND pos.imei = dv.imei
             $tramoJoin
             WHERE dv.id_despacho = ?" .
             ($this->columnaExiste('gps.DespachoVehiculos', 'motivo_remocion')
                ? " AND ISNULL(dv.motivo_remocion, '') <> 'error'" : "") . "
             ORDER BY dv.placa"
        );
        $stmt->execute([$id_despacho]);
        $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($this->tablaExiste('gps.DespachoRecorridos')) {
            $stats = $this->conn->prepare(
                "SELECT COUNT(*) AS checkpoints,
                        CONVERT(varchar(19), MIN(ISNULL(fecha_posicion, fecha_captura)), 120) AS primer_reporte,
                        CONVERT(varchar(19), MAX(ISNULL(fecha_posicion, fecha_captura)), 120) AS ultimo_reporte
                 FROM gps.DespachoRecorridos
                 WHERE id_dv = ?"
            );
            foreach ($equipos as &$e) {
                $stats->execute([(int)$e['id_dv']]);
                $s = $stats->fetch(PDO::FETCH_ASSOC) ?: [];
                $e['checkpoints'] = (int)($s['checkpoints'] ?? 0);
                $e['primer_reporte'] = $s['primer_reporte'] ?? null;
                $e['ultimo_reporte'] = $s['ultimo_reporte'] ?? null;
            }
            unset($e);
        } else {
            foreach ($equipos as &$e) {
                $e['checkpoints'] = 0;
                $e['primer_reporte'] = null;
                $e['ultimo_reporte'] = null;
            }
            unset($e);
        }

        if ($this->tablaExiste('gps.DespachoVehiculoIncidencias')) {
            $inc = $this->conn->prepare(
                "SELECT COUNT(*) AS incidencias_total,
                        SUM(CASE WHEN estado <> 'cerrada' THEN 1 ELSE 0 END) AS incidencias_abiertas,
                        MAX(CASE severidad WHEN 'alta' THEN 3 WHEN 'media' THEN 2 WHEN 'baja' THEN 1 ELSE 0 END) AS severidad_nivel,
                        CONVERT(varchar(19), MAX(fecha_incidencia), 120) AS ultima_incidencia
                 FROM gps.DespachoVehiculoIncidencias
                 WHERE id_dv = ?"
            );
            $det = $this->conn->prepare(
                "SELECT id_incidencia, tipo, severidad, descripcion, estado,
                        CONVERT(varchar(19), fecha_incidencia, 120) AS fecha_incidencia,
                        CONVERT(varchar(19), fecha_cierre, 120) AS fecha_cierre
                 FROM gps.DespachoVehiculoIncidencias
                 WHERE id_dv = ?
                 ORDER BY fecha_incidencia DESC, id_incidencia DESC"
            );
            foreach ($equipos as &$e) {
                $inc->execute([(int)$e['id_dv']]);
                $s = $inc->fetch(PDO::FETCH_ASSOC) ?: [];
                $nivel = (int)($s['severidad_nivel'] ?? 0);
                $e['incidencias_total'] = (int)($s['incidencias_total'] ?? 0);
                $e['incidencias_abiertas'] = (int)($s['incidencias_abiertas'] ?? 0);
                $e['incidencia_severidad_max'] = $nivel === 3 ? 'alta' : ($nivel === 2 ? 'media' : ($nivel === 1 ? 'baja' : null));
                $e['ultima_incidencia'] = $s['ultima_incidencia'] ?? null;
                $det->execute([(int)$e['id_dv']]);
                $e['incidencias_detalle'] = $det->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($e);
        } else {
            foreach ($equipos as &$e) {
                $e['incidencias_total'] = 0;
                $e['incidencias_abiertas'] = 0;
                $e['incidencia_severidad_max'] = null;
                $e['ultima_incidencia'] = null;
                $e['incidencias_detalle'] = [];
            }
            unset($e);
        }

        return ['despacho' => $despacho, 'equipos' => $equipos];
    }

    /**
     * Cuánto lleva parado cada vehículo, según su propio recorrido guardado.
     * "Parado" = sus reportes más recientes están a menos de ~45 m del último punto.
     * Se mide con la hora que reporta el GPS, no con la de captura.
     *
     * Devuelve id_dv => ['minutos_detenido' => int, 'detenido_desde' => 'Y-m-d H:i:s'].
     */
    public function detenciones(array $idsDv): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsDv)));
        $ids = array_filter($ids);
        if (!$ids || !$this->tablaExiste('gps.DespachoRecorridos')) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare(
            "WITH ult AS (
                SELECT r.id_dv, CAST(r.lat AS FLOAT) AS lat, CAST(r.lng AS FLOAT) AS lng,
                       ISNULL(r.fecha_posicion, r.fecha_captura) AS fecha,
                       ROW_NUMBER() OVER (PARTITION BY r.id_dv
                           ORDER BY ISNULL(r.fecha_posicion, r.fecha_captura) DESC, r.id_recorrido DESC) AS rn
                FROM gps.DespachoRecorridos r
                WHERE r.id_dv IN ($in)
             )
             SELECT u.id_dv,
                    CONVERT(varchar(19), u.fecha, 120)        AS ultima,
                    CONVERT(varchar(19), ini.fecha_ini, 120)  AS desde
             FROM ult u
             OUTER APPLY (
                -- último momento en que estuvo en OTRO lugar
                SELECT MAX(ISNULL(r2.fecha_posicion, r2.fecha_captura)) AS fecha_mov
                FROM gps.DespachoRecorridos r2
                WHERE r2.id_dv = u.id_dv
                  AND ( ABS(CAST(r2.lat AS FLOAT) - u.lat) > 0.0004
                     OR ABS(CAST(r2.lng AS FLOAT) - u.lng) > 0.0004 )
             ) mov
             OUTER APPLY (
                -- primer reporte en el punto actual después de ese movimiento
                SELECT MIN(ISNULL(r3.fecha_posicion, r3.fecha_captura)) AS fecha_ini
                FROM gps.DespachoRecorridos r3
                WHERE r3.id_dv = u.id_dv
                  AND ABS(CAST(r3.lat AS FLOAT) - u.lat) <= 0.0004
                  AND ABS(CAST(r3.lng AS FLOAT) - u.lng) <= 0.0004
                  AND (mov.fecha_mov IS NULL
                       OR ISNULL(r3.fecha_posicion, r3.fecha_captura) > mov.fecha_mov)
             ) ini
             WHERE u.rn = 1"
        );
        $stmt->execute($ids);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (empty($r['ultima']) || empty($r['desde'])) continue;
            $min = (int) floor((strtotime($r['ultima']) - strtotime($r['desde'])) / 60);
            $out[(int)$r['id_dv']] = [
                'minutos_detenido' => max(0, $min),
                'detenido_desde'   => $r['desde'],
            ];
        }
        return $out;
    }

    /**
     * Abre y cierra alertas automáticas según el estado actual de cada carro.
     * $registros trae ya calculados minutos_detenido y minutos_sin_reporte.
     *
     * Un "episodio" es una fila: se abre al detectarse y se marca 'resuelta'
     * cuando el carro vuelve a la normalidad. No se duplica mientras siga activa.
     */
    public function sincronizarAlertas(array $registros, int $umbralDet, int $umbralSin): array
    {
        if (!$registros || !$this->tablaExiste('gps.DespachoAlertas')) return ['nuevas' => 0, 'resueltas' => 0];

        $ids = array_map(fn($r) => (int)$r['id_dv'], $registros);
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->conn->prepare(
            "SELECT id_alerta, id_dv, tipo FROM gps.DespachoAlertas
             WHERE estado = 'activa' AND id_dv IN ($in)"
        );
        $stmt->execute($ids);
        $activas = [];   // id_dv => [tipo => id_alerta]
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $activas[(int)$a['id_dv']][$a['tipo']] = (int)$a['id_alerta'];
        }

        $insert = $this->conn->prepare(
            "INSERT INTO gps.DespachoAlertas
                (id_despacho, id_dv, id_tramo, id_cuenta, placa, imei, tipo, umbral_min,
                 minutos_detectado, lat, lng, direccion, fecha_inicio)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRY_CONVERT(datetime, NULLIF(?, ''), 120))"
        );
        $resolver = $this->conn->prepare(
            "UPDATE gps.DespachoAlertas
                SET estado = 'resuelta', fecha_resuelta = GETDATE(),
                    minutos_final = DATEDIFF(minute, ISNULL(fecha_inicio, fecha_detectada), GETDATE())
              WHERE id_alerta = ? AND estado = 'activa'"
        );

        $nuevas = 0; $resueltas = 0;
        foreach ($registros as $r) {
            $id_dv = (int)$r['id_dv'];
            $tipo  = $this->tipoAlerta($r, $umbralDet, $umbralSin);
            $abiertas = $activas[$id_dv] ?? [];

            // Cerrar las de otro tipo (o todas si ya no hay condición)
            foreach ($abiertas as $t => $id_alerta) {
                if ($t === $tipo) continue;
                $resolver->execute([$id_alerta]);
                $resueltas++;
            }
            if ($tipo === null || isset($abiertas[$tipo])) continue;

            $minutos = $tipo === 'detenido' ? ($r['minutos_detenido'] ?? null) : ($r['minutos_sin_reporte'] ?? null);
            $insert->execute([
                (int)$r['id_despacho'], $id_dv,
                $r['id_tramo'] ?: null,
                (int)($r['id_cuenta'] ?? 0),
                $r['placa'], $r['imei'] ?: null,
                $tipo, $tipo === 'detenido' ? $umbralDet : $umbralSin,
                $minutos !== null ? (int)$minutos : null,
                $r['lat'] ?? null, $r['lng'] ?? null, $r['direccion'] ?? null,
                $tipo === 'detenido' ? (string)($r['detenido_desde'] ?? '') : (string)($r['fecha'] ?? ''),
            ]);
            $nuevas++;
        }
        return ['nuevas' => $nuevas, 'resueltas' => $resueltas];
    }

    /** Qué alerta aplica ahora mismo a un carro, o null. Sin reporte manda sobre detenido. */
    private function tipoAlerta(array $r, int $umbralDet, int $umbralSin): ?string
    {
        $sin = $r['minutos_sin_reporte'] ?? null;
        if ($sin !== null && $sin >= $umbralSin) return 'sin_reporte';
        $det = $r['minutos_detenido'] ?? null;
        if ($det !== null && $det >= $umbralDet && !((float)($r['velocidad'] ?? 0) > 0)) return 'detenido';
        return null;
    }

    /** Alertas de un despacho (o de todos los activos). $estado: 'activa'|'resuelta'|'todas'. */
    public function alertas(?int $id_despacho, string $estado = 'todas', int $limite = 300): array
    {
        if (!$this->tablaExiste('gps.DespachoAlertas')) return [];
        $where = [];
        $params = [];
        if ($id_despacho !== null) { $where[] = 'a.id_despacho = ?'; $params[] = $id_despacho; }
        else                       { $where[] = "d.estado = 'activo'"; }
        if ($estado === 'activa' || $estado === 'resuelta') { $where[] = 'a.estado = ?'; $params[] = $estado; }
        $limite = max(1, min(1000, $limite));

        $stmt = $this->conn->prepare(
            "SELECT TOP $limite a.id_alerta, a.id_dv, a.id_despacho, d.nombre AS despacho,
                    a.placa, a.imei, a.tipo, a.estado, a.umbral_min,
                    a.minutos_detectado, a.minutos_final,
                    CAST(a.lat AS FLOAT) AS lat, CAST(a.lng AS FLOAT) AS lng, a.direccion,
                    CONVERT(varchar(19), a.fecha_inicio, 120)    AS fecha_inicio,
                    CONVERT(varchar(19), a.fecha_detectada, 120) AS fecha_detectada,
                    CONVERT(varchar(19), a.fecha_resuelta, 120)  AS fecha_resuelta,
                    DATEDIFF(minute, ISNULL(a.fecha_inicio, a.fecha_detectada),
                             ISNULL(a.fecha_resuelta, GETDATE())) AS minutos_totales
             FROM gps.DespachoAlertas a
             INNER JOIN gps.Despachos d ON d.id_despacho = a.id_despacho
             WHERE " . implode(' AND ', $where) . "
             ORDER BY CASE WHEN a.estado = 'activa' THEN 0 ELSE 1 END, a.fecha_detectada DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** ¿Existe una columna? (para migraciones que aún no se corren). Cachea por columna. */
    private function columnaExiste(string $tabla, string $columna): bool
    {
        $clave = "$tabla.$columna";
        if (isset($this->columnasCache[$clave])) return $this->columnasCache[$clave];
        $stmt = $this->conn->prepare(
            "SELECT CASE WHEN COL_LENGTH(?, ?) IS NULL THEN 0 ELSE 1 END"
        );
        $stmt->execute([$tabla, $columna]);
        return $this->columnasCache[$clave] = ((int)$stmt->fetchColumn() === 1);
    }
    private $columnasCache = [];

    private function tablaExiste(string $tabla): bool
    {
        if ($tabla === 'gps.DespachoVehiculoTramos' && $this->tablaTramosExiste !== null) {
            return $this->tablaTramosExiste;
        }
        $permitidas = [
            'gps.DespachoVehiculoTramos' => 'gps.DespachoVehiculoTramos',
            'gps.DespachoRecorridos' => 'gps.DespachoRecorridos',
            'gps.DespachoVehiculoIncidencias' => 'gps.DespachoVehiculoIncidencias',
            'gps.DespachoAlertas' => 'gps.DespachoAlertas',
            'gps.WorkerHeartbeats' => 'gps.WorkerHeartbeats',
        ];
        if (!isset($permitidas[$tabla])) return false;
        $nombre = $permitidas[$tabla];
        $stmt = $this->conn->query("SELECT CASE WHEN OBJECT_ID('$nombre', 'U') IS NULL THEN 0 ELSE 1 END");
        $ok = (int)$stmt->fetchColumn() === 1;
        if ($tabla === 'gps.DespachoVehiculoTramos') $this->tablaTramosExiste = $ok;
        return $ok;
    }

    /** Para el worker: imeis de Optimus en despachos activos. imei => [id_cuenta,placa,dispositivo]. */
    public function imeisOptimusActivos(): array
    {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT dv.imei, dv.id_cuenta, dv.placa, dv.dispositivo
             FROM gps.DespachoVehiculos dv
             INNER JOIN gps.Despachos   d ON d.id_despacho  = dv.id_despacho AND d.estado = 'activo'
             INNER JOIN gps.CuentasGPS  c ON c.id_cuenta    = dv.id_cuenta
             INNER JOIN gps.Plataformas p ON p.id_plataforma = c.id_plataforma
             WHERE dv.activo = 1 AND p.tipo_integracion = 'optimus'
               AND dv.imei IS NOT NULL AND dv.imei <> ''"
        );
        $stmt->execute();
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $map[trim($r['imei'])] = [
                'id_cuenta' => (int)$r['id_cuenta'],
                'placa'     => $r['placa'],
            ];
        }
        return $map;
    }

    /** Guarda un punto de recorrido del carro dentro de su despacho, evitando duplicados por reporte. */
    public function guardarPuntoRecorrido(array $vehiculo, array $pos): void
    {
        if (($pos['lat'] ?? null) === null || ($pos['lng'] ?? null) === null) return;

        $fecha = trim((string)($pos['fecha'] ?? ''));
        $existe = $this->conn->prepare(
            "SELECT COUNT(*) FROM gps.DespachoRecorridos
             WHERE id_dv = ?
               AND ABS(CAST(lat AS FLOAT) - ?) < 0.000001
               AND ABS(CAST(lng AS FLOAT) - ?) < 0.000001
               AND (
                    (? <> '' AND fecha_posicion = TRY_CONVERT(datetime, NULLIF(?, ''), 120))
                    OR
                    (? = '' AND DATEDIFF(second, fecha_captura, GETDATE()) < 120)
               )"
        );
        $existe->execute([
            (int)$vehiculo['id_dv'], (float)$pos['lat'], (float)$pos['lng'],
            $fecha, $fecha, $fecha
        ]);
        if ((int)$existe->fetchColumn() > 0) return;

        $insert = $this->conn->prepare(
            "INSERT INTO gps.DespachoRecorridos
                (id_dv, id_despacho, id_cuenta, placa, imei, lat, lng,
                 velocidad, rumbo, encendido, direccion, fecha_posicion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, TRY_CONVERT(datetime, NULLIF(?, ''), 120))"
        );
        $insert->execute([
            (int)$vehiculo['id_dv'],
            (int)$vehiculo['id_despacho'],
            (int)$vehiculo['id_cuenta'],
            $vehiculo['placa'],
            $vehiculo['imei'] ?? null,
            (float)$pos['lat'],
            (float)$pos['lng'],
            $pos['velocidad'] ?? null,
            $pos['rumbo'] ?? null,
            $pos['encendido'] ?? null,
            $pos['direccion'] ?? null,
            $fecha,
        ]);
    }

    /** Recorrido historico de un vehiculo en un despacho. */
    public function recorridoVehiculo(int $id_dv, ?int $id_tramo = null): array
    {
        $where = "r.id_dv = ?";
        $params = [$id_dv];
        $join = "";
        if ($id_tramo !== null && $this->tablaExiste('gps.DespachoVehiculoTramos')) {
            $join = "INNER JOIN gps.DespachoVehiculoTramos tr ON tr.id_tramo = ?";
            $where .= " AND ISNULL(r.fecha_posicion, r.fecha_captura) >= tr.fecha_inicio
                        AND ISNULL(r.fecha_posicion, r.fecha_captura) <= ISNULL(tr.fecha_fin, GETDATE())";
            array_unshift($params, $id_tramo);
        }
        $stmt = $this->conn->prepare(
            "SELECT r.id_recorrido, r.id_dv, r.id_despacho, r.placa,
                    CAST(r.lat AS FLOAT) AS lat, CAST(r.lng AS FLOAT) AS lng,
                    r.velocidad, r.rumbo, r.encendido, r.direccion,
                    CONVERT(varchar(19), r.fecha_posicion, 120) AS fecha,
                    CONVERT(varchar(19), r.fecha_captura, 120) AS fecha_captura
             FROM gps.DespachoRecorridos r
             $join
             WHERE $where
             ORDER BY ISNULL(r.fecha_posicion, r.fecha_captura), r.id_recorrido"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
