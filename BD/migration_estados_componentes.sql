-- ============================================================
-- Estados de componentes por máquina (fase 2 del catálogo)
-- - Cada máquina tiene una instancia de cada componente de primer
--   nivel de su modelo, con estado + observación (patrón eyectores)
-- - Historial de cambios de estado por componente
-- - Se REUTILIZA electronicas.EstadoEyector como catálogo general
--   de estados (OK / Falla / Advertencia / Apagado)
-- Ejecutar en SSMS una sola vez (después de
-- migration_catalogo_componentes.sql).
-- ============================================================

-- 1) Estado actual de cada componente por máquina
IF OBJECT_ID('electronicas.MaquinaComponentes', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.MaquinaComponentes (
        id_maquina_componente INT IDENTITY(1,1) PRIMARY KEY,
        id_maquina            INT          NOT NULL,
        id_modelo_componente  INT          NOT NULL,
        id_estado             INT          NOT NULL DEFAULT 1,
        observacion           VARCHAR(500) NULL,
        fecha_actualizacion   DATETIME     NULL,
        id_usuario_actualiza  INT          NULL,
        CONSTRAINT FK_MaqComp_Maquina    FOREIGN KEY (id_maquina)
            REFERENCES electronicas.Maquinas (id_maquina),
        CONSTRAINT FK_MaqComp_ModComp    FOREIGN KEY (id_modelo_componente)
            REFERENCES electronicas.ModeloComponentes (id_modelo_componente),
        CONSTRAINT FK_MaqComp_Estado     FOREIGN KEY (id_estado)
            REFERENCES electronicas.EstadoEyector (id_estado),
        CONSTRAINT UQ_MaqComp UNIQUE (id_maquina, id_modelo_componente)
    );
    CREATE INDEX IX_MaqComp_Maquina ON electronicas.MaquinaComponentes (id_maquina);
END
GO

-- 2) Historial de cambios de estado por componente
IF OBJECT_ID('electronicas.ComponenteHistorial', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.ComponenteHistorial (
        id_historial          INT          IDENTITY(1,1) PRIMARY KEY,
        id_maquina_componente INT          NOT NULL,
        id_estado_anterior    INT          NULL,
        id_estado_nuevo       INT          NOT NULL,
        observacion           VARCHAR(500) NULL,
        fecha                 DATETIME     NOT NULL DEFAULT GETDATE(),
        id_usuario            INT          NULL,
        CONSTRAINT FK_CompHist_MaqComp FOREIGN KEY (id_maquina_componente)
            REFERENCES electronicas.MaquinaComponentes (id_maquina_componente)
    );
    CREATE INDEX IX_CompHist_MaqComp ON electronicas.ComponenteHistorial (id_maquina_componente);
END
GO
