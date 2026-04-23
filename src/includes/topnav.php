<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$nombreUsuario = $_SESSION['nombre']  ?? 'Usuario';
$username      = $_SESSION['usuario'] ?? '';
?>
<!-- ======= Header ======= -->
<header id="header" class="header fixed-top d-flex align-items-center">

    <div class="d-flex align-items-center justify-content-between">
        <a href="inicio.php" class="logo d-flex align-items-center">
            <img src="./assets/img/trazabilidad.png" alt="">
            <span class="d-none d-lg-block">Control de Repuestos</span>
        </a>
        <i class="bi bi-list toggle-sidebar-btn"></i>
    </div>

    <nav class="header-nav ms-auto">
        <ul class="d-flex align-items-center">

            <li class="nav-item dropdown pe-3">
                <a class="nav-link nav-profile d-flex align-items-center pe-0"
                   href="#" data-bs-toggle="dropdown">
                    <img src="./assets/img/usuario.png" alt="Profile" class="rounded-circle">
                    <span class="d-none d-md-block dropdown-toggle ps-2">
                        <?= htmlspecialchars($nombreUsuario) ?>
                    </span>
                </a>

                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                        <h6><?= htmlspecialchars($nombreUsuario) ?></h6>
                        <span><?= htmlspecialchars($username) ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center"
                           href="/Electronicas/logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            <span>Cerrar sesión</span>
                        </a>
                    </li>
                </ul>
            </li>

        </ul>
    </nav>

</header>
