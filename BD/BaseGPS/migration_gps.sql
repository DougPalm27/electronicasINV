-- ============================================================
-- Módulo GPS — Migración completa (esquema normalizado)
-- Base: ElectronicasDB  |  Schema: gps
-- ============================================================
USE ElectronicasDB;
GO

-- ── 0. Limpiar tablas anteriores (orden: hijos primero) ──────
IF OBJECT_ID('gps.GPS',         'U') IS NOT NULL DROP TABLE gps.GPS;
IF OBJECT_ID('gps.CuentasGPS',  'U') IS NOT NULL DROP TABLE gps.CuentasGPS;
IF OBJECT_ID('gps.Transportes', 'U') IS NOT NULL DROP TABLE gps.Transportes;
IF OBJECT_ID('gps.Plataformas', 'U') IS NOT NULL DROP TABLE gps.Plataformas;
IF OBJECT_ID('gps.TiposVehiculo','U') IS NOT NULL DROP TABLE gps.TiposVehiculo;
IF OBJECT_ID('gps.Destinos',    'U') IS NOT NULL DROP TABLE gps.Destinos;
GO

-- ── 1. Schema ────────────────────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'gps')
    EXEC('CREATE SCHEMA gps');
GO

-- ── 2. Tablas de parametrización ─────────────────────────────

CREATE TABLE gps.TiposVehiculo (
    id_tipo_vehiculo  INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    nombre            NVARCHAR(50)  NOT NULL,
    activo            BIT           NOT NULL DEFAULT 1,
    fecha_creacion    DATETIME      NOT NULL DEFAULT GETDATE(),
    creado_por        INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

CREATE TABLE gps.Destinos (
    id_destino        INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    nombre            NVARCHAR(100) NOT NULL,
    activo            BIT           NOT NULL DEFAULT 1,
    fecha_creacion    DATETIME      NOT NULL DEFAULT GETDATE(),
    creado_por        INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

CREATE TABLE gps.Plataformas (
    id_plataforma     INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    nombre            NVARCHAR(100) NOT NULL,
    url_base          NVARCHAR(500) NULL,
    activo            BIT           NOT NULL DEFAULT 1,
    fecha_creacion    DATETIME      NOT NULL DEFAULT GETDATE(),
    creado_por        INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

-- ── 3. Transportes ───────────────────────────────────────────
CREATE TABLE gps.Transportes (
    id_transporte       INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    nombre              NVARCHAR(150) NOT NULL,
    contacto            NVARCHAR(100) NULL,
    telefono            NVARCHAR(20)  NULL,
    activo              BIT           NOT NULL DEFAULT 1,
    fecha_creacion      DATETIME      NOT NULL DEFAULT GETDATE(),
    fecha_actualizacion DATETIME      NULL,
    creado_por          INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario),
    actualizado_por     INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

-- ── 4. Cuentas GPS (transporte + plataforma + credenciales) ──
CREATE TABLE gps.CuentasGPS (
    id_cuenta           INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_transporte       INT           NOT NULL
        REFERENCES gps.Transportes(id_transporte),
    id_plataforma       INT           NOT NULL
        REFERENCES gps.Plataformas(id_plataforma),
    usuario             NVARCHAR(100) NULL,
    contrasena          NVARCHAR(150) NULL,
    activo              BIT           NOT NULL DEFAULT 1,
    fecha_creacion      DATETIME      NOT NULL DEFAULT GETDATE(),
    fecha_actualizacion DATETIME      NULL,
    creado_por          INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario),
    actualizado_por     INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

-- ── 5. GPS (vehículos) ───────────────────────────────────────
CREATE TABLE gps.GPS (
    id_gps              INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_cuenta           INT           NOT NULL
        REFERENCES gps.CuentasGPS(id_cuenta),
    id_tipo_vehiculo    INT           NULL
        REFERENCES gps.TiposVehiculo(id_tipo_vehiculo),
    id_destino          INT           NULL
        REFERENCES gps.Destinos(id_destino),
    placa               NVARCHAR(20)  NOT NULL,
    estado              BIT           NOT NULL DEFAULT 1,
    fecha_creacion      DATETIME      NOT NULL DEFAULT GETDATE(),
    fecha_actualizacion DATETIME      NULL,
    creado_por          INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario),
    actualizado_por     INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

-- ── 6. Seed: Tipos de Vehículo ───────────────────────────────
INSERT INTO gps.TiposVehiculo (nombre) VALUES
    ('Cabezal'),
    ('Pickup'),
    ('Camión'),
    ('Furgón'),
    ('Por Determinar');
GO

-- ── 7. Seed: Destinos ────────────────────────────────────────
INSERT INTO gps.Destinos (nombre) VALUES
    ('ACOPIO'),
    ('PUERTO'),
    ('OPC');
GO

-- ── 8. Seed: Plataformas ─────────────────────────────────────
INSERT INTO gps.Plataformas (nombre, url_base) VALUES
    ('TrackSolid Pro', 'https://us.tracksolidpro.com/resource/dev/index.html#/login'),
    ('Detektor GPS',   'http://hn.detektorgps.com/gps_hn/movil/mapa.php'),
    ('NavSat',         'http://rastreo.navsathn.com/main.php'),
    ('VTG',            'https://tr.vtghn.com/main.php'),
    ('ProYesal',       'http://server.proyesal.com'),
    ('Sabueso',        'http://sabueso.stgands.com'),
    ('Pendiente',      NULL);
GO

-- ── 9. Módulos en el sistema de permisos ─────────────────────
DECLARE @base INT;
SELECT @base = ISNULL(MAX(orden), 40) FROM electronicas.Modulos;

-- Limpiar módulos GPS anteriores para reinsertar con orden correcto
DELETE FROM electronicas.RolModulos
WHERE id_modulo IN (
    SELECT id_modulo FROM electronicas.Modulos
    WHERE clave IN ('gpsCredenciales','gpsTransportes','gpsCuentas',
                    'gpsTiposVehiculo','gpsDestinos','gpsPlataformas')
);
DELETE FROM electronicas.Modulos
WHERE clave IN ('gpsCredenciales','gpsTransportes','gpsCuentas',
                'gpsTiposVehiculo','gpsDestinos','gpsPlataformas');

INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden, activo) VALUES
    ('gpsCredenciales',  'Credenciales GPS',    'bi bi-geo-alt-fill',       'GPS', @base+10, 1),
    ('gpsCuentas',       'Cuentas GPS',          'bi bi-key-fill',           'GPS', @base+11, 1),
    ('gpsTransportes',   'Transportes',          'bi bi-truck',              'GPS', @base+12, 1),
    ('gpsTiposVehiculo', 'Tipos de Vehículo',    'bi bi-car-front',          'GPS', @base+13, 1),
    ('gpsDestinos',      'Destinos',             'bi bi-signpost-split',     'GPS', @base+14, 1),
    ('gpsPlataformas',   'Plataformas GPS',      'bi bi-broadcast',          'GPS', @base+15, 1);
GO

-- ── 10. Script de migración de datos desde sistema anterior ──
-- Ejecutar SOLO si tienes los datos del BaseGPS cargados en tablas temporales.
-- Ver archivo: migration_gps_data.sql
-- ─────────────────────────────────────────────────────────────
