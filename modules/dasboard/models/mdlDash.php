<?php

class mdlDash
{
    private $conn;

    public function __construct()
    {
        require_once "../../../config/Connection.php";
        $conexion   = new Connection();
        $this->conn = $conexion->dbConnect();
    }

    public function kpis()
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM electronicas.Repuestos
                     WHERE id_estado = 1)                                           AS total_repuestos,

                    (SELECT COUNT(*) FROM electronicas.Maquinas
                     WHERE id_estado = 1)                                           AS total_maquinas,

                    (SELECT COUNT(*) FROM electronicas.Mantenimientos
                     WHERE MONTH(fecha_mantenimiento) = MONTH(GETDATE())
                       AND YEAR(fecha_mantenimiento)  = YEAR(GETDATE()))            AS mantenimientos_mes,

                    (SELECT COUNT(*) FROM electronicas.Repuestos
                     WHERE id_estado = 1
                       AND stock_minimo > 0
                       AND stock <= stock_minimo)                                   AS stock_bajo";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function mantenimientosPorMes()
    {
        $sql = "SELECT
                    FORMAT(fecha_mantenimiento, 'MMM', 'es-GT') AS mes_label,
                    YEAR(fecha_mantenimiento)   AS anio,
                    MONTH(fecha_mantenimiento)  AS mes_num,
                    COUNT(*)                    AS total
                FROM electronicas.Mantenimientos
                WHERE fecha_mantenimiento >= DATEADD(MONTH, -5,
                      DATEFROMPARTS(YEAR(GETDATE()), MONTH(GETDATE()), 1))
                GROUP BY
                    FORMAT(fecha_mantenimiento, 'MMM', 'es-GT'),
                    YEAR(fecha_mantenimiento),
                    MONTH(fecha_mantenimiento)
                ORDER BY anio, mes_num";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function repuestosPorTipo()
    {
        $sql = "SELECT
                    ISNULL(t.nombre, 'Sin tipo') AS tipo,
                    COUNT(*)                     AS total
                FROM electronicas.Repuestos r
                LEFT JOIN electronicas.TiposRepuesto t ON t.id_tipo = r.id_tipo
                WHERE r.id_estado = 1
                GROUP BY t.nombre
                ORDER BY total DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ultimosMantenimientos()
    {
        $sql = "SELECT TOP 5
                    mq.nombre                          AS maquina,
                    tm.nombre                          AS tipo,
                    ISNULL(t.nombre, 'Sin técnico')    AS tecnico,
                    FORMAT(m.fecha_mantenimiento, 'dd/MM/yyyy') AS fecha
                FROM electronicas.Mantenimientos m
                INNER JOIN electronicas.Maquinas          mq ON m.id_maquina = mq.id_maquina
                INNER JOIN electronicas.TipoMantenimiento tm ON m.id_tipo    = tm.id_tipo
                LEFT  JOIN electronicas.Tecnicos           t ON m.id_tecnico = t.id_tecnico
                ORDER BY m.fecha_mantenimiento DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function repuestosStockBajo()
    {
        $sql = "SELECT TOP 6
                    nombre,
                    stock,
                    stock_minimo,
                    (stock_minimo - stock) AS deficit
                FROM electronicas.Repuestos
                WHERE id_estado = 1
                  AND stock_minimo > 0
                  AND stock <= stock_minimo
                ORDER BY deficit DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
