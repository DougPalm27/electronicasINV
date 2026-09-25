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
            "INSERT INTO electronicas.TurnoSesiones (id_usuario, estacion)
             OUTPUT INSERTED.id_sesion
             VALUES (?, ?)"
        );
        $stmt->execute([$id_usuario, $estacion]);
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
        $stmt = $this->conn->prepare(
            "SELECT id_usuario, nombre, pin_bloqueo
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
                        "INSERT INTO electronicas.TurnoSesiones (id_usuario, estacion, inicio, fin, uid_cliente)
                         VALUES (?, ?, ?, ?, ?)"
                    )->execute([$idUsuario, $estacion, $inicio, $fin, $uid]);
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
        $sql = "SELECT ts.id_sesion, ts.estacion, ts.inicio, u.nombre
                FROM electronicas.TurnoSesiones ts
                INNER JOIN electronicas.Usuarios u ON u.id_usuario = ts.id_usuario
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
                ORDER BY ts.inicio DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
