<?php
require_once '../../../../config/auth.php';
requireLogin(true);


header('Content-Type: application/json');

include_once "../models/mdlModelos.php";

$model  = new mdlModelos();
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

        case 'listarMarcas':
            response($model->listarMarcas());
            break;

        case 'listarTipos':
            response($model->listarTipos());
            break;

        case 'guardar':
            $data = [
                "nombre"         => trim($_POST["nombre"] ?? ''),
                "id_marca"       => $_POST["id_marca"] ?? null,
                "id_tipo_modelo" => $_POST["id_tipo_modelo"] ?? null,
            ];

            if (!$data["nombre"])   response([], true, "El nombre es requerido");
            if (!$data["id_marca"]) response([], true, "Debes seleccionar una marca");

            $resp = $model->guardar($data);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Modelo creado");
            break;

        case 'editar':
            $data = [
                "id_modelo"      => $_POST["id_modelo"] ?? null,
                "nombre"         => trim($_POST["nombre"] ?? ''),
                "id_marca"       => $_POST["id_marca"] ?? null,
                "id_tipo_modelo" => $_POST["id_tipo_modelo"] ?? null,
            ];

            if (!$data["id_modelo"]) response([], true, "ID inválido");
            if (!$data["nombre"])    response([], true, "El nombre es requerido");
            if (!$data["id_marca"])  response([], true, "Debes seleccionar una marca");

            $resp = $model->editar($data);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Modelo actualizado");
            break;

        case 'eliminar':
            $id = $_POST["id_modelo"] ?? null;
            if (!$id) response([], true, "ID inválido");

            $resp = $model->eliminar($id);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Modelo eliminado");
            break;

        default:
            response([], true, "Acción no válida");
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
