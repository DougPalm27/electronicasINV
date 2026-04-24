<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlDivisas.php';

$model  = new mdlDivisas();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function resp($data = [], bool $err = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$err, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        case 'listar':
            resp($model->listar());
            break;

        case 'predeterminada':
            resp($model->predeterminada());
            break;

        case 'crear':
            $nombre     = trim($_POST['nombre']      ?? '');
            $codigo     = trim($_POST['codigo']      ?? '');
            $simbolo    = trim($_POST['simbolo']     ?? '');
            $tipoCambio = (float)($_POST['tipo_cambio'] ?? 0);

            if (!$nombre || !$codigo || !$simbolo) resp([], true, 'Todos los campos son obligatorios.');
            if ($tipoCambio <= 0)                  resp([], true, 'El tipo de cambio debe ser mayor a 0.');

            $model->crear($nombre, $codigo, $simbolo, $tipoCambio);
            resp([], false, 'Divisa creada correctamente.');
            break;

        case 'actualizar':
            $id         = (int)($_POST['id_divisa']    ?? 0);
            $nombre     = trim($_POST['nombre']        ?? '');
            $simbolo    = trim($_POST['simbolo']       ?? '');
            $tipoCambio = (float)($_POST['tipo_cambio'] ?? 0);

            if (!$id || !$nombre || !$simbolo) resp([], true, 'Datos incompletos.');
            if ($tipoCambio <= 0)              resp([], true, 'El tipo de cambio debe ser mayor a 0.');

            $model->actualizar($id, $nombre, $simbolo, $tipoCambio);
            resp([], false, 'Divisa actualizada.');
            break;

        case 'setPredeterminada':
            $id = (int)($_POST['id_divisa'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->setPredeterminada($id);
            resp([], false, 'Divisa predeterminada actualizada.');
            break;

        case 'toggleActivo':
            $id = (int)($_POST['id_divisa'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->toggleActivo($id);
            resp([], false, 'Estado actualizado.');
            break;

        default:
            resp([], true, 'Acción no válida.');
    }
} catch (Throwable $e) {
    resp([], true, $e->getMessage());
}
