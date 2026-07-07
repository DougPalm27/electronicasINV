# Checklist — Revisión módulo Repuestos / Compras / Solicitudes

Fecha de revisión: 2026-07-02
Marcar con `[x]` al resolver. Referencias de archivo incluidas en cada ítem.

## Fase 1 — Crítico: integridad del inventario

- [x] **1. Kardex para entradas por serie** — `entradaSerie` (con transacción) y la recepción de series de OC ahora insertan un movimiento de entrada en `MovimientosRepuestos` por cada serie, con `id_detalle_repuesto`, proveedor, costo (el confirmado o el de la solicitud, en Lempiras) y `tipo_entrada`. La entrada manual por serie ahora envía proveedor y tipo de entrada desde el modal. Se agregó "Ver kardex" al menú de repuestos por serie. Además la recepción de series guarda `costo_recibido` en el detalle de la OC. ✅ 2026-07-02
- [x] **2. Transacciones en movimientos manuales** — `entradaRepuesto`, `salidaRepuesto`, `salidaSerie`, `anularMovimiento` (mdlRepuestos.php) envueltos en `beginTransaction`/`commit`/`rollBack`. ✅ 2026-07-02
- [x] **3. Condición de carrera en stock** — Patrón atómico `UPDATE ... SET stock = stock ± ? OUTPUT DELETED.stock, INSERTED.stock` (salidas con `AND stock >= ?`). Aplicado en mdlRepuestos.php (entrada/salida/anulación), mdlSolicitudes.php (aprobación) y mdlSolicitudesCompra.php (recepción, incluye promedio ponderado dentro del mismo UPDATE). Además: `salidaSerie` ahora toma la serie con condición `id_estado_repuesto = 1` (dos salidas concurrentes no pueden tomar la misma serie) y `anularMovimiento` marca el original con guardia `anulado = 0` (no se puede anular dos veces). ✅ 2026-07-02
- [x] **4. Validar sobre-recepción en backend** — `registrarRecepcion` limita lo recibido a lo pendiente en las tres ramas (externo, cantidad y serie — en serie recorta la lista de series); si el POST trae de más, registra solo el pendiente y devuelve warning. ✅ 2026-07-02

## Fase 2 — Seguridad y permisos

- [x] **5. Verificación de rol en repuestosController.php** — Check `$esAdmin` en acciones destructivas/de auditoría: `eliminar`, `anularMovimiento`, `editarDetalle`, `cambiarEstadoDetalle`. Entradas/salidas quedan abiertas a Técnico porque el rol tiene el módulo asignado como operación legítima (migration_roles.sql). UI: repuestos.js oculta "Desechar", botón anular del kardex y edición de series para no-admin. ✅ 2026-07-02
- [x] **6. Parámetros en vez de interpolación SQL** — `listarSolicitudes` (ambos modelos) y `cancelarSolicitud` (compras) ahora usan parámetros preparados para `id_usuario` y la lista de estados. ✅ 2026-07-02

## Fase 3 — Desconexiones entre módulos

- [x] **7. Anulación de entrada OC sincronizada con compras** — `anularMovimiento` detecta referencia `OC-*`: revierte `cantidad_recibida` en `SolicitudesCompraDetalle` y regresa la solicitud de 'Recibida' a 'Recibida parcial', todo en la misma transacción. Movimientos `SOL-*` quedan bloqueados desde el kardex (deben revertirse anulando el mantenimiento generado). El contra-movimiento ahora registra el origen en `observaciones` (convención CLAUDE.md). ✅ 2026-07-02
- [x] **8. Cancelar "Recibida parcial" deja rastro** — `cancelarSolicitud` registra en `notas` (con fecha) los ítems y cantidades ya ingresadas al inventario al momento de cancelar. ✅ 2026-07-02
- [x] **9. Puente stock bajo → solicitud de compra** — Opción "Solicitar compra" en el menú del repuesto cuando stock ≤ mínimo: guarda el repuesto en sessionStorage, navega a `?module=compras` y el modal de nueva solicitud se abre precargado (ítem, cantidad sugerida = mínimo − stock, costo promedio como referencia, descripción "Reposición de stock"). ✅ 2026-07-02
- [x] **10. Stock comprometido en solicitudes** — Informativo (opción elegida por el usuario): el selector de repuestos del técnico muestra "(N comprom.)" junto al stock cuando hay cantidades en otras solicitudes pendientes, y el modal de revisión del admin muestra "N comprom. en otras" por ítem (subconsulta que excluye la solicitud en revisión). ✅ 2026-07-02

## Fase 4 — Consistencia y menores

