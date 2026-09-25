-- ============================================================
-- Candado de operario: eventos ocurridos durante cada turno
-- Fallas, ajustes de configuración, cierres inesperados y
-- mantenimientos que Delvis registró mientras el operario tenía
-- la máquina. Los sube el candado junto con el turno.
-- Ejecutar en SSMS sobre la BD correcta (requiere TurnoSesiones).
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'TurnoEventos'
)
BEGIN
    CREATE TABLE electronicas.TurnoEventos (
        id_evento       INT IDENTITY(1,1) NOT NULL,
        uid_evento      UNIQUEIDENTIFIER  NOT NULL,
        id_sesion       INT               NOT NULL,
        fecha           DATETIME          NOT NULL,
        tipo            VARCHAR(20)       NOT NULL,   -- falla, falla_ok, ajuste, calibracion, crash, mantenimiento, aplicacion
        codigo          VARCHAR(200)      NULL,       -- falla: grupo:codigo | ajuste: ruta del parámetro | crash: archivo
        detalle         NVARCHAR(400)     NULL,
        valor_anterior  NVARCHAR(200)     NULL,
        valor_nuevo     NVARCHAR(200)     NULL,

        CONSTRAINT PK_TurnoEventos        PRIMARY KEY CLUSTERED (id_evento ASC),
        CONSTRAINT UQ_TurnoEventos_uid    UNIQUE (uid_evento),
        CONSTRAINT FK_TurnoEventos_Sesion FOREIGN KEY (id_sesion) REFERENCES electronicas.TurnoSesiones(id_sesion)
    );

    CREATE INDEX IX_TurnoEventos_sesion ON electronicas.TurnoEventos(id_sesion, fecha);

    PRINT 'Tabla electronicas.TurnoEventos creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.TurnoEventos ya existe. Sin cambios.';
END
GO
