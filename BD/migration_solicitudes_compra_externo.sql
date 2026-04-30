-- ============================================================
-- Módulo Solicitudes de Compra — soporte para ítems externos
-- Ejecutar UNA sola vez en SSMS después de migration_solicitudes_compra.sql
-- ============================================================

-- 1. Hacer id_repuesto nullable (ítems externos no tienen repuesto en catálogo)
ALTER TABLE electronicas.SolicitudesCompraDetalle
    ALTER COLUMN id_repuesto INT NULL;

-- 2. Columnas para ítems externos
ALTER TABLE electronicas.SolicitudesCompraDetalle
    ADD nombre_externo VARCHAR(300) NULL,
        enlace_externo VARCHAR(500) NULL;

-- 3. Garantizar que siempre haya id_repuesto O nombre_externo
ALTER TABLE electronicas.SolicitudesCompraDetalle
    ADD CONSTRAINT CK_SCD_TipoItem
    CHECK (id_repuesto IS NOT NULL OR nombre_externo IS NOT NULL);
