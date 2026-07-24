-- ============================================================
-- Modulo GPS - Incidencias por vehiculo en despacho
-- Base: ElectronicasDB  |  Schema: gps
--
-- Registra eventos manuales ocurridos durante una ruta/despacho.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.DespachoVehiculoIncidencias', 'U') IS NULL
CREATE TABLE gps.DespachoVehiculoIncidencias (
    id_incidencia     INT            NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_despacho       INT            NOT NULL
        REFERENCES gps.Despachos(id_despacho),
    id_dv             INT            NOT NULL
        REFERENCES gps.DespachoVehiculos(id_dv),
    id_tramo          INT            NULL,
    id_cuenta         INT            NOT NULL
        REFERENCES gps.CuentasGPS(id_cuenta),
    placa             NVARCHAR(20)   NOT NULL,
    imei              NVARCHAR(30)   NULL,
    tipo              NVARCHAR(60)   NOT NULL,
    severidad         NVARCHAR(10)   NOT NULL DEFAULT 'media',
    descripcion       NVARCHAR(800)  NULL,
    estado            NVARCHAR(15)   NOT NULL DEFAULT 'abierta',
    lat               DECIMAL(10,7)  NULL,
    lng               DECIMAL(10,7)  NULL,
    direccion         NVARCHAR(500)  NULL,
    fecha_incidencia  DATETIME       NOT NULL DEFAULT GETDATE(),
    fecha_cierre      DATETIME       NULL,
    creado_por        INT            NULL
        REFERENCES electronicas.Usuarios(id_usuario),
    cerrado_por       INT            NULL
        REFERENCES electronicas.Usuarios(id_usuario)
);
GO

IF OBJECT_ID('gps.DespachoVehiculoTramos', 'U') IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_DespachoVehiculoIncidencias_Tramos')
    ALTER TABLE gps.DespachoVehiculoIncidencias
        ADD CONSTRAINT FK_DespachoVehiculoIncidencias_Tramos
        FOREIGN KEY (id_tramo) REFERENCES gps.DespachoVehiculoTramos(id_tramo);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoVehiculoIncidencias_dv_estado')
    CREATE INDEX IX_DespachoVehiculoIncidencias_dv_estado
        ON gps.DespachoVehiculoIncidencias(id_dv, estado, fecha_incidencia DESC);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoVehiculoIncidencias_equipo')
    CREATE INDEX IX_DespachoVehiculoIncidencias_equipo
        ON gps.DespachoVehiculoIncidencias(id_cuenta, imei, placa, fecha_incidencia DESC);
GO
