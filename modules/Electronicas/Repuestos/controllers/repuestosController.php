<?php
require_once '../../../../config/auth.php';
requireLogin(true);


header('Content-Type: application/json');

include_once "../models/mdlRepuestos.php";

$model = new mdlRepuestos();

$accion = $_POST['accion'] ?? '';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode([
        "ok" => !$error,
        "data" => $data,
        "mensaje" => $mensaje
    ]);
    exit;
}

try {

    switch ($accion) {

        //////////////////////////////////////////////////////////
        // CATÁLOGOS
        //////////////////////////////////////////////////////////

        case 'proveedores':
            response($model->listarProveedores());
            break;

        case 'tipos':
            response($model->listarTipos());
            break;

        case 'estados':
            response($model->listarEstados());
            break;

        case 'marcas':
            response($model->listarMarcas());
            break;

        case 'modelos':
            $id_marca       = $_POST['id_marca'] ?? null;
            $id_tipo_modelo = $_POST['id_tipo_modelo'] ?? null;
            response($model->listarModelos($id_marca, $id_tipo_modelo));
            break;

        //////////////////////////////////////////////////////////
        // LISTAR
        //////////////////////////////////////////////////////////

        case 'listar':
            response($model->listarRepuestos());
            break;

        case 'seriesDisponibles':
            $id = $_POST["id_repuesto"] ?? 0;
            if (!$id) response([], true, "ID de repuesto inválido");
            response($model->obtenerSeriesDisponibles($id));
            break;

        //////////////////////////////////////////////////////////
        // GUARDAR / EDITAR / ELIMINAR
        //////////////////////////////////////////////////////////

        case 'guardar':
            $data = [
                "nombre"       => trim($_POST["nombre"] ?? ''),
                "numero_parte" => trim($_POST["numero_parte"] ?? ''),
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "costo"        => $_POST["costo"] ?? 0,
                "stock_minimo" => $_POST["stock_minimo"] ?? 0,
                "comentarios"  => trim($_POST["comentarios"] ?? ''),
                "id_tipo"      => $_POST["id_tipo"] ?? null,
                "id_marca"     => $_POST["id_marca"] ?? null,
                "id_modelo"    => $_POST["id_modelo"] ?? null,
                "maneja_serie" => $_POST["maneja_serie"] ?? 0
            ];

            if (!$data["nombre"])
                response([], true, "El nombre es requerido");

            if (!$data["id_proveedor"] || $data["id_proveedor"] == -1)
                response([], true, "Debes seleccionar un proveedor");

            $resp = $model->guardarRepuesto($data);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Repuesto creado");
            break;


        case 'editar':
            $data = [
                "id_repuesto"  => $_POST["id_repuesto"] ?? null,
                "nombre"       => trim($_POST["nombre"] ?? ''),
                "numero_parte" => trim($_POST["numero_parte"] ?? ''),
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "costo"        => $_POST["costo"] ?? 0,
                "stock_minimo" => $_POST["stock_minimo"] ?? 0,
                "comentarios"  => trim($_POST["comentarios"] ?? ''),
                "id_tipo"      => $_POST["id_tipo"] ?? null,
                "id_marca"     => $_POST["id_marca"] ?? null,
                "id_modelo"    => $_POST["id_modelo"] ?? null,
                "maneja_serie" => $_POST["maneja_serie"] ?? 0
            ];

            if (!$data["id_repuesto"])
                response([], true, "ID inválido");

            if (!$data["nombre"])
                response([], true, "El nombre es requerido");

            if (!$data["id_proveedor"] || $data["id_proveedor"] == -1)
                response([], true, "Debes seleccionar un proveedor");

            $resp = $model->editarRepuesto($data);

            if (isset($resp["error"]) && $resp["error"] === true)
                response([], true, $resp["mensaje"]);

            response($resp, false, "Repuesto actualizado");
            break;


        case 'eliminar':
            $id = $_POST["id_repuesto"] ?? null;
            if (!$id) response([], true, "ID inválido");
            response($model->eliminarRepuesto($id), false, "Repuesto eliminado");
            break;

        //////////////////////////////////////////////////////////
        // ENTRADA
        //////////////////////////////////////////////////////////

        case 'entrada':
            $id_repuesto = $_POST["id_repuesto"] ?? null;
            $cantidad    = (int)($_POST["cantidad"] ?? 0);
            $costo       = (float)($_POST["costo"] ?? 0);

            if (!$id_repuesto)
                response([], true, "Repuesto inválido");

            if ($cantidad <= 0)
                response([], true, "La cantidad debe ser mayor a 0");

            if ($costo < 0)
                response([], true, "El costo no puede ser negativo");

            $data = [
                "id_repuesto" => $id_repuesto,
                "cantidad"    => $cantidad,
                "costo"       => $costo,
                "referencia"  => $_POST["referencia"] ?? 'COMPRA'
            ];

            $resp = $model->entradaRepuesto($data);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Entrada registrada");
            break;


        case 'entradaSerie':
            $id_repuesto = $_POST['id_repuesto'] ?? null;
            $series      = $_POST['series'] ?? [];

            if (!$id_repuesto)
                response([], true, "Repuesto inválido");

            if (empty($series))
                response([], true, "Debes ingresar al menos una serie");

            $resp = $model->entradaSerie($id_repuesto, $series);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp);
            break;

        //////////////////////////////////////////////////////////
        // SALIDA
        //////////////////////////////////////////////////////////

        case 'salida':
            $id_repuesto = $_POST["id_repuesto"] ?? null;
            $cantidad    = (int)($_POST["cantidad"] ?? 0);
            $id_maquina  = $_POST["id_maquina"] ?? null;

            if (!$id_repuesto)
                response([], true, "Repuesto inválido");

            if ($cantidad <= 0)
                response([], true, "La cantidad debe ser mayor a 0");

            if (!$id_maquina)
                response([], true, "Debes indicar la máquina destino");

            $data = [
                "id_repuesto" => $id_repuesto,
                "cantidad"    => $cantidad,
                "costo"       => $_POST["costo"] ?? 0,
                "id_maquina"  => $id_maquina,
                "referencia"  => $_POST["referencia"] ?? 'MANTENIMIENTO'
            ];

            $resp = $model->salidaRepuesto($data);

            if (isset($resp["error"]) && $resp["error"] === true)
                response([], true, $resp["mensaje"]);

            response($resp, false, "Salida registrada");
            break;


        case 'salidaSerie':
            $id_repuesto = $_POST["id_repuesto"] ?? null;
            $id_maquina  = $_POST["id_maquina"] ?? null;
            $referencia  = $_POST["referencia"] ?? 'MANTENIMIENTO';
            $series      = $_POST["series"] ?? [];

            if (!is_array($series)) {
                $series = explode(",", $series);
            }

            if (!$id_repuesto || !$id_maquina || empty($series))
                response([], true, "Datos incompletos para salida por serie");

            $resp = $model->salidaSerie($id_repuesto, $id_maquina, $series, $referencia);

            if (isset($resp["error"]) && $resp["error"] === true)
                response([], true, $resp["mensaje"]);

            response($resp, false, "Salida por serie registrada");
            break;

        //////////////////////////////////////////////////////////
        // KARDEX
        //////////////////////////////////////////////////////////

        case 'kardex':
            $id = $_POST["id_repuesto"] ?? 0;
            response($model->obtenerKardex($id));
            break;

        //////////////////////////////////////////////////////////
        // DETALLE (SERIE)
        //////////////////////////////////////////////////////////

        case 'listarDetalle':
            $id = $_POST["id_repuesto"] ?? 0;
            response($model->obtenerDetalle($id));
            break;

        case 'editarDetalle':
            $data = [
                "id"     => $_POST["id_detalle_repuesto"] ?? null,
                "serie"  => $_POST["serie"] ?? '',
                "estado" => $_POST["id_estado_repuesto"] ?? null,
                "maquina"=> $_POST["id_maquina_actual"] ?? null
            ];
            response($model->editarDetalle($data), false, "Detalle actualizado");
            break;

        case 'cambiarEstadoDetalle':
            $data = [
                "id"     => $_POST["id_detalle_repuesto"] ?? null,
                "estado" => $_POST["id_estado_repuesto"] ?? null,
                "maquina"=> $_POST["id_maquina_actual"] ?? null
            ];
            response($model->cambiarEstadoDetalle($data), false, "Estado actualizado");
            break;

        //////////////////////////////////////////////////////////
        // DEFAULT
        //////////////////////////////////////////////////////////

        default:
            response([], true, "Acción no válida: " . $accion);
            break;
    }
} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
