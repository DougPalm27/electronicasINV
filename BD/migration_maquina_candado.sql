-- ============================================================
-- Candado de operario: qué PC tiene instalado el candado de cada máquina
--
-- El instalador deja elegir la máquina de una lista (las que tienen
-- Maquinas.estacion_candado asignada, ver migration_turno_maquina.sql) y,
-- al terminar, registra aquí a qué PC quedó ligada. Así una máquina que ya
-- tiene su candado no vuelve a aparecer en la lista de otra PC.
--
-- Una fila = una máquina con candado instalado. Un administrador puede
-- liberarla desde Máquinas (para cambiar de PC); reinstalar en la misma PC
-- no la bloquea a sí misma.
--
-- Ejecutar en SSMS sobre la BD correcta, DESPUÉS de migration_turno_maquina.sql
-- y ANTES de subir el código nuevo. Es repetible.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'MaquinaCandado'
)
BEGIN
    CREATE TABLE electronicas.MaquinaCandado (
        id_maquina         INT          NOT NULL,
        equipo_id          VARCHAR(64)  NOT NULL,   -- identificador de la PC (MachineGuid de Windows)
        equipo_nombre      VARCHAR(100) NULL,       -- nombre de la PC, solo para mostrarlo
        version            VARCHAR(20)  NULL,       -- versión del candado instalada
        fecha_instalacion  DATETIME     NOT NULL
            CONSTRAINT DF_MaquinaCandado_fecha DEFAULT GETDATE(),

        CONSTRAINT PK_MaquinaCandado        PRIMARY KEY CLUSTERED (id_maquina),   -- una PC por máquina
        CONSTRAINT UQ_MaquinaCandado_equipo UNIQUE (equipo_id),                    -- una máquina por PC
        CONSTRAINT FK_MaquinaCandado_Maquina FOREIGN KEY (id_maquina)
            REFERENCES electronicas.Maquinas(id_maquina) ON DELETE CASCADE
    );

    PRINT 'Tabla electronicas.MaquinaCandado creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.MaquinaCandado ya existe. Sin cambios.';
END
GO
