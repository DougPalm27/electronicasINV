<?php

class mdlComponentes
{
    private $conn;

    public function __construct()
    {
        $conexion   = new Connection();
        $this->conn = $conexion->dbConnect();
    }

    // ══════════════════════════════════════════════════════════════
    // CARDS: modelos con su resumen de catálogo
    // ══════════════════════════════════════════════════════════════
    public function listarModelos(): array
    {
        $sql = "SELECT mo.id_modelo,
                       mo.nombre                AS modelo,
                       ma.nombre                AS marca,
                       tm.nombre                AS tipo_modelo,
                       mo.imagen,
                       mo.imagen_esquema,
                       (SELECT COUNT(*) FROM electronicas.Maquinas mq
                        WHERE mq.id_modelo = mo.id_modelo)           AS maquinas,
                       (SELECT COUNT(*) FROM electronicas.ModeloComponentes mc
                        WHERE mc.id_modelo = mo.id_modelo)           AS componentes,
                       (SELECT ISNULL(SUM(mc.cantidad), 0) FROM electronicas.ModeloComponentes mc
                        WHERE mc.id_modelo = mo.id_modelo)           AS unidades
                FROM electronicas.Modelos mo
                INNER JOIN electronicas.Marcas ma ON ma.id_marca = mo.id_marca
                LEFT  JOIN electronicas.TiposModelo tm ON tm.id_tipo_modelo = mo.id_tipo_modelo
                ORDER BY ma.nombre, mo.nombre";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // FICHA de un modelo: cabecera + componentes
    // ══════════════════════════════════════════════════════════════
    public function obtenerFicha(int $id_modelo): array
    {
        $stmt = $this->conn->prepare(
            "SELECT mo.id_modelo, mo.nombre AS modelo, ma.nombre AS marca,
                    tm.nombre AS tipo_modelo, mo.imagen, mo.imagen_esquema,
                    (SELECT COUNT(*) FROM electronicas.Maquinas mq
                     WHERE mq.id_modelo = mo.id_modelo) AS maquinas,
                    (SELECT COUNT(*) FROM electronicas.ConfiguracionEyectores ce
                     WHERE ce.id_modelo = mo.id_modelo AND ce.activo = 1) AS tiene_eyectores
             FROM electronicas.Modelos mo
             INNER JOIN electronicas.Marcas ma ON ma.id_marca = mo.id_marca
             LEFT  JOIN electronicas.TiposModelo tm ON tm.id_tipo_modelo = mo.id_tipo_modelo
             WHERE mo.id_modelo = ?"
        );
        $stmt->execute([$id_modelo]);
        $modelo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$modelo) return [];

        $stmtC = $this->conn->prepare(
            "SELECT mc.id_modelo_componente, mc.id_componente, mc.id_padre,
                    mc.cantidad, mc.especificacion, mc.pos_x, mc.pos_y, mc.id_repuesto,
                    c.nombre AS componente, c.categoria,
                    r.nombre AS repuesto, r.maneja_serie, r.stock_minimo,
                    CASE WHEN r.maneja_serie = 1
                         THEN (SELECT COUNT(*) FROM electronicas.RepuestosDetalle rd
                               WHERE rd.id_repuesto = r.id_repuesto AND rd.id_estado_repuesto = 1)
                         ELSE r.stock END AS stock
             FROM electronicas.ModeloComponentes mc
             INNER JOIN electronicas.Componentes c ON c.id_componente = mc.id_componente
             LEFT  JOIN electronicas.Repuestos r ON r.id_repuesto = mc.id_repuesto
             WHERE mc.id_modelo = ?
             ORDER BY mc.id_modelo_componente"
        );
        $stmtC->execute([$id_modelo]);

        return [
            'modelo'      => $modelo,
            'componentes' => $stmtC->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // CATÁLOGO MAESTRO
    // ══════════════════════════════════════════════════════════════
    public function listarCatalogo(): array
    {
        return $this->conn
            ->query("SELECT id_componente, nombre, categoria
                     FROM electronicas.Componentes
                     WHERE activo = 1 ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarEnCatalogo(string $nombre, ?string $categoria): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_componente FROM electronicas.Componentes WHERE nombre = ?"
        );
        $stmt->execute([$nombre]);
        $existente = $stmt->fetchColumn();
        if ($existente) return ['id_componente' => (int)$existente];

        $this->conn->prepare(
            "INSERT INTO electronicas.Componentes (nombre, categoria) VALUES (?, ?)"
        )->execute([$nombre, $categoria ?: null]);

        return ['id_componente' => (int)$this->conn->lastInsertId()];
    }

    // ══════════════════════════════════════════════════════════════
    // REPUESTOS (para el select de vínculo)
    // ══════════════════════════════════════════════════════════════
    public function listarRepuestos(): array
    {
        return $this->conn
            ->query("SELECT id_repuesto, nombre FROM electronicas.Repuestos ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════
    // ITEMS del modelo (CRUD)
    // ══════════════════════════════════════════════════════════════
    public function guardarItem(array $d): array
    {
        $this->conn->prepare(
            "INSERT INTO electronicas.ModeloComponentes
                 (id_modelo, id_componente, id_padre, cantidad, especificacion, id_repuesto)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $d['id_modelo'], $d['id_componente'], $d['id_padre'],
            $d['cantidad'], $d['especificacion'], $d['id_repuesto']
        ]);
        return ['id_modelo_componente' => (int)$this->conn->lastInsertId()];
    }

    public function editarItem(array $d): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.ModeloComponentes
             SET id_componente = ?, id_padre = ?, cantidad = ?,
                 especificacion = ?, id_repuesto = ?
             WHERE id_modelo_componente = ?"
        )->execute([
            $d['id_componente'], $d['id_padre'], $d['cantidad'],
            $d['especificacion'], $d['id_repuesto'], $d['id_modelo_componente']
        ]);
    }

    public function eliminarItem(int $id): array
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) FROM electronicas.ModeloComponentes WHERE id_padre = ?"
        );
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() > 0) {
            return ['error' => true,
                    'mensaje' => 'Este componente tiene sub-componentes: eliminálos o reasignálos primero.'];
        }

        // Quitar también las instancias por máquina y su historial
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "DELETE h FROM electronicas.ComponenteHistorial h
                 INNER JOIN electronicas.MaquinaComponentes mc
                         ON mc.id_maquina_componente = h.id_maquina_componente
                 WHERE mc.id_modelo_componente = ?"
            )->execute([$id]);
            $this->conn->prepare(
                "DELETE FROM electronicas.MaquinaComponentes WHERE id_modelo_componente = ?"
            )->execute([$id]);
            $this->conn->prepare(
                "DELETE FROM electronicas.ModeloComponentes WHERE id_modelo_componente = ?"
            )->execute([$id]);
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
        return ['ok' => true];
    }

    // Posiciones de los pines sobre la radiografía (en porcentaje)
    public function guardarPosiciones(array $posiciones): void
    {
        $stmt = $this->conn->prepare(
            "UPDATE electronicas.ModeloComponentes
             SET pos_x = ?, pos_y = ? WHERE id_modelo_componente = ?"
        );
        foreach ($posiciones as $p) {
            $p = (array)$p;
            if (!isset($p['id'])) continue;
            $x = isset($p['x']) ? max(0, min(100, (float)$p['x'])) : null;
            $y = isset($p['y']) ? max(0, min(100, (float)$p['y'])) : null;
            $stmt->execute([$x, $y, (int)$p['id']]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // ESQUEMA (radiografía) del modelo
    // ══════════════════════════════════════════════════════════════
    public function obtenerEsquema(int $id_modelo): ?string
    {
        $stmt = $this->conn->prepare(
            "SELECT imagen_esquema FROM electronicas.Modelos WHERE id_modelo = ?"
        );
        $stmt->execute([$id_modelo]);
        $ruta = $stmt->fetchColumn();
        return $ruta !== false ? $ruta : null;
    }

    public function guardarEsquema(int $id_modelo, ?string $ruta): void
    {
        $this->conn->prepare(
            "UPDATE electronicas.Modelos SET imagen_esquema = ? WHERE id_modelo = ?"
        )->execute([$ruta, $id_modelo]);
    }

    // ══════════════════════════════════════════════════════════════
    // ESTADOS POR MÁQUINA (patrón eyectores)
    // ══════════════════════════════════════════════════════════════

    /** Modelos que tienen componentes en catálogo (para el dropdown de Máquinas). */
    public function modelosConComponentes(): array
    {
        return $this->conn
            ->query("SELECT DISTINCT id_modelo FROM electronicas.ModeloComponentes")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Estado de los componentes de una máquina. Crea las instancias que
     * falten (una por componente de primer nivel del modelo, en estado OK).
     */
    public function estadoMaquina(int $id_maquina): array
    {
        $stmtM = $this->conn->prepare(
            "SELECT mq.id_maquina, mq.nombre AS maquina, mq.id_modelo,
                    mo.nombre AS modelo, mo.imagen_esquema,
                    (SELECT COUNT(*) FROM electronicas.ConfiguracionEyectores ce
                     WHERE ce.id_modelo = mq.id_modelo AND ce.activo = 1) AS tiene_eyectores
             FROM electronicas.Maquinas mq
             INNER JOIN electronicas.Modelos mo ON mo.id_modelo = mq.id_modelo
             WHERE mq.id_maquina = ?"
        );
        $stmtM->execute([$id_maquina]);
        $maquina = $stmtM->fetch(PDO::FETCH_ASSOC);
        if (!$maquina) return [];

        // Crear instancias faltantes (primer nivel del catálogo del modelo)
        $this->conn->prepare(
            "INSERT INTO electronicas.MaquinaComponentes (id_maquina, id_modelo_componente)
             SELECT ?, mc.id_modelo_componente
             FROM electronicas.ModeloComponentes mc
             WHERE mc.id_modelo = ? AND mc.id_padre IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM electronicas.MaquinaComponentes x
                   WHERE x.id_maquina = ? AND x.id_modelo_componente = mc.id_modelo_componente
               )"
        )->execute([$id_maquina, $maquina['id_modelo'], $id_maquina]);

        $stmtC = $this->conn->prepare(
            "SELECT maq.id_maquina_componente, maq.id_estado, maq.observacion,
                    CONVERT(varchar, maq.fecha_actualizacion, 120) AS fecha_actualizacion,
                    mc.id_modelo_componente, mc.cantidad, mc.especificacion,
                    mc.pos_x, mc.pos_y,
                    c.nombre AS componente, c.categoria,
                    es.nombre AS estado, es.clase_css,
                    (SELECT COUNT(*) FROM electronicas.ModeloComponentes hijo
                     WHERE hijo.id_padre = mc.id_modelo_componente) AS hijos
             FROM electronicas.MaquinaComponentes maq
             INNER JOIN electronicas.ModeloComponentes mc
                     ON mc.id_modelo_componente = maq.id_modelo_componente
             INNER JOIN electronicas.Componentes c ON c.id_componente = mc.id_componente
             INNER JOIN electronicas.EstadoEyector es ON es.id_estado = maq.id_estado
             WHERE maq.id_maquina = ?
             ORDER BY mc.id_modelo_componente"
        );
        $stmtC->execute([$id_maquina]);

        $estados = $this->conn
            ->query("SELECT id_estado, nombre, clase_css, color
                     FROM electronicas.EstadoEyector ORDER BY orden")
            ->fetchAll(PDO::FETCH_ASSOC);

        return [
            'maquina'     => $maquina,
            'estados'     => $estados,
            'componentes' => $stmtC->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /** Cambia el estado de un componente de máquina y registra el historial. */
    public function actualizarEstado(int $id, int $id_estado, ?string $observacion, int $id_usuario): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id_estado FROM electronicas.MaquinaComponentes WHERE id_maquina_componente = ?"
        );
        $stmt->execute([$id]);
        $anterior = $stmt->fetchColumn();
        if ($anterior === false) {
            return ['error' => true, 'mensaje' => 'Componente no encontrado.'];
        }

        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "UPDATE electronicas.MaquinaComponentes
                 SET id_estado = ?, observacion = ?,
                     fecha_actualizacion = GETDATE(), id_usuario_actualiza = ?
                 WHERE id_maquina_componente = ?"
            )->execute([$id_estado, $observacion ?: null, $id_usuario, $id]);

            $this->conn->prepare(
                "INSERT INTO electronicas.ComponenteHistorial
                     (id_maquina_componente, id_estado_anterior, id_estado_nuevo, observacion, id_usuario)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$id, $anterior, $id_estado, $observacion ?: null, $id_usuario]);

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
        return ['ok' => true];
    }

    /** Historial de cambios de componentes de una máquina (más recientes primero). */
    public function historialMaquina(int $id_maquina): array
    {
        $stmt = $this->conn->prepare(
            "SELECT TOP 500
                    CONVERT(varchar, h.fecha, 120) AS fecha,
                    c.nombre AS componente,
                    ea.nombre AS estado_anterior, ea.clase_css AS clase_anterior,
                    en.nombre AS estado_nuevo,    en.clase_css AS clase_nuevo,
                    h.observacion,
                    u.nombre AS usuario
             FROM electronicas.ComponenteHistorial h
             INNER JOIN electronicas.MaquinaComponentes maq
                     ON maq.id_maquina_componente = h.id_maquina_componente
             INNER JOIN electronicas.ModeloComponentes mc
                     ON mc.id_modelo_componente = maq.id_modelo_componente
             INNER JOIN electronicas.Componentes c ON c.id_componente = mc.id_componente
             INNER JOIN electronicas.EstadoEyector en ON en.id_estado = h.id_estado_nuevo
             LEFT  JOIN electronicas.EstadoEyector ea ON ea.id_estado = h.id_estado_anterior
             LEFT  JOIN electronicas.Usuarios u ON u.id_usuario = h.id_usuario
             WHERE maq.id_maquina = ?
             ORDER BY h.fecha DESC, h.id_historial DESC"
        );
        $stmt->execute([$id_maquina]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
