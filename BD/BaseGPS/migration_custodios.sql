-- ============================================================
-- Módulo GPS — Custodios (directorio de nombres y teléfonos)
-- Base: ElectronicasDB  |  Schema: gps
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.Custodios', 'U') IS NULL
BEGIN
    CREATE TABLE gps.Custodios (
        id_custodio         INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
        nombre              NVARCHAR(150) NOT NULL,
        telefono            NVARCHAR(30)  NULL,
        activo              BIT           NOT NULL DEFAULT 1,
        fecha_creacion      DATETIME      NOT NULL DEFAULT GETDATE(),
        fecha_actualizacion DATETIME      NULL,
        creado_por          INT           NULL
            REFERENCES electronicas.Usuarios(id_usuario),
        actualizado_por     INT           NULL
            REFERENCES electronicas.Usuarios(id_usuario)
    );
END
GO
