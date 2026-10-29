<?php

class mdlTurnos
{
    private $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ── Kiosco: identificar por PIN ────────────────────────
    public function buscarPorPin(string $pin): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_usuario, nombre, foto
             FROM electronicas.Usuarios
             WHERE pin_bloqueo = ? AND activo = 1"
        );
        $stmt->execute([$pin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Máquina del inventario que tiene declarada esta estación ─
    // Maquinas.estacion_candado la asigna un administrador (módulo Máquinas).
    // Devuelve null si la estación aún no está ligada: el turno se registra igual.
    public function maquinaPorEstacion(string $estacion): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT id_maquina FROM electronicas.Maquinas WHERE estacion_candado = ?"
        );
        $stmt->execute([$estacion]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    // ── Instalador: máquinas que se pueden elegir para esta PC ─
    // Solo las que un administrador habilitó (tienen estacion_candado) y que
    // todavía no tienen su candado en otra PC. Si esta misma PC ya tiene una
    // (reinstalación o actualización), esa aparece marcada con mia = 1.
    public function maquinasParaInstalar(string $equipoId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.id_maquina, m.nombre, mo.nombre AS modelo, m.ubicacion, m.serie,
                    m.estacion_candado AS estacion,
                    CASE WHEN c.equipo_id = ? THEN 1 ELSE 0 END AS mia
             FROM electronicas.Maquinas m
             INNER JOIN electronicas.Modelos mo ON mo.id_modelo = m.id_modelo
             LEFT JOIN electronicas.MaquinaCandado c ON c.id_maquina = m.id_maquina
             WHERE m.estacion_candado IS NOT NULL
               AND (c.id_maquina IS NULL OR c.equipo_id = ?)
             ORDER BY m.ubicacion, m.nombre"
        );
        $stmt->execute([$equipoId, $equipoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Instalador: ¿se puede usar esta estación desde esta PC? ─
    // No si la máquina de esa estación ya tiene su candado en OTRA PC. Una estación que no está en el
    // inventario (modo manual) no se bloquea. Devuelve ['ok' => bool, 'mensaje' => string].
    public function estacionDisponible(string $estacion, string $equipoId): array
    {
        $q = $this->conn->prepare(
            "SELECT m.nombre, c.equipo_id, c.equipo_nombre
             FROM electronicas.Maquinas m
             LEFT JOIN electronicas.MaquinaCandado c ON c.id_maquina = m.id_maquina
             WHERE m.estacion_candado = ?"
        );
        $q->execute([$estacion]);
        $f = $q->fetch(PDO::FETCH_ASSOC);
        if ($f && $f['equipo_id'] !== null && $f['equipo_id'] !== $equipoId) {
            $otra = (string)$f['equipo_nombre'];
            return ['ok' => false, 'mensaje' => 'La máquina ' . $f['nombre'] . ' ya tiene el candado instalado en otra PC'
                . ($otra !== '' ? ' (' . $otra . ')' : '') . '. Elige otra, o pide al administrador que la libere.'];
        }
        return ['ok' => true, 'mensaje' => ''];
    }

    // ── Instalador: deja anotado qué PC tiene el candado de la máquina ─
    // Devuelve ['ok' => bool, 'mensaje' => string, 'maquina' => nombre].
    // Una PC solo corre un candado: si antes estaba ligada a otra máquina, esa se suelta.
    public function registrarCandado(string $estacion, string $equipoId, string $equipoNombre, string $version): array
    {
        $q = $this->conn->prepare(
            "SELECT id_maquina, nombre FROM electronicas.Maquinas WHERE estacion_candado = ?"
        );
        $q->execute([$estacion]);
        $m = $q->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            return ['ok' => false, 'mensaje' => 'La estación no está asignada a ninguna máquina del inventario.'];
        }
        $id = (int)$m['id_maquina'];

        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "DELETE FROM electronicas.MaquinaCandado WHERE equipo_id = ? AND id_maquina <> ?"
            )->execute([$equipoId, $id]);

            $u = $this->conn->prepare(
                "UPDATE electronicas.MaquinaCandado
                    SET equipo_nombre = ?, version = ?, fecha_instalacion = GETDATE()
                  WHERE id_maquina = ? AND equipo_id = ?"
            );
            $u->execute([$equipoNombre, $version, $id, $equipoId]);

            if ($u->rowCount() === 0) {
                $this->conn->prepare(
                    "INSERT INTO electronicas.MaquinaCandado (id_maquina, equipo_id, equipo_nombre, version)
                     VALUES (?, ?, ?, ?)"
                )->execute([$id, $equipoId, $equipoNombre, $version]);
            }
            $this->conn->commit();
            return ['ok' => true, 'mensaje' => '', 'maquina' => $m['nombre']];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            $codigo = (int)($e->errorInfo[1] ?? 0);
            // 2627 / 2601: la máquina ya la tomó otra PC (por ejemplo, dos instalaciones a la vez)
            if ($codigo === 2627 || $codigo === 2601) {
                $o = $this->conn->prepare(
                    "SELECT equipo_nombre FROM electronicas.MaquinaCandado WHERE id_maquina = ?"
                );
                $o->execute([$id]);
                $otra = (string)$o->fetchColumn();
                return ['ok' => false, 'mensaje' => 'La máquina ' . $m['nombre'] . ' ya tiene el candado instalado en otra PC'
                    . ($otra !== '' ? ' (' . $otra . ')' : '') . '.'];
            }
            throw $e;
        }
    }

    // ── Kiosco: sesión abierta actualmente en una estación ─
    public function sesionActivaEn(string $estacion): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT TOP 1 ts.id_sesion, ts.inicio, u.id_usuario, u.nombre, u.foto
             FROM electronicas.TurnoSesiones ts
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
             WHERE ts.estacion = ? AND ts.fin IS NULL
             ORDER BY ts.inicio DESC"
        );
        $stmt->execute([$estacion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // ── Kiosco: abrir turno (cierra cualquier sesión huérfana
    //    que haya quedado abierta en la misma estación) ─────
    public function abrirSesion(int $id_usuario, string $estacion): int
    {
        $this->conn->prepare(
            "UPDATE electronicas.TurnoSesiones SET fin = GETDATE()
             WHERE estacion = ? AND fin IS NULL"
        )->execute([$estacion]);

        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.TurnoSesiones (id_usuario, estacion, id_maquina)
             OUTPUT INSERTED.id_sesion
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$id_usuario, $estacion, $this->maquinaPorEstacion($estacion)]);
        return (int)$stmt->fetchColumn();
    }

    // ── Kiosco: bloquear (cierra la sesión activa de la estación) ─
    public function cerrarSesionEnEstacion(string $estacion): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.TurnoSesiones SET fin = GETDATE()
             WHERE estacion = ? AND fin IS NULL"
        )->execute([$estacion]);
    }

    // ── PCs de planta: operarios activos con PIN, para su copia local ─
    public function operariosParaSync(): array
    {
        // nivel_delvis llega con BD/migration_nivel_delvis.sql; sin ella se manda 0 (ninguno)
        $nivel = $this->conn->query("SELECT COL_LENGTH('electronicas.Usuarios', 'nivel_delvis')")->fetchColumn()
            ? 'nivel_delvis' : 'CAST(0 AS TINYINT) AS nivel_delvis';
        $stmt = $this->conn->prepare(
            "SELECT id_usuario, nombre, pin_bloqueo, $nivel
             FROM electronicas.Usuarios
             WHERE activo = 1 AND pin_bloqueo IS NOT NULL
             ORDER BY nombre"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── PCs de planta: recibir turnos hechos sin conexión ──
    // Idempotente por uid_cliente: reenviar el mismo turno no lo duplica;
    // reenviarlo con 'fin' lo cierra.
    public function subirTurnos(array $turnos): array
    {
        $ok = [];
        $rechazados = [];

        foreach ($turnos as $t) {
            $uid = is_array($t) ? (string)($t['uid'] ?? '') : '';
            try {
                $idUsuario = (int)($t['id_usuario'] ?? 0);
                $estacion  = trim((string)($t['estacion'] ?? ''));
                $inicio    = $this->fechaValida($t['inicio'] ?? null);
                $fin       = isset($t['fin']) && $t['fin'] !== '' ? $this->fechaValida($t['fin']) : null;

                if (!preg_match('/^[0-9a-fA-F-]{36}$/', $uid) || !$idUsuario || $estacion === ''
                    || !$inicio || (isset($t['fin']) && $t['fin'] !== '' && !$fin)) {
                    throw new RuntimeException('Turno inválido.');
                }

                $q = $this->conn->prepare(
                    "SELECT fin FROM electronicas.TurnoSesiones WHERE uid_cliente = ?"
                );
                $q->execute([$uid]);
                $existe = $q->fetch(PDO::FETCH_ASSOC);

                if ($existe) {
                    if ($fin && $existe['fin'] === null) {
                        $this->conn->prepare(
                            "UPDATE electronicas.TurnoSesiones SET fin = ? WHERE uid_cliente = ?"
                        )->execute([$fin, $uid]);
                    }
                } else {
                    if (!$fin) {
                        $this->conn->prepare(
                            "UPDATE electronicas.TurnoSesiones SET fin = ?
                             WHERE estacion = ? AND fin IS NULL AND inicio <= ?"
                        )->execute([$inicio, $estacion, $inicio]);
                    }
                    $this->conn->prepare(
                        "INSERT INTO electronicas.TurnoSesiones (id_usuario, estacion, inicio, fin, uid_cliente, id_maquina)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    )->execute([$idUsuario, $estacion, $inicio, $fin, $uid, $this->maquinaPorEstacion($estacion)]);
                }
                $ok[] = $uid;
            } catch (Throwable $e) {
                $rechazados[] = $uid;
            }
        }

        return ['procesados' => $ok, 'rechazados' => $rechazados];
    }

    // ── PCs de planta: eventos de Delvis ocurridos durante un turno ──
    // Idempotente por uid_evento. Requiere que el turno ya exista.
    public function subirEventos(array $eventos): array
    {
        $ok = [];
        $rechazados = [];
        $tipos = ['falla', 'falla_ok', 'ajuste', 'calibracion', 'crash', 'mantenimiento', 'aplicacion'];

        foreach ($eventos as $e) {
            $uid = is_array($e) ? (string)($e['uid'] ?? '') : '';
            try {
                $turnoUid = (string)($e['turno_uid'] ?? '');
                $fecha    = $this->fechaValida($e['fecha'] ?? null);
                $tipo     = (string)($e['tipo'] ?? '');

                if (!preg_match('/^[0-9a-fA-F-]{36}$/', $uid) || !preg_match('/^[0-9a-fA-F-]{36}$/', $turnoUid)
                    || !$fecha || !in_array($tipo, $tipos, true)) {
                    throw new RuntimeException('Evento inválido.');
                }

                $q = $this->conn->prepare("SELECT id_sesion FROM electronicas.TurnoSesiones WHERE uid_cliente = ?");
                $q->execute([$turnoUid]);
                $idSesion = $q->fetchColumn();
                if (!$idSesion) throw new RuntimeException('Turno no encontrado.');

                $x = $this->conn->prepare("SELECT 1 FROM electronicas.TurnoEventos WHERE uid_evento = ?");
                $x->execute([$uid]);
                if (!$x->fetchColumn()) {
                    $this->conn->prepare(
                        "INSERT INTO electronicas.TurnoEventos
                            (uid_evento, id_sesion, fecha, tipo, codigo, detalle, valor_anterior, valor_nuevo)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([
                        $uid, (int)$idSesion, $fecha, $tipo,
                        $this->corto($e['codigo'] ?? null, 200), $this->corto($e['detalle'] ?? null, 400),
                        $this->corto($e['anterior'] ?? null, 200), $this->corto($e['nuevo'] ?? null, 200),
                    ]);
                }
                $ok[] = $uid;
            } catch (Throwable $ex) {
                $rechazados[] = $uid;
            }
        }
        return ['procesados' => $ok, 'rechazados' => $rechazados];
    }

    private function corto($v, int $max): ?string
    {
        if ($v === null || $v === '') return null;
        return mb_substr((string)$v, 0, $max);
    }

    // ── Admin: línea de tiempo de un turno ─────────────────
    public function eventosDeSesion(int $idSesion): array
    {
        $stmt = $this->conn->prepare(
            "SELECT FORMAT(fecha,'dd/MM/yyyy HH:mm:ss') AS fecha, tipo, codigo, detalle,
                    valor_anterior, valor_nuevo
             FROM electronicas.TurnoEventos
             WHERE id_sesion = ?
             ORDER BY fecha, id_evento"
        );
        $stmt->execute([$idSesion]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        require_once __DIR__ . '/catalogoDelvis.php';
        foreach ($filas as &$f) {
            $f['etiqueta']    = CatalogoDelvis::etiqueta($f['tipo']);
            $f['descripcion'] = CatalogoDelvis::descripcion($f['tipo'], $f['codigo'], $f['detalle']);
        }
        return $filas;
    }

    private function fechaValida($v): ?string
    {
        $d = is_string($v) ? DateTime::createFromFormat('Y-m-d H:i:s', $v) : false;
        return ($d && $d->format('Y-m-d H:i:s') === $v) ? $v : null;
    }

    // ── Admin: estaciones con turno abierto ahora mismo ────
    public function activos(): array
    {
        $sql = "SELECT ts.id_sesion, ts.estacion, ts.inicio, u.nombre,
                       mq.nombre AS maquina, mo.nombre AS modelo, mq.ubicacion
                FROM electronicas.TurnoSesiones ts
                INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
                LEFT JOIN electronicas.Maquinas mq ON mq.id_maquina = ts.id_maquina
                LEFT JOIN electronicas.Modelos  mo ON mo.id_modelo  = mq.id_modelo
                WHERE ts.fin IS NULL
                ORDER BY ts.estacion";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Admin: historial completo ──────────────────────────
    public function historial(): array
    {
        $sql = "SELECT ts.id_sesion, ts.estacion, u.nombre,
                       mq.nombre AS maquina, mo.nombre AS modelo, mq.ubicacion,
                       FORMAT(ts.inicio,'dd/MM/yyyy HH:mm') AS inicio,
                       FORMAT(ts.fin,   'dd/MM/yyyy HH:mm') AS fin,
                       CASE WHEN ts.fin IS NULL THEN NULL
                            ELSE DATEDIFF(MINUTE, ts.inicio, ts.fin) END AS minutos,
                       (SELECT COUNT(*) FROM electronicas.TurnoEventos e
                         WHERE e.id_sesion = ts.id_sesion AND e.tipo = 'falla')  AS n_fallas,
                       (SELECT COUNT(*) FROM electronicas.TurnoEventos e
                         WHERE e.id_sesion = ts.id_sesion AND e.tipo IN ('ajuste','calibracion')) AS n_ajustes,
                       (SELECT COUNT(*) FROM electronicas.TurnoEventos e
                         WHERE e.id_sesion = ts.id_sesion AND e.tipo = 'crash')  AS n_crashes
                FROM electronicas.TurnoSesiones ts
                INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
                LEFT JOIN electronicas.Maquinas mq ON mq.id_maquina = ts.id_maquina
                LEFT JOIN electronicas.Modelos  mo ON mo.id_modelo  = mq.id_modelo
                ORDER BY ts.inicio DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
