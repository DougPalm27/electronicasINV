-- ============================================================
-- Agrega proveedor y tipo de entrada a MovimientosRepuestos
-- Ejecutar en SSMS una sola vez
-- ============================================================

ALTER TABLE electronicas.MovimientosRepuestos
    ADD id_proveedor INT          NULL,
        tipo_entrada VARCHAR(60)  NOT NULL DEFAULT 'Compra';

ALTER TABLE electronicas.MovimientosRepuestos
    ADD CONSTRAINT FK_Movimientos_Proveedor
        FOREIGN KEY (id_proveedor)
        REFERENCES electronicas.Proveedores(id_proveedor);
