-- ============================================================
-- Catálogo central de preajustes de Delvis (Satake Evolution RGB)
--
-- Guarda la "receta" de un preajuste (cuadrículas, diagonales,
-- esferas, tamaños de defecto, erosión, segunda pasada) para no
-- tener que recrearla a mano en cada máquina. El candado de
-- operario de cada PC la descarga y la escribe en el Presets.xml
-- local de Delvis.
--
-- Importante: el CENTRO de color de una esfera cambia de lote a
-- lote (el tono del café bueno de hoy no es el de la semana
-- pasada), así que NO se guarda como un valor fijo para siempre.
-- Las reglas marcadas depende_de_muestra = 1 se calibran en la
-- máquina real con el botón "Muestra" de Delvis, y lo calibrado
-- se puede replicar a otras máquinas el mismo día (DelvisPresetMuestras),
-- nunca reutilizar de forma automática días después.
--
-- Ejecutar en SSMS sobre la BD correcta.
-- ============================================================

-- ── 1. Preajustes: identidad y lo que no cambia por lote ────
IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'DelvisPresets'
)
BEGIN
    CREATE TABLE electronicas.DelvisPresets (
        id_preset           INT IDENTITY(1,1) NOT NULL,
        nombre              NVARCHAR(100)      NOT NULL,   -- nombre del preajuste en Delvis (_name), ej. "sucios"
        descripcion         NVARCHAR(400)      NULL,
        tipo_grano          NVARCHAR(20)       NOT NULL DEFAULT 'cafe',  -- cafe, arroz
        erosion             TINYINT            NOT NULL DEFAULT 0,      -- 0-6, recorte de borde del objeto (PerPresetSettings.Erosion)
        activo              BIT                NOT NULL DEFAULT 1,
        version             INT                NOT NULL DEFAULT 1,      -- sube cada vez que cambia la estructura; el candado compara antes de reescribir
        id_usuario_creador  INT                NULL,
        fecha_creacion      DATETIME           NOT NULL DEFAULT GETDATE(),
        fecha_modificacion  DATETIME           NOT NULL DEFAULT GETDATE(),

        CONSTRAINT PK_DelvisPresets    PRIMARY KEY CLUSTERED (id_preset ASC),
        CONSTRAINT UQ_DelvisPresets_nombre UNIQUE (nombre),
        CONSTRAINT FK_DelvisPresets_Usuario FOREIGN KEY (id_usuario_creador) REFERENCES electronicas.Usuarios(id_usuario)
    );

    PRINT 'Tabla electronicas.DelvisPresets creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.DelvisPresets ya existe. Sin cambios.';
END
GO

-- ── 2. Reglas del preajuste: cada esfera, cuadrícula o diagonal ──
IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'DelvisPresetReglas'
)
BEGIN
    CREATE TABLE electronicas.DelvisPresetReglas (
        id_regla            INT IDENTITY(1,1) NOT NULL,
        id_preset           INT                NOT NULL,
        paso                VARCHAR(10)        NOT NULL,   -- primaria, resort  (RgbGeometryPrimarySettings / RgbGeometryResortSettings)
        orden_slot          TINYINT            NOT NULL,   -- 0-7: posición fija dentro de ese paso (SettingIndex de Delvis)
        habilitado          BIT                NOT NULL DEFAULT 1,
        tipo_geometria      VARCHAR(12)        NOT NULL,   -- esfera, cuadricula, diagonal  (TypeId 0/1/2)
        -- esfera:     Reject1..8, Accept1..8, DominantAccept1..8
        -- cuadricula: L_Light, L_Dark, a_Light, a_Dark, b_Light, b_Dark
        -- diagonal:   DarkRed, LightGreen, DarkYellow, LightBlue, RedBlue, GreenYellow,
        --             LightRed, DarkGreen, LightYellow, DarkBlue, YellowRed, BlueGreen
        subtipo             VARCHAR(30)        NOT NULL,
        clase_defecto       CHAR(1)            NOT NULL,   -- A, B, C, D (AreaDefect: qué tamaño de DelvisPresetTamanos dispara el rechazo)
        vista               VARCHAR(10)        NOT NULL DEFAULT 'ambas',  -- frontal, trasera, ambas
        descripcion         NVARCHAR(200)      NULL,       -- qué defecto busca, en español (para mostrarlo en el candado)

        -- Solo aplica a tipo_geometria = 'esfera'. Si depende_de_muestra = 1,
        -- estos tres quedan NULL: el centro real sale de DelvisPresetMuestras,
        -- calibrado con "Muestra" en la máquina. Si = 0, es un color fijo
        -- conocido (ej. negro puro) que no cambia de lote a lote.
        depende_de_muestra  BIT                NOT NULL DEFAULT 0,
        centro_l            DECIMAL(5,2)       NULL,
        centro_a            DECIMAL(5,2)       NULL,
        centro_b            DECIMAL(5,2)       NULL,
        sensibilidad        DECIMAL(4,3)       NULL,       -- 0.000-1.000; NULL si depende_de_muestra y aún no se calibra

        CONSTRAINT PK_DelvisPresetReglas     PRIMARY KEY CLUSTERED (id_regla ASC),
        CONSTRAINT UQ_DelvisPresetReglas_slot UNIQUE (id_preset, paso, orden_slot),
        CONSTRAINT FK_DelvisPresetReglas_Preset FOREIGN KEY (id_preset) REFERENCES electronicas.DelvisPresets(id_preset)
    );

    CREATE INDEX IX_DelvisPresetReglas_preset ON electronicas.DelvisPresetReglas(id_preset);

    PRINT 'Tabla electronicas.DelvisPresetReglas creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.DelvisPresetReglas ya existe. Sin cambios.';
