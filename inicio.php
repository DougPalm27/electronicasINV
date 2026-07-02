<?php
require_once './config/auth.php';
requireLogin();
require_once './src/includes/navContext.php';

// ── Título dinámico de la pestaña: "Módulo · Sección" ──
$moduloActivo  = moduloActual();
$tituloPestana = tituloModulo($moduloActivo);
$seccionActual = nombreSeccion($moduloActivo);
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de control de trazabilidad">
    <title><?= htmlspecialchars($tituloPestana) ?> · <?= htmlspecialchars($seccionActual) ?></title>
</head>
<body>

<?php include "./src/includes/links.php"; ?>
<?php include "./src/includes/topnav.php"; ?>
<?php include "./src/includes/sidenav.php"; ?>

<main id="main" class="main">
    <?php include "./content.php"; ?>
</main>

<?php include "./src/includes/scripts.php"; ?>
<?php include "./src/includes/footer.php"; ?>

<a href="#" class="back-to-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
</a>

</body>
</html>
