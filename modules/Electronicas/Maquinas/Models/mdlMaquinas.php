<?php


class mdlMaquinas extends Connection
{
    private $conn;

    public function __construct()
    {
        $database = new Connection();
        $this->conn = $database->dbConnect();
    }

    // Nombre de la estación del candado (la PC de esta máquina). Vacío = sin estación (NULL),
    // porque el índice único filtrado solo ignora NULL, no cadenas vacías.
    private function normalizarEstacion($losDatos)
    {
        $v = trim((string) ($losDatos->estacion_candado ?? ''));
        return $v === '' ? null : $v;
    }

    // Valida costo, año y estación del candado; devuelve un mensaje de error o null si todo está bien.
    private function validarDatos($losDatos)
    {
        if ((float) ($losDatos->costo ?? 0) < 0) {
            return "El costo no puede ser negativo.";
        }

        $anio = trim((string) ($losDatos->anio ?? ''));
        if ($anio !== '') {
            $anioNum = (int) $anio;
            $maxAnio = (int) date('Y') + 1;
            if ($anioNum < 1900 || $anioNum > $maxAnio) {
                return "El año de fabricación debe estar entre 1900 y $maxAnio.";
            }
        }

        // Mismo formato que acepta el endpoint del candado (kioscoController.php)
        $estacion = $this->normalizarEstacion($losDatos);
        if ($estacion !== null) {
            if (!preg_match('/^[\p{L}\p{N} _.\-]{1,100}$/u', $estacion)) {
                return "La estación del candado solo admite letras, números, espacios, punto, guion y guion bajo (máximo 100).";
            }
            $stmt = $this->conn->prepare(
                "SELECT nombre FROM electronicas.Maquinas
                 WHERE estacion_candado = ? AND id_maquina <> ?"
            );
            $stmt->execute([$estacion, (int) ($losDatos->id_maquina ?? 0)]);
            $otra = $stmt->fetchColumn();
            if ($otra !== false) {
                // Llaves obligatorias: PHP toma los caracteres no ASCII (como ») como parte del nombre de la variable
                return "La estación «{$estacion}» ya está asignada a la máquina «{$otra}».";
            }
        }

        return null;
    }

    // Al asignar una estación a una máquina, liga los turnos que esa estación ya tenía
    // registrados sin máquina. Es un extra: si falla, la máquina ya quedó guardada.
    private function ligarTurnosPrevios($estacion)
    {
        if ($estacion === null) {
            return;
        }
        try {
            $this->conn->prepare(
                "UPDATE ts
                    SET ts.id_maquina = m.id_maquina
                   FROM electronicas.TurnoSesiones ts
                  INNER JOIN electronicas.Maquinas m ON m.estacion_candado = ts.estacion
                  WHERE ts.id_maquina IS NULL AND m.estacion_candado = ?"
            )->execute([$estacion]);
        } catch (PDOException $e) {
            error_log('ligarTurnosPrevios: ' . $e->getMessage());
        }
    }

