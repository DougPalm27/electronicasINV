<?php
require_once __DIR__ . '/navContext.php';

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
    return $modulo === $actual ? 'nav-link active' : 'nav-link';
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

    <a href="inicio.php" class="sidebar-brand">
      <span class="brand-icon"><i class="bi bi-box-seam"></i></span>
      <span>
        <span class="brand-name">Honducafe</span>
        <span class="brand-sub"><?= htmlspecialchars(nombreSeccion(moduloActual())) ?></span>
      </span>
    </a>

    <ul class="sidebar-nav" id="sidebar-nav">

      <!-- Dashboard -->
      <?php if (puedeVer('dasboard')): ?>
      <li class="nav-item">
        <a class="<?= ($mod === '' || $mod === 'dasboard') ? 'nav-link active' : 'nav-link' ?>"
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

      <!-- Manual Satake -->
      <?php if (puedeVer('satake')): ?>
      <li class="nav-item">
        <a class="<?= navLink('satake', $mod) ?>" href="?module=satake">
          <i class="bi bi-journal-medical"></i>
          <span>Manual Satake</span>
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

      <!-- Componentes -->
      <?php if (puedeVer('componentes')): ?>
      <li class="nav-item">
        <a class="<?= navLink('componentes', $mod) ?>" href="?module=componentes">
          <i class="bi bi-diagram-3"></i>
          <span>Componentes</span>
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

      <!-- ── Configuración ───────────────────────────────── -->
      <?php
      $gpsParamModulos  = ['gpsTiposVehiculo','gpsDestinos','gpsPlataformas'];
      $gpsParamOpen     = in_array($mod, $gpsParamModulos);
      $gpsParamVisible  = false;
      foreach ($gpsParamModulos as $gp) { if (puedeVer($gp)) { $gpsParamVisible = true; break; } }

      $configVisible = puedeVer('usuarios') || puedeVer('roles') || $paraVisible || $gpsParamVisible;
      ?>
      <?php if ($configVisible): ?>
      <li class="nav-heading">Configuración</li>

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

      <!-- Parametrización (grupo colapsable) -->
      <?php if ($paraVisible): ?>
      <li class="nav-item">
        <a class="nav-link <?= $paraOpen ? '' : 'collapsed' ?>"
           data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-sliders"></i>
          <span>Parametrización</span>
          <i class="bi bi-chevron-down"></i>
        </a>
        <ul id="tables-nav"
            class="nav-content collapse <?= $paraOpen ? 'show' : '' ?>"
            data-bs-parent="#sidebar-nav">
          <?php if (puedeVer('proveedores')): ?>
          <li>
            <a href="?module=proveedores" class="<?= navSubItem('proveedores', $mod) ?>">
              <span>Proveedores</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('marcas')): ?>
          <li>
            <a href="?module=marcas" class="<?= navSubItem('marcas', $mod) ?>">
              <span>Marcas</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('modelos')): ?>
          <li>
            <a href="?module=modelos" class="<?= navSubItem('modelos', $mod) ?>">
              <span>Modelos</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('tiposRepuestos')): ?>
          <li>
            <a href="?module=tiposRepuestos" class="<?= navSubItem('tiposRepuestos', $mod) ?>">
              <span>Tipos de Repuestos</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('divisas')): ?>
          <li>
            <a href="?module=divisas" class="<?= navSubItem('divisas', $mod) ?>">
              <span>Divisas</span>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>

      <!-- Parametrización GPS (grupo colapsable) -->
      <?php if ($gpsParamVisible): ?>
      <li class="nav-item">
        <a class="nav-link <?= $gpsParamOpen ? '' : 'collapsed' ?>"
           data-bs-target="#gps-param-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-gear"></i>
          <span>Parametrización GPS</span>
          <i class="bi bi-chevron-down"></i>
        </a>
        <ul id="gps-param-nav"
            class="nav-content collapse <?= $gpsParamOpen ? 'show' : '' ?>"
            data-bs-parent="#sidebar-nav">
          <?php if (puedeVer('gpsTiposVehiculo')): ?>
          <li>
            <a href="?module=gpsTiposVehiculo" class="<?= navSubItem('gpsTiposVehiculo', $mod) ?>">
              <span>Tipos de Vehículo</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('gpsDestinos')): ?>
          <li>
            <a href="?module=gpsDestinos" class="<?= navSubItem('gpsDestinos', $mod) ?>">
              <span>Destinos</span>
            </a>
          </li>
          <?php endif; ?>
          <?php if (puedeVer('gpsPlataformas')): ?>
          <li>
            <a href="?module=gpsPlataformas" class="<?= navSubItem('gpsPlataformas', $mod) ?>">
              <span>Plataformas</span>
            </a>
          </li>
          <?php endif; ?>
        </ul>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <!-- ── GPS ─────────────────────────────────────────── -->
      <?php
      // Solo los módulos operativos; la parametrización GPS vive en Configuración
      $gpsVisible = false;
      foreach (['gpsMapa','gpsCredenciales','gpsTransportes','gpsCuentas'] as $g) {
          if (puedeVer($g)) { $gpsVisible = true; break; }
      }
      ?>
      <?php if ($gpsVisible): ?>
      <li class="nav-heading">GPS</li>

      <?php if (puedeVer('gpsMapa')): ?>
      <li class="nav-item">
        <a class="<?= navLink('gpsMapa', $mod) ?>" href="?module=gpsMapa">
          <i class="bi bi-map"></i>
          <span>Mapa GPS</span>
        </a>
      </li>
      <?php endif; ?>

      <?php if (puedeVer('gpsCredenciales')): ?>
      <li class="nav-item">
        <a class="<?= navLink('gpsCredenciales', $mod) ?>" href="?module=gpsCredenciales">
          <i class="bi bi-geo-alt"></i>
          <span>Credenciales GPS</span>
        </a>
      </li>
      <?php endif; ?>

      <?php if (puedeVer('gpsTransportes')): ?>
      <li class="nav-item">
        <a class="<?= navLink('gpsTransportes', $mod) ?>" href="?module=gpsTransportes">
          <i class="bi bi-truck"></i>
          <span>Transportes</span>
        </a>
      </li>
      <?php endif; ?>

      <?php if (puedeVer('gpsCuentas')): ?>
      <li class="nav-item">
        <a class="<?= navLink('gpsCuentas', $mod) ?>" href="?module=gpsCuentas">
          <i class="bi bi-key"></i>
          <span>Cuentas GPS</span>
        </a>
      </li>
      <?php endif; ?>

      <?php endif; ?>

      <!-- ── RRHH ────────────────────────────────────────── -->
      <?php if (puedeVer('horariosMonitoreo')): ?>
      <li class="nav-heading">RRHH</li>
      <li class="nav-item">
        <a class="<?= navLink('horariosMonitoreo', $mod) ?>" href="?module=horariosMonitoreo">
          <i class="bi bi-calendar-week"></i>
          <span>Horarios Monitoreo</span>
        </a>
      </li>
      <?php endif; ?>

    </ul>
  </aside><!-- End Sidebar-->
