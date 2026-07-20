-- ============================================================
-- Módulo GPS — Mapa de rastreo centralizado (piloto)
-- Base: ElectronicasDB  |  Schema: gps
--
-- Agrega:
--   1. Columnas de integración a gps.Plataformas (tipo_integracion, api_url)
--   2. Tabla gps.Posiciones — caché de la última posición por dispositivo
--   3. Módulo 'gpsMapa' en el sistema de permisos
--   4. Marca NavSat y VTG como motor 'gps-server' (piloto)
--
-- Idempotente: se puede correr varias veces sin duplicar.
-- ============================================================
USE ElectronicasDB;
GO

-- ── 1. Columnas de integración en Plataformas ────────────────
IF COL_LENGTH('gps.Plataformas', 'tipo_integracion') IS NULL
    ALTER TABLE gps.Plataformas ADD tipo_integracion NVARCHAR(30) NULL;
GO
IF COL_LENGTH('gps.Plataformas', 'api_url') IS NULL
    ALTER TABLE gps.Plataformas ADD api_url NVARCHAR(300) NULL;
GO

-- tipo_integracion: clave del motor de rastreo ('gps-server', 'gpswox', 'tracksolid', …)
--                   NULL = plataforma sin integración automática (solo acceso rápido)
-- api_url:          URL base del motor (scheme://host), sin /main.php ni /index.php

-- ── 2. Caché de posiciones ───────────────────────────────────
-- Guarda la última posición conocida de cada dispositivo. El mapa la lee al
-- instante y el refresco en vivo la actualiza. Nunca se borra el histórico de
-- la plataforma origen; esto es solo un espejo de la última lectura.
IF OBJECT_ID('gps.Posiciones', 'U') IS NULL
CREATE TABLE gps.Posiciones (
    id_posicion     INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    id_cuenta       INT           NOT NULL
        REFERENCES gps.CuentasGPS(id_cuenta),
    id_gps          INT           NULL      -- vehículo emparejado por placa (si existe)
        REFERENCES gps.GPS(id_gps),
    dispositivo     NVARCHAR(100) NULL,      -- nombre del equipo en la plataforma
    imei            NVARCHAR(30)  NULL,
    placa           NVARCHAR(20)  NULL,
    lat             DECIMAL(10,7) NULL,
    lng             DECIMAL(10,7) NULL,
    velocidad       INT           NULL,      -- km/h
    rumbo           INT           NULL,      -- 0-359
    encendido       BIT           NULL,
    direccion       NVARCHAR(400) NULL,
    fecha_posicion  DATETIME      NULL,      -- último reporte del equipo
    fecha_captura   DATETIME      NOT NULL DEFAULT GETDATE(),
    CONSTRAINT UQ_Posiciones_cuenta_imei UNIQUE (id_cuenta, imei)
);
GO

-- ── 2b. IMEI en los vehículos (emparejamiento robusto) ───────
-- El nombre del equipo varía entre motores (en Optimus es "PLACA alias"),
-- así que emparejamos la posición con el vehículo por IMEI cuando esté.
-- El selector "Agregar vehículos" llena esta columna al vincular.
IF COL_LENGTH('gps.GPS', 'imei') IS NULL
    ALTER TABLE gps.GPS ADD imei NVARCHAR(30) NULL;
GO

-- ── 3. Módulo en el sistema de permisos ──────────────────────
IF NOT EXISTS (SELECT 1 FROM electronicas.Modulos WHERE clave = 'gpsMapa')
    INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden, activo)
    SELECT 'gpsMapa', 'Mapa GPS', 'bi bi-map-fill', 'GPS',
           ISNULL((SELECT MIN(orden) FROM electronicas.Modulos WHERE grupo = 'GPS'), 50) - 1,
           1;
GO

