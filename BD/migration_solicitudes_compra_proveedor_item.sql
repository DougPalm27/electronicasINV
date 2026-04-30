-- ============================================================
-- Módulo Solicitudes de Compra — proveedor por ítem
-- Ejecutar en SSMS después de migration_solicitudes_compra_externo.sql
-- ============================================================

ALTER TABLE electronicas.SolicitudesCompraDetalle
    ADD id_proveedor INT NULL
        CONSTRAINT FK_SCD_Proveedor
        FOREIGN KEY REFERENCES electronicas.Proveedores(id_proveedor)


        SELECT * FROM electronicas.Proveedores