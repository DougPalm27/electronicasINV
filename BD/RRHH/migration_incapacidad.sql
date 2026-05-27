-- ================================================================
-- Módulo: HorariosMonitoreo — Incapacidades
-- Amplía los tipos y roles permitidos en las tablas de excepciones
-- ================================================================

-- Agregar tipo 'incapacidad' a ExcepcionesHorario
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CHK_ExcH_Tipo')
    ALTER TABLE RRHH.ExcepcionesHorario DROP CONSTRAINT CHK_ExcH_Tipo;
GO
ALTER TABLE RRHH.ExcepcionesHorario
    ADD CONSTRAINT CHK_ExcH_Tipo
    CHECK (tipo IN ('feriado','urgencia_sabado','cambio_turno','otro','incapacidad'));
GO

-- Agregar rol 'ausente' a ExcepcionTecnicos
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'CHK_ET_Rol')
    ALTER TABLE RRHH.ExcepcionTecnicos DROP CONSTRAINT CHK_ET_Rol;
GO
ALTER TABLE RRHH.ExcepcionTecnicos
    ADD CONSTRAINT CHK_ET_Rol
    CHECK (rol IN ('trabaja','descansa','cubre','ausente'));
GO




ALTER TABLE RRHH.ExcepcionesHorario DROP CONSTRAINT CHK_ExcH_Res;

ALTER TABLE RRHH.ExcepcionesHorario
    ADD CONSTRAINT CHK_ExcH_Res
    CHECK (resolucion IN ('doble_pago','compensacion','cobertura','sin_accion') OR resolucion IS NULL);