-- ============================================================
-- Migración: cambiar id_tecnico en SolicitudesRepuestos
-- para que apunte a Usuarios en lugar de Tecnicos
-- ============================================================

-- 1. Eliminar FK vieja (que apuntaba a Tecnicos)
ALTER TABLE electronicas.SolicitudesRepuestos
    DROP CONSTRAINT FK_Sol_Tecnico;

-- 2. Agregar FK nueva apuntando a Usuarios
ALTER TABLE electronicas.SolicitudesRepuestos
    ADD CONSTRAINT FK_Sol_Tecnico_User
        FOREIGN KEY (id_tecnico) REFERENCES electronicas.Usuarios(id_usuario);
