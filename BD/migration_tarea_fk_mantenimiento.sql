-- ══════════════════════════════════════════════════════════════
-- Agrega columna id_tarea (FK nullable) a MantenimientoTareas
-- para vincular cada tarea registrada con el catálogo SatakeTareas.
-- La columna descripcion se mantiene como snapshot de texto.
-- ══════════════════════════════════════════════════════════════

ALTER TABLE electronicas.MantenimientoTareas
    ADD id_tarea INT NULL
        CONSTRAINT FK_MantenimientoTareas_SatakeTareas
        FOREIGN KEY REFERENCES electronicas.SatakeTareas(id_tarea);
GO
