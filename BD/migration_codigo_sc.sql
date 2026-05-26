-- ============================================================
-- Agrega columna código con nomenclatura SC-00001
-- Columna computada y persistida — no requiere trigger
-- Ejecutar en SSMS una sola vez
-- ============================================================

ALTER TABLE electronicas.SolicitudesCompra
ADD codigo AS (
    'SC-' + CASE
        WHEN id_solicitud_compra < 100000
        THEN RIGHT('00000' + CAST(id_solicitud_compra AS VARCHAR(10)), 5)
        ELSE CAST(id_solicitud_compra AS VARCHAR(10))
    END
) PERSISTED;

CREATE UNIQUE INDEX UX_SC_Codigo ON electronicas.SolicitudesCompra (codigo);
