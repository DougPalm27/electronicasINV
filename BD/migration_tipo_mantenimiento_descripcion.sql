-- ============================================================
-- Agrega descripcion a TipoMantenimiento
-- Ejecutar en SSMS una sola vez
-- ============================================================

ALTER TABLE electronicas.TipoMantenimiento
    ADD descripcion NVARCHAR(400) NULL;

-- Seed de descripciones para tipos comunes
-- (solo actualiza filas que coincidan por nombre)
UPDATE electronicas.TipoMantenimiento SET descripcion = N'Intervención planificada con anticipación para prevenir fallas. Se realiza según un calendario establecido, sin que la máquina presente problemas.'
WHERE nombre = N'Preventivo';

UPDATE electronicas.TipoMantenimiento SET descripcion = N'Intervención no planificada realizada para reparar una falla que ya ocurrió. La máquina presentó un problema y se actúa para restaurar su funcionamiento.'
WHERE nombre = N'Correctivo';

UPDATE electronicas.TipoMantenimiento SET descripcion = N'Intervención basada en el monitoreo del estado real del equipo. Se actúa cuando los indicadores señalan que el desgaste está cerca del límite, antes de que ocurra la falla.'
WHERE nombre = N'Predictivo';

UPDATE electronicas.TipoMantenimiento SET descripcion = N'Revisión de bajo impacto que no requiere desmontar piezas. Incluye inspección visual, limpieza externa y verificación de funcionamiento general.'
WHERE nombre = N'Inspección';
