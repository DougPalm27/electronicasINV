-- ============================================================
-- Refactorización: Tabla MantenimientoTareas
-- Problema: confusión de id_tarea (PK vs FK)
-- Solución: Renombrar PK y agregar FK a SatakeTareas
-- ============================================================

-- PASO 1: Crear tabla nueva con estructura correcta
CREATE TABLE electronicas.MantenimientoTareas_Nueva (
    id_mantenimiento_tarea INT IDENTITY(1,1) PRIMARY KEY,
    id_mantenimiento INT NOT NULL
        CONSTRAINT FK_MantTareasNueva_Mant
            FOREIGN KEY REFERENCES electronicas.Mantenimientos(id_mantenimiento),
    id_tarea_catalogo INT NULL
        CONSTRAINT FK_MantTareasNueva_TareaCatalogo
            FOREIGN KEY REFERENCES electronicas.SatakeTareas(id_tarea),
    descripcion VARCHAR(500) NOT NULL,
    orden INT NOT NULL DEFAULT 0
);

-- PASO 2: Migrar datos de tabla antigua
INSERT INTO electronicas.MantenimientoTareas_Nueva
    (id_mantenimiento, id_tarea_catalogo, descripcion, orden)
SELECT
    id_mantenimiento,
    CASE
        WHEN id_tarea > 0 AND id_tarea <= 10000 THEN id_tarea
        ELSE NULL
    END AS id_tarea_catalogo,
    descripcion,
    orden
FROM electronicas.MantenimientoTareas;

-- PASO 3: Eliminar tabla antigua
DROP TABLE electronicas.MantenimientoTareas;

-- PASO 4: Renombrar tabla nueva
EXEC sp_rename 'electronicas.MantenimientoTareas_Nueva', 'MantenimientoTareas';

-- PASO 5: Crear índices
CREATE INDEX IX_MantTareas_Mant ON electronicas.MantenimientoTareas (id_mantenimiento);
CREATE INDEX IX_MantTareas_TareaCatalogo ON electronicas.MantenimientoTareas (id_tarea_catalogo);

-- PASO 6: Verificación
SELECT 'Migración completada correctamente' AS Resultado;
SELECT COUNT(*) AS TotalTareas FROM electronicas.MantenimientoTareas;
