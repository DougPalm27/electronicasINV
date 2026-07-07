-- ============================================================
-- Migración: auditoría de usuario en MovimientosRepuestos
-- Ejecutar en SSMS una sola vez
--
-- La columna id_usuario se llena AUTOMÁTICAMENTE vía DEFAULT
-- con SESSION_CONTEXT('id_usuario'), que config/connection.php
-- establece al abrir cada conexión con el usuario logueado.
-- Ningún INSERT existente necesita modificarse; los INSERT
-- futuros también quedan cubiertos.
-- Requiere SQL Server 2016 o superior.
-- ============================================================

ALTER TABLE electronicas.MovimientosRepuestos
    ADD id_usuario INT NULL
        CONSTRAINT DF_MovRep_Usuario
            DEFAULT (CONVERT(INT, SESSION_CONTEXT(N'id_usuario')))
        CONSTRAINT FK_MovRep_Usuario
            REFERENCES electronicas.Usuarios (id_usuario);
GO

CREATE INDEX IX_MovRep_Usuario
    ON electronicas.MovimientosRepuestos (id_usuario);
GO