-- Otorgar el módulo a todos los roles que ya ven Credenciales GPS
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT rm.id_rol, mMapa.id_modulo
FROM electronicas.RolModulos rm
INNER JOIN electronicas.Modulos mCred ON mCred.id_modulo = rm.id_modulo AND mCred.clave = 'gpsCredenciales'
CROSS JOIN electronicas.Modulos mMapa
WHERE mMapa.clave = 'gpsMapa'
  AND NOT EXISTS (
      SELECT 1 FROM electronicas.RolModulos x
      WHERE x.id_rol = rm.id_rol AND x.id_modulo = mMapa.id_modulo
  );
GO

-- ── 4. Piloto: marcar las plataformas del motor gps-server.net ──
-- Se empareja por DOMINIO (no por nombre visible): en producción la instancia
-- de VTG aparece como "Vaestrack", así que buscar por nombre no la tomaría.
-- El adaptador limpia la URL (quita /main.php); api_url se fija al host https.
UPDATE gps.Plataformas
   SET tipo_integracion = 'gps-server', api_url = 'https://rastreo.navsathn.com'
 WHERE url_base LIKE '%navsathn.com%';

UPDATE gps.Plataformas
   SET tipo_integracion = 'gps-server', api_url = 'https://tr.vtghn.com'
 WHERE url_base LIKE '%vtghn.com%';

-- rastreohn: mismo motor gps-server (confirmado: CSRF_TOKEN + converter_es + VTracker)
UPDATE gps.Plataformas
   SET tipo_integracion = 'gps-server', api_url = 'https://v1.rastreohn.com'
 WHERE url_base LIKE '%rastreohn.com%';
GO

-- Optimus: motor LoopBack. Su LISTADO de equipos es REST (sirve para el selector);
-- sus posiciones en vivo llegan por WebSocket (worker pendiente, fase siguiente).
UPDATE gps.Plataformas
   SET tipo_integracion = 'optimus', api_url = 'https://optimushn.com/api'
 WHERE url_base LIKE '%optimushn.com%';
GO

-- TrackSolid Pro (Jimi): sin API abierta; se integra por login web headless que
-- devuelve token JWT, y luego lectura de /v3/new/newEquipment/queryEquipmentList.
UPDATE gps.Plataformas
   SET tipo_integracion = 'tracksolid', api_url = 'https://us.tracksolidpro.com'
 WHERE url_base LIKE '%tracksolidpro.com%';
GO

-- Detektor GPS: plataforma PHP propia. Login por sesión (movil/filtro.php) +
-- lectura de movil/mapa-json.php.
UPDATE gps.Plataformas
   SET tipo_integracion = 'detektor', api_url = 'https://hn.detektorgps.com/gps_hn/movil'
 WHERE url_base LIKE '%detektorgps.com%';
GO

-- skytekgps: plataforma PHP/IIS propia. Login por sesión (index.php) +
-- lectura de tabla_localizar.php (tabla HTML).
UPDATE gps.Plataformas
   SET tipo_integracion = 'skytek', api_url = 'http://www.skytekgps1.com'
 WHERE url_base LIKE '%skytekgps1.com%';
GO

-- GPSWOX (motor white-label): davisgps, bigsoluthn y otras. API estándar
-- /api/login → user_api_hash → /api/get_devices. api_url = scheme://host.
UPDATE gps.Plataformas SET tipo_integracion = 'gpswox', api_url = 'https://davisgps.com'        WHERE url_base LIKE '%davisgps.com%';
UPDATE gps.Plataformas SET tipo_integracion = 'gpswox', api_url = 'https://lite.bigsoluthn.com' WHERE url_base LIKE '%bigsoluthn.com%';
GO

-- globaltechn (white-label de service24gps): login por sesión (nick + passwd) +
-- lectura de /Moviles/getVehiculos (JSON indexado por IMEI).
UPDATE gps.Plataformas
   SET tipo_integracion = 'globaltechn', api_url = 'https://mapas.globaltechn.net'
 WHERE url_base LIKE '%globaltechn%';
GO
