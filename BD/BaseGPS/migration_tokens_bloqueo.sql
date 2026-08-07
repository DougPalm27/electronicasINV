-- ============================================================
-- Modulo GPS - Bloqueo de cuentas con credenciales invalidas
-- Base: ElectronicasDB  |  Schema: gps
--
-- Distingue el motivo del fallo de login:
--   'credenciales' -> usuario/clave malos: tras varios intentos se DEJA de probar
--   'tecnico'      -> Chrome, red o timeout: sigue reintentando con espera creciente
--
-- Al corregir la contrasena en el modulo Cuentas GPS el bloqueo se levanta solo.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF COL_LENGTH('gps.CuentaTokens', 'tipo_error') IS NULL
    ALTER TABLE gps.CuentaTokens ADD tipo_error NVARCHAR(20) NULL;
GO
