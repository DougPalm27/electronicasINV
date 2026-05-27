-- ================================================================
-- Módulo: HorariosMonitoreo
-- Schema: RRHH (tablas propias) + FK cross-schema a electronicas
-- ================================================================

-- Crear schema RRHH si no existe
IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'RRHH')
    EXEC('CREATE SCHEMA RRHH');
GO

-- ── 1. Definición de turnos ──────────────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'TurnosMonitoreo')
BEGIN
    CREATE TABLE RRHH.TurnosMonitoreo (
        id_turno        INT IDENTITY(1,1) NOT NULL,
        nombre          VARCHAR(50)       NOT NULL,
        hora_inicio     TIME(0)           NOT NULL,
        hora_fin        TIME(0)           NOT NULL,
        dia_descanso    TINYINT           NOT NULL,  -- 0=Dom 1=Lun 2=Mar 3=Mié
        hora_sab_inicio TIME(0)           NULL,      -- NULL = igual a hora_inicio
        hora_sab_fin    TIME(0)           NULL,      -- NULL = igual a hora_fin
        es_comodin      BIT               NOT NULL DEFAULT 0,
        orden_rotacion  TINYINT           NOT NULL,
        activo          BIT               NOT NULL DEFAULT 1,
        CONSTRAINT PK_TurnosMonitoreo PRIMARY KEY (id_turno)
    );

    INSERT INTO RRHH.TurnosMonitoreo
        (nombre, hora_inicio, hora_fin, dia_descanso, hora_sab_inicio, hora_sab_fin, es_comodin, orden_rotacion)
    VALUES
        ('8am - 5pm',  '08:00:00', '17:00:00', 0, '08:00:00', '12:00:00', 1, 1),
        ('5am - 2pm',  '05:00:00', '14:00:00', 1, NULL,       NULL,       0, 2),
        ('2pm - 10pm', '14:00:00', '22:00:00', 2, NULL,       NULL,       0, 3),
        ('10pm - 5am', '22:00:00', '05:00:00', 3, NULL,       NULL,       0, 4);
END
GO

-- ── 2. Técnicos designados al área de monitoreo ──────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'TecnicosMonitoreo')
BEGIN
    CREATE TABLE RRHH.TecnicosMonitoreo (
        id_tecnico_mon   INT IDENTITY(1,1) NOT NULL,
        id_usuario       INT               NOT NULL,
        activo           BIT               NOT NULL DEFAULT 1,
        fecha_asignacion DATETIME          NOT NULL DEFAULT GETDATE(),
        CONSTRAINT PK_TecnicosMonitoreo PRIMARY KEY (id_tecnico_mon),
        CONSTRAINT UQ_TecMon_Usuario     UNIQUE     (id_usuario),
        CONSTRAINT FK_TecMon_Usuario     FOREIGN KEY (id_usuario)
            REFERENCES electronicas.Usuarios(id_usuario)   -- FK cross-schema
    );
END
GO

-- ── 3. Cabecera de horario mensual ───────────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'HorarioMes')
BEGIN
    CREATE TABLE RRHH.HorarioMes (
        id_horario_mes   INT IDENTITY(1,1) NOT NULL,
        mes              TINYINT           NOT NULL,
        anio             SMALLINT          NOT NULL,
        estado           VARCHAR(20)       NOT NULL DEFAULT 'borrador',
        notas            VARCHAR(500)      NULL,
        fecha_creacion   DATETIME          NOT NULL DEFAULT GETDATE(),
        fecha_activacion DATETIME          NULL,
        CONSTRAINT PK_HorarioMes         PRIMARY KEY (id_horario_mes),
        CONSTRAINT UQ_HorarioMes         UNIQUE (mes, anio),
        CONSTRAINT CHK_HorarioMes_Estado CHECK (estado IN ('borrador','activo','archivado'))
    );
END
GO

-- ── 4. Detalle: técnico → turno por mes ─────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'HorarioMesDetalle')
BEGIN
    CREATE TABLE RRHH.HorarioMesDetalle (
        id_detalle         INT IDENTITY(1,1) NOT NULL,
        id_horario_mes     INT               NOT NULL,
        id_tecnico_mon     INT               NOT NULL,
        id_turno           INT               NOT NULL,
        meses_consecutivos TINYINT           NOT NULL DEFAULT 1,
        CONSTRAINT PK_HorarioMesDetalle PRIMARY KEY (id_detalle),
        CONSTRAINT UQ_HMD               UNIQUE (id_horario_mes, id_tecnico_mon),
        CONSTRAINT FK_HMD_Mes           FOREIGN KEY (id_horario_mes)
            REFERENCES RRHH.HorarioMes(id_horario_mes),
        CONSTRAINT FK_HMD_Tecnico       FOREIGN KEY (id_tecnico_mon)
            REFERENCES RRHH.TecnicosMonitoreo(id_tecnico_mon),
        CONSTRAINT FK_HMD_Turno         FOREIGN KEY (id_turno)
            REFERENCES RRHH.TurnosMonitoreo(id_turno)
    );
END
GO

