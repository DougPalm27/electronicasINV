<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');

include_once "../../../../config/Connection.php";
include_once "../models/mdlComponentes.php";

$model  = new mdlComponentes();
$accion = $_POST['accion'] ?? '';

$esAdmin = ($_SESSION['nombre_rol'] ?? '') === 'Administrador';

function response($data = [], $error = false, $mensaje = "")
{
    echo json_encode(["ok" => !$error, "data" => $data, "mensaje" => $mensaje]);
    exit;
}

function requiereAdmin(bool $esAdmin): void
{
    if (!$esAdmin) response([], true, "Solo el administrador puede modificar el catálogo.");
}

/**
 * Valida y guarda la imagen de esquema subida ($_FILES['esquema']).
 * Devuelve la ruta relativa, o termina con error si no es válida.
 */
function procesarEsquemaSubido(): string
{
    if (empty($_FILES['esquema']) || $_FILES['esquema']['error'] === UPLOAD_ERR_NO_FILE) {
        response([], true, "No se envió ninguna imagen");
    }

    $file = $_FILES['esquema'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        response([], true, "Error al subir la imagen (código {$file['error']})");
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        response([], true, "La imagen no puede superar 3 MB");
    }

    $mime = mime_content_type($file['tmp_name']);
    $extPorMime = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    if (!isset($extPorMime[$mime])) {
        response([], true, "Formato no permitido: usa JPG, PNG, WebP o SVG");
    }

    // SVG: rechazar scripts/manejadores de eventos embebidos
    if ($mime === 'image/svg+xml') {
        $contenido = file_get_contents($file['tmp_name']);
        if (preg_match('/<script|on\w+\s*=|javascript:/i', $contenido)) {
            response([], true, "El SVG contiene código no permitido");
        }
    }

    $dir = __DIR__ . '/../../../../assets/img/esquemas/';
    if (!is_dir($dir)) mkdir($dir, 0775, true);

    $nombre  = 'esquema_' . uniqid() . '.' . $extPorMime[$mime];
    $destino = $dir . $nombre;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        response([], true, "No se pudo guardar la imagen en el servidor");
    }

    return 'assets/img/esquemas/' . $nombre;
}

/** Borra del disco un esquema anterior (solo archivos subidos, nunca los default). */
function borrarEsquemaAnterior(?string $ruta): void
{
    if (!$ruta || strpos($ruta, 'assets/img/esquemas/esquema_') !== 0) return;
    $abs = __DIR__ . '/../../../../' . $ruta;
    if (is_file($abs)) @unlink($abs);
}

try {

    switch ($accion) {

        case 'listarModelos':
            response($model->listarModelos());
            break;

        case 'ficha':
            $id = (int)($_POST['id_modelo'] ?? 0);
            if (!$id) response([], true, "Modelo inválido");
            $ficha = $model->obtenerFicha($id);
            if (!$ficha) response([], true, "Modelo no encontrado");
            response($ficha);
            break;

        case 'catalogo':
            response($model->listarCatalogo());
            break;

        case 'repuestos':
            response($model->listarRepuestos());
            break;

        case 'guardarComponenteCatalogo':
            requiereAdmin($esAdmin);
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') response([], true, "El nombre es requerido");
            response($model->guardarEnCatalogo($nombre, trim($_POST['categoria'] ?? '')));
            break;

        case 'guardarItem':
        case 'editarItem':
            requiereAdmin($esAdmin);
            $d = [
                'id_modelo_componente' => (int)($_POST['id_modelo_componente'] ?? 0),
                'id_modelo'            => (int)($_POST['id_modelo'] ?? 0),
                'id_componente'        => (int)($_POST['id_componente'] ?? 0),
                'id_padre'             => (int)($_POST['id_padre'] ?? 0) ?: null,
                'cantidad'             => max(1, (int)($_POST['cantidad'] ?? 1)),
                'especificacion'       => trim($_POST['especificacion'] ?? '') ?: null,
                'id_repuesto'          => (int)($_POST['id_repuesto'] ?? 0) ?: null,
            ];
            if (!$d['id_componente']) response([], true, "Selecciona un componente");

            if ($accion === 'guardarItem') {
                if (!$d['id_modelo']) response([], true, "Modelo inválido");
                response($model->guardarItem($d), false, "Componente agregado");
            }

            if (!$d['id_modelo_componente']) response([], true, "ID inválido");
            if ($d['id_padre'] === $d['id_modelo_componente']) {
                response([], true, "Un componente no puede ser su propio padre");
            }
            $model->editarItem($d);
            response([], false, "Componente actualizado");
            break;

        case 'eliminarItem':
            requiereAdmin($esAdmin);
            $id = (int)($_POST['id_modelo_componente'] ?? 0);
            if (!$id) response([], true, "ID inválido");
            $resp = $model->eliminarItem($id);
            if (isset($resp['error'])) response([], true, $resp['mensaje']);
            response([], false, "Componente eliminado");
            break;

        case 'guardarPosiciones':
            requiereAdmin($esAdmin);
            $posiciones = json_decode($_POST['posiciones'] ?? '[]', true);
            if (!is_array($posiciones)) response([], true, "Posiciones inválidas");
            $model->guardarPosiciones($posiciones);
            response([], false, "Posiciones guardadas");
            break;

        case 'subirEsquema':
            requiereAdmin($esAdmin);
            $id = (int)($_POST['id_modelo'] ?? 0);
            if (!$id) response([], true, "Modelo inválido");

            $anterior = $model->obtenerEsquema($id);
            $ruta = procesarEsquemaSubido();
            $model->guardarEsquema($id, $ruta);
            borrarEsquemaAnterior($anterior);
            response(['imagen_esquema' => $ruta], false, "Esquema actualizado");
            break;

        default:
            response([], true, "Acción no válida");
    }

} catch (Throwable $e) {
    response([], true, $e->getMessage());
}
