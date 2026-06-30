<?php
require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlHorario.php';

$model  = new mdlHorario();
$accion = $_POST['accion'] ?? '';
$esAdmin = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';

function respH($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

function requireAdmin(): void
{
    global $esAdmin;
    if (!$esAdmin) respH([], true, 'Solo los administradores pueden realizar esta acción.');
}

try {
    switch ($accion) {

        // ── Catálogos ────────────────────────────────────────────
        case 'listarTurnos':
            respH($model->listarTurnos());
            break;

        case 'listarTecnicosMon':
            respH($model->listarTecnicosMonitoreo());
            break;

        case 'listarUsuariosDisponibles':
            requireAdmin();
            respH($model->listarUsuariosDisponibles());
            break;

        // ── Técnicos de monitoreo ─────────────────────────────────
        case 'agregarTecnico':
            requireAdmin();
            $id_usuario = (int)($_POST['id_usuario'] ?? 0);
            if (!$id_usuario) respH([], true, 'ID de usuario inválido.');
            $model->agregarTecnico($id_usuario);
            respH([], false, 'Técnico agregado correctamente.');
            break;

        case 'toggleTecnico':
            requireAdmin();
            $id = (int)($_POST['id_tecnico_mon'] ?? 0);
            if (!$id) respH([], true, 'ID inválido.');
            $model->toggleTecnico($id);
            respH([], false, 'Estado actualizado.');
            break;

        // ── Horario mensual ──────────────────────────────────────
        case 'obtenerHorarioMes':
            $mes  = (int)($_POST['mes']  ?? date('n'));
            $anio = (int)($_POST['anio'] ?? date('Y'));
            $data = $model->obtenerHorarioMes($mes, $anio);
            if (!$data) respH(null, false, 'Sin horario para ese mes.');
            respH($data);
            break;

        case 'guardarHorario':
            requireAdmin();
            $mes    = (int)($_POST['mes']    ?? 0);
            $anio   = (int)($_POST['anio']   ?? 0);
            $notas  = trim($_POST['notas']   ?? '');
            $detalle = json_decode($_POST['detalle'] ?? '[]', true);

            if (!$mes || !$anio)     respH([], true, 'Mes y año requeridos.');
            if (empty($detalle))     respH([], true, 'Debe asignar al menos un técnico.');

            $id = $model->guardarHorario($mes, $anio, $detalle, $notas ?: null);
            respH(['id_horario_mes' => $id], false, 'Borrador guardado.');
            break;

        case 'activarHorario':
            requireAdmin();
            $id = (int)($_POST['id_horario_mes'] ?? 0);
            if (!$id) respH([], true, 'ID inválido.');
            $model->activarHorario($id);
            respH([], false, 'Horario activado correctamente. Se detectaron los feriados del mes.');
            break;

        case 'eliminarBorrador':
            requireAdmin();
            $id = (int)($_POST['id_horario_mes'] ?? 0);
            if (!$id) respH([], true, 'ID inválido.');
            $model->eliminarBorrador($id);
            respH([], false, 'Borrador eliminado.');
            break;

        case 'revertirHorario':
            requireAdmin();
            $id = (int)($_POST['id_horario_mes'] ?? 0);
            if (!$id) respH([], true, 'ID inválido.');
            $model->revertirABorrador($id);
            respH([], false, 'Horario revertido a borrador. Ya puedes corregirlo y volver a activarlo.');
            break;

        case 'eliminarHorario':
            requireAdmin();
            $id = (int)($_POST['id_horario_mes'] ?? 0);
            if (!$id) respH([], true, 'ID inválido.');
            $model->eliminarHorario($id);
            respH([], false, 'Horario eliminado completamente.');
            break;

        case 'proponerRotacion':
            requireAdmin();
            $tMes  = isset($_POST['mes'])  ? (int)$_POST['mes']  : null;
            $tAnio = isset($_POST['anio']) ? (int)$_POST['anio'] : null;
            respH($model->proponerRotacion($tMes, $tAnio));
            break;

        case 'listarHistorial':
            respH($model->listarHistorial());
            break;

        // ── Feriados ─────────────────────────────────────────────
        case 'listarFeriados':
            $anio = ($_POST['anio'] ?? null) ? (int)$_POST['anio'] : null;
            respH($model->listarFeriados($anio));
            break;

        case 'guardarFeriado':
            requireAdmin();
            $id    = ($_POST['id_feriado'] ?? '') !== '' ? (int)$_POST['id_feriado'] : null;
            $fecha = trim($_POST['fecha']  ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $tipo  = trim($_POST['tipo']   ?? 'nacional');

            if (!$nombre)              respH([], true, 'El nombre es requerido.');
            if (!$id && !$fecha)       respH([], true, 'La fecha es requerida.');
            if (!in_array($tipo, ['nacional','especial'])) respH([], true, 'Tipo inválido.');

            $model->guardarFeriado($id, $fecha, $nombre, $tipo);
            respH([], false, $id ? 'Feriado actualizado.' : 'Feriado agregado.');
            break;

        case 'toggleFeriado':
            requireAdmin();
            $id = (int)($_POST['id_feriado'] ?? 0);
            if (!$id) respH([], true, 'ID inválido.');
            $model->toggleFeriado($id);
            respH([], false, 'Estado del feriado actualizado.');
            break;

        // ── Excepciones ──────────────────────────────────────────
        case 'listarExcepciones':
            $id_mes = (int)($_POST['id_horario_mes'] ?? 0);
            if (!$id_mes) respH([], true, 'ID de horario requerido.');
            respH($model->listarExcepciones($id_mes));
            break;

        case 'crearExcepcion':
            requireAdmin();
            $id_mes      = (int)($_POST['id_horario_mes'] ?? 0);
            $fecha       = trim($_POST['fecha']       ?? '');
            $tipo        = trim($_POST['tipo']        ?? 'otro');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (!$id_mes || !$fecha) respH([], true, 'Datos incompletos.');

            $id = $model->crearExcepcion([
                'id_horario_mes' => $id_mes,
                'fecha'          => $fecha,
                'tipo'           => $tipo,
                'descripcion'    => $descripcion ?: null,
                'id_usuario'     => (int)$_SESSION['id_usuario'],
            ]);
            respH(['id_excepcion' => $id], false, 'Excepción registrada.');
            break;

        case 'crearIncapacidad':
            requireAdmin();
            $id_mes     = (int)($_POST['id_horario_mes'] ?? 0);
            $id_tecnico = (int)($_POST['id_tecnico_mon'] ?? 0);
            $fecha_ini  = trim($_POST['fecha_inicio']   ?? '');
            $fecha_fin  = trim($_POST['fecha_fin']      ?? '');
            $descripcion = trim($_POST['descripcion']   ?? '');

            if (!$id_mes || !$id_tecnico || !$fecha_ini || !$fecha_fin)
                respH([], true, 'Datos incompletos.');

            $n = $model->crearIncapacidad($id_mes, $id_tecnico, $fecha_ini, $fecha_fin, $descripcion ?: null);
            respH(['dias' => $n], false, "$n día(s) de incapacidad registrados.");
            break;

        case 'resolverExcepcion':
            requireAdmin();
            $id          = (int)($_POST['id_excepcion'] ?? 0);
            $resolucion  = trim($_POST['resolucion']    ?? '');
            $notas       = trim($_POST['notas']         ?? '');
            $tecnicoRoles = json_decode($_POST['tecnico_roles'] ?? '[]', true);

            if (!$id)         respH([], true, 'ID inválido.');
            if (!$resolucion) respH([], true, 'Selecciona una resolución.');

            $valid = ['doble_pago','compensacion','cobertura','sin_accion'];
            if (!in_array($resolucion, $valid)) respH([], true, 'Resolución inválida.');

            $model->resolverExcepcion($id, $resolucion, $notas ?: null, $tecnicoRoles);
            respH([], false, 'Excepción resuelta correctamente.');
            break;

        case 'anularIncapacidad':
            requireAdmin();
            $id        = (int)($_POST['id_excepcion'] ?? 0);
            $todoGrupo = ($_POST['todo_grupo'] ?? '0') === '1';
            if (!$id) respH([], true, 'ID inválido.');
            $n   = $model->anularIncapacidad($id, $todoGrupo);
            $msg = $todoGrupo
                ? "$n día(s) de incapacidad anulado(s)."
                : 'Día de incapacidad anulado.';
            respH(['dias' => $n], false, $msg);
            break;

        case 'detectarFeriados':
            requireAdmin();
            $id_mes = (int)($_POST['id_horario_mes'] ?? 0);
            if (!$id_mes) respH([], true, 'ID inválido.');
            $model->detectarFeriadosMes($id_mes);
            respH([], false, 'Detección de feriados completada.');
            break;

        default:
            respH([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) {
    respH([], true, $e->getMessage());
}
