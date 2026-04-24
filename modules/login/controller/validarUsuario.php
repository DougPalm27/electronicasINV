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
            $acceso = true;
        }
    } catch (Throwable $e) {
        // Error de BD: tratarlo como credenciales inválidas (no exponer detalle)
        $acceso = false;
    }
}

if ($acceso) {
    header('Location: ../../../inicio.php');
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
