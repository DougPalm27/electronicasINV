-- ============================================================
-- Mueve fecha_entrega_est de la cabecera al detalle (por ítem)
-- Ejecutar en SSMS una sola vez
-- ============================================================

-- 1. Agregar columna en el detalle
ALTER TABLE electronicas.SolicitudesCompraDetalle
    ADD fecha_entrega_est DATE NULL;

-- 2. Quitar columna de la cabecera
ALTER TABLE electronicas.SolicitudesCompra
    DROP COLUMN fecha_entrega_est;
