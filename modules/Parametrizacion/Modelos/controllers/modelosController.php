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

/**
 * Valida y guarda la imagen subida (campo $_FILES['imagen']).
 * Devuelve la ruta relativa guardada, null si no se envió archivo,
 * o termina con error si el archivo no es válido.
 */
function procesarImagenSubida(): ?string
{
    if (empty($_FILES['imagen']) || $_FILES['imagen']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['imagen'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        response([], true, "Error al subir la imagen (código {$file['error']})");
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        response([], true, "La imagen no puede superar 2 MB");
    }

    // Validar tipo real del archivo, no solo la extensión
    $mime = mime_content_type($file['tmp_name']);
    $extPorMime = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extPorMime[$mime])) {
        response([], true, "Formato no permitido: usa JPG, PNG o WebP");
    }

    $dir = __DIR__ . '/../../../../assets/img/modelos/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $nombre  = 'modelo_' . uniqid() . '.' . $extPorMime[$mime];
    $destino = $dir . $nombre;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        response([], true, "No se pudo guardar la imagen en el servidor");
    }

    return 'assets/img/modelos/' . $nombre;
}

/** Borra del disco una imagen previa del modelo (si existe y está en la carpeta esperada). */
function borrarImagenAnterior(?string $ruta): void
{
    if (!$ruta || strpos($ruta, 'assets/img/modelos/') !== 0) return;
    $abs = __DIR__ . '/../../../../' . $ruta;
    if (is_file($abs)) @unlink($abs);
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

            $data["imagen"] = procesarImagenSubida();

            $resp = $model->guardar($data);
            if (isset($resp["error"])) {
                borrarImagenAnterior($data["imagen"]);
                response([], true, $resp["mensaje"]);
            }
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

            $imagenNueva  = procesarImagenSubida();
            $quitarImagen = ($_POST["quitar_imagen"] ?? '0') === '1';
            $imagenActual = $model->obtenerImagen($data["id_modelo"]);

            if ($imagenNueva !== null) {
                $data["imagen_set"] = true;
                $data["imagen"]     = $imagenNueva;
            } elseif ($quitarImagen) {
                $data["imagen_set"] = true;
                $data["imagen"]     = null;
            }

            $resp = $model->editar($data);
            if (isset($resp["error"])) {
                borrarImagenAnterior($imagenNueva);
                response([], true, $resp["mensaje"]);
            }

            // Actualización exitosa: limpiar la imagen anterior del disco
            if (($imagenNueva !== null || $quitarImagen) && $imagenActual) {
                borrarImagenAnterior($imagenActual);
            }
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
