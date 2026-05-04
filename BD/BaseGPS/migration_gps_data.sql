-- ============================================================
-- GPS — Migración de datos desde BaseGPS (DB_RH)
-- Ejecutar DESPUÉS de migration_gps.sql
-- Requiere que los datos del sistema anterior estén accesibles.
-- Ajusta el nombre del servidor/base si es distinto.
-- ============================================================
USE ElectronicasDB;
GO

-- ── Transportes ──────────────────────────────────────────────
-- Inserta los transportes del sistema anterior manteniendo nombre.
-- El id_transporte nuevo es IDENTITY, no importa el id original.
INSERT INTO gps.Transportes (nombre) VALUES
    ('BALBINO MEZA'),
    ('BRYAN JOSE CRUZ CRUZ'),
    ('COMERCIALIZADORA CUCUYAGUA'),
    ('COOPERATIVA COADECAL'),
    ('CRISTIAN CABALLERO'),
    ('CRISTOBAL ESCALANTE'),
    ('DANNY OMAR RIVERA CALDERON'),
    ('DESCONOCIDO'),
    ('TRANSPORTES DIEGO'),
    ('DON EMIL'),
    ('FAUSTINO'),
    ('GERLEN BONILLA'),
    ('GRUPO F S.A DE C.V'),
    ('GUILLEN'),
    ('INTERCARGA'),
    ('INTERNO'),
    ('IRMA MALDONADO'),
    ('ISAAC'),
    ('JHONY ANDERSON OSORIO AMAYA'),
    ('JIMENEZ'),
    ('JULIO BU'),
    ('MARIO MARTINEZ'),
    ('NELSON EDY MALDONADO'),
    ('NORY'),
    ('PADILLA'),
    ('Pendiente'),
    ('SUAZO'),
    ('TRANSPORTE ANDRE S DE R.L. DE CAPITAL VARIABLE'),
    ('TRANSPORTE PINEDA'),
    ('TRANSPORTE RAPALO'),
    ('TRANSPORTE Y SERVICIOS MULTIPLES TARIO S.A.'),
    ('TRANSPORTES GAMA'),
    ('TRANSPORTES NAVARRO'),
    ('TRANZU'),
    ('WILMER SUAZO'),
    ('YACO');
GO

-- ── Cuentas GPS ──────────────────────────────────────────────
-- Inserta una cuenta por combinación única (transporte, plataforma, usuario).
-- Ajusta los nombres de transporte para que coincidan con los insertados arriba.

-- Referencia de IDs de plataforma (ver migration_gps.sql seed):
-- 1=TrackSolid Pro, 2=Detektor GPS, 3=NavSat, 4=VTG, 5=ProYesal, 6=Sabueso, 7=Pendiente

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'Edwjo', N'edjo2021'
FROM gps.Transportes t WHERE t.nombre = 'BALBINO MEZA';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'INCRUZ', N'Agost023'
FROM gps.Transportes t WHERE t.nombre = 'BRYAN JOSE CRUZ CRUZ';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'CAFE1', N'Comcafec24'
FROM gps.Transportes t WHERE t.nombre = 'COMERCIALIZADORA CUCUYAGUA';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 2, N'CAGROPECUARIA', N'0-0-0-0'
FROM gps.Transportes t WHERE t.nombre = 'COOPERATIVA COADECAL';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 5, N'Cristhiancaballero1976@yahoo.com', N'123456'
FROM gps.Transportes t WHERE t.nombre = 'CRISTIAN CABALLERO';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'DARIV', N'eco123'
FROM gps.Transportes t WHERE t.nombre = 'DANNY OMAR RIVERA CALDERON';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'RONIC', N'roni1991'
FROM gps.Transportes t WHERE t.nombre = 'DESCONOCIDO';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 3, N'faustino', N'faustino2024'
FROM gps.Transportes t WHERE t.nombre = 'FAUSTINO';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'bfuschich', N'basilio2022'
FROM gps.Transportes t WHERE t.nombre = 'GRUPO F S.A DE C.V';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'CAGUILLEN', N'guillen23'
FROM gps.Transportes t WHERE t.nombre = 'GUILLEN';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 3, N'idalia', N'idalia2025'
FROM gps.Transportes t WHERE t.nombre = 'INTERCARGA';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'INTERCARGA', N'inter2023'
FROM gps.Transportes t WHERE t.nombre = 'INTERCARGA';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 3, N'Suammy', N'suammy2023'
FROM gps.Transportes t WHERE t.nombre = 'INTERCARGA';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 4, N'honducafe', N'2024honducafe'
FROM gps.Transportes t WHERE t.nombre = 'INTERNO';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 2, N'IJSANTOS84', N'0-0-0-0'
FROM gps.Transportes t WHERE t.nombre = 'IRMA MALDONADO';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 1, N'cristian_lopez', N'cris.lopez1'
FROM gps.Transportes t WHERE t.nombre = 'ISAAC';

INSERT INTO gps.CuentasGPS (id_transporte, id_plataforma, usuario, contrasena)
SELECT t.id_transporte, 6, N'transportes.ivaro@yahoo.com', N'2023ivaro'
FROM gps.Transportes t WHERE t.nombre = 'PADILLA';
GO

-- ── GPS (vehículos) ──────────────────────────────────────────
-- Inserta vehículos usando id_cuenta resuelto por (transporte, usuario).
-- id_tipo_vehiculo: 1=Cabezal, 5=Por Determinar
-- id_destino: 1=ACOPIO, 2=PUERTO, 3=OPC

INSERT INTO gps.GPS (id_cuenta, id_tipo_vehiculo, id_destino, placa)
SELECT c.id_cuenta, 1, 1, N'JAX2805'
FROM gps.CuentasGPS c
INNER JOIN gps.Transportes t ON t.id_transporte = c.id_transporte
WHERE t.nombre = 'BALBINO MEZA' AND c.usuario = 'Edwjo';

-- Agrega el resto de vehículos siguiendo el mismo patrón.
-- Consulta el archivo BaseGPS.sql para el listado completo de INSERT.
GO
