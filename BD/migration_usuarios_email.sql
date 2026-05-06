-- Agrega columna email a la tabla Usuarios
-- Ejecutar una sola vez en ElectronicasDB

ALTER TABLE electronicas.Usuarios
    ADD email NVARCHAR(150) NULL;
