-- ============================================================
-- Módulo: Catálogo de componentes por modelo
-- - Catálogo maestro de tipos de componente
-- - Componentes por modelo con jerarquía (padre/hijo),
--   vínculo opcional a repuestos y posición sobre la radiografía
-- - Imagen de esquema (radiografía) por modelo
-- Ejecutar en SSMS una sola vez.
-- ============================================================

-- 1) Catálogo maestro de componentes
IF OBJECT_ID('electronicas.Componentes', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.Componentes (
        id_componente INT IDENTITY(1,1) PRIMARY KEY,
        nombre        VARCHAR(100) NOT NULL,
        categoria     VARCHAR(60)  NULL,
        descripcion   VARCHAR(255) NULL,
        activo        BIT          NOT NULL DEFAULT 1,
        CONSTRAINT UQ_Componentes_Nombre UNIQUE (nombre)
    );
END
GO

-- 2) Componentes de cada modelo
IF OBJECT_ID('electronicas.ModeloComponentes', 'U') IS NULL
BEGIN
    CREATE TABLE electronicas.ModeloComponentes (
        id_modelo_componente INT IDENTITY(1,1) PRIMARY KEY,
        id_modelo            INT           NOT NULL,
        id_componente        INT           NOT NULL,
        id_padre             INT           NULL,      -- jerarquía: barra → eyectores
        cantidad             INT           NOT NULL DEFAULT 1,
        especificacion       VARCHAR(255)  NULL,
        id_repuesto          INT           NULL,      -- vínculo opcional a inventario
        pos_x                DECIMAL(5,2)  NULL,      -- posición del pin sobre la
        pos_y                DECIMAL(5,2)  NULL,      -- radiografía, en porcentaje
        CONSTRAINT FK_ModComp_Modelo     FOREIGN KEY (id_modelo)     REFERENCES electronicas.Modelos (id_modelo),
        CONSTRAINT FK_ModComp_Componente FOREIGN KEY (id_componente) REFERENCES electronicas.Componentes (id_componente),
        CONSTRAINT FK_ModComp_Padre      FOREIGN KEY (id_padre)      REFERENCES electronicas.ModeloComponentes (id_modelo_componente),
        CONSTRAINT FK_ModComp_Repuesto   FOREIGN KEY (id_repuesto)   REFERENCES electronicas.Repuestos (id_repuesto)
    );
    CREATE INDEX IX_ModComp_Modelo ON electronicas.ModeloComponentes (id_modelo);
END
GO

-- 3) Imagen de esquema (radiografía) por modelo
IF COL_LENGTH('electronicas.Modelos', 'imagen_esquema') IS NULL
BEGIN
    ALTER TABLE electronicas.Modelos ADD imagen_esquema VARCHAR(255) NULL;
END
GO

-- 4) Registrar el módulo y dar permiso a Administrador y Técnico
IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'componentes')
BEGIN
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden)
    VALUES ('componentes', 'Componentes', 'bi-diagram-3', 'Operaciones', 4);
END
GO

INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT r.id_rol, m.id_modulo
FROM electronicas.Roles r
CROSS JOIN electronicas.Modulos m
WHERE m.clave = 'componentes'
  AND r.nombre IN ('Administrador', 'Técnico')
  AND NOT EXISTS (
      SELECT 1 FROM electronicas.RolModulos rm
      WHERE rm.id_rol = r.id_rol AND rm.id_modulo = m.id_modulo
  );
GO

-- ============================================================
-- SEED: catálogo básico + componentes del/los modelo(s) Evo
-- ============================================================
INSERT INTO electronicas.Componentes (nombre, categoria)
SELECT v.nombre, v.categoria
FROM (VALUES
    ('Barra de eyectores', 'Conjunto'),
    ('Eyector',            'Neumática')
) v (nombre, categoria)
WHERE NOT EXISTS (
    SELECT 1 FROM electronicas.Componentes c WHERE c.nombre = v.nombre
);
GO

-- Esquema por defecto para el Evo (dibujo referencial incluido en assets)
UPDATE electronicas.Modelos
SET imagen_esquema = 'assets/img/esquemas/evo_default.svg'
WHERE nombre LIKE '%Evo%' AND imagen_esquema IS NULL;
GO

-- Barra de eyectores del Evo (padre) + eyectores (hijo)
INSERT INTO electronicas.ModeloComponentes
    (id_modelo, id_componente, id_padre, cantidad, especificacion, pos_x, pos_y)
SELECT mo.id_modelo, c.id_componente, NULL, 1, '2 lados · 92 + 92 boquillas', 59.00, 75.00
FROM electronicas.Modelos mo
CROSS JOIN electronicas.Componentes c
WHERE mo.nombre LIKE '%Evo%'
  AND c.nombre = 'Barra de eyectores'
  AND NOT EXISTS (
      SELECT 1 FROM electronicas.ModeloComponentes mc
      WHERE mc.id_modelo = mo.id_modelo AND mc.id_componente = c.id_componente
  );
GO

INSERT INTO electronicas.ModeloComponentes
    (id_modelo, id_componente, id_padre, cantidad, especificacion)
SELECT mo.id_modelo, c.id_componente, padre.id_modelo_componente, 184, 'Filas 31 / 31 / 30 por lado'
FROM electronicas.Modelos mo
CROSS JOIN electronicas.Componentes c
INNER JOIN electronicas.ModeloComponentes padre
        ON padre.id_modelo = mo.id_modelo
       AND padre.id_componente = (SELECT id_componente FROM electronicas.Componentes WHERE nombre = 'Barra de eyectores')
WHERE mo.nombre LIKE '%Evo%'
  AND c.nombre = 'Eyector'
  AND NOT EXISTS (
      SELECT 1 FROM electronicas.ModeloComponentes mc
      WHERE mc.id_modelo = mo.id_modelo AND mc.id_componente = c.id_componente
  );
GO