END
GO

-- ── 3. Tamaños de defecto por clase (A-D) y por paso ────────
IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'DelvisPresetTamanos'
)
BEGIN
    CREATE TABLE electronicas.DelvisPresetTamanos (
        id_preset      INT          NOT NULL,
        clase_defecto  CHAR(1)      NOT NULL,   -- A, B, C, D
        paso           VARCHAR(10)  NOT NULL,   -- primaria, resort
        tamano_px      SMALLINT     NOT NULL,   -- umbral: píxeles en la ventana de área (17x15), o tamaño de mancha si es Spot

        CONSTRAINT PK_DelvisPresetTamanos PRIMARY KEY CLUSTERED (id_preset, clase_defecto, paso),
        CONSTRAINT FK_DelvisPresetTamanos_Preset FOREIGN KEY (id_preset) REFERENCES electronicas.DelvisPresets(id_preset)
    );

    PRINT 'Tabla electronicas.DelvisPresetTamanos creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.DelvisPresetTamanos ya existe. Sin cambios.';
END
GO

-- ── 4. Calibraciones en vivo ("Muestra") por máquina y fecha ──
-- Historial, no un valor único: permite ver con qué color se trabajó
-- cada lote, y replicar la calibración de una máquina a otra el mismo
-- día sin tener que repetir "Muestra" en cada línea.
IF NOT EXISTS (
    SELECT 1 FROM sys.tables t
    INNER JOIN sys.schemas s ON s.schema_id = t.schema_id
    WHERE s.name = 'electronicas' AND t.name = 'DelvisPresetMuestras'
)
BEGIN
    CREATE TABLE electronicas.DelvisPresetMuestras (
        id_muestra    INT IDENTITY(1,1) NOT NULL,
        id_regla      INT                NOT NULL,
        estacion      NVARCHAR(100)      NOT NULL,   -- máquina donde se calibró (mismo valor que TurnoSesiones.estacion)
        centro_l      DECIMAL(5,2)       NOT NULL,
        centro_a      DECIMAL(5,2)       NOT NULL,
        centro_b      DECIMAL(5,2)       NOT NULL,
        sensibilidad  DECIMAL(4,3)       NOT NULL,
        vigente       BIT                NOT NULL DEFAULT 1,  -- se apaga sola al llegar una calibración más nueva de esa estación
        notas         NVARCHAR(200)      NULL,                -- ej. "lote 30/09, finca La Esperanza"
        id_usuario    INT                NULL,
        fecha         DATETIME           NOT NULL DEFAULT GETDATE(),

        CONSTRAINT PK_DelvisPresetMuestras      PRIMARY KEY CLUSTERED (id_muestra ASC),
        CONSTRAINT FK_DelvisPresetMuestras_Regla FOREIGN KEY (id_regla) REFERENCES electronicas.DelvisPresetReglas(id_regla),
        CONSTRAINT FK_DelvisPresetMuestras_Usuario FOREIGN KEY (id_usuario) REFERENCES electronicas.Usuarios(id_usuario)
    );

    CREATE INDEX IX_DelvisPresetMuestras_regla_estacion ON electronicas.DelvisPresetMuestras(id_regla, estacion, fecha DESC);
    CREATE INDEX IX_DelvisPresetMuestras_regla          ON electronicas.DelvisPresetMuestras(id_regla, fecha DESC);

    PRINT 'Tabla electronicas.DelvisPresetMuestras creada correctamente.';
END
ELSE
BEGIN
    PRINT 'La tabla electronicas.DelvisPresetMuestras ya existe. Sin cambios.';
END
GO

-- ── Registrar módulo en el catálogo de permisos ────────────
IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'preajustes_delvis')
BEGIN
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden)
    VALUES ('preajustes_delvis', 'Preajustes Delvis', 'bi-sliders', 'Operaciones', 14);

    PRINT 'Módulo preajustes_delvis registrado en electronicas.Modulos.';
END
ELSE
BEGIN
    PRINT 'El módulo preajustes_delvis ya está registrado. Sin cambios.';
END
GO

-- ── Asignar al rol Administrador ───────────────────────────
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT r.id_rol, m.id_modulo
FROM electronicas.Roles r
CROSS JOIN electronicas.Modulos m
WHERE r.nombre = N'Administrador' AND m.clave = 'preajustes_delvis'
  AND NOT EXISTS (
        SELECT 1 FROM electronicas.RolModulos rm
        WHERE rm.id_rol = r.id_rol AND rm.id_modulo = m.id_modulo
  );
GO
