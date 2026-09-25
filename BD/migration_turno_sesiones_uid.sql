-- ============================================================
-- Candado de operario: identificador de sesión generado por la PC
-- Las PCs de planta trabajan sin conexión y suben sus turnos después;
-- uid_cliente evita duplicar un turno si se reenvía.
-- Ejecutar en SSMS sobre la BD correcta.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.TurnoSesiones') AND name = 'uid_cliente'
)
BEGIN
    ALTER TABLE electronicas.TurnoSesiones
        ADD uid_cliente UNIQUEIDENTIFIER NULL;

    PRINT 'Columna uid_cliente agregada a electronicas.TurnoSesiones.';
END
ELSE
BEGIN
    PRINT 'La columna uid_cliente ya existe. Sin cambios.';
END
GO

SET QUOTED_IDENTIFIER ON;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('electronicas.TurnoSesiones') AND name = 'UQ_TurnoSesiones_uid_cliente'
)
BEGIN
    CREATE UNIQUE INDEX UQ_TurnoSesiones_uid_cliente
        ON electronicas.TurnoSesiones(uid_cliente)
        WHERE uid_cliente IS NOT NULL;

    PRINT 'Índice UQ_TurnoSesiones_uid_cliente creado.';
END
ELSE
BEGIN
    PRINT 'El índice UQ_TurnoSesiones_uid_cliente ya existe. Sin cambios.';
END
GO
