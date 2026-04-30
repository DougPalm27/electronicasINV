-- ============================================================
-- Tabla Ubicaciones — dónde están físicamente los repuestos
-- Ejecutar en SSMS
-- ============================================================

CREATE TABLE electronicas.Ubicaciones (
    id_ubicacion  INT           IDENTITY(1,1) PRIMARY KEY,
    nombre        VARCHAR(100)  NOT NULL,
    descripcion   VARCHAR(300)  NULL,
    activo        BIT           NOT NULL DEFAULT 1
);

-- Ubicaciones iniciales de ejemplo
INSERT INTO electronicas.Ubicaciones (nombre) VALUES
    ('Bodega'),
    ('Oficina Fundación'),
    ('Compras'),
    ('Taller'),
    ('Recepción');

-- Agregar columna id_ubicacion a Repuestos
ALTER TABLE electronicas.Repuestos
    ADD id_ubicacion INT NULL
        CONSTRAINT FK_Repuestos_Ubicacion
        FOREIGN KEY REFERENCES electronicas.Ubicaciones(id_ubicacion);

CREATE INDEX IX_Repuestos_Ubicacion ON electronicas.Repuestos (id_ubicacion);
