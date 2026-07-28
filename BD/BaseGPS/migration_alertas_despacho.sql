-- ============================================================
-- Modulo GPS - Alertas automaticas de despacho
-- Base: ElectronicasDB  |  Schema: gps
--
-- Guarda cada alerta detectada por el mapa (carro detenido o sin reportar)
-- para poder revisarlas despues. Una fila por episodio: se abre cuando se
-- detecta y se cierra ('resuelta') cuando el carro vuelve a la normalidad.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.DespachoAlertas', 'U') IS NULL
CREATE TABLE gps.DespachoAlertas (
    id_alerta          INT            NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_despacho        INT            NOT NULL
        REFERENCES gps.Despachos(id_despacho),
    id_dv              INT            NOT NULL
        REFERENCES gps.DespachoVehiculos(id_dv),
    id_tramo           INT            NULL,
    id_cuenta          INT            NOT NULL,
    placa              NVARCHAR(20)   NOT NULL,
    imei               NVARCHAR(30)   NULL,
    tipo               NVARCHAR(20)   NOT NULL,   -- 'detenido' | 'sin_reporte'
    umbral_min         INT            NOT NULL,
    minutos_detectado  INT            NULL,       -- cuanto llevaba al detectarse
    minutos_final      INT            NULL,       -- cuanto duro en total
    lat                DECIMAL(10,7)  NULL,
    lng                DECIMAL(10,7)  NULL,
    direccion          NVARCHAR(500)  NULL,
    fecha_inicio       DATETIME       NULL,       -- desde cuando estaba asi
    fecha_detectada    DATETIME       NOT NULL DEFAULT GETDATE(),
    fecha_resuelta     DATETIME       NULL,
    estado             NVARCHAR(12)   NOT NULL DEFAULT 'activa'  -- 'activa' | 'resuelta'
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoAlertas_dv_estado')
    CREATE INDEX IX_DespachoAlertas_dv_estado
        ON gps.DespachoAlertas(id_dv, tipo, estado);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_DespachoAlertas_despacho')
    CREATE INDEX IX_DespachoAlertas_despacho
        ON gps.DespachoAlertas(id_despacho, fecha_detectada DESC);
GO
