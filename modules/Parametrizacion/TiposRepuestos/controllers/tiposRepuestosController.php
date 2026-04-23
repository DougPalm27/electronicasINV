<?php

header('Content-Type: application/json');

include_once "../models/mdlTiposRepuestos.php";

$model  = new mdlTiposRepuestos();
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

            $resp = $model->guardar(["nombre" => $nombre]);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Tipo creado");
            break;

        case 'editar':
            $id     = $_POST['id_tipo_repuesto'] ?? null;
            $nombre = trim($_POST['nombre'] ?? '');

            if (!$id)     response([], true, "ID inválido");
            if (!$nombre) response([], true, "El nombre es requerido");

            $resp = $model->editar(["id_tipo_repuesto" => $id, "nombre" => $nombre]);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Tipo actualizado");
            break;

        case 'eliminar':
            $id = $_POST['id_tipo_repuesto'] ?? null;
            if (!$id) response([], true, "ID inválido");

            $resp = $model->eliminar($id);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Tipo eliminado");
            break;

        default:
            response([], true, "Acción no válida");
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
