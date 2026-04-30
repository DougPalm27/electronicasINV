-- ============================================================
-- Migración: Módulo de Solicitudes de Repuestos
-- ============================================================

-- 1. Cabecera de la solicitud
CREATE TABLE electronicas.SolicitudesRepuestos (
    id_solicitud      INT IDENTITY(1,1) PRIMARY KEY,
    id_usuario        INT          NOT NULL   -- quien crea la solicitud (Usuarios)
        CONSTRAINT FK_Sol_Usuario  FOREIGN KEY REFERENCES electronicas.Usuarios(id_usuario),
    id_tecnico        INT          NULL       -- técnico que ejecutará (Tecnicos)
        CONSTRAINT FK_Sol_Tecnico  FOREIGN KEY REFERENCES electronicas.Tecnicos(id_tecnico),
    id_tipo           INT          NOT NULL   -- tipo de mantenimiento
        CONSTRAINT FK_Sol_Tipo     FOREIGN KEY REFERENCES electronicas.TipoMantenimiento(id_tipo),
    descripcion       VARCHAR(600) NOT NULL,
    fecha_programada  DATE         NOT NULL,
    estado            VARCHAR(20)  NOT NULL DEFAULT 'Pendiente',   -- Pendiente/Aprobado/Rechazado/Cancelado
    fecha_solicitud   DATETIME     NOT NULL DEFAULT GETDATE(),
    fecha_revision    DATETIME     NULL,
    id_revisor        INT          NULL
        CONSTRAINT FK_Sol_Revisor  FOREIGN KEY REFERENCES electronicas.Usuarios(id_usuario),
    motivo_rechazo    VARCHAR(600) NULL
);

-- 2. Una fila por cada máquina incluida en la solicitud
CREATE TABLE electronicas.SolicitudesMaquinas (
    id_solicitud_maquina    INT IDENTITY(1,1) PRIMARY KEY,
    id_solicitud            INT NOT NULL
        CONSTRAINT FK_SolMaq_Sol FOREIGN KEY REFERENCES electronicas.SolicitudesRepuestos(id_solicitud),
    id_maquina              INT NOT NULL
        CONSTRAINT FK_SolMaq_Maq FOREIGN KEY REFERENCES electronicas.Maquinas(id_maquina),
    descripcion             VARCHAR(600) NULL,
    id_mantenimiento_generado INT NULL
        CONSTRAINT FK_SolMaq_Mant FOREIGN KEY REFERENCES electronicas.Mantenimientos(id_mantenimiento)
);

-- 3. Repuestos específicos para cada máquina de la solicitud
CREATE TABLE electronicas.SolicitudesDetalle (
    id_detalle           INT IDENTITY(1,1) PRIMARY KEY,
    id_solicitud_maquina INT NOT NULL
        CONSTRAINT FK_SolDet_Maq FOREIGN KEY REFERENCES electronicas.SolicitudesMaquinas(id_solicitud_maquina),
    id_repuesto          INT NOT NULL
        CONSTRAINT FK_SolDet_Rep FOREIGN KEY REFERENCES electronicas.Repuestos(id_repuesto),
    cantidad             INT NOT NULL DEFAULT 1
);

-- ============================================================
-- Agregar módulo 'solicitudes' al sistema de permisos
-- ============================================================
INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden) VALUES
('solicitudes', 'Solicitudes', 'bi-clipboard-check', 'Operaciones', 5);

-- Reasignar orden de los módulos existentes que siguen (opcional)
-- UPDATE electronicas.Modulos SET orden = orden + 1 WHERE orden >= 5 AND clave != 'solicitudes';

-- Asignar a Administrador (id_rol = 1)
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 1, id_modulo FROM electronicas.Modulos WHERE clave = 'solicitudes';

-- Asignar a Técnico (id_rol = 2)
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 2, id_modulo FROM electronicas.Modulos WHERE clave = 'solicitudes';

