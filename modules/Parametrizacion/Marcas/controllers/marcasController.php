<?php
require_once '../../../../config/auth.php';
requireLogin(true);


header('Content-Type: application/json');

include_once "../models/mdlMarcas.php";

$model  = new mdlMarcas();
$accion = $_POST['accion'] ?? '';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode(["ok" => !$error, "data" => $data, "mensaje" => $mensaje]);
    exit;
}

try {

    switch ($accion) {

        case 'listar':
            response($model->listar());
            break;

        case 'guardar':
            $nombre = trim($_POST['nombre'] ?? '');
            if (!$nombre) response([], true, "El nombre es requerido");

            $resp = $model->guardar($nombre);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Marca creada");
            break;

        case 'editar':
            $id     = $_POST['id_marca'] ?? null;
            $nombre = trim($_POST['nombre'] ?? '');

            if (!$id)     response([], true, "ID inválido");
            if (!$nombre) response([], true, "El nombre es requerido");

            $resp = $model->editar($id, $nombre);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Marca actualizada");
            break;

        case 'eliminar':
            $id = $_POST['id_marca'] ?? null;
            if (!$id) response([], true, "ID inválido");

            $resp = $model->eliminar($id);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Marca eliminada");
            break;

        default:
            response([], true, "Acción no válida");
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
