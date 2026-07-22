-- ============================================================
-- Modulo GPS - Tramos por vehiculo en despacho
-- Base: ElectronicasDB  |  Schema: gps
--
-- Mide inicio/fin de ruta por cada carro dentro de un despacho.
-- Un carro puede tener varios tramos historicos, pero solo uno abierto.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.DespachoVehiculoTramos', 'U') IS NULL
CREATE TABLE gps.DespachoVehiculoTramos (
    id_tramo        INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_despacho     INT           NOT NULL
        REFERENCES gps.Despachos(id_despacho),
    id_dv           INT           NOT NULL
        REFERENCES gps.DespachoVehiculos(id_dv),
    placa           NVARCHAR(20)  NOT NULL,
    estado          NVARCHAR(12)  NOT NULL DEFAULT 'en_ruta',
    fecha_inicio    DATETIME      NOT NULL DEFAULT GETDATE(),
    lat_inicio      DECIMAL(10,7) NULL,
    lng_inicio      DECIMAL(10,7) NULL,
    direccion_inicio NVARCHAR(400) NULL,
    fecha_fin       DATETIME      NULL,
    lat_fin         DECIMAL(10,7) NULL,
    lng_fin         DECIMAL(10,7) NULL,
    direccion_fin   NVARCHAR(400) NULL,
    duracion_minutos INT          NULL,
    iniciado_por    INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario),
    finalizado_por  INT           NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoVehiculoTramos_dv_estado')
    CREATE INDEX IX_DespachoVehiculoTramos_dv_estado
        ON gps.DespachoVehiculoTramos(id_dv, estado, fecha_inicio DESC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'UX_DespachoVehiculoTramos_abierto')
    CREATE UNIQUE INDEX UX_DespachoVehiculoTramos_abierto
        ON gps.DespachoVehiculoTramos(id_dv)
        WHERE estado = 'en_ruta';
GO
