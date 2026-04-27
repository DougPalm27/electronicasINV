-- ============================================================
-- Agrega columna anulado a MovimientosRepuestos
-- Ejecutar una sola vez en SSMS
-- ============================================================

ALTER TABLE electronicas.MovimientosRepuestos
    ADD anulado BIT NOT NULL DEFAULT 0;

-- Índice para filtrar/consultar rápido
CREATE INDEX IX_MovimientosRepuestos_anulado
    ON electronicas.MovimientosRepuestos (anulado);
