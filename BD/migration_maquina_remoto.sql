-- ============================================================
-- Conexión remota (VNC) a la PC de cada máquina
--
-- Desde Máquinas, un administrador pulsa «Conectar» y baja un archivo .vnc
-- que abre su visor VNC (TightVNC / UltraVNC) contra la PC de esa máquina.
--
-- remoto_host:   nombre o IP de la PC. Si queda vacío se usa el nombre de la
--                PC donde se instaló el candado (MaquinaCandado.equipo_nombre).
-- remoto_puerto: puerto VNC. Vacío = 5900.
-- No se guardan contraseñas: las pide el visor al conectar.
--
-- Ejecutar en SSMS sobre la BD correcta ANTES de subir el código nuevo. Es repetible.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Maquinas') AND name = 'remoto_host'
)
BEGIN
    ALTER TABLE electronicas.Maquinas ADD remoto_host VARCHAR(100) NULL;
    PRINT 'Columna remoto_host agregada a electronicas.Maquinas.';
END
ELSE
    PRINT 'La columna remoto_host ya existe. Sin cambios.';
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Maquinas') AND name = 'remoto_puerto'
)
BEGIN
    ALTER TABLE electronicas.Maquinas ADD remoto_puerto INT NULL;
    PRINT 'Columna remoto_puerto agregada a electronicas.Maquinas.';
END
ELSE
    PRINT 'La columna remoto_puerto ya existe. Sin cambios.';
GO
