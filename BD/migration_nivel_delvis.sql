-- ============================================================
-- Candado de operario: nivel de Delvis por usuario (0 a 3)
-- Con el candado abierto por ese usuario, cuando Delvis pide la
-- contraseña de un nivel igual o menor, el candado la escribe.
-- NULL o 0 = ninguno. Ejecutar en SSMS sobre la BD correcta.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Usuarios') AND name = 'nivel_delvis'
)
BEGIN
    ALTER TABLE electronicas.Usuarios
        ADD nivel_delvis TINYINT NULL
            CONSTRAINT CK_Usuarios_nivel_delvis CHECK (nivel_delvis BETWEEN 0 AND 3);

    PRINT 'Columna nivel_delvis agregada a electronicas.Usuarios.';
END
ELSE
BEGIN
    PRINT 'La columna nivel_delvis ya existe en electronicas.Usuarios. Sin cambios.';
END
GO
