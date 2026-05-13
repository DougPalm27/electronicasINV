<?php

require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');

include_once "../models/mdlMantenimientos.php";

$model  = new mdlMantenimientos();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode(["ok" => !$error, "data" => $data, "mensaje" => $mensaje]);
    exit;
}

try {

    switch ($accion) {

        case 'listar':
            response($model->listarMantenimientos());
            break;

        case 'detalle':
            $id = (int)($_POST['id_mantenimiento'] ?? 0);
            if (!$id) response([], true, "ID de mantenimiento inválido");
            response([
                "tareas"     => $model->obtenerTareas($id),
                "instalados" => $model->obtenerDetalleMantenimiento($id),
                "retiros"    => $model->obtenerRetirosMantenimiento($id)
            ]);
            break;

        case 'maquinas':
            response($model->listarMaquinas());
            break;

        case 'tipos':
            response($model->listarTipos());
            break;

        case 'tecnicos':
            response($model->listarTecnicos());
            break;

        case 'repuestos':
            response($model->listarRepuestosDisponibles());
            break;

        case 'series':
            $id_rep = (int)($_POST['id_repuesto'] ?? 0);
            if (!$id_rep) response([], true, 'ID de repuesto inválido.');
            response($model->obtenerSeriesDisponibles($id_rep));
            break;

        case 'instalados':
            $id_maq = (int)($_POST['id_maquina'] ?? 0);
            if (!$id_maq) response([], true, 'ID de máquina inválido.');
            response($model->obtenerInstalados($id_maq));
            break;

        case 'guardar':
            $payload = $_POST["losDatos"] ?? null;
            if (!$payload) response([], true, "No se recibieron datos");

            $obj = json_decode($payload);
            if (!$obj) response([], true, "Payload inválido");

            // Extraer tareas antes de pasar el objeto al modelo
            $tareas = isset($obj->tareas) ? (array)$obj->tareas : [];
            unset($obj->tareas);

            // Asegurar que repuestos/retiros sean arrays (pueden venir del payload o vacíos)
            if (!isset($obj->repuestos) || !is_array($obj->repuestos)) $obj->repuestos = [];
            if (!isset($obj->retiros)   || !is_array($obj->retiros))   $obj->retiros   = [];

            $resp = $model->guardarMantenimiento($obj);

            if (isset($resp["error"]) && $resp["error"] === true) {
                response([], true, $resp["mensaje"]);
            }

            // Guardar tareas vinculadas al mantenimiento recién creado
            if (!empty($tareas)) {
                $model->guardarTareas((int)$resp["id_mantenimiento"], $tareas);
            }

            response($resp, false, "Mantenimiento guardado correctamente");
            break;

        default:
            response([], true, "Acción no válida: " . $accion);
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
