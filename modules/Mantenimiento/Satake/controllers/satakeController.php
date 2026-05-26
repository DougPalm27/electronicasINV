<?php
ob_start();

require_once '../../../../config/auth.php';
requireLogin(true);

ob_clean();
header('Content-Type: application/json');

$accion  = $_POST['accion'] ?? $_GET['accion'] ?? '';
$esAdmin = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';

function resp($data = [], $error = false, $msg = ''): void
{
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    include_once '../models/mdlSatake.php';
    $model = new mdlSatake();
    switch ($accion) {

        // ── Catálogos ─────────────────────────────────────────
        case 'listarFrecuencias':
            resp($model->listarFrecuencias());
            break;

        case 'listarCategorias':
            resp($model->listarCategorias());
            break;

        // ── Tareas ────────────────────────────────────────────
        case 'listarTareas':
            $idFrecuencia = (int)($_POST['id_frecuencia'] ?? 0);
            $idCategoria  = (int)($_POST['id_categoria']  ?? 0);
            resp($model->listarTareas($idFrecuencia, $idCategoria));
            break;

        case 'obtenerTarea':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $t = $model->obtenerTarea($id);
            if (!$t) resp([], true, 'Tarea no encontrada.');
            resp($t);
            break;

        case 'guardarTarea':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $d = [
                'nombre'        => trim($_POST['nombre']        ?? ''),
                'id_frecuencia' => (int)($_POST['id_frecuencia'] ?? 0),
                'id_categoria'  => (int)($_POST['id_categoria']  ?? 0),
                'por_que'       => trim($_POST['por_que']       ?? ''),
                'si_no_se_hace' => trim($_POST['si_no_se_hace'] ?? ''),
                'metodo'        => trim($_POST['metodo']        ?? '') ?: null,
                'solo_tecnico'  => (int)($_POST['solo_tecnico'] ?? 0),
            ];
            if (!$d['nombre'] || !$d['id_frecuencia'] || !$d['id_categoria']
                || !$d['por_que'] || !$d['si_no_se_hace']) {
                resp([], true, 'Completa todos los campos requeridos.');
            }
            $id = $model->guardarTarea($d);
            resp(['id_tarea' => $id], false, 'Tarea creada correctamente.');
            break;

        case 'actualizarTarea':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $d = [
                'nombre'        => trim($_POST['nombre']        ?? ''),
                'id_frecuencia' => (int)($_POST['id_frecuencia'] ?? 0),
                'id_categoria'  => (int)($_POST['id_categoria']  ?? 0),
                'por_que'       => trim($_POST['por_que']       ?? ''),
                'si_no_se_hace' => trim($_POST['si_no_se_hace'] ?? ''),
                'metodo'        => trim($_POST['metodo']        ?? '') ?: null,
                'solo_tecnico'  => (int)($_POST['solo_tecnico'] ?? 0),
            ];
            if (!$d['nombre'] || !$d['id_frecuencia'] || !$d['id_categoria']
                || !$d['por_que'] || !$d['si_no_se_hace']) {
                resp([], true, 'Completa todos los campos requeridos.');
            }
            $model->actualizarTarea($id, $d);
            resp([], false, 'Tarea actualizada correctamente.');
            break;

        case 'toggleActivoTarea':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->toggleActivoTarea($id);
            resp([], false, 'Estado actualizado.');
            break;

        // ── Alertas ───────────────────────────────────────────
        case 'listarAlertas':
            resp($model->listarAlertas());
            break;

        case 'obtenerAlerta':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $a = $model->obtenerAlerta($id);
            if (!$a) resp([], true, 'Alerta no encontrada.');
            resp($a);
            break;

        case 'guardarAlerta':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $d = [
                'senal'  => trim($_POST['senal']         ?? ''),
                'causa'  => trim($_POST['causa']         ?? ''),
                'accion' => trim($_POST['accion_alerta'] ?? ''),
            ];
            if (!$d['senal'] || !$d['causa'] || !$d['accion']) {
                resp([], true, 'Completa todos los campos requeridos.');
            }
            $id = $model->guardarAlerta($d);
            resp(['id_alerta' => $id], false, 'Alerta creada correctamente.');
            break;

        case 'actualizarAlerta':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $d = [
                'senal'  => trim($_POST['senal']         ?? ''),
                'causa'  => trim($_POST['causa']         ?? ''),
                'accion' => trim($_POST['accion_alerta'] ?? ''),
            ];
            if (!$d['senal'] || !$d['causa'] || !$d['accion']) {
                resp([], true, 'Completa todos los campos requeridos.');
            }
            $model->actualizarAlerta($id, $d);
            resp([], false, 'Alerta actualizada correctamente.');
            break;

        case 'toggleActivoAlerta':
            if (!$esAdmin) resp([], true, 'Sin permisos.');
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->toggleActivoAlerta($id);
            resp([], false, 'Estado actualizado.');
            break;

        default:
            resp([], true, "Acción no válida: $accion");
    }

} catch (Throwable $e) {
    resp([], true, $e->getMessage());
}
