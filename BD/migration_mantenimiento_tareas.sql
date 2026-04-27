-- ============================================================
-- Migración: Tareas de Mantenimiento
-- ============================================================
CREATE TABLE electronicas.MantenimientoTareas (
    id_tarea         INT IDENTITY(1,1) PRIMARY KEY,
    id_mantenimiento INT          NOT NULL
        CONSTRAINT FK_MantTareas_Mant
            FOREIGN KEY REFERENCES electronicas.Mantenimientos(id_mantenimiento),
    descripcion      VARCHAR(500) NOT NULL,
    orden            INT          NOT NULL DEFAULT 0
);

CREATE INDEX IX_MantTareas_Mant ON electronicas.MantenimientoTareas (id_mantenimiento);