- [x] **11. Unificar criterio de repuesto activo** — Criterio único `id_estado != 5` en los selectores de solicitudes (que no filtraba) y compras (que usaba `= 1`), y en la validación de `crearSolicitud`. ✅ 2026-07-02
- [x] **12. Referencia según tipo de entrada** — La entrada manual usa como referencia el tipo real en mayúsculas (COMPRA, GARANTÍA, DEVOLUCIÓN, DONACIÓN, AJUSTE DE INVENTARIO). ✅ 2026-07-02
- [x] **13. Costo promedio en entrada manual** — `entradaRepuesto` recalcula promedio ponderado dentro del UPDATE atómico cuando la entrada trae costo > 0 (entradas sin costo, como garantías, no alteran el promedio). ✅ 2026-07-02
- [x] **14. Validaciones en eliminarRepuesto** — Bloquea si hay stock (o series disponibles), si está en solicitudes de repuestos pendientes o en solicitudes de compra activas. ✅ 2026-07-02
- [x] **15. Auditoría de usuario en kardex** — ⚠️ **Requiere ejecutar `BD/migration_usuario_movimientos.sql` en SSMS ANTES de usar la app.** La columna `id_usuario` se llena sola por DEFAULT con `SESSION_CONTEXT('id_usuario')`, que `config/connection.php` establece al conectar — cubre los 16 INSERT existentes y cualquier INSERT futuro sin tocarlos. El kardex muestra el usuario de cada movimiento. ✅ 2026-07-02

## Hallazgos posteriores (agregados durante la implementación)

- [x] **16. Condición de carrera en mdlMantenimientos.php** — Patrón atómico `UPDATE ... OUTPUT` aplicado a: instalación por cantidad, retiro con devolución, reversión de retiros e instalaciones en `ejecutarAnulacion`, y reversión por edición. Esta última además restauraba el stock a un valor histórico (`stock_anterior` del movimiento), pisando movimientos posteriores — ahora suma/resta cantidades relativas, y la consulta usa subconsulta en vez de JOIN para no duplicar reversiones. ✅ 2026-07-02
- [x] **17. Salidas manuales y de mantenimiento compartían referencia 'MANTENIMIENTO'** — Las salidas manuales del módulo Repuestos ahora usan `'SALIDA MANUAL'` por defecto (JS, controller y modelo). Las consultas de anulación de mantenimientos que buscan `'MANTENIMIENTO'` ya no chocan con salidas manuales nuevas. Los movimientos históricos conservan la referencia vieja. ✅ 2026-07-02

## Revisión del kardex (cumplimiento como kardex de inventario)

- [x] **18. Saldo corrido para repuestos por serie** — Todos los movimientos por serie (entrada manual, recepción de OC, salida manual, instalación/retiro/anulación de mantenimiento) ahora graban `stock_anterior/stock_nuevo` con el conteo real de series disponibles (helper `contarSeriesDisponibles` en los 3 modelos). Los movimientos históricos conservan 0/0. ✅ 2026-07-02
- [x] **19. Valorización del kardex (parcial)** — Nueva columna "Importe (L)" (cantidad × costo del movimiento), resumen del período con totales de entradas/salidas en unidades e importes, y valuación actual del inventario (stock × promedio) en el encabezado del modal. Pendiente para una fase futura: almacenar el costo promedio vigente tras cada movimiento (requiere migración) para reconstruir el saldo valorizado histórico exacto, y valorar retiros/devoluciones que entran con costo 0. ✅ 2026-07-02
- [x] **20. Costo de salidas al promedio vigente** — Toda salida se valora automáticamente al costo promedio del momento, tomado con `OUTPUT DELETED.costo_promedio` del mismo UPDATE atómico (salida manual, aprobación de solicitud, instalación de mantenimiento) o leído en transacción (salidas por serie). El campo de costo del modal de salida quedó deshabilitado ("Automático"); el front ya no lo envía. ✅ 2026-07-02
- [x] **21. Cambios de estado de series sin movimiento** — `editarDetalle`/`cambiarEstadoDetalle` ahora comparten un núcleo transaccional que, si el cambio de estado altera la disponibilidad, inserta un movimiento `'AJUSTE SERIE'` con saldo corrido y observación "Cambio de estado manual: X → Y". También valida serie duplicada al renombrar. ✅ 2026-07-02
- [x] **22. Ajuste negativo de inventario** — Nueva acción `ajusteNegativo` (solo admin): salida sin máquina con motivo obligatorio, referencia `'AJUSTE INVENTARIO'`, valorada al costo promedio, con descuento atómico. UI: opción "Ajuste de inventario" en el menú de repuestos por cantidad (diálogo con cantidad + motivo). Para series, la baja con rastro ya la cubre el ítem 21. ✅ 2026-07-02
- [x] **23. Orden estable del kardex** — `obtenerKardex` ordena por fecha con desempate por `id_movimiento`. ✅ 2026-07-02
- [x] **24. Corte de período** — Filtro Desde/Hasta en el modal del kardex; `obtenerKardex` acepta rango y devuelve `saldo_inicial` (último saldo antes del período) + movimientos. La tabla abre con fila "Saldo inicial" y cierra con resumen (entradas/salidas del período en unidades e importes, saldo inicial y final). La impresión incluye el período y el resumen. Nota: la fila "Punto de partida" fue reemplazada por la fila de saldo inicial; el primer movimiento ahora es anulable como cualquier otro (el backend valida stock). ✅ 2026-07-02
- [x] **25. Moneda explícita en el kardex** — Encabezados "Costo unit. (L)" / "Importe (L)", nota "Valuación en Lempiras (L)" en el modal y en la impresión. ✅ 2026-07-02
