-- ============================================================
-- Crea tabla SatakeAlertas + seed de señales de problema
-- Fuente: Satake Evolution RGB — Sección 3
-- Ejecutar en SSMS una sola vez
-- ============================================================

CREATE TABLE electronicas.SatakeAlertas (
    id_alerta INT           IDENTITY(1,1) PRIMARY KEY,
    senal     NVARCHAR(300) NOT NULL,
    causa     NVARCHAR(MAX) NOT NULL,
    accion    NVARCHAR(MAX) NOT NULL,
    activo    BIT           NOT NULL DEFAULT 1
);

-- ── SEED ─────────────────────────────────────────────────────

INSERT INTO electronicas.SatakeAlertas (senal, causa, accion) VALUES
(
    N'Eyector funciona de forma errática',
    N'Suciedad, humedad o aceite acumulado en la aleta de la válvula.',
    N'Limpiar el eyector según el procedimiento de limpieza de eyectores de 10 mm.'
),(
    N'Eyector sopla continuamente',
    N'Material extraño atrapado entre la aleta y el asiento de la válvula.',
    N'Limpiar o reemplazar el eyector.'
),(
    N'Defectos no se desvían correctamente',
    N'Malla del filtro de entrada obstruida, restricción de flujo de aire.',
    N'Limpiar o reemplazar el elemento filtrante del eyector.'
),(
    N'Alarma de falla de lámparas del producto',
    N'Una placa LED del producto no puede calibrarse.',
    N'Reemplazar la placa LED del producto.'
),(
    N'Alarma de falla de lámparas de fondo',
    N'El fondo no puede calibrarse.',
    N'Reemplazar la placa LED de fondo.'
),(
    N'Indicador del filtro de aceite en rojo',
    N'El elemento filtrante está saturado de partículas.',
    N'Reemplazar el elemento filtrante. No se puede limpiar, debe sustituirse por uno nuevo.'
),(
    N'Agua o aceite en los filtros',
    N'Problema en el sistema de aire comprimido (compresor o secador).',
    N'Revisar el sistema de aire comprimido inmediatamente.'
),(
    N'Reflejos irregulares hacia las cámaras',
    N'Pintura del deflector o la parte posterior del deflector desgastada, dejando metal desnudo expuesto.',
    N'Repintar el deflector. Si no se repinta, los reflejos del metal desnudo causan disparos desiguales en el rango de tiempo de detección.'
);
