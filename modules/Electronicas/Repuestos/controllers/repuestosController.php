<?php
require_once '../../../../config/auth.php';
requireLogin(true);


header('Content-Type: application/json');

include_once "../models/mdlRepuestos.php";

$model = new mdlRepuestos();

$accion  = $_POST['accion'] ?? '';
$esAdmin = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';

// Acciones destructivas o de auditoría: solo Administrador
$accionesAdmin = ['eliminar', 'anularMovimiento', 'editarDetalle', 'cambiarEstadoDetalle', 'ajusteNegativo'];
if (in_array($accion, $accionesAdmin, true) && !$esAdmin) {
    echo json_encode(["ok" => false, "data" => [], "mensaje" => "Sin permisos para esta acción"]);
    exit;
}

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

        case 'ubicaciones':
            response($model->listarUbicaciones());
            break;

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
                "maneja_serie" => $_POST["maneja_serie"] ?? 0,
                "id_divisa"    => $_POST["id_divisa"] ?? null,
                "id_ubicacion" => $_POST["id_ubicacion"] ?? null,
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
                "maneja_serie" => $_POST["maneja_serie"] ?? 0,
                "id_divisa"    => $_POST["id_divisa"] ?? null,
                "id_ubicacion" => $_POST["id_ubicacion"] ?? null,
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
            $resp = $model->eliminarRepuesto($id);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Repuesto eliminado");
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

            $tipo_entrada = trim($_POST["tipo_entrada"] ?? '') ?: 'Compra';

            $data = [
                "id_repuesto"  => $id_repuesto,
                "cantidad"     => $cantidad,
                "costo"        => $costo,
                "referencia"   => mb_strtoupper($tipo_entrada, 'UTF-8'),
                "id_proveedor" => $_POST["id_proveedor"] ?? null,
                "tipo_entrada" => $tipo_entrada,
            ];

            $resp = $model->entradaRepuesto($data);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Entrada registrada");
            break;


        case 'entradaSerie':
            $id_repuesto  = $_POST['id_repuesto'] ?? null;
            $series       = $_POST['series'] ?? [];
            $id_proveedor = $_POST['id_proveedor'] ?? null;
            $tipo_entrada = $_POST['tipo_entrada'] ?? 'Compra';

            if (!$id_repuesto)
                response([], true, "Repuesto inválido");

            if (empty($series))
                response([], true, "Debes ingresar al menos una serie");

            $resp = $model->entradaSerie($id_repuesto, $series, $id_proveedor, $tipo_entrada);
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

            // El costo de la salida lo determina el modelo (costo promedio vigente)
            $data = [
                "id_repuesto" => $id_repuesto,
                "cantidad"    => $cantidad,
                "id_maquina"  => $id_maquina,
                "referencia"  => $_POST["referencia"] ?? 'SALIDA MANUAL'
            ];

            $resp = $model->salidaRepuesto($data);

            if (isset($resp["error"]) && $resp["error"] === true)
                response([], true, $resp["mensaje"]);

            response($resp, false, "Salida registrada");
            break;


        case 'salidaSerie':
            $id_repuesto = $_POST["id_repuesto"] ?? null;
            $id_maquina  = $_POST["id_maquina"] ?? null;
            $referencia  = $_POST["referencia"] ?? 'SALIDA MANUAL';
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
            $id    = $_POST["id_repuesto"] ?? 0;
            $desde = trim($_POST["desde"] ?? '') ?: null;
            $hasta = trim($_POST["hasta"] ?? '') ?: null;
            response($model->obtenerKardex($id, $desde, $hasta));
            break;

        case 'anularMovimiento':
            $id_movimiento = (int)($_POST['id_movimiento'] ?? 0);
            if (!$id_movimiento) response([], true, 'ID de movimiento inválido.');
            $resp = $model->anularMovimiento($id_movimiento);
            if (isset($resp['error'])) response([], true, $resp['mensaje']);
            response($resp, false, 'Movimiento anulado correctamente.');
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
                "serie"  => trim($_POST["serie"] ?? ''),
                "estado" => $_POST["id_estado_repuesto"] ?? null,
                "maquina"=> $_POST["id_maquina_actual"] ?? null
            ];
            if (!$data["id"] || !$data["serie"] || !$data["estado"])
                response([], true, "Datos incompletos");
            $resp = $model->editarDetalle($data);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Detalle actualizado");
            break;

        case 'cambiarEstadoDetalle':
            $data = [
                "id"     => $_POST["id_detalle_repuesto"] ?? null,
                "estado" => $_POST["id_estado_repuesto"] ?? null,
                "maquina"=> $_POST["id_maquina_actual"] ?? null
            ];
            if (!$data["id"] || !$data["estado"])
                response([], true, "Datos incompletos");
            $resp = $model->cambiarEstadoDetalle($data);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Estado actualizado");
            break;

        //////////////////////////////////////////////////////////
        // AJUSTE NEGATIVO DE INVENTARIO
        //////////////////////////////////////////////////////////

        case 'ajusteNegativo':
            $id_repuesto = $_POST["id_repuesto"] ?? null;
            $cantidad    = (int)($_POST["cantidad"] ?? 0);
            $motivo      = trim($_POST["motivo"] ?? '');

            if (!$id_repuesto)
                response([], true, "Repuesto inválido");
            if ($cantidad <= 0)
                response([], true, "La cantidad debe ser mayor a 0");
            if ($motivo === '')
                response([], true, "El motivo es obligatorio");

            $resp = $model->ajusteNegativo($id_repuesto, $cantidad, $motivo);
            if (is_array($resp) && isset($resp["error"])) response([], true, $resp["mensaje"]);
            response($resp, false, "Ajuste registrado");
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
