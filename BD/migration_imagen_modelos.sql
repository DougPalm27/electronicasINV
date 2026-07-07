-- ============================================================
-- Migración: columna imagen en electronicas.Modelos
-- Permite asociar una fotografía a cada modelo de máquina,
-- usada en las tarjetas de la pantalla de Máquinas.
-- Idempotente: se puede ejecutar más de una vez sin error.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Modelos')
      AND name = 'imagen'
)
BEGIN
    ALTER TABLE electronicas.Modelos
        ADD imagen NVARCHAR(255) NULL;
END
GO
