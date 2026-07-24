-- ============================================================
-- Modulo GPS - Latidos de workers
-- Base: ElectronicasDB  |  Schema: gps
--
-- Permite validar desde el modulo si un worker de fondo sigue vivo.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.WorkerHeartbeats', 'U') IS NULL
CREATE TABLE gps.WorkerHeartbeats (
    worker          NVARCHAR(50)   NOT NULL PRIMARY KEY,
    estado          NVARCHAR(20)   NOT NULL DEFAULT 'ok',
    detalle         NVARCHAR(400)  NULL,
    pid             INT            NULL,
    host            NVARCHAR(120)  NULL,
    fecha_latido    DATETIME       NOT NULL DEFAULT GETDATE(),
    fecha_inicio    DATETIME       NULL
);
GO
