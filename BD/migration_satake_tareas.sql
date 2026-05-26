-- ============================================================
-- Tablas normalizadas: SatakeFrecuencias, SatakeCategorias,
-- SatakeTareas + seed completo del manual
-- Fuente: Satake Evolution RGB — Guía de Mantenimiento HonduCafé
-- Ejecutar en SSMS una sola vez
-- ============================================================

-- ── 1. Lookup: Frecuencias ────────────────────────────────────
CREATE TABLE electronicas.SatakeFrecuencias (
    id_frecuencia INT           IDENTITY(1,1) PRIMARY KEY,
    nombre        NVARCHAR(30)  NOT NULL UNIQUE,
    orden         INT           NOT NULL,       -- para ORDER BY
    color_badge   NVARCHAR(60)  NOT NULL        -- clase Bootstrap del badge
);

INSERT INTO electronicas.SatakeFrecuencias (nombre, orden, color_badge) VALUES
(N'Diario',    1, 'bg-danger'),
(N'Semanal',   2, 'bg-warning text-dark'),
(N'Mensual',   3, 'bg-primary'),
(N'Semestral', 4, 'bg-info text-dark'),
(N'Anual',     5, 'bg-success');

-- ── 2. Lookup: Categorías ─────────────────────────────────────
CREATE TABLE electronicas.SatakeCategorias (
    id_categoria INT           IDENTITY(1,1) PRIMARY KEY,
    nombre       NVARCHAR(60)  NOT NULL UNIQUE,
    color_badge  NVARCHAR(60)  NOT NULL
);

INSERT INTO electronicas.SatakeCategorias (nombre, color_badge) VALUES
(N'Limpieza',   'bg-info text-dark'),
(N'Inspección', 'bg-warning text-dark'),
(N'Prueba',     'bg-primary'),
(N'Revisión',   'bg-secondary');

-- ── 3. Tabla principal: Tareas ────────────────────────────────
CREATE TABLE electronicas.SatakeTareas (
    id_tarea      INT           IDENTITY(1,1) PRIMARY KEY,
    nombre        NVARCHAR(300) NOT NULL,
    id_frecuencia INT           NOT NULL
        CONSTRAINT FK_SatakeTareas_Frec
            REFERENCES electronicas.SatakeFrecuencias(id_frecuencia),
    id_categoria  INT           NOT NULL
        CONSTRAINT FK_SatakeTareas_Cat
            REFERENCES electronicas.SatakeCategorias(id_categoria),
    por_que       NVARCHAR(MAX) NOT NULL,
    si_no_se_hace NVARCHAR(MAX) NOT NULL,
    metodo        NVARCHAR(MAX) NULL,
    solo_tecnico  BIT           NOT NULL DEFAULT 0,
    activo        BIT           NOT NULL DEFAULT 1
);

CREATE INDEX IX_SatakeTareas_Frec ON electronicas.SatakeTareas (id_frecuencia);
CREATE INDEX IX_SatakeTareas_Cat  ON electronicas.SatakeTareas (id_categoria);

-- ── 4. Seed de tareas ─────────────────────────────────────────
-- Usamos subconsultas para los FKs, así el seed no depende del orden de IDENTITY

-- ============================================================
-- DIARIO
-- ============================================================
INSERT INTO electronicas.SatakeTareas
    (nombre, id_frecuencia, id_categoria, por_que, si_no_se_hace, metodo, solo_tecnico)
SELECT src.nombre, f.id_frecuencia, c.id_categoria,
       src.por_que, src.si_no_se_hace, src.metodo, src.solo_tecnico
