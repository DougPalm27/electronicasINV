-- ============================================================
-- Módulo Solicitudes de Compra — soporte entregas parciales
-- Ejecutar en SSMS después de las migraciones anteriores
-- ============================================================

-- Ampliar CHECK constraint para incluir 'Recibida parcial'
ALTER TABLE electronicas.SolicitudesCompra
    DROP CONSTRAINT CK_SC_Estado;

ALTER TABLE electronicas.SolicitudesCompra
    ADD CONSTRAINT CK_SC_Estado CHECK (estado IN (
        'Borrador','Pendiente','Aprobada','Ordenada',
        'Recibida parcial','Recibida','Rechazada','Cancelada'
    ));
