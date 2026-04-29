-- ============================================================
-- Migración: Cambiar FK de Mantenimientos.id_tecnico
--            de Tecnicos  →  Usuarios(id_usuario)
-- Ejecutar en SSMS
-- ============================================================

-- 1. Eliminar la FK existente (ajusta el nombre si difiere)
IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('electronicas.Mantenimientos')
      AND name LIKE '%Tecnico%'
)
BEGIN
    DECLARE @fk NVARCHAR(256);
    SELECT @fk = name FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('electronicas.Mantenimientos')
      AND name LIKE '%Tecnico%';
    EXEC('ALTER TABLE electronicas.Mantenimientos DROP CONSTRAINT ' + @fk);
END

-- 2. Limpiar referencias huérfanas (si las hubiera)
UPDATE electronicas.Mantenimientos
SET id_tecnico = NULL
WHERE id_tecnico IS NOT NULL
  AND id_tecnico NOT IN (SELECT id_usuario FROM electronicas.Usuarios);

-- 3. Agregar nueva FK apuntando a Usuarios
ALTER TABLE electronicas.Mantenimientos
    ADD CONSTRAINT FK_Mantenimientos_Tecnico_Usuario
        FOREIGN KEY (id_tecnico) REFERENCES electronicas.Usuarios(id_usuario);
