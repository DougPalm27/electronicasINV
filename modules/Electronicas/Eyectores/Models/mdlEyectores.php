<?php


class mdlEyectores extends Connection
{
    private $conn;

    public function __construct()
    {
        $database = new Connection();
        $this->conn = $database->dbConnect();
    }

    // Configuración de la barra para la máquina (según su modelo). null si no aplica.
    public function obtenerConfiguracion($id_maquina)
    {
        $sql = "SELECT c.id_config, c.id_modelo, c.total, c.lado_izq, c.lado_der,
                       c.filas_izq, c.filas_der
                FROM electronicas.Maquinas mq
                INNER JOIN electronicas.ConfiguracionEyectores c
                    ON c.id_modelo = mq.id_modelo AND c.activo = 1
                WHERE mq.id_maquina = :id_maquina";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":id_maquina", $id_maquina);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listarEstados()
    {
        header('Content-Type: application/json');

        $sql = "SELECT id_estado, nombre, clase_css, color
                FROM electronicas.EstadoEyector
                ORDER BY orden";

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

    // Config + estados + eyectores de una máquina
    public function listar($id_maquina)
    {
        header('Content-Type: application/json');

        try {
            $config = $this->obtenerConfiguracion($id_maquina);

            $stmtNom = $this->conn->prepare(
                "SELECT nombre FROM electronicas.Maquinas WHERE id_maquina = :id"
            );
            $stmtNom->bindParam(":id", $id_maquina);
            $stmtNom->execute();
            $maquina = $stmtNom->fetchColumn() ?: 'Máquina';

            if (!$config) {
                echo json_encode([
                    'ok'          => true,
                    'configurado' => false,
                    'maquina'     => $maquina,
                    'eyectores'   => []
                ]);
                exit;
            }

            $estados = $this->conn
                ->query("SELECT id_estado, nombre, clase_css, color
                         FROM electronicas.EstadoEyector ORDER BY orden")
                ->fetchAll(PDO::FETCH_ASSOC);

            $sqlEy = "SELECT e.id_eyector, e.numero, e.lado, e.fila, e.posicion,
                             e.id_estado, e.observacion,
                             CONVERT(varchar, e.fecha_actualizacion, 120) AS fecha_actualizacion,
                             es.nombre AS estado, es.clase_css, es.color
                      FROM electronicas.Eyectores e
                      INNER JOIN electronicas.EstadoEyector es ON es.id_estado = e.id_estado
                      WHERE e.id_maquina = :id_maquina
                      ORDER BY e.numero";

            $stmt = $this->conn->prepare($sqlEy);
            $stmt->bindParam(":id_maquina", $id_maquina);
            $stmt->execute();
            $eyectores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok'           => true,
                'configurado'  => true,
                'inicializado' => count($eyectores) > 0,
                'maquina'      => $maquina,
                'config'       => $config,
                'estados'      => $estados,
                'eyectores'    => $eyectores
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    // Genera los eyectores de la máquina según la configuración (una sola vez)
    public function inicializarBarra($id_maquina)
    {
        header('Content-Type: application/json');

        try {
            $config = $this->obtenerConfiguracion($id_maquina);
            if (!$config) {
                echo json_encode(['ok' => false, 'mensaje' => 'El modelo de esta máquina no tiene configuración de eyectores.']);
                exit;
            }

            $chk = $this->conn->prepare(
                "SELECT COUNT(*) FROM electronicas.Eyectores WHERE id_maquina = :id"
            );
            $chk->bindParam(":id", $id_maquina);
            $chk->execute();
            if ((int) $chk->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'La barra ya fue inicializada.']);
                exit;
            }

            $this->conn->beginTransaction();

            $ins = $this->conn->prepare(
                "INSERT INTO electronicas.Eyectores
                    (id_maquina, numero, lado, fila, posicion, id_estado)
                 VALUES (:id_maquina, :numero, :lado, :fila, :posicion, 1)"
            );

            // Cada lado se numera de forma independiente: Izq 1..N y Der 1..N
            $this->generarLado($ins, $id_maquina, 'I', $config['filas_izq']);
            $this->generarLado($ins, $id_maquina, 'D', $config['filas_der']);

            $this->conn->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Barra inicializada correctamente.']);
            exit;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    private function generarLado($ins, $id_maquina, $lado, $filasCsv)
    {
        $filas = array_map('intval', explode(',', $filasCsv));
        $numero = 1; // numeración independiente por lado (1..N)
        $nFila = 1;

        foreach ($filas as $cant) {
            for ($pos = 1; $pos <= $cant; $pos++) {
                $ins->execute([
                    ':id_maquina' => $id_maquina,
                    ':numero'     => $numero,
                    ':lado'       => $lado,
                    ':fila'       => $nFila,
                    ':posicion'   => $pos
                ]);
                $numero++;
            }
            $nFila++;
        }
    }

    // Cambia el estado de un eyector y registra el historial (transacción)
    public function actualizar($id_eyector, $id_estado, $observacion, $id_usuario)
    {
        header('Content-Type: application/json');

        try {
            $q = $this->conn->prepare(
                "SELECT id_estado FROM electronicas.Eyectores WHERE id_eyector = :id"
            );
            $q->bindParam(":id", $id_eyector);
            $q->execute();
            $anterior = $q->fetchColumn();

            if ($anterior === false) {
                echo json_encode(['ok' => false, 'mensaje' => 'Eyector no encontrado.']);
                exit;
            }

            $this->conn->beginTransaction();

            $up = $this->conn->prepare(
                "UPDATE electronicas.Eyectores
                    SET id_estado = :id_estado,
                        observacion = :observacion,
                        fecha_actualizacion = GETDATE(),
                        id_usuario_actualiza = :id_usuario
                  WHERE id_eyector = :id_eyector"
            );
            $up->execute([
                ':id_estado'   => $id_estado,
                ':observacion' => $observacion !== '' ? $observacion : null,
                ':id_usuario'  => $id_usuario,
                ':id_eyector'  => $id_eyector
            ]);

            $hist = $this->conn->prepare(
                "INSERT INTO electronicas.EyectorHistorial
                    (id_eyector, id_estado_anterior, id_estado_nuevo, observacion, id_usuario)
                 VALUES (:id_eyector, :ant, :nuevo, :observacion, :id_usuario)"
            );
            $hist->execute([
                ':id_eyector'  => $id_eyector,
                ':ant'         => $anterior,
                ':nuevo'       => $id_estado,
                ':observacion' => $observacion !== '' ? $observacion : null,
                ':id_usuario'  => $id_usuario
            ]);

            $this->conn->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Eyector actualizado.']);
            exit;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    // Cambia el estado de varios eyectores a la vez (una sola transacción)
    public function actualizarLote($ids, $id_estado, $observacion, $id_usuario)
    {
        header('Content-Type: application/json');

        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (empty($ids)) {
            echo json_encode(['ok' => false, 'mensaje' => 'No se seleccionaron eyectores.']);
            exit;
        }

        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $obs = $observacion !== '' ? $observacion : null;

        try {
            // Estados actuales (para el historial)
            $sel = $this->conn->prepare(
                "SELECT id_eyector, id_estado FROM electronicas.Eyectores
                 WHERE id_eyector IN ($ph)"
            );
            $sel->execute($ids);
            $actuales = $sel->fetchAll(PDO::FETCH_KEY_PAIR); // id_eyector => id_estado

            $this->conn->beginTransaction();

            $up = $this->conn->prepare(
                "UPDATE electronicas.Eyectores
                    SET id_estado = ?,
                        observacion = ?,
                        fecha_actualizacion = GETDATE(),
                        id_usuario_actualiza = ?
                  WHERE id_eyector IN ($ph)"
            );
            $up->execute(array_merge([$id_estado, $obs, $id_usuario], $ids));

            $hist = $this->conn->prepare(
                "INSERT INTO electronicas.EyectorHistorial
                    (id_eyector, id_estado_anterior, id_estado_nuevo, observacion, id_usuario)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($ids as $id) {
                $hist->execute([$id, $actuales[$id] ?? null, $id_estado, $obs, $id_usuario]);
            }

            $this->conn->commit();
            echo json_encode(['ok' => true, 'mensaje' => 'Eyectores actualizados.', 'count' => count($ids)]);
            exit;
        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
            exit;
        }
    }
}
