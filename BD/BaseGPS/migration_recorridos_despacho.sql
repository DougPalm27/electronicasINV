-- ============================================================
-- Modulo GPS - Recorridos por vehiculo en despacho
-- Base: ElectronicasDB  |  Schema: gps
--
-- Guarda puntos historicos de cada carro mientras esta en un despacho.
-- El mapa usa esta tabla para dibujar el recorrido del vehiculo seleccionado.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.DespachoRecorridos', 'U') IS NULL
CREATE TABLE gps.DespachoRecorridos (
    id_recorrido    INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_dv           INT           NOT NULL
        REFERENCES gps.DespachoVehiculos(id_dv),
    id_despacho     INT           NOT NULL
        REFERENCES gps.Despachos(id_despacho),
    id_cuenta       INT           NOT NULL
        REFERENCES gps.CuentasGPS(id_cuenta),
    placa           NVARCHAR(20)  NOT NULL,
    imei            NVARCHAR(30)  NULL,
    lat             DECIMAL(10,7) NOT NULL,
    lng             DECIMAL(10,7) NOT NULL,
    velocidad       INT           NULL,
    rumbo           INT           NULL,
    encendido       BIT           NULL,
    direccion       NVARCHAR(400) NULL,
    fecha_posicion  DATETIME      NULL,
    fecha_captura   DATETIME      NOT NULL DEFAULT GETDATE()
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoRecorridos_dv_fecha')
    CREATE INDEX IX_DespachoRecorridos_dv_fecha
        ON gps.DespachoRecorridos(id_dv, fecha_posicion, id_recorrido);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoRecorridos_despacho')
    CREATE INDEX IX_DespachoRecorridos_despacho
        ON gps.DespachoRecorridos(id_despacho, id_dv);
GO