-- ── 5. Feriados nacionales configurables ────────────────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'FeriadosNacionales')
BEGIN
    CREATE TABLE RRHH.FeriadosNacionales (
        id_feriado  INT IDENTITY(1,1) NOT NULL,
        fecha       DATE              NOT NULL,
        nombre      VARCHAR(100)      NOT NULL,
        tipo        VARCHAR(20)       NOT NULL DEFAULT 'nacional',
        activo      BIT               NOT NULL DEFAULT 1,
        CONSTRAINT PK_FeriadosNacionales PRIMARY KEY (id_feriado),
        CONSTRAINT UQ_Feriado_Fecha      UNIQUE (fecha),
        CONSTRAINT CHK_Feriado_Tipo      CHECK (tipo IN ('nacional','especial'))
    );

    -- Feriados fijos de Honduras 2025-2026
    -- NOTA: Las fechas de Semana Santa varían cada año — agregar manualmente.
    INSERT INTO RRHH.FeriadosNacionales (fecha, nombre, tipo) VALUES
    ('2025-01-01', 'Año Nuevo',                  'nacional'),
    ('2025-05-01', 'Día del Trabajo',             'nacional'),
    ('2025-09-15', 'Día de la Independencia',     'nacional'),
    ('2025-10-03', 'Día del Soldado',             'nacional'),
    ('2025-10-12', 'Día de la Raza',              'nacional'),
    ('2025-10-21', 'Día de las Fuerzas Armadas',  'nacional'),
    ('2025-12-25', 'Navidad',                     'nacional'),
    ('2026-01-01', 'Año Nuevo',                   'nacional'),
    ('2026-04-14', 'Día de las Américas',         'nacional'),
    ('2026-05-01', 'Día del Trabajo',             'nacional'),
    ('2026-09-15', 'Día de la Independencia',     'nacional'),
    ('2026-10-03', 'Día del Soldado',             'nacional'),
    ('2026-10-12', 'Día de la Raza',              'nacional'),
    ('2026-10-21', 'Día de las Fuerzas Armadas',  'nacional'),
    ('2026-12-25', 'Navidad',                     'nacional');
END
GO

-- ── 6. Excepciones de horario (feriados, urgencias) ─────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'ExcepcionesHorario')
BEGIN
    CREATE TABLE RRHH.ExcepcionesHorario (
        id_excepcion        INT IDENTITY(1,1) NOT NULL,
        id_horario_mes      INT               NOT NULL,
        fecha               DATE              NOT NULL,
        tipo                VARCHAR(30)       NOT NULL DEFAULT 'feriado',
        descripcion         VARCHAR(200)      NULL,
        resolucion          VARCHAR(30)       NULL,
        estado              VARCHAR(20)       NOT NULL DEFAULT 'pendiente',
        notas               VARCHAR(500)      NULL,
        fecha_registro      DATETIME          NOT NULL DEFAULT GETDATE(),
        id_usuario_registro INT               NULL,
        CONSTRAINT PK_ExcepcionesHorario PRIMARY KEY (id_excepcion),
        CONSTRAINT FK_ExcH_Mes           FOREIGN KEY (id_horario_mes)
            REFERENCES RRHH.HorarioMes(id_horario_mes),
        CONSTRAINT CHK_ExcH_Tipo CHECK (tipo IN ('feriado','urgencia_sabado','cambio_turno','otro')),
        CONSTRAINT CHK_ExcH_Res  CHECK (resolucion IN ('doble_pago','compensacion','turno_12h','sin_accion') OR resolucion IS NULL),
        CONSTRAINT CHK_ExcH_Est  CHECK (estado IN ('pendiente','resuelta'))
    );
END
GO

-- ── 7. Técnicos por excepción (resolución turno_12h) ────────
IF NOT EXISTS (SELECT 1 FROM sys.tables t
               INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
               WHERE s.name = 'RRHH' AND t.name = 'ExcepcionTecnicos')
BEGIN
    CREATE TABLE RRHH.ExcepcionTecnicos (
        id             INT IDENTITY(1,1) NOT NULL,
        id_excepcion   INT               NOT NULL,
        id_tecnico_mon INT               NOT NULL,
        rol            VARCHAR(30)       NOT NULL,
        turno_especial VARCHAR(30)       NULL,
        CONSTRAINT PK_ExcepcionTecnicos PRIMARY KEY (id),
        CONSTRAINT FK_ET_Excepcion      FOREIGN KEY (id_excepcion)
            REFERENCES RRHH.ExcepcionesHorario(id_excepcion),
        CONSTRAINT FK_ET_Tecnico        FOREIGN KEY (id_tecnico_mon)
            REFERENCES RRHH.TecnicosMonitoreo(id_tecnico_mon),
        CONSTRAINT CHK_ET_Rol           CHECK (rol IN ('trabaja','descansa','cubre'))
    );
END
GO

-- ── Registrar el módulo en el sistema de navegación ─────────
IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'horariosMonitoreo')
BEGIN
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden, activo)
    VALUES ('horariosMonitoreo', 'Horarios Monitoreo', 'bi-calendar-week', 'RRHH', 50, 1);
END
GO
