<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
require_once '../../../../config/Connection.php';

$conn       = (new Connection())->dbConnect();
$id_maquina = (int)($_POST['id_maquina'] ?? 0);

if (!$id_maquina) {
    echo json_encode(['ok' => false, 'mensaje' => 'ID de máquina inválido.']);
    exit;
}

// ── 1. Nombre de la máquina ───────────────────────────────
$stmtNombre = $conn->prepare(
    "SELECT nombre FROM electronicas.Maquinas WHERE id_maquina = ?"
);
$stmtNombre->execute([$id_maquina]);
$maquinaNombre = $stmtNombre->fetchColumn() ?: 'Máquina';

// ── 2. Todos los mantenimientos de la máquina ─────────────
$sqlMant = "SELECT
                m.id_mantenimiento,
                FORMAT(m.fecha_mantenimiento, 'dd/MM/yyyy') AS fecha,
                tm.nombre                                   AS tipo,
                ISNULL(t.nombre, 'Sin técnico')             AS tecnico,
                ISNULL(m.descripcion, '')                   AS descripcion,
                FORMAT(m.proximo_mantenimiento, 'dd/MM/yyyy') AS proximo
            FROM electronicas.Mantenimientos m
            INNER JOIN electronicas.TipoMantenimiento tm ON tm.id_tipo    = m.id_tipo
            LEFT  JOIN electronicas.Tecnicos           t  ON t.id_tecnico = m.id_tecnico
            WHERE m.id_maquina = ?
            ORDER BY m.fecha_mantenimiento DESC";

$stmtMant = $conn->prepare($sqlMant);
$stmtMant->execute([$id_maquina]);
$mantenimientos = $stmtMant->fetchAll(PDO::FETCH_ASSOC);

if (empty($mantenimientos)) {
    echo json_encode(['ok' => true, 'maquina' => $maquinaNombre, 'data' => []]);
    exit;
}

// ── 3. Repuestos usados en esos mantenimientos ────────────
$ids          = array_column($mantenimientos, 'id_mantenimiento');
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$sqlRep = "SELECT
               mr.id_mantenimiento,
               r.nombre                                AS repuesto,
               mr.cantidad,
               mr.costo_unitario,
               (mr.cantidad * mr.costo_unitario)       AS total,
               ISNULL(dv.simbolo,
                   (SELECT TOP 1 simbolo FROM electronicas.Divisas
                    WHERE predeterminada = 1 AND activo = 1)
               )                                        AS divisa_simbolo
           FROM electronicas.MantenimientoRepuestos mr
           INNER JOIN electronicas.Repuestos r  ON r.id_repuesto = mr.id_repuesto
           LEFT  JOIN electronicas.Divisas   dv ON dv.id_divisa  = r.id_divisa
           WHERE mr.id_mantenimiento IN ($placeholders)
           ORDER BY mr.id_mantenimiento, r.nombre";

$stmtRep = $conn->prepare($sqlRep);
$stmtRep->execute($ids);
$repuestos = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

// ── 4. Agrupar repuestos por mantenimiento ────────────────
$repPorMant = [];
foreach ($repuestos as $rep) {
    $repPorMant[(int)$rep['id_mantenimiento']][] = $rep;
}

foreach ($mantenimientos as &$m) {
    $m['repuestos'] = $repPorMant[(int)$m['id_mantenimiento']] ?? [];
}
unset($m);

echo json_encode(['ok' => true, 'maquina' => $maquinaNombre, 'data' => $mantenimientos]);
