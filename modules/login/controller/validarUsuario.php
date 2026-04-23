<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$usuarios = [
    'msorto'    => ['password' => 'proDuX10n_03', 'nombre' => 'M. Sorto'],
    'dpalma'    => ['password' => 'Hpo051!',       'nombre' => 'D. Palma'],
    'Sania' => ['password' => '$plan2024',     'nombre' => 'Sania Alvarenga'],
];

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (isset($usuarios[$username]) && $usuarios[$username]['password'] === $password) {
    $_SESSION['logueado'] = true;
    $_SESSION['usuario']  = $username;
    $_SESSION['nombre']   = $usuarios[$username]['nombre'];
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