    public function guardarMaquina($losDatos)
    {
        header('Content-Type: application/json');

        $error = $this->validarDatos($losDatos);
        if ($error !== null) {
            echo json_encode([["status" => "400", "mensaje" => $error]]);
            exit;
        }

        $sql = "INSERT INTO electronicas.Maquinas
            (
                nombre,
                id_modelo,
                serie,
                comentarios,
                id_estado,
                costo,
                anio,
                ubicacion,
                estacion_candado
            )
            VALUES
            (
                :nombre,
                :id_modelo,
                :serie,
                :comentarios,
                :id_estado,
                :costo,
                :anio,
                :ubicacion,
                :estacion_candado
            )";

        $estacion = $this->normalizarEstacion($losDatos);

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":nombre", $losDatos->nombre);
        $stmt->bindParam(":id_modelo", $losDatos->id_modelo);
        $stmt->bindParam(":serie", $losDatos->serie);
        $stmt->bindParam(":comentarios", $losDatos->comentarios);
        $stmt->bindParam(":id_estado", $losDatos->id_estado);
        $stmt->bindParam(":costo", $losDatos->costo);
        $stmt->bindParam(":anio", $losDatos->anio);
        $stmt->bindParam(":ubicacion", $losDatos->ubicacion);
        $stmt->bindValue(":estacion_candado", $estacion, $estacion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        try {
            $stmt->execute();
            $this->ligarTurnosPrevios($estacion);
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina registrada correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function editarMaquina($losDatos)
    {
        header('Content-Type: application/json');

        $error = $this->validarDatos($losDatos);
        if ($error !== null) {
            echo json_encode([["status" => "400", "mensaje" => $error]]);
            exit;
        }

        $sql = "UPDATE electronicas.Maquinas
            SET nombre = :nombre,
                id_modelo = :id_modelo,
                serie = :serie,
                comentarios = :comentarios,
                id_estado = :id_estado,
                costo = :costo,
                anio = :anio,
                ubicacion = :ubicacion,
                estacion_candado = :estacion_candado
            WHERE id_maquina = :id_maquina";

        $estacion = $this->normalizarEstacion($losDatos);

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $losDatos->id_maquina);
        $stmt->bindParam(":nombre", $losDatos->nombre);
        $stmt->bindParam(":id_modelo", $losDatos->id_modelo);
        $stmt->bindParam(":serie", $losDatos->serie);
        $stmt->bindParam(":comentarios", $losDatos->comentarios);
        $stmt->bindParam(":id_estado", $losDatos->id_estado);
        $stmt->bindParam(":costo", $losDatos->costo);
        $stmt->bindParam(":anio", $losDatos->anio);
        $stmt->bindParam(":ubicacion", $losDatos->ubicacion);
        $stmt->bindValue(":estacion_candado", $estacion, $estacion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

        try {
            $stmt->execute();
            $this->ligarTurnosPrevios($estacion);
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina actualizada correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function listarMaquinas()
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                mq.id_maquina,
                mq.nombre,
                mq.serie,
                mq.comentarios,
                mq.id_modelo,
                mq.id_estado,
                mq.costo,
                mq.anio,
                mq.ubicacion,
                mo.nombre AS modelo,
                mo.imagen AS modelo_imagen,
                tm.nombre AS tipo_modelo,
                ma.nombre AS marca,
                em.nombre AS estado
            FROM electronicas.Maquinas mq
            INNER JOIN electronicas.Modelos mo
                ON mq.id_modelo = mo.id_modelo
            INNER JOIN electronicas.Marcas ma
                ON mo.id_marca = ma.id_marca
            INNER JOIN electronicas.EstadoMaquina em
                ON mq.id_estado = em.id_estado
            LEFT JOIN electronicas.TiposModelo tm
                ON mo.id_tipo_modelo = tm.id_tipo_modelo
            ORDER BY mq.id_maquina DESC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function obtenerMaquina($id_maquina)
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                mq.id_maquina,
                mq.nombre,
                mq.id_modelo,
                mo.id_marca,
                mq.serie,
                mq.comentarios,
                mq.id_estado,
                mq.costo,
                mq.anio,
                mq.ubicacion,
                mq.estacion_candado,
                cd.equipo_nombre AS candado_equipo,
                cd.version AS candado_version,
                FORMAT(cd.fecha_instalacion, 'dd/MM/yyyy HH:mm') AS candado_fecha
            FROM electronicas.Maquinas mq
            INNER JOIN electronicas.Modelos mo
                ON mq.id_modelo = mo.id_modelo
            LEFT JOIN electronicas.MaquinaCandado cd
                ON cd.id_maquina = mq.id_maquina
            WHERE mq.id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }
    // Suelta la máquina del candado instalado en una PC, para poder elegirla desde otra
    // (por ejemplo al cambiar la PC de la máquina). No toca la PC vieja: su candado sigue
    // funcionando con su configuración hasta que se desinstale.
    public function liberarCandado($id_maquina)
    {
        header('Content-Type: application/json');

        $stmt = $this->conn->prepare(
            "DELETE FROM electronicas.MaquinaCandado WHERE id_maquina = :id_maquina"
        );
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Máquina liberada. Ya se puede elegir en el instalador de otra PC."]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(["error" => $e->getMessage()]);
            exit;
        }
    }

    public function cambiarEstadoMaquina($id_maquina, $id_estado)
    {
        header('Content-Type: application/json');

        $sql = "UPDATE electronicas.Maquinas
                SET id_estado = :id_estado
                WHERE id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);
        $stmt->bindParam(":id_estado", $id_estado);

        try {
            $stmt->execute();
            echo json_encode([
                ["status" => "200", "mensaje" => "Estado actualizado correctamente"]
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarMarcas()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_marca, nombre
                FROM electronicas.Marcas
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarModelos($id_marca)
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_modelo, nombre
                FROM electronicas.Modelos
                WHERE id_marca = :id_marca
                ORDER BY nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_marca", $id_marca);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }

    public function listarEstadosMaquina()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_estado, nombre
                FROM electronicas.EstadoMaquina
                ORDER BY id_estado ASC";

        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }
    public function obtenerRepuestosMaquina($id_maquina)
    {
        header('Content-Type: application/json');

        $sql = "SELECT
                r.id_repuesto,
                r.nombre,
                r.costo,
                ISNULL(dv.simbolo, 'L.') AS divisa_simbolo,
                e.nombre AS estado,
                FORMAT(MAX(m.fecha_mantenimiento), 'dd/MM/yyyy') AS ultima_fecha
            FROM electronicas.Repuestos r
            INNER JOIN electronicas.EstadoRepuestos e
                ON r.id_estado = e.id_estado
            LEFT JOIN electronicas.Divisas dv
                ON dv.id_divisa = r.id_divisa
            LEFT JOIN electronicas.MantenimientoRepuestos mr
                ON r.id_repuesto = mr.id_repuesto
            LEFT JOIN electronicas.Mantenimientos m
                ON mr.id_mantenimiento = m.id_mantenimiento
               AND m.anulado = 0
            WHERE r.id_maquina_actual = :id_maquina
            GROUP BY
                r.id_repuesto,
                r.nombre,
                r.costo,
                dv.simbolo,
                e.nombre
            ORDER BY r.nombre";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);

        try {
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($data);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                "error" => $e->getMessage()
            ]);
            exit;
        }
    }
}
