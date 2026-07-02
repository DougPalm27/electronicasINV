-- ============================================================
-- Reset de eyectores para re-inicializar con numeración POR LADO
-- (Izq 1..N / Der 1..N) y agregar la clave única de negocio.
--
-- Úsalo solo si YA habías inicializado una barra con el esquema
-- anterior (numeración global 1..184).
--
-- NO borra el catálogo de estados ni la configuración por modelo;
-- solo limpia los eyectores generados y su historial.
-- ============================================================

-- 1) Limpiar datos (primero el hijo por la FK, luego el padre)
DELETE FROM electronicas.EyectorHistorial;
DELETE FROM electronicas.Eyectores;
GO

-- 2) Agregar la clave única (id_maquina, lado, numero) si la tabla
--    ya existía desde antes y por eso no la tiene.
IF NOT EXISTS (
    SELECT 1 FROM sys.key_constraints
    WHERE name = 'UQ_Eyector_Maquina_Lado_Numero'
)
BEGIN
    ALTER TABLE electronicas.Eyectores
        ADD CONSTRAINT UQ_Eyector_Maquina_Lado_Numero UNIQUE (id_maquina, lado, numero);
END
GO
