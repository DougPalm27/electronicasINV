-- ============================================================
-- Agrega el flag puede_ejecutar_mantenimiento a Roles
-- Permite definir desde la UI cuáles roles aparecen en el
-- select de técnico y pueden registrar mantenimientos.
-- Ejecutar en SSMS una sola vez.
-- ============================================================

ALTER TABLE electronicas.Roles
    ADD puede_ejecutar_mantenimiento BIT NOT NULL DEFAULT 0;

-- Activar para los roles que ya ejecutan mantenimientos
UPDATE electronicas.Roles
    SET puede_ejecutar_mantenimiento = 1
    WHERE nombre IN (N'Administrador', N'Técnico');
