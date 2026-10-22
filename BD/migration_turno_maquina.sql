-- ============================================================
-- Candado de operario: ligar cada estación con una máquina del inventario
--
-- Hasta ahora TurnoSesiones.estacion era solo un texto escrito a mano al
-- instalar el candado ("Evolution-Linea1"), sin relación con electronicas.Maquinas.
-- Ahora cada máquina puede declarar el nombre de estación de su PC
-- (Maquinas.estacion_candado) y cada turno guarda a qué máquina pertenece
-- (TurnoSesiones.id_maquina), con lo que el panel de Turnos puede mostrar
-- modelo y ubicación y se puede cruzar con repuestos, eyectores y mantenimientos.
--
-- Es opcional y no rompe nada: una estación sin máquina asignada sigue
-- funcionando igual (id_maquina queda NULL y el panel la marca como "sin asignar").
--
-- Ejecutar en SSMS sobre la BD correcta, ANTES de subir el código nuevo.
-- Es repetible: se puede correr varias veces sin efectos.
-- ============================================================

-- ── 1. Maquinas.estacion_candado ────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.Maquinas') AND name = 'estacion_candado'
)
BEGIN
    ALTER TABLE electronicas.Maquinas
        ADD estacion_candado VARCHAR(100) NULL;

    PRINT 'Columna estacion_candado agregada a electronicas.Maquinas.';
END
ELSE
BEGIN
    PRINT 'La columna estacion_candado ya existe en electronicas.Maquinas. Sin cambios.';
END
GO

-- Una estación no puede pertenecer a dos máquinas. Índice filtrado para que
-- las máquinas sin estación (NULL) no choquen entre sí. Requiere QUOTED_IDENTIFIER ON.
SET QUOTED_IDENTIFIER ON;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('electronicas.Maquinas') AND name = 'UQ_Maquinas_estacion_candado'
)
BEGIN
    CREATE UNIQUE INDEX UQ_Maquinas_estacion_candado
        ON electronicas.Maquinas(estacion_candado)
        WHERE estacion_candado IS NOT NULL;

    PRINT 'Índice único UQ_Maquinas_estacion_candado creado.';
END
ELSE
BEGIN
    PRINT 'El índice UQ_Maquinas_estacion_candado ya existe. Sin cambios.';
END
GO

-- ── 2. TurnoSesiones.id_maquina ─────────────────────────────
IF NOT EXISTS (
    SELECT 1 FROM sys.columns
    WHERE object_id = OBJECT_ID('electronicas.TurnoSesiones') AND name = 'id_maquina'
)
BEGIN
    ALTER TABLE electronicas.TurnoSesiones
        ADD id_maquina INT NULL;

    PRINT 'Columna id_maquina agregada a electronicas.TurnoSesiones.';
END
ELSE
BEGIN
    PRINT 'La columna id_maquina ya existe en electronicas.TurnoSesiones. Sin cambios.';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name = 'FK_TurnoSesiones_Maquina'
      AND parent_object_id = OBJECT_ID('electronicas.TurnoSesiones')
)
BEGIN
    ALTER TABLE electronicas.TurnoSesiones
        ADD CONSTRAINT FK_TurnoSesiones_Maquina
            FOREIGN KEY (id_maquina) REFERENCES electronicas.Maquinas(id_maquina);

    PRINT 'Llave foránea FK_TurnoSesiones_Maquina creada.';
END
ELSE
BEGIN
    PRINT 'La llave foránea FK_TurnoSesiones_Maquina ya existe. Sin cambios.';
END
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('electronicas.TurnoSesiones') AND name = 'IX_TurnoSesiones_maquina'
)
BEGIN
    CREATE INDEX IX_TurnoSesiones_maquina
        ON electronicas.TurnoSesiones(id_maquina, inicio);

    PRINT 'Índice IX_TurnoSesiones_maquina creado.';
END
ELSE
BEGIN
    PRINT 'El índice IX_TurnoSesiones_maquina ya existe. Sin cambios.';
END
GO

-- ── 3. Ligar los turnos que ya existían ─────────────────────
-- Solo toca turnos sin máquina cuya estación ya esté declarada en alguna
-- máquina. Si todavía no hay ninguna estación asignada, no cambia nada;
-- al asignar una desde Máquinas, el sistema liga sus turnos viejos solo.
UPDATE ts
   SET ts.id_maquina = m.id_maquina
  FROM electronicas.TurnoSesiones ts
 INNER JOIN electronicas.Maquinas m ON m.estacion_candado = ts.estacion
 WHERE ts.id_maquina IS NULL;

PRINT 'Turnos existentes ligados a su máquina: ' + CAST(@@ROWCOUNT AS VARCHAR(12)) + '.';
GO
