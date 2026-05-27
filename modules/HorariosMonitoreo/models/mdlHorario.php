<?php

class mdlHorario
{
    private PDO $conn;

    public function __construct()
    {
        require_once '../../../config/Connection.php';
        $this->conn = (new Connection())->dbConnect();
    }

    // ══════════════════════════════════════════════════════════
    // TÉCNICOS DE MONITOREO
    // ══════════════════════════════════════════════════════════

    public function listarTecnicosMonitoreo(): array
    {
        $stmt = $this->conn->query(
            "SELECT tm.id_tecnico_mon, tm.id_usuario, tm.activo,
                    FORMAT(tm.fecha_asignacion,'dd/MM/yyyy') AS fecha_asignacion,
                    u.nombre, u.username, u.email
             FROM RRHH.TecnicosMonitoreo tm
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = tm.id_usuario
             ORDER BY u.nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarUsuariosDisponibles(): array
    {
        $stmt = $this->conn->query(
            "SELECT u.id_usuario, u.nombre, u.username
             FROM electronicas.Usuarios u
             WHERE u.activo = 1
               AND u.id_usuario NOT IN (
                   SELECT id_usuario FROM RRHH.TecnicosMonitoreo WHERE activo = 1
               )
             ORDER BY u.nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarTecnico(int $id_usuario): void
    {
        $stmt = $this->conn->prepare(
            "SELECT id_tecnico_mon, activo FROM RRHH.TecnicosMonitoreo WHERE id_usuario = ?"
        );
        $stmt->execute([$id_usuario]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ((int)$existing['activo'] === 1)
                throw new RuntimeException('Este usuario ya es técnico de monitoreo.');
            $this->conn->prepare(
                "UPDATE RRHH.TecnicosMonitoreo SET activo = 1, fecha_asignacion = GETDATE() WHERE id_tecnico_mon = ?"
            )->execute([$existing['id_tecnico_mon']]);
        } else {
            $this->conn->prepare(
                "INSERT INTO RRHH.TecnicosMonitoreo (id_usuario) VALUES (?)"
            )->execute([$id_usuario]);
        }
    }

    public function toggleTecnico(int $id): void
    {
        $this->conn->prepare(
            "UPDATE RRHH.TecnicosMonitoreo SET activo = 1 - activo WHERE id_tecnico_mon = ?"
        )->execute([$id]);
    }

    // ══════════════════════════════════════════════════════════
    // TURNOS
    // ══════════════════════════════════════════════════════════

    public function listarTurnos(): array
    {
        $stmt = $this->conn->query(
            "SELECT id_turno, nombre,
                    CONVERT(VARCHAR(5), hora_inicio, 108)     AS hora_inicio,
                    CONVERT(VARCHAR(5), hora_fin, 108)        AS hora_fin,
                    dia_descanso,
                    ISNULL(CONVERT(VARCHAR(5), hora_sab_inicio, 108),
                           CONVERT(VARCHAR(5), hora_inicio, 108))  AS hora_sab_inicio,
                    ISNULL(CONVERT(VARCHAR(5), hora_sab_fin, 108),
                           CONVERT(VARCHAR(5), hora_fin, 108))     AS hora_sab_fin,
                    es_comodin, orden_rotacion
             FROM RRHH.TurnosMonitoreo
             WHERE activo = 1
             ORDER BY orden_rotacion"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════
    // HORARIO MENSUAL
    // ══════════════════════════════════════════════════════════

    public function obtenerHorarioMes(int $mes, int $anio): ?array
    {
        $stmtH = $this->conn->prepare(
            "SELECT id_horario_mes, mes, anio, estado, notas,
                    FORMAT(fecha_creacion,   'dd/MM/yyyy') AS fecha_creacion,
                    FORMAT(fecha_activacion, 'dd/MM/yyyy') AS fecha_activacion
             FROM RRHH.HorarioMes
             WHERE mes = ? AND anio = ?"
        );
        $stmtH->execute([$mes, $anio]);
        $horario = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$horario) return null;

        $id = (int)$horario['id_horario_mes'];

        $stmtD = $this->conn->prepare(
            "SELECT hmd.id_detalle, hmd.id_tecnico_mon, hmd.id_turno, hmd.meses_consecutivos,
                    u.nombre AS nombre_tecnico,
                    tm.nombre AS nombre_turno,
                    CONVERT(VARCHAR(5), tm.hora_inicio, 108)   AS hora_inicio,
                    CONVERT(VARCHAR(5), tm.hora_fin, 108)      AS hora_fin,
                    tm.dia_descanso,
                    ISNULL(CONVERT(VARCHAR(5), tm.hora_sab_inicio, 108),
                           CONVERT(VARCHAR(5), tm.hora_inicio, 108))  AS hora_sab_inicio,
                    ISNULL(CONVERT(VARCHAR(5), tm.hora_sab_fin, 108),
                           CONVERT(VARCHAR(5), tm.hora_fin, 108))     AS hora_sab_fin,
                    tm.es_comodin, tm.orden_rotacion
             FROM RRHH.HorarioMesDetalle hmd
             INNER JOIN RRHH.TecnicosMonitoreo tmon ON tmon.id_tecnico_mon = hmd.id_tecnico_mon
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = tmon.id_usuario
             INNER JOIN RRHH.TurnosMonitoreo tm ON tm.id_turno = hmd.id_turno
             WHERE hmd.id_horario_mes = ?
             ORDER BY tm.orden_rotacion"
        );
        $stmtD->execute([$id]);
        $horario['tecnicos'] = $stmtD->fetchAll(PDO::FETCH_ASSOC);

        $stmtF = $this->conn->prepare(
            "SELECT CONVERT(VARCHAR(10), fecha, 23) AS fecha, nombre, tipo
             FROM RRHH.FeriadosNacionales
             WHERE MONTH(fecha) = ? AND YEAR(fecha) = ? AND activo = 1
             ORDER BY fecha"
        );
        $stmtF->execute([$mes, $anio]);
        $horario['feriados'] = $stmtF->fetchAll(PDO::FETCH_ASSOC);

        $stmtE = $this->conn->prepare(
            "SELECT id_excepcion, CONVERT(VARCHAR(10), fecha, 23) AS fecha,
                    tipo, descripcion, resolucion, estado, notas, referencia
             FROM RRHH.ExcepcionesHorario
             WHERE id_horario_mes = ?
             ORDER BY fecha"
        );
        $stmtE->execute([$id]);
        $excepciones = $stmtE->fetchAll(PDO::FETCH_ASSOC);

        // Incluir técnicos asignados por excepción (necesario para render de turnos especiales)
        $stmtET = $this->conn->prepare(
            "SELECT et.id_tecnico_mon, et.rol, et.turno_especial, u.nombre
             FROM RRHH.ExcepcionTecnicos et
             INNER JOIN RRHH.TecnicosMonitoreo tm ON tm.id_tecnico_mon = et.id_tecnico_mon
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = tm.id_usuario
             WHERE et.id_excepcion = ?"
        );
        foreach ($excepciones as &$exc) {
            $stmtET->execute([(int)$exc['id_excepcion']]);
            $exc['tecnicos'] = $stmtET->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($exc);
        $horario['excepciones'] = $excepciones;

        return $horario;
    }

    public function guardarHorario(int $mes, int $anio, array $detalle, ?string $notas): int
    {
        $stmt = $this->conn->prepare(
            "SELECT id_horario_mes, estado FROM RRHH.HorarioMes WHERE mes = ? AND anio = ?"
        );
        $stmt->execute([$mes, $anio]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing && $existing['estado'] !== 'borrador')
            throw new RuntimeException('Solo se puede editar un horario en estado borrador.');

        $this->conn->beginTransaction();
        try {
            if ($existing) {
                $id = (int)$existing['id_horario_mes'];
                $this->conn->prepare(
                    "UPDATE RRHH.HorarioMes SET notas = ? WHERE id_horario_mes = ?"
                )->execute([$notas, $id]);
                $this->conn->prepare(
                    "DELETE FROM RRHH.HorarioMesDetalle WHERE id_horario_mes = ?"
                )->execute([$id]);
            } else {
                $stmtI = $this->conn->prepare(
                    "INSERT INTO RRHH.HorarioMes (mes, anio, notas)
                     OUTPUT INSERTED.id_horario_mes VALUES (?, ?, ?)"
                );
                $stmtI->execute([$mes, $anio, $notas]);
                $id = (int)$stmtI->fetchColumn();
            }

            $stmtDet = $this->conn->prepare(
                "INSERT INTO RRHH.HorarioMesDetalle
                    (id_horario_mes, id_tecnico_mon, id_turno, meses_consecutivos)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($detalle as $d) {
                $stmtDet->execute([
                    $id,
                    (int)$d['id_tecnico_mon'],
                    (int)$d['id_turno'],
                    (int)($d['meses_consecutivos'] ?? 1)
                ]);
            }

            $this->conn->commit();
            return $id;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function activarHorario(int $id_horario_mes): void
    {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "SELECT estado FROM RRHH.HorarioMes WHERE id_horario_mes = ?"
            );
            $stmt->execute([$id_horario_mes]);
            $h = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$h) throw new RuntimeException('Horario no encontrado.');
            if ($h['estado'] !== 'borrador')
                throw new RuntimeException('Solo se puede activar un horario en estado borrador.');

            $this->conn->prepare(
                "UPDATE RRHH.HorarioMes SET estado = 'archivado'
                 WHERE estado = 'activo' AND id_horario_mes <> ?"
            )->execute([$id_horario_mes]);

            $this->conn->prepare(
                "UPDATE RRHH.HorarioMes
                 SET estado = 'activo', fecha_activacion = GETDATE()
                 WHERE id_horario_mes = ?"
            )->execute([$id_horario_mes]);

            $this->conn->commit();
            $this->detectarFeriadosMes($id_horario_mes);
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function eliminarBorrador(int $id_horario_mes): void
    {
        $stmt = $this->conn->prepare(
            "SELECT estado FROM RRHH.HorarioMes WHERE id_horario_mes = ?"
        );
        $stmt->execute([$id_horario_mes]);
        $h = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$h || $h['estado'] !== 'borrador')
            throw new RuntimeException('Solo se puede eliminar un borrador.');

        $this->conn->prepare(
            "DELETE FROM RRHH.HorarioMesDetalle WHERE id_horario_mes = ?"
        )->execute([$id_horario_mes]);
        $this->conn->prepare(
            "DELETE FROM RRHH.HorarioMes WHERE id_horario_mes = ?"
        )->execute([$id_horario_mes]);
    }

    public function proponerRotacion(?int $targetMes = null, ?int $targetAnio = null): array
    {
        if ($targetMes === null || $targetAnio === null) {
            $hoy = new DateTime();
            $targetMes  = (int)$hoy->format('n') + 1;
            $targetAnio = (int)$hoy->format('Y');
            if ($targetMes > 12) { $targetMes = 1; $targetAnio++; }
        }

        // Buscar el último horario confirmado anterior al mes objetivo como referencia de rotación
        $stmt = $this->conn->prepare(
            "SELECT TOP 1 id_horario_mes, mes, anio
             FROM RRHH.HorarioMes
             WHERE estado IN ('activo','archivado')
               AND (anio < ? OR (anio = ? AND mes < ?))
             ORDER BY anio DESC, mes DESC"
        );
        $stmt->execute([$targetAnio, $targetAnio, $targetMes]);
        $ref = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmtT = $this->conn->query(
            "SELECT id_turno, nombre, orden_rotacion FROM RRHH.TurnosMonitoreo WHERE activo = 1 ORDER BY orden_rotacion"
        );
        $turnosPorOrden = [];
        foreach ($stmtT->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $turnosPorOrden[(int)$t['orden_rotacion']] = $t;
        }

        $stmtTec = $this->conn->query(
            "SELECT tm.id_tecnico_mon, u.nombre
             FROM RRHH.TecnicosMonitoreo tm
             INNER JOIN electronicas.Usuarios u ON u.id_usuario = tm.id_usuario
             WHERE tm.activo = 1
             ORDER BY u.nombre"
        );
        $tecnicos = $stmtTec->fetchAll(PDO::FETCH_ASSOC);

        $propuesta = [];

        if ($ref) {
            $stmtD = $this->conn->prepare(
                "SELECT hmd.id_tecnico_mon, hmd.id_turno, hmd.meses_consecutivos,
                        tm.orden_rotacion
                 FROM RRHH.HorarioMesDetalle hmd
                 INNER JOIN RRHH.TurnosMonitoreo tm ON tm.id_turno = hmd.id_turno
                 WHERE hmd.id_horario_mes = ?"
            );
            $stmtD->execute([$ref['id_horario_mes']]);
            $asignActuales = [];
            foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $asignActuales[(int)$a['id_tecnico_mon']] = $a;
            }

            foreach ($tecnicos as $tec) {
                $id   = (int)$tec['id_tecnico_mon'];
                $curr = $asignActuales[$id] ?? null;

                if ($curr) {
                    $currOrden   = (int)$curr['orden_rotacion'];
                    $nextOrden   = ($currOrden % 4) + 1;
                    $nextTurno   = $turnosPorOrden[$nextOrden];
                    $mesConsecut = (int)$curr['id_turno'] === (int)$nextTurno['id_turno']
                        ? (int)$curr['meses_consecutivos'] + 1
                        : 1;
                    $propuesta[] = [
                        'id_tecnico_mon'      => $id,
                        'nombre'              => $tec['nombre'],
                        'id_turno_actual'     => (int)$curr['id_turno'],
                        'nombre_turno_actual' => $turnosPorOrden[$currOrden]['nombre'] ?? '—',
                        'orden_actual'        => $currOrden,
                        'id_turno'            => (int)$nextTurno['id_turno'],
                        'nombre_turno'        => $nextTurno['nombre'],
                        'meses_consecutivos'  => $mesConsecut,
                    ];
                } else {
                    $defaultTurno = $turnosPorOrden[1];
                    $propuesta[]  = [
                        'id_tecnico_mon'      => $id,
                        'nombre'              => $tec['nombre'],
                        'id_turno_actual'     => null,
                        'nombre_turno_actual' => 'Sin asignación',
                        'orden_actual'        => null,
                        'id_turno'            => (int)$defaultTurno['id_turno'],
                        'nombre_turno'        => $defaultTurno['nombre'],
                        'meses_consecutivos'  => 1,
                    ];
                }
            }
        } else {
            $i = 1;
            foreach ($tecnicos as $tec) {
                $orden       = (($i - 1) % 4) + 1;
                $turnoAsig   = $turnosPorOrden[$orden];
                $propuesta[] = [
                    'id_tecnico_mon'      => (int)$tec['id_tecnico_mon'],
                    'nombre'              => $tec['nombre'],
                    'id_turno_actual'     => null,
                    'nombre_turno_actual' => 'Sin historial',
                    'orden_actual'        => null,
                    'id_turno'            => (int)$turnoAsig['id_turno'],
                    'nombre_turno'        => $turnoAsig['nombre'],
                    'meses_consecutivos'  => 1,
                ];
                $i++;
            }
        }

        return [
            'propuesta'   => $propuesta,
            'target_mes'  => $targetMes,
            'target_anio' => $targetAnio,
            'turnos'      => array_values($turnosPorOrden),
        ];
    }

    public function listarHistorial(): array
    {
        $stmt = $this->conn->query(
            "SELECT hm.id_horario_mes, hm.mes, hm.anio, hm.estado,
                    FORMAT(hm.fecha_activacion,'dd/MM/yyyy') AS fecha_activacion,
                    FORMAT(hm.fecha_creacion,  'dd/MM/yyyy') AS fecha_creacion,
                    COUNT(hmd.id_detalle) AS total_tecnicos
             FROM RRHH.HorarioMes hm
             LEFT JOIN RRHH.HorarioMesDetalle hmd ON hmd.id_horario_mes = hm.id_horario_mes
             GROUP BY hm.id_horario_mes, hm.mes, hm.anio, hm.estado,
                      hm.fecha_activacion, hm.fecha_creacion
             ORDER BY hm.anio DESC, hm.mes DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════
    // FERIADOS
    // ══════════════════════════════════════════════════════════

    public function listarFeriados(?int $anio = null): array
    {
        $where = $anio ? "WHERE YEAR(fecha) = $anio" : '';
        $stmt  = $this->conn->query(
            "SELECT id_feriado, CONVERT(VARCHAR(10), fecha, 23) AS fecha,
                    nombre, tipo, activo
             FROM RRHH.FeriadosNacionales
             $where
             ORDER BY fecha"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarFeriado(?int $id, string $fecha, string $nombre, string $tipo): void
    {
        if ($id) {
            $this->conn->prepare(
                "UPDATE RRHH.FeriadosNacionales SET nombre = ?, tipo = ? WHERE id_feriado = ?"
            )->execute([$nombre, $tipo, $id]);
        } else {
            $this->conn->prepare(
                "INSERT INTO RRHH.FeriadosNacionales (fecha, nombre, tipo) VALUES (?, ?, ?)"
            )->execute([$fecha, $nombre, $tipo]);
        }
    }

    public function toggleFeriado(int $id): void
    {
        $this->conn->prepare(
            "UPDATE RRHH.FeriadosNacionales SET activo = 1 - activo WHERE id_feriado = ?"
        )->execute([$id]);
    }

    // ══════════════════════════════════════════════════════════
    // EXCEPCIONES
    // ══════════════════════════════════════════════════════════

    public function listarExcepciones(int $id_horario_mes): array
    {
        $stmt = $this->conn->prepare(
            "SELECT e.id_excepcion,
                    CONVERT(VARCHAR(10), e.fecha, 23) AS fecha,
                    e.tipo, e.descripcion, e.resolucion, e.estado, e.notas,
                    FORMAT(e.fecha_registro,'dd/MM/yyyy HH:mm') AS fecha_registro,
                    u.nombre AS registrado_por
             FROM RRHH.ExcepcionesHorario e
             LEFT JOIN electronicas.Usuarios u ON u.id_usuario = e.id_usuario_registro
             WHERE e.id_horario_mes = ?
             ORDER BY e.fecha"
        );
        $stmt->execute([$id_horario_mes]);
        $excepciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($excepciones as &$exc) {
            $stmtT = $this->conn->prepare(
                "SELECT et.rol, et.turno_especial, u.nombre
                 FROM RRHH.ExcepcionTecnicos et
                 INNER JOIN RRHH.TecnicosMonitoreo tm ON tm.id_tecnico_mon = et.id_tecnico_mon
                 INNER JOIN electronicas.Usuarios u ON u.id_usuario = tm.id_usuario
                 WHERE et.id_excepcion = ?"
            );
            $stmtT->execute([$exc['id_excepcion']]);
            $exc['tecnicos'] = $stmtT->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($exc);

        return $excepciones;
    }

    public function crearExcepcion(array $d): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO RRHH.ExcepcionesHorario
                (id_horario_mes, fecha, tipo, descripcion, id_usuario_registro)
             OUTPUT INSERTED.id_excepcion
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$d['id_horario_mes'],
            $d['fecha'],
            $d['tipo'],
            $d['descripcion'] ?? null,
            $d['id_usuario']
        ]);
        return (int)$stmt->fetchColumn();
    }

    public function resolverExcepcion(int $id, string $resolucion, ?string $notas, array $tecnicoRoles): void
    {
        $this->conn->beginTransaction();
        try {
            $this->conn->prepare(
                "UPDATE RRHH.ExcepcionesHorario
                 SET resolucion = ?, estado = 'resuelta', notas = ?
                 WHERE id_excepcion = ?"
            )->execute([$resolucion, $notas, $id]);

            $this->conn->prepare(
                "DELETE FROM RRHH.ExcepcionTecnicos WHERE id_excepcion = ?"
            )->execute([$id]);

            if (!empty($tecnicoRoles)) {
                $stmtR = $this->conn->prepare(
                    "INSERT INTO RRHH.ExcepcionTecnicos
                        (id_excepcion, id_tecnico_mon, rol, turno_especial)
                     VALUES (?, ?, ?, ?)"
                );
                foreach ($tecnicoRoles as $tr) {
                    $stmtR->execute([
                        $id,
                        (int)$tr['id_tecnico_mon'],
                        $tr['rol'],
                        $tr['turno_especial'] ?? null
                    ]);
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function crearIncapacidad(int $id_horario_mes, int $id_tecnico_mon, string $fecha_inicio, string $fecha_fin, ?string $descripcion): int
    {
        $stmt = $this->conn->prepare("SELECT id_horario_mes FROM RRHH.HorarioMes WHERE id_horario_mes = ?");
        $stmt->execute([$id_horario_mes]);
        if (!$stmt->fetch()) throw new RuntimeException('Horario no encontrado.');

        $ini = new DateTime($fecha_inicio);
        $fin = new DateTime($fecha_fin);
        if ($ini > $fin) throw new RuntimeException('La fecha inicio debe ser anterior o igual a la fecha fin.');

        $stmtCheck = $this->conn->prepare(
            "SELECT id_excepcion FROM RRHH.ExcepcionesHorario WHERE id_horario_mes = ? AND fecha = ?"
        );
        $referencia = 'INC' . uniqid();

        $stmtIns = $this->conn->prepare(
            "INSERT INTO RRHH.ExcepcionesHorario
                (id_horario_mes, fecha, tipo, descripcion, estado, referencia)
             OUTPUT INSERTED.id_excepcion
             VALUES (?, ?, 'incapacidad', ?, 'pendiente', ?)"
        );
        $stmtTec = $this->conn->prepare(
            "INSERT INTO RRHH.ExcepcionTecnicos (id_excepcion, id_tecnico_mon, rol, turno_especial)
             VALUES (?, ?, 'ausente', NULL)"
        );

        $count = 0;
        $this->conn->beginTransaction();
        try {
            $current = clone $ini;
            while ($current <= $fin) {
                $fechaStr = $current->format('Y-m-d');
                $stmtCheck->execute([$id_horario_mes, $fechaStr]);
                if (!$stmtCheck->fetch()) {
                    $stmtIns->execute([$id_horario_mes, $fechaStr, $descripcion, $referencia]);
                    $idExc = (int)$stmtIns->fetchColumn();
                    $stmtTec->execute([$idExc, $id_tecnico_mon]);
                    $count++;
                }
                $current->modify('+1 day');
            }
            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
        return $count;
    }

    public function detectarFeriadosMes(int $id_horario_mes): void
    {
        $stmtH = $this->conn->prepare(
            "SELECT mes, anio FROM RRHH.HorarioMes WHERE id_horario_mes = ?"
        );
        $stmtH->execute([$id_horario_mes]);
        $h = $stmtH->fetch(PDO::FETCH_ASSOC);
        if (!$h) return;

        $mes  = (int)$h['mes'];
        $anio = (int)$h['anio'];

        $stmtF = $this->conn->prepare(
            "SELECT CONVERT(VARCHAR(10), fecha, 23) AS fecha, nombre
             FROM RRHH.FeriadosNacionales
             WHERE MONTH(fecha) = ? AND YEAR(fecha) = ? AND activo = 1"
        );
        $stmtF->execute([$mes, $anio]);
        $feriados = $stmtF->fetchAll(PDO::FETCH_ASSOC);
        if (empty($feriados)) return;

        $stmtD = $this->conn->prepare(
            "SELECT tm.dia_descanso
             FROM RRHH.HorarioMesDetalle hmd
             INNER JOIN RRHH.TurnosMonitoreo tm ON tm.id_turno = hmd.id_turno
             WHERE hmd.id_horario_mes = ?"
        );
        $stmtD->execute([$id_horario_mes]);
        $diasDescanso = array_column($stmtD->fetchAll(PDO::FETCH_ASSOC), 'dia_descanso');

        $stmtCheck = $this->conn->prepare(
            "SELECT COUNT(1) FROM RRHH.ExcepcionesHorario
             WHERE id_horario_mes = ? AND fecha = ?"
        );
        $stmtIns = $this->conn->prepare(
            "INSERT INTO RRHH.ExcepcionesHorario
                (id_horario_mes, fecha, tipo, descripcion, estado)
             VALUES (?, ?, 'feriado', ?, 'pendiente')"
        );

        foreach ($feriados as $f) {
            $stmtCheck->execute([$id_horario_mes, $f['fecha']]);
            if ((int)$stmtCheck->fetchColumn() > 0) continue;

            $diaSem = (int)(new DateTime($f['fecha']))->format('w');

            $algunoTrabaja = false;
            foreach ($diasDescanso as $dd) {
                if ((int)$dd !== $diaSem) { $algunoTrabaja = true; break; }
            }

            if ($algunoTrabaja) {
                $stmtIns->execute([
                    $id_horario_mes,
                    $f['fecha'],
                    'Feriado: ' . $f['nombre']
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────────────────
    // Anula uno o todos los días de una incapacidad (por referencia)
    // ──────────────────────────────────────────────────────────
    public function anularIncapacidad(int $id_excepcion, bool $todoGrupo): int
    {
        $this->conn->beginTransaction();
        try {
            if ($todoGrupo) {
                $stmtRef = $this->conn->prepare(
                    "SELECT referencia, id_horario_mes
                     FROM RRHH.ExcepcionesHorario WHERE id_excepcion = ?"
                );
                $stmtRef->execute([$id_excepcion]);
                $row = $stmtRef->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException('Excepción no encontrada.');

                $referencia     = $row['referencia'];
                $id_horario_mes = (int)$row['id_horario_mes'];

                if ($referencia) {
                    $this->conn->prepare(
                        "DELETE FROM RRHH.ExcepcionTecnicos
                         WHERE id_excepcion IN (
                             SELECT id_excepcion FROM RRHH.ExcepcionesHorario
                             WHERE referencia = ? AND id_horario_mes = ?
                         )"
                    )->execute([$referencia, $id_horario_mes]);

                    $stmtDel = $this->conn->prepare(
                        "DELETE FROM RRHH.ExcepcionesHorario
                         WHERE referencia = ? AND id_horario_mes = ?"
                    );
                    $stmtDel->execute([$referencia, $id_horario_mes]);
                    $count = (int)$stmtDel->rowCount();
                } else {
                    // Sin referencia (registros anteriores): borrar solo este día
                    $this->conn->prepare(
                        "DELETE FROM RRHH.ExcepcionTecnicos WHERE id_excepcion = ?"
                    )->execute([$id_excepcion]);
                    $this->conn->prepare(
                        "DELETE FROM RRHH.ExcepcionesHorario WHERE id_excepcion = ?"
                    )->execute([$id_excepcion]);
                    $count = 1;
                }
            } else {
                // Solo este día
                $this->conn->prepare(
                    "DELETE FROM RRHH.ExcepcionTecnicos WHERE id_excepcion = ?"
                )->execute([$id_excepcion]);
                $stmtDel = $this->conn->prepare(
                    "DELETE FROM RRHH.ExcepcionesHorario WHERE id_excepcion = ?"
                );
                $stmtDel->execute([$id_excepcion]);
                $count = (int)$stmtDel->rowCount();
            }

            $this->conn->commit();
            return $count;
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
