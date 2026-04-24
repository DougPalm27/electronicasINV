-- ============================================================
-- Agrega id_divisa a electronicas.Repuestos
-- Fecha: 2026-04-23
-- ============================================================
USE [ElectronicasDB];
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Repuestos') AND name = 'id_divisa'
)
BEGIN
    ALTER TABLE electronicas.Repuestos
        ADD id_divisa INT NULL
        CONSTRAINT FK_Repuestos_Divisa
        FOREIGN KEY REFERENCES electronicas.Divisas(id_divisa);

    -- Asignar la divisa predeterminada a los repuestos existentes
    UPDATE electronicas.Repuestos
    SET id_divisa = (
        SELECT TOP 1 id_divisa FROM electronicas.Divisas
        WHERE predeterminada = 1 AND activo = 1
    )
    WHERE id_divisa IS NULL;

    PRINT 'Columna id_divisa agregada y registros existentes actualizados.';
END
ELSE
BEGIN
    PRINT 'La columna id_divisa ya existe. Sin cambios.';
END
GO
