  <!-- ======= Sidebar ======= -->
  <aside id="sidebar" class="sidebar">

    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link " href="?module=dasboard">
          <i class="bi bi-grid"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <li hidden class="nav-item">
        <a class="nav-link collapsed" href="?module=asignacion">
          <i class="bi bi-arrow-up-right-square-fill"></i>
          <span>Asignar equipo</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link collapsed" href="?module=mantenimientos">
          <i class="bi bi-laptop"></i>
          <span>Matenimiento</span>
        </a>
      </li><!-- End Profile Page Nav -->
      <li class="nav-item">
        <a class="nav-link collapsed" href="?module=maquinas">
          <i class="bi bi-phone"></i>
          <span>Maquinas</span>
        </a>
      </li><!-- End Profile Page Nav -->
      <li class="nav-item">
        <a class="nav-link collapsed" href="?module=repuestos">
          <i class="bi bi-palette2"></i>
          <span>Repuestos</span>
        </a>
      </li><!-- End Profile Page Nav -->
      <li class="nav-item" hidden>
        <a class="nav-link collapsed" data-bs-target="#forms-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-journal-text"></i><span>Reportes</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="forms-nav" class="nav-content " data-bs-parent="#sidebar-nav">

          <li>
            <a href="?module=r1" class="listaProductor">
              <i class="bi bi-circle"></i><span>Reporte de Inventario General</span>
            </a>
          </li>
          <li>
            <a href="?module=r2" class="listaProductor">
              <i class="bi bi-circle"></i><span>Reporte de Inventario Historico</span>
            </a>
          </li>
          <li>
            <a href="?module=r3" class="listaProductor">
              <i class="bi bi-circle"></i><span>Reporte de Equipo Total por categoria y Estado</span>
            </a>
          </li>
          <li>
            <a href="?module=r4" class="listaProductor">
              <i class="bi bi-circle"></i><span>Reporte de Asignaciones</span>
            </a>
          </li>
          <li>
            <a href="?module=r5" class="listaProductor">
              <i class="bi bi-circle"></i><span>Equipo Dañado o Reparación</span>
            </a>
          </li>
        </ul>
      </li><!-- End Forms Nav -->
      <li class="nav-item">
        <a class="nav-link collapsed" data-bs-target="#tables-nav" data-bs-toggle="collapse" href="#">
          <i class="bi bi-layout-text-window-reverse"></i><span>Formularios de parametrizacion</span><i class="bi bi-chevron-down ms-auto"></i>
        </a>
        <ul id="tables-nav" class="nav-content collapse " data-bs-parent="#sidebar-nav">
          <li>
            <a href='?module=proveedores'>
              <i class="bi bi-circle"></i><span>Proveedores</span>
            </a>
          </li>
          <li>
            <a href='?module=marcas'>
              <i class="bi bi-circle"></i><span>Marcas</span>
            </a>
          </li>
          <li>
            <a href='?module=modelos'>
              <i class="bi bi-circle"></i><span>Modelos</span>
            </a>
          </li>
          <li>
            <a href='?module=tiposRepuestos'>
              <i class="bi bi-circle"></i><span>Tipos de Repuestos</span>
            </a>
          </li>
        </ul>
      </li>
  </aside><!-- End Sidebar-->