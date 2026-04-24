-- ============================================================
-- Tabla: electronicas.Divisas
-- Fecha: 2026-04-23
-- ============================================================
USE [ElectronicasDB];
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'Divisas'
)
BEGIN
    CREATE TABLE electronicas.Divisas (
        id_divisa           INT IDENTITY(1,1)  NOT NULL,
        nombre              VARCHAR(80)        NOT NULL,
        codigo              VARCHAR(10)        NOT NULL,   -- HNL, USD, EUR...
        simbolo             VARCHAR(5)         NOT NULL,   -- L., $, €
        tipo_cambio         DECIMAL(12,4)      NOT NULL    DEFAULT 1.0000,  -- relativo al Lempira
        predeterminada      BIT                NOT NULL    CONSTRAINT DF_Divisas_pred  DEFAULT 0,
        activo              BIT                NOT NULL    CONSTRAINT DF_Divisas_actv  DEFAULT 1,
        fecha_actualizacion DATETIME           NOT NULL    CONSTRAINT DF_Divisas_fecha DEFAULT GETDATE(),

        CONSTRAINT PK_Divisas          PRIMARY KEY CLUSTERED (id_divisa ASC),
        CONSTRAINT UQ_Divisas_codigo   UNIQUE (codigo)
    );

    -- Seed: Lempira (predeterminada) + Dólar
    INSERT INTO electronicas.Divisas (nombre, codigo, simbolo, tipo_cambio, predeterminada) VALUES
    ('Lempira',               'HNL', 'L.',  1.0000,  1),
    ('Dólar estadounidense',  'USD', '$',   25.0000, 0),
    ('Euro',                  'EUR', '€',   27.0000, 0);

    PRINT 'Tabla electronicas.Divisas creada e inicializada.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.Divisas ya existe. Sin cambios.';
END
GO
