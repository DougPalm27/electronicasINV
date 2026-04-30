-- ============================================================
-- Módulo GPS — Migración para ElectronicasDB
-- Ejecutar conectado a la base de datos ElectronicasDB
-- ============================================================

USE ElectronicasDB;
GO

-- ── 1. Crear schema gps ──────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'gps')
BEGIN
    EXEC('CREATE SCHEMA gps');
END
GO

-- ── 2. Tabla de empresas de transporte ───────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'gps' AND t.name = 'Transportes'
)
BEGIN
    CREATE TABLE gps.Transportes (
        id_transporte   INT             NOT NULL IDENTITY(1,1) PRIMARY KEY,
        nombre          NVARCHAR(150)   NOT NULL,
        contacto        NVARCHAR(100)   NULL,
        telefono        NVARCHAR(20)    NULL,
        activo          BIT             NOT NULL DEFAULT 1,
        created_at      DATETIME        NOT NULL DEFAULT GETDATE()
    );
END
GO

-- ── 3. Tabla de credenciales GPS por vehículo ────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'gps' AND t.name = 'GPS'
)
BEGIN
    CREATE TABLE gps.GPS (
        id_gps          INT             NOT NULL IDENTITY(1,1) PRIMARY KEY,
        id_transporte   INT             NULL,
        tipo_vehiculo   NVARCHAR(50)    NULL,
        placa           NVARCHAR(20)    NOT NULL,
        plataforma      NVARCHAR(500)   NULL,
        destino         NVARCHAR(150)   NULL,
        usuario         NVARCHAR(100)   NULL,
        contrasena      NVARCHAR(150)   NULL,
        estado          BIT             NOT NULL DEFAULT 1,
        created_at      DATETIME        NOT NULL DEFAULT GETDATE(),
        updated_at      DATETIME        NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_GPS_Transporte FOREIGN KEY (id_transporte)
            REFERENCES gps.Transportes (id_transporte)
    );
END
GO

-- ── 4. Registrar módulos GPS en el sistema de permisos ───────
-- Obtener el orden máximo actual para continuar la secuencia
DECLARE @ordenBase INT;
SELECT @ordenBase = ISNULL(MAX(orden), 40) FROM electronicas.Modulos;

IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'gpsCredenciales')
BEGIN
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden, activo)
    VALUES ('gpsCredenciales', 'Credenciales GPS', 'bi bi-geo-alt-fill', 'GPS', @ordenBase + 10, 1);
END

IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'gpsTransportes')
BEGIN
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden, activo)
    VALUES ('gpsTransportes', 'Transportes', 'bi bi-truck', 'GPS', @ordenBase + 11, 1);
END
GO
