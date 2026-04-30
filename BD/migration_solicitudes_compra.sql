-- ============================================================
-- Módulo: Solicitudes de Compra de Repuestos
-- Ejecutar en SSMS una sola vez
-- ============================================================

-- ── 1. Tabla cabecera ──────────────────────────────────────
CREATE TABLE electronicas.SolicitudesCompra (
    id_solicitud_compra  INT           IDENTITY(1,1) PRIMARY KEY,
    id_usuario           INT           NOT NULL,
    id_proveedor         INT           NULL,
    id_divisa            INT           NOT NULL,
    descripcion          VARCHAR(600)  NOT NULL,
    notas                VARCHAR(1000) NULL,
    estado               VARCHAR(30)   NOT NULL DEFAULT 'Borrador',
    fecha_solicitud      DATETIME      NOT NULL DEFAULT GETDATE(),
    -- Aprobación
    id_aprobador         INT           NULL,
    fecha_aprobacion     DATETIME      NULL,
    motivo_rechazo       VARCHAR(600)  NULL,
    -- Orden al proveedor
    numero_orden         VARCHAR(100)  NULL,
    fecha_orden          DATETIME      NULL,
    fecha_entrega_est    DATE          NULL,
    -- Recepción
    id_receptor          INT           NULL,
    fecha_recepcion      DATETIME      NULL,

    CONSTRAINT FK_SC_Usuario   FOREIGN KEY (id_usuario)   REFERENCES electronicas.Usuarios(id_usuario),
    CONSTRAINT FK_SC_Proveedor FOREIGN KEY (id_proveedor) REFERENCES electronicas.Proveedores(id_proveedor),
    CONSTRAINT FK_SC_Divisa    FOREIGN KEY (id_divisa)    REFERENCES electronicas.Divisas(id_divisa),
    CONSTRAINT FK_SC_Aprobador FOREIGN KEY (id_aprobador) REFERENCES electronicas.Usuarios(id_usuario),
    CONSTRAINT FK_SC_Receptor  FOREIGN KEY (id_receptor)  REFERENCES electronicas.Usuarios(id_usuario),
    CONSTRAINT CK_SC_Estado    CHECK (estado IN (
        'Borrador','Pendiente','Aprobada','Ordenada','Recibida','Rechazada','Cancelada'
    ))
);

CREATE INDEX IX_SC_Estado   ON electronicas.SolicitudesCompra (estado);
CREATE INDEX IX_SC_Usuario  ON electronicas.SolicitudesCompra (id_usuario);

-- ── 2. Tabla detalle (ítems) ───────────────────────────────
CREATE TABLE electronicas.SolicitudesCompraDetalle (
    id_detalle          INT            IDENTITY(1,1) PRIMARY KEY,
    id_solicitud_compra INT            NOT NULL,
    id_repuesto         INT            NOT NULL,
    cantidad_solicitada INT            NOT NULL,
    costo_unitario      DECIMAL(12,4)  NOT NULL DEFAULT 0,  -- en divisa de la solicitud
    cantidad_recibida   INT            NOT NULL DEFAULT 0,
    costo_recibido      DECIMAL(12,4)  NULL,                -- costo real confirmado al recibir

    CONSTRAINT FK_SCD_Solicitud  FOREIGN KEY (id_solicitud_compra) REFERENCES electronicas.SolicitudesCompra(id_solicitud_compra),
    CONSTRAINT FK_SCD_Repuesto   FOREIGN KEY (id_repuesto)         REFERENCES electronicas.Repuestos(id_repuesto)
);

CREATE INDEX IX_SCD_Solicitud ON electronicas.SolicitudesCompraDetalle (id_solicitud_compra);

-- ── 3. Módulo en el sistema de permisos ───────────────────
INSERT INTO electronicas.Modulos (clave, nombre, icono, grupo, orden)
VALUES ('compras', 'Solicitudes de Compra', 'bi-cart-check', 'Operaciones', 6);

-- Asignar a Administrador (id_rol = 1)
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 1, id_modulo FROM electronicas.Modulos WHERE clave = 'compras';

-- Asignar a Técnico (id_rol = 2)  — puede crear solicitudes
INSERT INTO electronicas.RolModulos (id_rol, id_modulo)
SELECT 2, id_modulo FROM electronicas.Modulos WHERE clave = 'compras';
