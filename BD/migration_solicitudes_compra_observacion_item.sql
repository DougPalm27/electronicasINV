-- Agrega observacion por item a las solicitudes de compra
ALTER TABLE electronicas.SolicitudesCompraDetalle
    ADD observacion VARCHAR(600) NULL;