FROM (VALUES

(N'Limpiar polvo y residuos de la máquina',
 N'Diario', N'Limpieza',
 N'El polvo y residuos del café se acumulan durante la operación. Si no se retiran pueden entrar a la zona óptica o a los eyectores y causar errores de clasificación. También pueden obstruir entradas de aire y sobrecalentar componentes electrónicos.',
 N'Los sensores y cámaras pierden precisión. El clasificador empieza a rechazar granos buenos o a dejar pasar defectos.',
 NULL, 0),

(N'Limpiar bandejas de alimentación y conductos',
 N'Diario', N'Limpieza',
 N'Las bandejas guían el flujo de granos hacia el área de clasificación. Las partículas finas del café (polvo, mucílago seco) se adhieren y forman capas que ralentizan o desvían el flujo, haciendo que los granos lleguen al sensor a velocidad o posición incorrecta.',
 N'Flujo irregular genera clasificación imprecisa. La producción baja y aumentan los errores de rechazo.',
 N'Usar limpiador a base de alcohol (sin amoníaco) y paño suave. Para acumulaciones usar cuchilla de plástico. En condiciones frías, activar calentadores de bandeja.',
 0),

(N'Verificar niveles de compuerta de tolva',
 N'Diario', N'Inspección',
 N'La tolva controla la cantidad de granos que entran al sistema. Si las compuertas no están al mismo nivel el flujo es desigual: unas canaletas reciben más grano que otras.',
 N'Flujo desigual genera clasificación inconsistente. Algunas canaletas pierden defectos y otras clasifican mal por exceso de granos.',
 N'Todas las compuertas deben estar ajustadas a la misma altura.',
 0),

(N'Soplar polvo dentro del área del visor',
 N'Diario', N'Limpieza',
 N'El área del visor es donde las cámaras ven los granos. El polvo suspendido se deposita sobre ventanas y lentes, reduciendo la claridad de imagen que reciben las cámaras.',
 N'Imágenes borrosas o con partículas causan que el sistema no distinga correctamente granos buenos de defectuosos.',
 N'Usar aire comprimido limpio y seco. NO usar aire para limpiar el interior del visor (puede empujar polvo hacia zona óptica). En el interior usar aspiradora.',
 0),

(N'Limpiar interior del visor (vidrio, deflector, antideslumbrante)',
 N'Diario', N'Limpieza',
 N'El vidrio del visor tiene revestimiento antirreflejo (AR) delicado. Los residuos del producto crean interferencias ópticas. El deflector y antideslumbrante controlan cómo la luz llega a las cámaras.',
 N'El visor sucio altera la lectura de color y brillo de los granos, generando falsos rechazos o defectos sin rechazar.',
 N'Usar paño de microfibra (Edmund NT54-718), toallas de vidrio óptico (NT53-984), toallitas prehumedecidas (NT53-985), detergente lavavajillas suave + agua, mezcla 50/50 alcohol + agua destilada. PROHIBIDO: Windex u otros con amoníaco, toallas de papel, manos o dedos, paños sucios.',
 0),

(N'Inspeccionar las lámparas',
 N'Diario', N'Inspección',
 N'Las lámparas LED iluminan los granos durante la clasificación. El sistema usa esa luz para comparar color y brillo de cada grano. Si una lámpara falla o pierde intensidad la iluminación es desigual.',
 N'Iluminación irregular produce lecturas incorrectas de color. El sistema puede generar alarma de falla de calibración. Si falla la calibración, reemplazar la placa LED correspondiente.',
 NULL, 0),

(N'Verificar si hay fallas en pantalla',
 N'Diario', N'Revisión',
 N'La pantalla táctil muestra en tiempo real si hay algún componente con problema: eyectores fallidos, errores de calibración, problemas de comunicación interna, etc.',
 N'Un problema pequeño puede escalar a parada completa o a clasificación incorrecta sin que el operador lo note.',
 NULL, 0),

(N'Verificar funcionamiento del limpiaparabrisas',
 N'Diario', N'Revisión',
 N'El limpiaparabrisas limpia automáticamente las ventanas del visor durante la operación. Si el cepillo o fieltro está desgastado deja residuos en el vidrio que las cámaras interpretan como defectos.',
 N'Limpieza incompleta del visor genera imágenes con manchas y errores de clasificación constantes y difíciles de identificar.',
 N'Si el cepillo o fieltro está desgastado o deformado, reemplazar.',
 0),

(N'Verificar que todos los gabinetes estén cerrados y pestillos asegurados',
 N'Diario', N'Revisión',
 N'Los gabinetes protegen los componentes electrónicos del polvo del ambiente. En una planta de beneficiado hay mucho polvo en suspensión.',
 N'Acumulación de polvo en electrónica provoca cortocircuitos, sobrecalentamiento y fallos inesperados de componentes costosos.',
 NULL, 0),

-- ============================================================
-- SEMANAL
-- ============================================================
(N'Inspeccionar sujetadores, protectores laterales y soportes',
 N'Semanal', N'Inspección',
 N'Las vibraciones constantes de la máquina pueden aflojar tornillos con el tiempo. Los protectores laterales evitan que materiales extraños entren a zonas críticas. Un soporte suelto puede desalinear componentes.',
 N'Piezas sueltas pueden caer al interior, dañar eyectores o atascar el flujo. Una desalineación afecta la precisión óptica.',
 NULL, 0),

(N'Inspeccionar compuertas de la tolva y ajustar si es necesario',
 N'Semanal', N'Inspección',
 N'Con el uso diario, las compuertas pueden desplazarse levemente. Una inspección semanal permite detectar y corregir variaciones antes de que afecten la producción.',
 N'Compuertas desajustadas crean flujos desiguales que reducen la eficiencia de clasificación.',
 NULL, 0),

(N'Inspeccionar filtros de aire y aceite',
 N'Semanal', N'Inspección',
 N'El clasificador usa aire comprimido para activar los eyectores. Si el filtro está sucio, el aire contiene humedad, aceite o partículas que dañan o hacen fallar los eyectores.',
 N'Aire contaminado hace que los eyectores se peguen, soplen continuamente, o no respondan. Los defectos no se expulsan correctamente.',
 N'Prefiltro: elimina partículas líquidas y sólidas. Recipiente transparente para monitoreo visual. Limpiar de adentro hacia afuera con aire. Reemplazar si no se puede limpiar eficazmente. / Filtro de extracción de aceite: indicador verde = OK, rojo = cambiar. El elemento filtrante NO se limpia, debe reemplazarse. Si hay agua o aceite acumulado, revisar el sistema de aire comprimido inmediatamente.',
 0),

(N'Realizar prueba de eyectores y reemplazar los fallidos',
 N'Semanal', N'Prueba',
 N'Los eyectores son las válvulas de aire que expulsan los granos defectuosos. Cada eyector cubre una pequeña zona de la canaleta. Si uno falla, los defectos de esa zona pasan sin ser rechazados.',
 N'Un eyector fallido permite que granos defectuosos contaminen el lote de café de exportación.',
 N'Señales de problema: eyector errático (suciedad, humedad o aceite en la aleta); eyector sopla continuamente (material extraño entre aleta y válvula); defectos no desviados (malla del filtro de entrada obstruida). Mantener un juego de eyectores de repuesto disponible.',
 0),

(N'Inspeccionar cepillos del limpiaparabrisas para detectar desgaste',
 N'Semanal', N'Inspección',
 N'El cepillo o fieltro se desgasta con el uso continuo. Un cepillo deformado o gastado no limpia bien el vidrio y puede rayarlo, dañando permanentemente el revestimiento antirreflejo.',
 N'Rayones en el visor son daño permanente e irreparable.',
 NULL, 0),

(N'Inspeccionar conductos para detectar desgaste',
 N'Semanal', N'Inspección',
 N'Los conductos por donde caen los granos se desgastan con el paso constante del producto. Un conducto rayado o rugoso altera la trayectoria de los granos al área de detección.',
 N'Granos con trayectoria incorrecta generan errores de detección y pueden no ser desviados correctamente por los eyectores.',
 NULL, 0),

(N'Realizar prueba de derrame',
 N'Semanal', N'Prueba',
 N'Verifica que el sistema de expulsión funciona correctamente. Se activan los feeders, se desactivan los eyectores y se observa si el producto cae en el lugar correcto.',
 N'Un problema de flujo o alineación puede pasar desapercibido y causar clasificación incorrecta sin señales de alarma.',
 N'Poner en marcha los alimentadores, apagar los eyectores, observar el flujo del producto en el área de visualización. Si el producto cae en el área de rechazo, tomar medidas correctivas.',
 0),

(N'Inspeccionar filtro del A/C, manguera de drenaje, evaporador y condensador',
 N'Semanal', N'Inspección',
 N'El clasificador tiene aire acondicionado para proteger los componentes electrónicos del calor. Si el filtro está tapado o el drenaje bloqueado, la temperatura interna sube.',
 N'Sobrecalentamiento puede causar fallos inesperados, pérdida de calibración o daño permanente en tarjetas de control.',
 N'Desconectar el voltaje de entrada ANTES de trabajar en el A/C.',
 0),

-- ============================================================
-- MENSUAL
-- ============================================================
(N'Inspeccionar gabinetes electrónicos para detectar acumulación de polvo',
 N'Mensual', N'Inspección',
 N'Aunque los gabinetes están cerrados, algo de polvo fino puede infiltrarse. Se hace revisión profunda con aspiradora del interior donde están las tarjetas de control.',
 N'El polvo sobre componentes electrónicos actúa como aislante térmico y puede producir cortocircuitos o daños en tarjetas.',
 N'Usar aspiradora (recomendada). Si no está disponible, usar aire seco y limpio. APAGAR LA MÁQUINA y desconectar toda alimentación antes.',
 0),

(N'Inspeccionar juntas de protección contra la intemperie',
 N'Mensual', N'Inspección',
 N'Las juntas o sellos evitan que el polvo del ambiente entre por las rendijas y uniones de la máquina. Con el tiempo se deterioran o se llenan de polvo y pierden su función.',
 N'Sellos deteriorados permiten entrada de polvo a zonas ópticas y electrónicas, acelerando el deterioro de componentes.',
 N'Inspeccionar para detectar rastros de polvo. Sellar según sea necesario.',
 0),

(N'Inspeccionar pernos y bisagras del visor frontal y apretar si es necesario',
 N'Mensual', N'Inspección',
 N'El visor frontal se abre frecuentemente para mantenimiento. Los pernos y bisagras pueden aflojarse. Un visor mal asegurado puede vibrar durante la operación y afectar las lecturas ópticas.',
 N'Un visor suelto que vibra genera imágenes inestables para las cámaras, produciendo errores aleatorios difíciles de diagnosticar.',
 NULL, 0),

(N'Limpiar interior del cristal del visor y área óptica',
 N'Mensual', N'Limpieza',
 N'Mensualmente se hace una limpieza profunda del interior del cristal y del área donde están las cámaras y lentes. Se accede girando el visor frontal hacia afuera.',
 N'La acumulación gradual de residuos en el área óptica interna reduce la calidad de imagen progresivamente, sin alarmas claras.',
 N'Apagar máquina → girar visor hacia afuera → limpiar con paño suave y limpiador a base de alcohol (sin amoníaco) → regresar visor y asegurar. Abrir puertas de visores y aspirar polvo del interior. NO usar aire comprimido en el interior del visor.',
 0),

(N'Limpiar filtros ópticos con paño suave para lentes',
 N'Mensual', N'Limpieza',
 N'Los filtros ópticos controlan qué longitudes de luz llegan a los sensores. El polvo sobre ellos cambia las lecturas de color que usa el sistema para clasificar.',
 N'Filtros sucios alteran la lectura de color de todos los granos. El sistema puede rechazar granos buenos o dejar pasar defectos de forma sistemática.',
 NULL, 0),

-- ============================================================
-- SEMESTRAL
-- ============================================================
(N'Limpieza completa interior y exterior de la máquina',
 N'Semestral', N'Limpieza',
 N'Dos veces al año se hace una limpieza exhaustiva de toda la máquina: todos los compartimentos, zonas internas, exterior, filtros, conductos y componentes. Es un reinicio profundo del estado de limpieza.',
 N'Se acumulan residuos en zonas difíciles de alcanzar en la limpieza diaria, degradando el rendimiento general del equipo.',
 NULL, 0),

(N'Inspeccionar estado y funcionamiento de sensores de tolva',
 N'Semestral', N'Inspección',
 N'Los sensores de la tolva detectan el nivel de producto y controlan el flujo de entrada al clasificador. Si un sensor falla, la máquina puede operar sin producto o con desbordamiento.',
 N'Sensores fallidos pueden causar operación en vacío (desgaste sin clasificar) o desbordamiento que daña otros componentes.',
 NULL, 0),

-- ============================================================
-- ANUAL
-- ============================================================
(N'Visita de técnico especialista Satake',
 N'Anual', N'Revisión',
 N'Un técnico oficial realiza calibraciones profundas, ajustes de parámetros ópticos, verificación de componentes internos que el operador no puede revisar, y actualización de configuraciones si es necesario.',
 N'Los parámetros del clasificador pueden derivar gradualmente de sus valores óptimos, reduciendo la calidad de clasificación sin que sea evidente en el día a día.',
 NULL, 1)

) AS src(nombre, frec_nombre, cat_nombre, por_que, si_no_se_hace, metodo, solo_tecnico)
INNER JOIN electronicas.SatakeFrecuencias f ON f.nombre = src.frec_nombre
INNER JOIN electronicas.SatakeCategorias  c ON c.nombre = src.cat_nombre;
