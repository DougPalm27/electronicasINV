<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../model/mdlLogin.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$acceso = false;

if ($username !== '' && $password !== '') {
    try {
        $model   = new mdlLogin();
        $usuario = $model->buscarUsuario($username);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            $_SESSION['logueado']   = true;
            $_SESSION['usuario']    = $usuario['username'];
            $_SESSION['nombre']     = $usuario['nombre'];
            $_SESSION['id_usuario'] = (int)$usuario['id_usuario'];
            $_SESSION['id_rol']     = $usuario['id_rol']     ? (int)$usuario['id_rol'] : null;
            $_SESSION['nombre_rol'] = $usuario['nombre_rol'] ?? null;

            // Cargar módulos permitidos según el rol asignado
            if (!empty($usuario['id_rol'])) {
                $_SESSION['modulos'] = $model->obtenerModulosRol((int)$usuario['id_rol']);
            } else {
                $_SESSION['modulos'] = null; // sin restricción (usuario legacy)
            }

            $acceso = true;
        }
    } catch (Throwable $e) {
        // Error de BD: tratarlo como credenciales inválidas (no exponer detalle)
        $acceso = false;
    }
}

if ($acceso) {
    header('Location: ../../../selector.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso denegado</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.28/dist/sweetalert2.all.min.js"></script>
</head>
<body>
<script>
    Swal.fire({
        title: 'Control de Inventario',
        text:  'Credenciales incorrectas',
        icon:  'error',
        confirmButtonColor: '#3085d6',
        confirmButtonText:  'Volver a intentar'
    }).then(() => { window.location = '../../../index.php'; });
</script>
</body>
</html>
