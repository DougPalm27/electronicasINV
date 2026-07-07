-- ============================================================
-- Migración: columna foto en electronicas.Usuarios
-- Fotografía de perfil del usuario, mostrada en el menú
-- superior y en el apartado "Mi perfil".
-- Idempotente: se puede ejecutar más de una vez sin error.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Usuarios')
      AND name = 'foto'
)
BEGIN
    ALTER TABLE electronicas.Usuarios
        ADD foto NVARCHAR(255) NULL;
END
GO
