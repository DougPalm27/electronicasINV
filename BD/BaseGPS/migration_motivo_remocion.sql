-- ============================================================
-- Modulo GPS - Motivo de remocion de vehiculo en despacho
-- Base: ElectronicasDB  |  Schema: gps
--
-- Distingue por que se quito un carro del despacho:
--   'removido' -> salio de ruta / cumplio (comportamiento normal)
--   'error'    -> se agrego por equivocacion
-- Todo sigue siendo soft-delete (activo = 0); esto solo etiqueta el motivo.
-- Idempotente.
-- ============================================================
USE ElectronicasDB;
GO

IF COL_LENGTH('gps.DespachoVehiculos', 'motivo_remocion') IS NULL
    ALTER TABLE gps.DespachoVehiculos
        ADD motivo_remocion NVARCHAR(15) NULL;
GO
