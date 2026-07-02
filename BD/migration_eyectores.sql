-- ============================================================
-- Módulo de componentes: Eyectores (barra de eyectores)
-- - Estado por eyector (OK / Falla / Advertencia / Apagado)
-- - Historial de cambios de estado
-- - Layout de la barra configurable por modelo
-- Ejecutar en SSMS una sola vez.
-- ============================================================

-- 1) Catálogo de estados de eyector
IF OBJECT_ID('electronicas.EstadoEyector', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.EstadoEyector (
        id_estado  INT IDENTITY(1,1) PRIMARY KEY,
        nombre     VARCHAR(50)  NOT NULL,
        clase_css  VARCHAR(30)  NOT NULL,
        color      VARCHAR(10)  NOT NULL,
        orden      INT          NOT NULL DEFAULT 0
    );

    INSERT INTO electronicas.EstadoEyector (nombre, clase_css, color, orden) VALUES
        ('OK',          'bg-success',   '#22c55e', 1),
        ('Falla',       'bg-danger',    '#ef4444', 2),
        ('Advertencia', 'bg-warning',   '#facc15', 3),
        ('Apagado',     'bg-secondary', '#64748b', 4);
END
GO

-- 2) Configuración de la barra por modelo
IF OBJECT_ID('electronicas.ConfiguracionEyectores', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.ConfiguracionEyectores (
        id_config  INT IDENTITY(1,1) PRIMARY KEY,
        id_modelo  INT          NOT NULL,
        total      INT          NOT NULL,
        lado_izq   INT          NOT NULL,
        lado_der   INT          NOT NULL,
        filas_izq  VARCHAR(200) NOT NULL,   -- ej: '31,31,30'
        filas_der  VARCHAR(200) NOT NULL,   -- ej: '31,31,30'
        activo     BIT          NOT NULL DEFAULT 1,
        CONSTRAINT FK_ConfigEyector_Modelo FOREIGN KEY (id_modelo)
            REFERENCES electronicas.Modelos (id_modelo)
    );
END
GO

-- Sembrar configuración para el/los modelo(s) Evo (184 = 92 + 92, filas 31/31/30)
INSERT INTO electronicas.ConfiguracionEyectores
    (id_modelo, total, lado_izq, lado_der, filas_izq, filas_der, activo)
SELECT m.id_modelo, 184, 92, 92, '31,31,30', '31,31,30', 1
FROM electronicas.Modelos m
WHERE m.nombre LIKE '%Evo%'
  AND NOT EXISTS (
      SELECT 1 FROM electronicas.ConfiguracionEyectores c
      WHERE c.id_modelo = m.id_modelo
  );
GO

-- 3) Estado actual de cada eyector por máquina
IF OBJECT_ID('electronicas.Eyectores', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.Eyectores (
        id_eyector           INT IDENTITY(1,1) PRIMARY KEY,
        id_maquina           INT          NOT NULL,
        numero               INT          NOT NULL,
        lado                 CHAR(1)      NOT NULL,   -- 'I' / 'D'
        fila                 INT          NOT NULL,
        posicion             INT          NOT NULL,
        id_estado            INT          NOT NULL DEFAULT 1,
        observacion          VARCHAR(500) NULL,
        fecha_actualizacion  DATETIME     NULL,
        id_usuario_actualiza INT          NULL,
        CONSTRAINT FK_Eyector_Maquina FOREIGN KEY (id_maquina)
            REFERENCES electronicas.Maquinas (id_maquina),
        CONSTRAINT FK_Eyector_Estado FOREIGN KEY (id_estado)
            REFERENCES electronicas.EstadoEyector (id_estado),
        -- Clave de negocio: cada lado se numera 1..N de forma independiente,
        -- por eso el número es único dentro de (máquina, lado), no globalmente.
        CONSTRAINT UQ_Eyector_Maquina_Lado_Numero UNIQUE (id_maquina, lado, numero)
    );
    CREATE INDEX IX_Eyectores_Maquina ON electronicas.Eyectores (id_maquina);
END
GO

-- 4) Historial de cambios de estado por eyector
IF OBJECT_ID('electronicas.EyectorHistorial', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.EyectorHistorial (
        id_historial       INT IDENTITY(1,1) PRIMARY KEY,
        id_eyector         INT          NOT NULL,
        id_estado_anterior INT          NULL,
        id_estado_nuevo    INT          NOT NULL,
        observacion        VARCHAR(500) NULL,
        fecha              DATETIME     NOT NULL DEFAULT GETDATE(),
        id_usuario         INT          NULL,
        CONSTRAINT FK_EyectorHist_Eyector FOREIGN KEY (id_eyector)
            REFERENCES electronicas.Eyectores (id_eyector)
    );
    CREATE INDEX IX_EyectorHist_Eyector ON electronicas.EyectorHistorial (id_eyector);
END
GO
