-- ================================================================
-- Módulo: HorariosMonitoreo — Anular / Editar Incapacidades
-- Agrega columna 'referencia' para agrupar los días de una misma
-- incapacidad y permitir anularla o editarla en bloque.
-- ================================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('RRHH.ExcepcionesHorario')
      AND name = 'referencia'
)
    ALTER TABLE RRHH.ExcepcionesHorario ADD referencia VARCHAR(100) NULL;
GO
