<?php
require_once '../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
include_once '../models/mdlUsuarios.php';

$model  = new mdlUsuarios();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

function resp($data = [], bool $error = false, string $msg = ''): void
{
    echo json_encode(['ok' => !$error, 'data' => $data, 'mensaje' => $msg]);
    exit;
}

try {
    switch ($accion) {

        case 'listar':
            resp($model->listar());
            break;

        case 'listarRoles':
            resp($model->listarRoles());
            break;

        case 'crear':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password']        ?? '';
            $nombre   = trim($_POST['nombre']     ?? '');
            $email    = trim($_POST['email']      ?? '') ?: null;
            $id_rol   = $_POST['id_rol'] ?? null;
            $id_rol   = ($id_rol !== null && $id_rol !== '') ? (int)$id_rol : null;

            if (!$username || !$password || !$nombre) {
                resp([], true, 'Todos los campos son obligatorios.');
            }
            if (strlen($password) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                resp([], true, 'El correo electrónico no es válido.');
            }

            $model->crear($username, $password, $nombre, $id_rol, $email);
            resp([], false, 'Usuario creado correctamente.');
            break;

        case 'editarNombre':
            $id     = (int)($_POST['id_usuario'] ?? 0);
            $nombre = trim($_POST['nombre']      ?? '');
            $email  = trim($_POST['email']       ?? '') ?: null;
            $id_rol = $_POST['id_rol'] ?? null;
            $id_rol = ($id_rol !== null && $id_rol !== '') ? (int)$id_rol : null;

            if (!$id || !$nombre) resp([], true, 'Datos incompletos.');
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                resp([], true, 'El correo electrónico no es válido.');
            }

            $model->editarNombre($id, $nombre, $id_rol, $email);
            resp([], false, 'Usuario actualizado.');
            break;

        case 'asignarRol':
            $id     = (int)($_POST['id_usuario'] ?? 0);
            $id_rol = $_POST['id_rol'] ?? null;
            $id_rol = ($id_rol !== null && $id_rol !== '') ? (int)$id_rol : null;

            if (!$id) resp([], true, 'ID inválido.');
            $model->asignarRol($id, $id_rol);
            resp([], false, 'Rol asignado.');
            break;

        case 'resetPassword':
            $id       = (int)($_POST['id_usuario']      ?? 0);
            $password = $_POST['password']               ?? '';

            if (!$id || strlen($password) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }

            $model->resetPassword($id, $password);
            resp([], false, 'Contraseña restablecida.');
            break;

        case 'toggleActivo':
            $id = (int)($_POST['id_usuario'] ?? 0);
            if (!$id) resp([], true, 'ID inválido.');
            $model->toggleActivo($id);
            resp([], false, 'Estado actualizado.');
            break;

        case 'actualizarFoto': {
            // Autoservicio: siempre sobre el usuario en sesión
            $id = (int)$_SESSION['id_usuario'];

            if (empty($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
                resp([], true, 'No se recibió ninguna imagen.');
            }

            $file = $_FILES['foto'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                resp([], true, "Error al subir la imagen (código {$file['error']}).");
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                resp([], true, 'La imagen no puede superar 2 MB.');
            }

            // Validar tipo real del archivo, no solo la extensión
            $mime = mime_content_type($file['tmp_name']);
            $extPorMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!isset($extPorMime[$mime])) {
                resp([], true, 'Formato no permitido: usa JPG, PNG o WebP.');
            }

            $dir = __DIR__ . '/../../../assets/img/usuarios/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);

            $nombreArchivo = 'usuario_' . $id . '_' . uniqid() . '.' . $extPorMime[$mime];
            if (!move_uploaded_file($file['tmp_name'], $dir . $nombreArchivo)) {
                resp([], true, 'No se pudo guardar la imagen en el servidor.');
            }
            $ruta = 'assets/img/usuarios/' . $nombreArchivo;

            // Reemplazar en BD y limpiar la foto anterior del disco
            $anterior = $model->obtenerFoto($id);
            $model->actualizarFoto($id, $ruta);
            if ($anterior && strpos($anterior, 'assets/img/usuarios/') === 0) {
                $abs = __DIR__ . '/../../../' . $anterior;
                if (is_file($abs)) @unlink($abs);
            }

            $_SESSION['foto'] = $ruta;
            resp(['foto' => $ruta], false, 'Foto de perfil actualizada.');
            break;
        }

        case 'quitarFoto': {
            $id = (int)$_SESSION['id_usuario'];

            $anterior = $model->obtenerFoto($id);
            $model->actualizarFoto($id, null);
            if ($anterior && strpos($anterior, 'assets/img/usuarios/') === 0) {
                $abs = __DIR__ . '/../../../' . $anterior;
                if (is_file($abs)) @unlink($abs);
            }

            $_SESSION['foto'] = null;
            resp([], false, 'Foto de perfil eliminada.');
            break;
        }

        case 'cambiarPassword':
            $id     = (int)$_SESSION['id_usuario'];
            $actual = $_POST['password_actual'] ?? '';
            $nueva  = $_POST['password_nueva']  ?? '';
            $conf   = $_POST['confirmar']        ?? '';

            if (!$actual || !$nueva || !$conf) {
                resp([], true, 'Completa todos los campos.');
            }
            if ($nueva !== $conf) {
                resp([], true, 'Las contraseñas nuevas no coinciden.');
            }
            if (strlen($nueva) < 6) {
                resp([], true, 'La contraseña debe tener al menos 6 caracteres.');
            }

            $model->cambiarPassword($id, $actual, $nueva);
            resp([], false, '¡Contraseña actualizada correctamente!');
            break;

        default:
            resp([], true, 'Acción no válida.');
    }
} catch (Throwable $e) {
    resp([], true, $e->getMessage());
}
