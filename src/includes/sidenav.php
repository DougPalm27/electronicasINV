<?php
$mod = $_GET['module'] ?? '';

// ── Permisos de módulos ─────────────────────────────────
// null = sin restricción (legado / sin rol); array = solo los permitidos
$_modulos_permitidos = $_SESSION['modulos'] ?? null;

function puedeVer(string $clave): bool {
    global $_modulos_permitidos;
    if ($_modulos_permitidos === null) return true;
    return in_array($clave, $_modulos_permitidos);
}

// Helpers para clases activas
function navLink(string $modulo, string $actual): string {
    return $modulo === $actual ? 'nav-link' : 'nav-link collapsed';
}
function navSubItem(string $modulo, string $actual): string {
    return $modulo === $actual ? 'active' : '';
}

// El grupo "Parametrización" debe estar abierto si el módulo activo pertenece a él
$parametrizacion = ['proveedores','marcas','modelos','tiposRepuestos','divisas'];
$paraOpen = in_array($mod, $parametrizacion);

// Verificar si hay al menos un módulo de parametrización visible
$paraVisible = false;
foreach ($parametrizacion as $p) {
    if (puedeVer($p)) { $paraVisible = true; break; }
}
?>
  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

      <!-- Dashboard -->
      <?php if (puedeVer('dasboard')): ?>
      <li class="nav-item">
        <a class="<?= ($mod === '' || $mod === 'dasboard') ? 'nav-link' : 'nav-link collapsed' ?>"
           href="?module=dasboard">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Mantenimientos -->
      <?php if (puedeVer('mantenimientos')): ?>
      <li class="nav-item">
        <a class="<?= navLink('mantenimientos', $mod) ?>" href="?module=mantenimientos">
          <i class="bi bi-laptop"></i>
          <span>Mantenimiento</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Máquinas -->
      <?php if (puedeVer('maquinas')): ?>
      <li class="nav-item">
        <a class="<?= navLink('maquinas', $mod) ?>" href="?module=maquinas">
          <i class="bi bi-cpu"></i>
          <span>Máquinas</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Repuestos -->
      <?php if (puedeVer('repuestos')): ?>
      <li class="nav-item">
        <a class="<?= navLink('repuestos', $mod) ?>" href="?module=repuestos">
          <i class="bi bi-box-seam"></i>
          <span>Repuestos</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Solicitudes de Repuestos -->
      <?php if (puedeVer('solicitudes')): ?>
      <li class="nav-item">
        <a class="<?= navLink('solicitudes', $mod) ?>" href="?module=solicitudes">
          <i class="bi bi-clipboard-check"></i>
          <span>Solicitudes</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Solicitudes de Compra -->
      <?php if (puedeVer('compras')): ?>
      <li class="nav-item">
        <a class="<?= navLink('compras', $mod) ?>" href="?module=compras">
          <i class="bi bi-cart-check"></i>
          <span>Compras</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Parametrización (grupo colapsable) -->
      <?php if ($paraVisible): ?>
      <li class="nav-item">
        <a class="nav-link <?= $paraOpen ? '' : 'collapsed' ?>"
           data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-layout-text-window-reverse"></i>
          <span>Parametrización</span>
          <i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="tables-nav"
            class="nav-content collapse <?= $paraOpen ? 'show' : '' ?>"
            data-bs-parent="#sidebar-nav">
          <?php if (puedeVer('proveedores')): ?>
          <li>
            <a href="?module=proveedores" class="<?= navSubItem('proveedores', $mod) ?>">
              <i class="bi bi-circle"></i><span>Proveedores</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('marcas')): ?>
          <li>
            <a href="?module=marcas" class="<?= navSubItem('marcas', $mod) ?>">
              <i class="bi bi-circle"></i><span>Marcas</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('modelos')): ?>
          <li>
            <a href="?module=modelos" class="<?= navSubItem('modelos', $mod) ?>">
              <i class="bi bi-circle"></i><span>Modelos</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('tiposRepuestos')): ?>
          <li>
            <a href="?module=tiposRepuestos" class="<?= navSubItem('tiposRepuestos', $mod) ?>">
              <i class="bi bi-circle"></i><span>Tipos de Repuestos</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('divisas')): ?>
          <li>
            <a href="?module=divisas" class="<?= navSubItem('divisas', $mod) ?>">
              <i class="bi bi-circle"></i><span>Divisas</span>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- Usuarios -->
      <?php if (puedeVer('usuarios')): ?>
      <li class="nav-item">
        <a class="<?= navLink('usuarios', $mod) ?>" href="?module=usuarios">
          <i class="bi bi-people"></i>
          <span>Usuarios</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Roles y Permisos -->
      <?php if (puedeVer('roles')): ?>
      <li class="nav-item">
        <a class="<?= navLink('roles', $mod) ?>" href="?module=roles">
          <i class="bi bi-shield-lock"></i>
          <span>Roles y Permisos</span>
        </a>
      </li>
      <?php endif; ?>

    </ul>
  </aside><!-- End Sidebar-->
