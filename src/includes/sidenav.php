<?php
$mod = $_GET['module'] ?? '';

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
?>
  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">

      <!-- Dashboard -->
      <li class="nav-item">
        <a class="<?= ($mod === '' || $mod === 'dasboard') ? 'nav-link' : 'nav-link collapsed' ?>"
           href="?module=dasboard">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- Mantenimientos -->
      <li class="nav-item">
        <a class="<?= navLink('mantenimientos', $mod) ?>" href="?module=mantenimientos">
          <i class="bi bi-laptop"></i>
          <span>Mantenimiento</span>
        </a>
      </li>

      <!-- Máquinas -->
      <li class="nav-item">
        <a class="<?= navLink('maquinas', $mod) ?>" href="?module=maquinas">
          <i class="bi bi-cpu"></i>
          <span>Máquinas</span>
        </a>
      </li>

      <!-- Repuestos -->
      <li class="nav-item">
        <a class="<?= navLink('repuestos', $mod) ?>" href="?module=repuestos">
          <i class="bi bi-box-seam"></i>
          <span>Repuestos</span>
        </a>
      </li>

      <!-- Parametrización (grupo colapsable) -->
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
          <li>
            <a href="?module=proveedores" class="<?= navSubItem('proveedores', $mod) ?>">
              <i class="bi bi-circle"></i><span>Proveedores</span>
            </a>
          </li>
          <li>
            <a href="?module=marcas" class="<?= navSubItem('marcas', $mod) ?>">
              <i class="bi bi-circle"></i><span>Marcas</span>
            </a>
          </li>
          <li>
            <a href="?module=modelos" class="<?= navSubItem('modelos', $mod) ?>">
              <i class="bi bi-circle"></i><span>Modelos</span>
            </a>
          </li>
          <li>
            <a href="?module=tiposRepuestos" class="<?= navSubItem('tiposRepuestos', $mod) ?>">
              <i class="bi bi-circle"></i><span>Tipos de Repuestos</span>
            </a>
          </li>
          <li>
            <a href="?module=divisas" class="<?= navSubItem('divisas', $mod) ?>">
              <i class="bi bi-circle"></i><span>Divisas</span>
            </a>
          </li>
        </ul>
      </li>

      <!-- Usuarios -->
      <li class="nav-item">
        <a class="<?= navLink('usuarios', $mod) ?>" href="?module=usuarios">
          <i class="bi bi-people"></i>
          <span>Usuarios</span>
        </a>
      </li>

    </ul>
  </aside><!-- End Sidebar-->
