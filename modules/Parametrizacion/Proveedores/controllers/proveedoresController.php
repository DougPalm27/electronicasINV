<?php

header('Content-Type: application/json');

include_once "../models/mdlProveedores.php";

$model  = new mdlProveedores();
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
            $data = [
                "nombre"    => trim($_POST["nombre"]    ?? ''),
                "telefono"  => trim($_POST["telefono"]  ?? ''),
                "correo"    => trim($_POST["correo"]    ?? ''),
                "direccion" => trim($_POST["direccion"] ?? ''),
            ];

            if (!$data["nombre"]) response([], true, "El nombre es requerido");

            if ($data["correo"] && !filter_var($data["correo"], FILTER_VALIDATE_EMAIL))
                response([], true, "El correo no tiene un formato válido");

            $resp = $model->guardar($data);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Proveedor creado");
            break;

        case 'editar':
            $data = [
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "nombre"       => trim($_POST["nombre"]    ?? ''),
                "telefono"     => trim($_POST["telefono"]  ?? ''),
                "correo"       => trim($_POST["correo"]    ?? ''),
                "direccion"    => trim($_POST["direccion"] ?? ''),
            ];

            if (!$data["id_proveedor"]) response([], true, "ID inválido");
            if (!$data["nombre"])       response([], true, "El nombre es requerido");

            if ($data["correo"] && !filter_var($data["correo"], FILTER_VALIDATE_EMAIL))
                response([], true, "El correo no tiene un formato válido");

            $resp = $model->editar($data);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Proveedor actualizado");
            break;

        case 'eliminar':
            $id = $_POST["id_proveedor"] ?? null;
            if (!$id) response([], true, "ID inválido");

            $resp = $model->eliminar($id);
            if (isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Proveedor eliminado");
            break;

        default:
            response([], true, "Acción no válida");
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
