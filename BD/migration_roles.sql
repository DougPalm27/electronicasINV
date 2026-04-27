-- ============================================================
-- Migración: Sistema de Roles y Permisos
-- Ejecutar en SSMS sobre la BD correcta
-- ============================================================

-- 1. Tabla de Roles
CREATE TABLE electronicas.Roles (
    id_rol      INT IDENTITY(1,1) PRIMARY KEY,
    nombre      VARCHAR(80)  NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo      BIT          NOT NULL DEFAULT 1
);

-- 2. Tabla de Módulos del sistema
CREATE TABLE electronicas.Modulos (
    id_modulo   INT IDENTITY(1,1) PRIMARY KEY,
    clave       VARCHAR(60)  NOT NULL UNIQUE,
    nombre      VARCHAR(80)  NOT NULL,
    icono       VARCHAR(60)  NOT NULL DEFAULT 'bi-circle',
    grupo       VARCHAR(60)  NOT NULL DEFAULT 'General',
    orden       INT          NOT NULL DEFAULT 0,
    activo      BIT          NOT NULL DEFAULT 1
);

-- 3. Pivot: qué módulos tiene cada rol
CREATE TABLE electronicas.RolModulos (
    id_rol    INT NOT NULL,
    id_modulo INT NOT NULL,
    PRIMARY KEY (id_rol, id_modulo),
    CONSTRAINT FK_RolModulos_Rol    FOREIGN KEY (id_rol)    REFERENCES electronicas.Roles(id_rol),
    CONSTRAINT FK_RolModulos_Modulo FOREIGN KEY (id_modulo) REFERENCES electronicas.Modulos(id_modulo)
);

-- 4. Columna id_rol en Usuarios
ALTER TABLE electronicas.Usuarios
    ADD id_rol INT NULL
        CONSTRAINT FK_Usuarios_Rol FOREIGN KEY REFERENCES electronicas.Roles(id_rol);

-- ============================================================
-- SEED: Módulos
-- ============================================================
INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden) VALUES
('dasboard',       'Dashboard',            'bi-grid',                  'Core',            1),
('mantenimientos', 'Mantenimiento',         'bi-laptop',                'Operaciones',     2),
('maquinas',       'Máquinas',              'bi-cpu',                   'Operaciones',     3),
('repuestos',      'Repuestos',             'bi-box-seam',              'Operaciones',     4),
('proveedores',    'Proveedores',           'bi-truck',                 'Parametrización', 5),
('marcas',         'Marcas',               'bi-bookmark',              'Parametrización', 6),
('modelos',        'Modelos',              'bi-list-ul',               'Parametrización', 7),
('tiposRepuestos', 'Tipos de Repuestos',   'bi-tags',                  'Parametrización', 8),
('divisas',        'Divisas',              'bi-currency-exchange',     'Parametrización', 9),
('usuarios',       'Usuarios',             'bi-people',                'Administración',  10),
('roles',          'Roles y Permisos',     'bi-shield-lock',           'Administración',  11);

-- ============================================================
-- SEED: Roles
-- ============================================================
INSERT INTO electronicas.Roles (nombre, descripcion) VALUES
('Administrador', 'Acceso completo al sistema'),
('Técnico',       'Acceso a operaciones de mantenimiento y repuestos');

-- ============================================================
-- SEED: Permisos por rol
-- ============================================================
-- Administrador → todos los módulos
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 1, id_modulo FROM electronicas.Modulos WHERE activo = 1;

-- Técnico → dashboard + operaciones
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 2, id_modulo FROM electronicas.Modulos
WHERE clave IN ('dasboard','mantenimientos','maquinas','repuestos');

-- ============================================================
-- OPCIONAL: asignar rol Administrador al primer usuario existente
-- UPDATE electronicas.Usuarios SET id_rol = 1 WHERE id_usuario = 1;
-- ============================================================
