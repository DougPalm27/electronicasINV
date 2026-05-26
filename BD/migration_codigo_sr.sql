-- ============================================================
-- Agrega columna código con nomenclatura SR-00001
-- a la tabla SolicitudesRepuestos.
-- Columna computada y persistida — no requiere trigger.
-- Ejecutar en SSMS una sola vez.
-- ============================================================

ALTER TABLE electronicas.SolicitudesRepuestos
ADD codigo AS (
    'SR-' + CASE
        WHEN id_solicitud < 100000
        THEN RIGHT('00000' + CAST(id_solicitud AS VARCHAR(10)), 5)
        ELSE CAST(id_solicitud AS VARCHAR(10))
    END
) PERSISTED;

CREATE UNIQUE INDEX UX_SR_Codigo ON electronicas.SolicitudesRepuestos (codigo);
