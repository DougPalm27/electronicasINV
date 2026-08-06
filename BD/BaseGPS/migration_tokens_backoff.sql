-- ============================================================
-- Modulo GPS - Backoff de logins fallidos (TrackSolid)
-- Base: ElectronicasDB  |  Schema: gps
--
-- Las cuentas con clave vencida fallaban en cada corrida del worker
-- (cada 30 min), y cada intento fallido dejaba un perfil de Chrome
-- huerfano que llenaba el disco. Con estas columnas el worker espera
-- cada vez mas antes de reintentar una cuenta que no sirve.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF COL_LENGTH('gps.CuentaTokens', 'intentos_fallidos') IS NULL
    ALTER TABLE gps.CuentaTokens ADD intentos_fallidos INT NOT NULL DEFAULT 0;
GO

IF COL_LENGTH('gps.CuentaTokens', 'fecha_error') IS NULL
    ALTER TABLE gps.CuentaTokens ADD fecha_error DATETIME NULL;
GO

IF COL_LENGTH('gps.CuentaTokens', 'ultimo_error') IS NULL
    ALTER TABLE gps.CuentaTokens ADD ultimo_error NVARCHAR(300) NULL;
GO
