-- ============================================================
-- Modulo GPS - Verificacion del estado de la base
-- SOLO LECTURA: no modifica nada. Correr en la base de produccion.
--
-- Dice que migraciones ya se aplicaron y cuales faltan.
-- ============================================================
USE ElectronicasDB;
GO

SELECT elemento, requerido_por,
       CASE WHEN existe = 1 THEN 'OK' ELSE '>>> FALTA' END AS estado
FROM (
    SELECT 'Tabla gps.Despachos'                  AS elemento, 'migration_despachos.sql'          AS requerido_por, CASE WHEN OBJECT_ID('gps.Despachos','U')                  IS NULL THEN 0 ELSE 1 END AS existe, 1 AS orden
    UNION ALL SELECT 'Tabla gps.DespachoVehiculos',        'migration_despachos.sql',            CASE WHEN OBJECT_ID('gps.DespachoVehiculos','U')        IS NULL THEN 0 ELSE 1 END, 2
    UNION ALL SELECT 'Tabla gps.DespachoRecorridos',       'migration_recorridos_despacho.sql',  CASE WHEN OBJECT_ID('gps.DespachoRecorridos','U')       IS NULL THEN 0 ELSE 1 END, 3
    UNION ALL SELECT 'Tabla gps.DespachoVehiculoTramos',   'migration_tramos_despacho.sql',      CASE WHEN OBJECT_ID('gps.DespachoVehiculoTramos','U')   IS NULL THEN 0 ELSE 1 END, 4
    UNION ALL SELECT 'Tabla gps.DespachoVehiculoIncidencias','migration_incidencias_despacho.sql',CASE WHEN OBJECT_ID('gps.DespachoVehiculoIncidencias','U') IS NULL THEN 0 ELSE 1 END, 5
    UNION ALL SELECT 'Tabla gps.WorkerHeartbeats',         'migration_worker_heartbeats.sql',    CASE WHEN OBJECT_ID('gps.WorkerHeartbeats','U')         IS NULL THEN 0 ELSE 1 END, 6
    UNION ALL SELECT 'Tabla gps.CuentaTokens',             'migration_tracksolid_tokens.sql',    CASE WHEN OBJECT_ID('gps.CuentaTokens','U')             IS NULL THEN 0 ELSE 1 END, 7
    UNION ALL SELECT 'Tabla gps.DespachoAlertas',          'migration_alertas_despacho.sql',     CASE WHEN OBJECT_ID('gps.DespachoAlertas','U')          IS NULL THEN 0 ELSE 1 END, 8
    UNION ALL SELECT 'Columna DespachoVehiculos.motivo_remocion','migration_motivo_remocion.sql', CASE WHEN COL_LENGTH('gps.DespachoVehiculos','motivo_remocion') IS NULL THEN 0 ELSE 1 END, 9
    UNION ALL SELECT 'Columna CuentaTokens.intentos_fallidos',  'migration_tokens_backoff.sql',  CASE WHEN COL_LENGTH('gps.CuentaTokens','intentos_fallidos')    IS NULL THEN 0 ELSE 1 END, 10
    UNION ALL SELECT 'Columna CuentaTokens.fecha_error',        'migration_tokens_backoff.sql',  CASE WHEN COL_LENGTH('gps.CuentaTokens','fecha_error')          IS NULL THEN 0 ELSE 1 END, 11
    UNION ALL SELECT 'Columna CuentaTokens.ultimo_error',       'migration_tokens_backoff.sql',  CASE WHEN COL_LENGTH('gps.CuentaTokens','ultimo_error')         IS NULL THEN 0 ELSE 1 END, 12
    UNION ALL SELECT 'Columna CuentaTokens.tipo_error',         'migration_tokens_bloqueo.sql',  CASE WHEN COL_LENGTH('gps.CuentaTokens','tipo_error')           IS NULL THEN 0 ELSE 1 END, 13
) v
ORDER BY existe, orden;
GO
