-- ============================================================
-- Candado de operario: registro de turnos por estación
-- Cada fila = una sesión de "trabajando en la máquina X",
-- abierta al validar el PIN en bloqueo.php y cerrada al bloquear
-- o por auto-bloqueo. Registra el módulo Turnos en el catálogo
-- de permisos y lo asigna al rol Administrador.
-- Ejecutar en SSMS sobre la BD correcta.
-- ============================================================

IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'TurnoSesiones'
)
BEGIN
    CREATE TABLE electronicas.TurnoSesiones (
        id_sesion   INT IDENTITY(1,1) NOT NULL,
        id_usuario  INT           NOT NULL,
        estacion    VARCHAR(100)  NOT NULL,
        inicio      DATETIME      NOT NULL CONSTRAINT DF_TurnoSesiones_inicio DEFAULT GETDATE(),
        fin         DATETIME      NULL,

        CONSTRAINT PK_TurnoSesiones        PRIMARY KEY CLUSTERED (id_sesion ASC),
        CONSTRAINT FK_TurnoSesiones_Usuario FOREIGN KEY (id_usuario) REFERENCES electronicas.Usuarios(id_usuario)
    );

    CREATE INDEX IX_TurnoSesiones_estacion_fin ON electronicas.TurnoSesiones(estacion, fin);

    PRINT 'Tabla electronicas.TurnoSesiones creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.TurnoSesiones ya existe. Sin cambios.';
END
GO

-- ── Registrar módulo en el catálogo de permisos ────────────
IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'turnos')
BEGIN
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden)
    VALUES ('turnos', 'Turnos de Operarios', 'bi-person-badge', 'Operaciones', 13);

    PRINT 'Módulo turnos registrado en electronicas.Modulos.';
END
ELSE
BEGIN
    PRINT 'El módulo turnos ya está registrado. Sin cambios.';
END
GO

-- ── Asignar al rol Administrador ───────────────────────────
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT r.id_rol, m.id_modulo
FROM electronicas.Roles r
CROSS JOIN electronicas.Modulos m
WHERE r.nombre = N'Administrador' AND m.clave = 'turnos'
  AND NOT EXISTS (
        SELECT 1 FROM electronicas.RolModulos rm
        WHERE rm.id_rol = r.id_rol AND rm.id_modulo = m.id_modulo
  );
GO
