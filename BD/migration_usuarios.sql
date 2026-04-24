-- ============================================================
-- Tabla: electronicas.Usuarios
-- Fecha: 2026-04-23
-- ============================================================
USE [ElectronicasDB];
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'Usuarios'
)
BEGIN
    CREATE TABLE electronicas.Usuarios (
        id_usuario      INT IDENTITY(1,1)   NOT NULL,
        username        VARCHAR(50)         NOT NULL,
        password_hash   VARCHAR(255)        NOT NULL,   -- bcrypt via PHP password_hash()
        nombre          VARCHAR(150)        NOT NULL,
        activo          BIT                 NOT NULL    CONSTRAINT DF_Usuarios_activo    DEFAULT 1,
        fecha_registro  DATETIME            NOT NULL    CONSTRAINT DF_Usuarios_fecha_reg DEFAULT GETDATE(),

        CONSTRAINT PK_Usuarios          PRIMARY KEY CLUSTERED (id_usuario ASC),
        CONSTRAINT UQ_Usuarios_username UNIQUE (username)
    );

    PRINT 'Tabla electronicas.Usuarios creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.Usuarios ya existe. Sin cambios.';
END
GO
