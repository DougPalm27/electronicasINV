USE [ElectronicasDB]
GO

-- ══════════════════════════════════════════════════════════════════
-- 1. Tabla MaquinaRepuestos  (ciclo instalación / retiro por máquina)
-- ══════════════════════════════════════════════════════════════════
IF NOT EXISTS (
    SELECT 1 FROM sys.tables
    WHERE object_id = OBJECT_ID(N'electronicas.MaquinaRepuestos')
)
BEGIN
    CREATE TABLE electronicas.MaquinaRepuestos (
        id_maquina_repuesto         INT          IDENTITY(1,1) NOT NULL,
        id_maquina                  INT          NOT NULL,
        id_repuesto                 INT          NOT NULL,
        id_detalle_repuesto         INT          NULL,        -- serie (NULL para cantidad)
        cantidad                    INT          NOT NULL CONSTRAINT DF_MaqRep_Cantidad DEFAULT 1,
        id_mantenimiento_instalacion INT         NOT NULL,
        fecha_instalacion           DATETIME     NOT NULL CONSTRAINT DF_MaqRep_FechaIns DEFAULT GETDATE(),
        id_mantenimiento_retiro     INT          NULL,
        fecha_retiro                DATETIME     NULL,
        tipo_retiro                 VARCHAR(20)  NULL,        -- 'baja' | 'devolucion'
        observaciones_retiro        VARCHAR(255) NULL,

        CONSTRAINT PK_MaquinaRepuestos PRIMARY KEY (id_maquina_repuesto),
        CONSTRAINT FK_MaqRep_Maquina  FOREIGN KEY (id_maquina)
            REFERENCES electronicas.Maquinas(id_maquina),
        CONSTRAINT FK_MaqRep_Repuesto FOREIGN KEY (id_repuesto)
            REFERENCES electronicas.Repuestos(id_repuesto),
        CONSTRAINT FK_MaqRep_Detalle  FOREIGN KEY (id_detalle_repuesto)
            REFERENCES electronicas.RepuestosDetalle(id_detalle_repuesto),
        CONSTRAINT FK_MaqRep_MantIns  FOREIGN KEY (id_mantenimiento_instalacion)
            REFERENCES electronicas.Mantenimientos(id_mantenimiento),
        CONSTRAINT FK_MaqRep_MantRet  FOREIGN KEY (id_mantenimiento_retiro)
            REFERENCES electronicas.Mantenimientos(id_mantenimiento)
    );
    PRINT 'Tabla MaquinaRepuestos creada.';
END
ELSE
    PRINT 'Tabla MaquinaRepuestos ya existe.';
GO

-- ══════════════════════════════════════════════════════════════════
-- 2. Estado "Dado de baja" en EstadoRepuestos  (para series)
-- ══════════════════════════════════════════════════════════════════
IF NOT EXISTS (SELECT 1 FROM electronicas.EstadoRepuestos WHERE nombre = 'Dado de baja')
BEGIN
    INSERT INTO electronicas.EstadoRepuestos (nombre) VALUES ('Dado de baja');
    PRINT 'Estado "Dado de baja" insertado.';
END
ELSE
    PRINT 'Estado "Dado de baja" ya existe.';
GO

-- ══════════════════════════════════════════════════════════════════
-- 3. Tipos de movimiento para retiros
-- ══════════════════════════════════════════════════════════════════
IF NOT EXISTS (SELECT 1 FROM electronicas.TiposMovimientoRepuesto WHERE nombre = 'Retiro - Devolución')
BEGIN
    INSERT INTO electronicas.TiposMovimientoRepuesto (nombre, afecta_stock, signo)
    VALUES ('Retiro - Devolución', 1, 1);
    PRINT 'Tipo movimiento "Retiro - Devolución" insertado.';
END
ELSE
    PRINT 'Tipo "Retiro - Devolución" ya existe.';
GO

IF NOT EXISTS (SELECT 1 FROM electronicas.TiposMovimientoRepuesto WHERE nombre = 'Retiro - Baja')
BEGIN
    INSERT INTO electronicas.TiposMovimientoRepuesto (nombre, afecta_stock, signo)
    VALUES ('Retiro - Baja', 0, -1);
    PRINT 'Tipo movimiento "Retiro - Baja" insertado.';
END
ELSE
    PRINT 'Tipo "Retiro - Baja" ya existe.';
GO

PRINT '✔ Migración completada.';
GO
