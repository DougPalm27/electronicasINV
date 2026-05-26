<?php
require_once './config/auth.php';
requireLogin();

$modulos_usuario = $_SESSION['modulos'] ?? null;

$grupoElectronicas = ['dasboard','maquinas','repuestos','mantenimientos','solicitudes',
                      'marcas','modelos','proveedores','tiposRepuestos','divisas','usuarios','roles',
                      'satake','compras'];
$grupoGPS          = ['gpsCredenciales','gpsTransportes'];

function tieneAccesoGrupo(?array $permitidos, array $grupo): bool {
    if ($permitidos === null) return true;
    foreach ($grupo as $clave) {
        if (in_array($clave, $permitidos)) return true;
    }
    return false;
}

$tieneElectronicas = tieneAccesoGrupo($modulos_usuario, $grupoElectronicas);
$tieneGPS          = tieneAccesoGrupo($modulos_usuario, $grupoGPS);

// Si solo tiene acceso a un sistema, redirigir directo
if ($tieneElectronicas && !$tieneGPS) {
    header('Location: inicio.php');
    exit;
}
if (!$tieneElectronicas && $tieneGPS) {
    header('Location: inicio.php?module=gpsCredenciales');
    exit;
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Sistema</title>
    <link rel="stylesheet" href="./assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="./assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff 0%, #e8f5e9 100%);
            min-height: 100vh;
        }
        .sistema-card {
            border: none;
            border-radius: 1.25rem;
            transition: transform .2s ease, box-shadow .2s ease;
            cursor: pointer;
        }
        .sistema-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0,0,0,.12) !important;
        }
        .icon-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
        }
        .icon-circle i { font-size: 2.6rem; }
        .btn-entrar {
            border-radius: 50rem;
            padding: .55rem 2rem;
            font-weight: 600;
            letter-spacing: .3px;
        }
        .user-badge {
            background: rgba(255,255,255,.7);
            backdrop-filter: blur(6px);
            border-radius: 50rem;
            padding: .4rem 1.2rem;
            font-size: .9rem;
        }
    </style>
</head>
<body class="d-flex flex-column align-items-center justify-content-center px-3 py-5">

    <!-- Bienvenida -->
    <div class="text-center mb-2">
        <span class="user-badge shadow-sm">
            <i class="bi bi-person-circle me-1 text-primary"></i>
            <strong><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></strong>
            &nbsp;·&nbsp;
            <span class="text-muted"><?= htmlspecialchars($_SESSION['nombre_rol'] ?? '') ?></span>
        </span>
    </div>

    <div class="text-center mb-5 mt-3">
        <h2 class="fw-bold mb-1" style="color:#2c3e50">Bienvenido al sistema</h2>
        <p class="text-muted mb-0">Selecciona el módulo al que deseas acceder</p>
    </div>

    <!-- Cards de sistemas -->
    <div class="row g-4 justify-content-center" style="max-width:800px; width:100%">

        <?php if ($tieneElectronicas): ?>
        <div class="col-sm-10 col-md-5">
            <a href="inicio.php" class="text-decoration-none">
                <div class="card sistema-card shadow text-center p-4 h-100">
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="icon-circle" style="background:#e8eeff">
                            <i class="bi bi-cpu-fill text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-2" style="color:#2c3e50">Inventario Electrónicas</h4>
                        <p class="text-muted small mb-4">
                            Gestión de máquinas, repuestos, mantenimientos y solicitudes de compra.
                        </p>
                        <div class="mt-auto">
                            <span class="btn btn-primary btn-entrar">
                                <i class="bi bi-arrow-right-circle me-1"></i> Entrar
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($tieneGPS): ?>
        <div class="col-sm-10 col-md-5">
            <a href="inicio.php?module=gpsCredenciales" class="text-decoration-none">
                <div class="card sistema-card shadow text-center p-4 h-100">
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="icon-circle" style="background:#e8f5e9">
                            <i class="bi bi-geo-alt-fill text-success"></i>
                        </div>
                        <h4 class="fw-bold mb-2" style="color:#2c3e50">GPS</h4>
                        <p class="text-muted small mb-4">
                            Registro y consulta de credenciales GPS por vehículo y empresa de transporte.
                        </p>
                        <div class="mt-auto">
                            <span class="btn btn-success btn-entrar">
                                <i class="bi bi-arrow-right-circle me-1"></i> Entrar
                            </span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

    </div>

    <!-- Cerrar sesión -->
    <div class="mt-5">
        <a href="./logout.php" class="text-muted small text-decoration-none">
            <i class="bi bi-box-arrow-left me-1"></i> Cerrar sesión
        </a>
    </div>

    <script src="./assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
