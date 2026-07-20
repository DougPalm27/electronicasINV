-- ============================================================
-- Módulo GPS — Despachos (listas de seguimiento temporales)
-- Base: ElectronicasDB  |  Schema: gps
--
-- Un "despacho" es una lista de seguimiento que se arma cuando se necesita:
-- se eligen los carros que salen (de cualquier plataforma/cuenta), se pueden
-- sumar/quitar sobre la marcha, y se cierra cuando terminan. Al cerrar queda
-- como historial. El mapa gira en torno a los despachos ACTIVOS.
--
-- gps.GPS (Credenciales) NO se toca: sigue siendo el catálogo de cuentas.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

-- ── Despachos ────────────────────────────────────────────────
IF OBJECT_ID('gps.Despachos', 'U') IS NULL
CREATE TABLE gps.Despachos (
    id_despacho     INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    nombre          NVARCHAR(120) NOT NULL,
    estado          NVARCHAR(10)  NOT NULL DEFAULT 'activo',   -- 'activo' | 'cerrado'
    fecha_apertura  DATETIME      NOT NULL DEFAULT GETDATE(),
    fecha_cierre    DATETIME      NULL,
    creado_por      INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

-- ── Vehículos de cada despacho ───────────────────────────────
-- activo = 1 (siguiendo) / 0 (quitado). No se borra: queda como historial.
IF OBJECT_ID('gps.DespachoVehiculos', 'U') IS NULL
CREATE TABLE gps.DespachoVehiculos (
    id_dv           INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_despacho     INT           NOT NULL
        REFERENCES gps.Despachos(id_despacho),
    id_cuenta       INT           NOT NULL
        REFERENCES gps.CuentasGPS(id_cuenta),
    placa           NVARCHAR(20)  NOT NULL,
    imei            NVARCHAR(30)  NULL,
    dispositivo     NVARCHAR(100) NULL,
    activo          BIT           NOT NULL DEFAULT 1,
    fecha_agregado  DATETIME      NOT NULL DEFAULT GETDATE(),
    fecha_removido  DATETIME      NULL,
    agregado_por    INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoVehiculos_despacho')
    CREATE INDEX IX_DespachoVehiculos_despacho ON gps.DespachoVehiculos(id_despacho, activo);
GO
