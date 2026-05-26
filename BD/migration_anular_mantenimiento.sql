-- ============================================================
-- Soporte para anulación de mantenimientos
-- Ejecutar en SSMS una sola vez
-- ============================================================

ALTER TABLE electronicas.Mantenimientos
    ADD anulado          BIT           NOT NULL DEFAULT 0,
        motivo_anulacion NVARCHAR(500)     NULL;
