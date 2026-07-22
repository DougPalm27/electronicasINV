# Convenciones del proyecto Electronicas

## Stack
- **Backend**: PHP + PDO con SQL Server (`electronicas` schema, driver `pdo_sqlsrv`)
- **Frontend**: Bootstrap 5, jQuery, DataTables, SweetAlert2, Bootstrap Icons (`bi bi-*`)
- **Patrón MVC**: `views/` → `controllers/` → `models/`

---

## Diseño de vistas — regla universal

**Lenguaje visual: "Ficha técnica de taller"** (definido en `assets/css/app.css`):
esquinas rectas, IBM Plex Mono para datos/etiquetas/botones, encabezados de tabla
con banda y regla gruesa, estados como marcas con franja lateral (los `.badge.bg-*`
se estilizan solos desde app.css). No usar `rounded-pill` ni sombras nuevas.
Los códigos (`SOL-00001`, etc.) usan `badge bg-light font-monospace` y se ven como
texto mono verde.

Todas las vistas de módulo siguen el patrón de **dos tarjetas**:

```html
<!-- Tarjeta 1: encabezado con título + meta de ficha + botón de acción -->
<div class="row mb-3">
  <div class="col-12">
    <div class="card shadow-sm border-0 ft-cabecera">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Título del módulo</h5>
          <small class="text-muted">Subtítulo descriptivo</small>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span class="ft-meta d-none d-md-block">Grupo<br>Módulo XXX</span>
          <button class="btn btn-primary" id="btnNuevo">
            <i class="bi bi-plus-lg me-1"></i> Nuevo
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tarjeta 2: tabla -->
<div class="card shadow-sm border-0">
  <div class="card-body">
    <table class="table table-hover w-100" id="tablaModulo">
      <thead><tr>...</tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>
```

### Reglas de la tabla
- Clases: `table table-hover w-100` — **nunca** `table-striped`, `table-sm`, `align-middle`, `table-responsive`
- `<thead>` sin clases de color (`table-dark`, `table-primary`, etc.)
- Sin wrapper `<div class="table-responsive">`
- DataTables con `columns` explícitos en JS y `language` en español

### Columna de acciones
Usar siempre un **dropdown Bootstrap 5**, no botones sueltos:

```javascript
render: function (data, type, row) {
    return `
        <div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle py-1"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                    <button class="dropdown-item btn-ver" type="button"
                            data-id="${row.id_xxx}">
                        <i class="bi bi-eye me-2 text-info"></i>Ver detalle
                    </button>
                </li>
                <li>
                    <button class="dropdown-item btn-editar" type="button"
                            data-id="${row.id_xxx}">
                        <i class="bi bi-pencil me-2 text-warning"></i>Editar
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button class="dropdown-item text-danger btn-eliminar" type="button"
                            data-id="${row.id_xxx}">
                        <i class="bi bi-trash me-2"></i>Eliminar
                    </button>
                </li>
            </ul>
        </div>`;
}
```

- Los event handlers siempre van **delegados** sobre el `#tablaModulo` por clase (`.btn-ver`, `.btn-editar`, etc.)
- Acciones destructivas (eliminar, anular) van al final del menú, separadas con `<hr class="dropdown-divider">` y en `text-danger`

---

## Migraciones SQL
- Van en `BD/migration_<nombre>.sql` en la raíz del proyecto
- Una migración por cambio lógico
- Nombrar con prefijo descriptivo: `migration_anular_mantenimiento.sql`

---

## Roles y permisos (sesión)
- `$_SESSION['nombre_rol']` — valor: `'Administrador'`, `'Técnico'`, etc.
- En JS: `window.USUARIO_ROL` y `window.USUARIO_ID` inyectados por la vista PHP
- Verificar en controller con `$esAdmin = ($_SESSION['nombre_rol'] ?? '') === 'Administrador'`

---

## Kardex de repuestos
- Tabla `MovimientosRepuestos`; columna `referencia` y `observaciones`
- Movimientos de anulación usan `referencia = 'ANULACION'` y describen el origen en `observaciones`
- El kardex en JS detecta `referencia.startsWith('ANULACION')` y muestra badge amarillo

---

## Inventario — reversión de movimientos
Al anular operaciones que mueven stock:
1. Revertir **retiros primero**, luego **instalaciones** (evita stock negativo)
2. Insertar contra-movimiento en `MovimientosRepuestos` (nunca eliminar el original)
3. Usar transacción con `beginTransaction` / `commit` / `rollBack`
