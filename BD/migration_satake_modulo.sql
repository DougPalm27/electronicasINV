-- ============================================================
-- Registrar el módulo Satake en el sistema de permisos
-- y asignarlo al rol Administrador
-- Ejecutar en SSMS una sola vez
-- ============================================================

-- 1. Insertar el módulo en el catálogo
INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden)
VALUES ('satake', 'Manual Satake', 'bi-journal-medical', 'Operaciones', 12);

-- 2. Asignar al rol Administrador (id_rol = 1)
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 1, id_modulo
FROM electronicas.Modulos
WHERE clave = 'satake';
