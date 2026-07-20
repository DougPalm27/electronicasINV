-- ============================================================
-- Módulo GPS — Caché de tokens (TrackSolid / motores con login web)
-- Base: ElectronicasDB  |  Schema: gps
--
-- TrackSolid no expone API abierta: se entra con login web (headless) que
-- devuelve un token JWT (dura horas). Este token se guarda por cuenta y se
-- reutiliza en las llamadas de datos; se renueva cuando caduca.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF OBJECT_ID('gps.CuentaTokens', 'U') IS NULL
CREATE TABLE gps.CuentaTokens (
    usuario      NVARCHAR(150)  NOT NULL PRIMARY KEY,  -- login de la cuenta (clave del token)
    token        NVARCHAR(2000) NULL,                  -- JWT devuelto por el login headless
    query_body   NVARCHAR(2000) NULL,                  -- cuerpo de la llamada de datos (userId/orgId/userType)
    account_id   NVARCHAR(50)   NULL,
    fecha_token  DATETIME       NOT NULL DEFAULT GETDATE()
);
GO
