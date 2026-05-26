<?php

require_once '../../../../config/Connection.php';

class mdlSatake
{
    private PDO $conn;

    public function __construct()
    {
        $this->conn = (new Connection())->dbConnect();
    }

    /* ═══════════════════════════════════════════════════════════
       CATÁLOGOS
    ═══════════════════════════════════════════════════════════ */

    public function listarFrecuencias(): array
    {
        return $this->conn
            ->query("SELECT id_frecuencia, nombre, orden, color_badge
                     FROM electronicas.SatakeFrecuencias
                     ORDER BY orden")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCategorias(): array
    {
        return $this->conn
            ->query("SELECT id_categoria, nombre, color_badge
                     FROM electronicas.SatakeCategorias
                     ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ═══════════════════════════════════════════════════════════
       TAREAS
    ═══════════════════════════════════════════════════════════ */

    public function listarTareas(int $idFrecuencia = 0, int $idCategoria = 0): array
    {
        $where  = [];
        $params = [];

        if ($idFrecuencia) {
            $where[]  = 't.id_frecuencia = ?';
            $params[] = $idFrecuencia;
        }
        if ($idCategoria) {
            $where[]  = 't.id_categoria = ?';
            $params[] = $idCategoria;
        }

        $sql = "SELECT t.id_tarea, t.nombre,
                       t.id_frecuencia, f.nombre AS frecuencia, f.color_badge AS frec_badge,
                       t.id_categoria,  c.nombre AS categoria,  c.color_badge AS cat_badge,
                       t.por_que, t.si_no_se_hace, t.metodo,
                       t.solo_tecnico, t.activo
                FROM electronicas.SatakeTareas t
                INNER JOIN electronicas.SatakeFrecuencias f ON f.id_frecuencia = t.id_frecuencia
                INNER JOIN electronicas.SatakeCategorias  c ON c.id_categoria  = t.id_categoria"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY f.orden, t.nombre';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerTarea(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id_tarea, t.nombre,
                    t.id_frecuencia, f.nombre AS frecuencia, f.color_badge AS frec_badge,
                    t.id_categoria,  c.nombre AS categoria,  c.color_badge AS cat_badge,
                    t.por_que, t.si_no_se_hace, t.metodo,
                    t.solo_tecnico, t.activo
             FROM electronicas.SatakeTareas t
             INNER JOIN electronicas.SatakeFrecuencias f ON f.id_frecuencia = t.id_frecuencia
             INNER JOIN electronicas.SatakeCategorias  c ON c.id_categoria  = t.id_categoria
             WHERE t.id_tarea = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function guardarTarea(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.SatakeTareas
                 (nombre, id_frecuencia, id_categoria, por_que, si_no_se_hace, metodo, solo_tecnico)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $d['nombre'],
            (int)$d['id_frecuencia'],
            (int)$d['id_categoria'],
            $d['por_que'],
            $d['si_no_se_hace'],
            $d['metodo'] ?: null,
            (int)($d['solo_tecnico'] ?? 0),
        ]);
        return (int)$this->conn->lastInsertId();
    }

    public function actualizarTarea(int $id, array $d): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.SatakeTareas
             SET nombre        = ?,
                 id_frecuencia = ?,
                 id_categoria  = ?,
                 por_que       = ?,
                 si_no_se_hace = ?,
                 metodo        = ?,
                 solo_tecnico  = ?
             WHERE id_tarea = ?"
        )->execute([
            $d['nombre'],
            (int)$d['id_frecuencia'],
            (int)$d['id_categoria'],
            $d['por_que'],
            $d['si_no_se_hace'],
            $d['metodo'] ?: null,
            (int)($d['solo_tecnico'] ?? 0),
            $id,
        ]);
    }

    public function toggleActivoTarea(int $id): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.SatakeTareas
             SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END
             WHERE id_tarea = ?"
        )->execute([$id]);
    }

    /* ═══════════════════════════════════════════════════════════
       ALERTAS
    ═══════════════════════════════════════════════════════════ */

    public function listarAlertas(): array
    {
        return $this->conn
            ->query("SELECT id_alerta, senal, causa, accion, activo
                     FROM electronicas.SatakeAlertas
                     ORDER BY id_alerta")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAlerta(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT id_alerta, senal, causa, accion, activo
             FROM electronicas.SatakeAlertas WHERE id_alerta = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function guardarAlerta(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO electronicas.SatakeAlertas (senal, causa, accion)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$d['senal'], $d['causa'], $d['accion']]);
        return (int)$this->conn->lastInsertId();
    }

    public function actualizarAlerta(int $id, array $d): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.SatakeAlertas
             SET senal  = ?, causa  = ?, accion = ?
             WHERE id_alerta = ?"
        )->execute([$d['senal'], $d['causa'], $d['accion'], $id]);
    }

    public function toggleActivoAlerta(int $id): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.SatakeAlertas
             SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END
             WHERE id_alerta = ?"
        )->execute([$id]);
    }
}
