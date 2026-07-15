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

        $this->conn->prepare(
            "DELETE FROM electronicas.ModeloComponentes WHERE id_modelo_componente = ?"
        )->execute([$id]);
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
}
