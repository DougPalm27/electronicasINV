-- ============================================================
-- Candado de operario: código PIN de acceso rápido en Usuarios
-- Se usa desde bloqueo.php (kiosco de planta) para identificar
-- quién está trabajando en cada máquina, sin pedir usuario/contraseña.
-- Ejecutar en SSMS sobre la BD correcta.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Usuarios') AND name = 'pin_bloqueo'
)
BEGIN
    ALTER TABLE electronicas.Usuarios
        ADD pin_bloqueo VARCHAR(6) NULL;

    PRINT 'Columna pin_bloqueo agregada a electronicas.Usuarios.';
END
ELSE
BEGIN
    PRINT 'La columna pin_bloqueo ya existe en electronicas.Usuarios. Sin cambios.';
END
GO

-- Único cuando tiene valor; SQL Server permite varios NULL bajo un índice
-- filtrado (a diferencia de un UNIQUE normal, que solo admite uno).
-- Los índices filtrados requieren QUOTED_IDENTIFIER ON en la sesión.
SET QUOTED_IDENTIFIER ON;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('electronicas.Usuarios') AND name = 'UQ_Usuarios_pin_bloqueo'
)
BEGIN
    CREATE UNIQUE INDEX UQ_Usuarios_pin_bloqueo
        ON electronicas.Usuarios(pin_bloqueo)
        WHERE pin_bloqueo IS NOT NULL;

    PRINT 'Índice único UQ_Usuarios_pin_bloqueo creado.';
END
ELSE
BEGIN
    PRINT 'El índice UQ_Usuarios_pin_bloqueo ya existe. Sin cambios.';
END
GO
