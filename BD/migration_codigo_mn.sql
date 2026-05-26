-- ============================================================
-- Agrega columna código con nomenclatura MN-00001
-- a la tabla Mantenimientos.
-- Columna computada y persistida — no requiere trigger.
-- Ejecutar en SSMS una sola vez.
-- ============================================================

ALTER TABLE electronicas.Mantenimientos
ADD codigo AS (
    'MN-' + CASE
        WHEN id_mantenimiento < 100000
        THEN RIGHT('00000' + CAST(id_mantenimiento AS VARCHAR(10)), 5)
        ELSE CAST(id_mantenimiento AS VARCHAR(10))
    END
) PERSISTED;

CREATE UNIQUE INDEX UX_MN_Codigo ON electronicas.Mantenimientos (codigo);
