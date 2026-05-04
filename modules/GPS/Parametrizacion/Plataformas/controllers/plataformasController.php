<?php
require_once '../../../../../config/auth.php';
requireLogin(true);
header('Content-Type: application/json');
include_once '../models/mdlPlataformas.php';

$model  = new mdlPlataformas();
$accion = $_POST['accion'] ?? '';
$uid    = (int)($_SESSION['id_usuario'] ?? 0);

function respPL($data = [], bool $error = false, string $msg = ''): void {
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]); exit;
}

try {
    switch ($accion) {
        case 'listar':
            respPL($model->listar());
            break;
        case 'listarActivas':
            respPL($model->listarActivas());
            break;
        case 'crear':
            $nombre   = trim($_POST['nombre']   ?? '');
            $url_base = trim($_POST['url_base'] ?? '');
            if (!$nombre) respPL([], true, 'El nombre es requerido.');
            $id = $model->crear($nombre, $url_base, $uid);
            respPL(['id' => $id], false, "Plataforma '$nombre' creada.");
            break;
        case 'editar':
            $id       = (int)($_POST['id_plataforma'] ?? 0);
            $nombre   = trim($_POST['nombre']         ?? '');
            $url_base = trim($_POST['url_base']       ?? '');
            if (!$id)     respPL([], true, 'ID inválido.');
            if (!$nombre) respPL([], true, 'El nombre es requerido.');
            $model->editar($id, $nombre, $url_base);
            respPL([], false, 'Plataforma actualizada.');
            break;
        case 'toggleActivo':
            $id = (int)($_POST['id_plataforma'] ?? 0);
            if (!$id) respPL([], true, 'ID inválido.');
            $model->toggleActivo($id);
            respPL([], false, 'Estado actualizado.');
            break;
        default:
            respPL([], true, "Acción no válida: $accion");
    }
} catch (Throwable $e) { respPL([], true, $e->getMessage()); }
