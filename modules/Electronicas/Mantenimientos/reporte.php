<?php
/**
 * Reporte imprimible de un mantenimiento.
 * URL: ./modules/Electronicas/Mantenimientos/reporte.php?id=X
 */

require_once '../../../config/auth.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); die('ID de mantenimiento requerido.'); }

require_once '../../../config/Connection.php';
$conn = (new Connection())->dbConnect();

/* ── Cabecera del mantenimiento ─────────────────────────── */
$stmtM = $conn->prepare("
    SELECT
        m.id_mantenimiento,
        mq.nombre                        AS maquina,
        mq.serie                         AS maquina_serie,
        tm.nombre                        AS tipo,
        ISNULL(t.nombre, '—')            AS tecnico,
        ISNULL(t.telefono, '')           AS tecnico_tel,
        m.fecha_mantenimiento,
        m.proximo_mantenimiento,
        ISNULL(m.descripcion, '')        AS descripcion,
        CONVERT(VARCHAR, m.fecha_registro, 103) AS fecha_registro
    FROM electronicas.Mantenimientos m
    INNER JOIN electronicas.Maquinas          mq ON m.id_maquina = mq.id_maquina
    INNER JOIN electronicas.TipoMantenimiento tm ON m.id_tipo    = tm.id_tipo
    LEFT  JOIN electronicas.Tecnicos           t ON m.id_tecnico = t.id_tecnico
    WHERE m.id_mantenimiento = ?
");
$stmtM->execute([$id]);
$mant = $stmtM->fetch(PDO::FETCH_ASSOC);
if (!$mant) { http_response_code(404); die('Mantenimiento no encontrado.'); }

/* ── Repuestos instalados ───────────────────────────────── */
$stmtR = $conn->prepare("
    SELECT
        r.nombre                            AS repuesto,
        mr.cantidad,
        mr.costo_unitario,
        (mr.cantidad * mr.costo_unitario)   AS subtotal,
        CASE WHEN r.maneja_serie = 1 THEN rd.serie ELSE NULL END AS serie
    FROM electronicas.MantenimientoRepuestos mr
    INNER JOIN electronicas.Repuestos r ON mr.id_repuesto = r.id_repuesto
    LEFT  JOIN electronicas.RepuestosDetalle rd ON mr.id_detalle_repuesto = rd.id_detalle_repuesto
    WHERE mr.id_mantenimiento = ?
    ORDER BY r.nombre
");
$stmtR->execute([$id]);
$repuestos = $stmtR->fetchAll(PDO::FETCH_ASSOC);
$totalCosto = array_sum(array_column($repuestos, 'subtotal'));

/* ── Piezas retiradas ───────────────────────────────────── */
$stmtRet = $conn->prepare("
    SELECT
        r.nombre                AS repuesto,
        mq.cantidad,
        mq.tipo_retiro,
        ISNULL(mq.observaciones_retiro, '—') AS observaciones,
        rd.serie
    FROM electronicas.MaquinaRepuestos mq
    INNER JOIN electronicas.Repuestos r ON r.id_repuesto = mq.id_repuesto
    LEFT  JOIN electronicas.RepuestosDetalle rd ON rd.id_detalle_repuesto = mq.id_detalle_repuesto
    WHERE mq.id_mantenimiento_retiro = ?
    ORDER BY r.nombre
");
$stmtRet->execute([$id]);
$retiros = $stmtRet->fetchAll(PDO::FETCH_ASSOC);

/* ── Helpers ────────────────────────────────────────────── */
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }
function fecha($f) { return $f ? date('d/m/Y', strtotime($f)) : '—'; }
$folio = str_pad($id, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Mantenimiento #<?= $folio ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11pt;
            color: #111;
            background: #f4f4f4;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            background: #fff;
            padding: 18mm 16mm 20mm;
            box-shadow: 0 2px 12px rgba(0,0,0,.15);
        }

        /* ── Encabezado ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #1a56db;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header .empresa h1 { font-size: 16pt; color: #1a56db; }
        .header .empresa p  { font-size: 9pt; color: #555; margin-top: 2px; }
        .header .folio {
            text-align: right;
        }
        .header .folio .numero {
            font-size: 20pt;
            font-weight: 700;
            color: #1a56db;
        }
        .header .folio .label {
            font-size: 8pt;
            color: #888;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ── Info general ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 20px;
            background: #f8faff;
            border: 1px solid #dde3f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .info-grid .field label {
            font-size: 8pt;
            text-transform: uppercase;
            color: #888;
            letter-spacing: .4px;
            display: block;
        }
        .info-grid .field span {
            font-size: 10.5pt;
            font-weight: 600;
        }
        .info-grid .field.full { grid-column: 1 / -1; }

        /* ── Secciones ── */
        .section-title {
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #fff;
            background: #1a56db;
            padding: 4px 10px;
            border-radius: 4px 4px 0 0;
            margin-top: 16px;
        }
        .section-title.retiro { background: #c0392b; }
        .section-title.firma  { background: #2e7d32; }

        /* ── Tablas ── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
        }
        table thead th {
            background: #edf2ff;
            border: 1px solid #c5d0e8;
            padding: 5px 8px;
            text-align: left;
            font-size: 9pt;
        }
        table tbody td {
            border: 1px solid #dde3f0;
            padding: 5px 8px;
            vertical-align: top;
        }
        table tbody tr:nth-child(even) td { background: #fafbff; }
        .text-right  { text-align: right; }
        .text-center { text-align: center; }
        .total-row td {
            font-weight: 700;
            background: #edf2ff !important;
            border-top: 2px solid #1a56db;
        }
        .sin-datos td {
            text-align: center;
            color: #aaa;
            font-style: italic;
            padding: 10px;
        }
        .badge-baja      { color: #c0392b; font-weight: 600; }
        .badge-devolucion { color: #27ae60; font-weight: 600; }

        /* ── Firmas ── */
        .firmas {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 10px;
        }
        .firma-box {
            text-align: center;
        }
        .firma-box .linea {
            border-top: 1.5px solid #333;
            margin-top: 48px;
            padding-top: 4px;
        }
        .firma-box .rol {
            font-size: 9pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .firma-box .nombre {
            font-size: 9pt;
            color: #555;
            margin-top: 2px;
        }

        /* ── Pie de página ── */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #dde3f0;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #aaa;
        }

        /* ── Botón imprimir (solo pantalla) ── */
        .print-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1a56db;
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 12px 24px;
            font-size: 13pt;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(26,86,219,.4);
            z-index: 999;
        }
        .print-btn:hover { background: #1447b8; }

        @media print {
            body    { background: #fff; }
            .page   { margin: 0; box-shadow: none; padding: 12mm 14mm 14mm; }
            .print-btn { display: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨 Imprimir / PDF</button>

<div class="page">

    <!-- ── Encabezado ─────────────────────────────────── -->
    <div class="header">
        <div class="empresa">
            <h1>Electronicas</h1>
            <p>Sistema de Control de Mantenimiento</p>
        </div>
        <div class="folio">
            <div class="label">Orden de Mantenimiento</div>
            <div class="numero">#<?= $folio ?></div>
            <div style="font-size:9pt;color:#555;margin-top:2px">
                Emitido: <?= fecha(date('Y-m-d')) ?>
            </div>
        </div>
    </div>

    <!-- ── Info general ──────────────────────────────── -->
    <div class="info-grid">
        <div class="field">
            <label>Máquina</label>
            <span><?= e($mant['maquina']) ?><?= $mant['maquina_serie'] ? ' — Serie: ' . e($mant['maquina_serie']) : '' ?></span>
        </div>
        <div class="field">
            <label>Tipo de mantenimiento</label>
            <span><?= e($mant['tipo']) ?></span>
        </div>
        <div class="field">
            <label>Técnico responsable</label>
            <span><?= e($mant['tecnico']) ?></span>
        </div>
        <div class="field">
            <label>Fecha del mantenimiento</label>
            <span><?= fecha($mant['fecha_mantenimiento']) ?></span>
        </div>
        <div class="field">
            <label>Próximo mantenimiento</label>
            <span><?= fecha($mant['proximo_mantenimiento']) ?></span>
        </div>
        <div class="field">
            <label>Registrado en sistema</label>
            <span><?= e($mant['fecha_registro']) ?></span>
        </div>
        <?php if ($mant['descripcion']): ?>
        <div class="field full">
            <label>Descripción / Observaciones</label>
            <span style="font-weight:400"><?= e($mant['descripcion']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Repuestos instalados ───────────────────────── -->
    <div class="section-title">Repuestos utilizados / instalados</div>
    <table>
        <thead>
            <tr>
                <th>Repuesto</th>
                <th class="text-center" style="width:55px">Cant.</th>
                <th class="text-center" style="width:120px">N° Serie</th>
                <th class="text-right"  style="width:100px">Costo unit.</th>
                <th class="text-right"  style="width:100px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($repuestos): ?>
                <?php foreach ($repuestos as $r): ?>
                <tr>
                    <td><?= e($r['repuesto']) ?></td>
                    <td class="text-center"><?= $r['cantidad'] ?></td>
                    <td class="text-center"><?= $r['serie'] ? e($r['serie']) : '—' ?></td>
                    <td class="text-right">Q <?= number_format($r['costo_unitario'], 2) ?></td>
                    <td class="text-right">Q <?= number_format($r['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3"></td>
                    <td class="text-right">TOTAL</td>
                    <td class="text-right">Q <?= number_format($totalCosto, 2) ?></td>
                </tr>
            <?php else: ?>
                <tr class="sin-datos"><td colspan="5">Sin repuestos registrados en este mantenimiento.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ── Piezas retiradas ───────────────────────────── -->
    <?php if ($retiros): ?>
    <div class="section-title retiro">Piezas retiradas en este mantenimiento</div>
    <table>
        <thead>
            <tr>
                <th>Repuesto</th>
                <th class="text-center" style="width:55px">Cant.</th>
                <th class="text-center" style="width:120px">N° Serie</th>
                <th class="text-center" style="width:100px">Tipo retiro</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($retiros as $r): ?>
            <tr>
                <td><?= e($r['repuesto']) ?></td>
                <td class="text-center"><?= $r['cantidad'] ?></td>
                <td class="text-center"><?= $r['serie'] ? e($r['serie']) : '—' ?></td>
                <td class="text-center <?= $r['tipo_retiro'] === 'baja' ? 'badge-baja' : 'badge-devolucion' ?>">
                    <?= $r['tipo_retiro'] === 'baja' ? 'Baja' : 'Devolución' ?>
                </td>
                <td><?= e($r['observaciones']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- ── Firmas ─────────────────────────────────────── -->
    <div class="section-title firma" style="margin-top:24px">Conformidad y firmas</div>
    <div style="border:1px solid #c8e6c9; padding:16px 14px 8px; border-radius:0 0 6px 6px;">
        <div class="firmas">
            <div class="firma-box">
                <div class="linea">
                    <div class="rol">Solicitado por</div>
                    <div class="nombre"><?= e($mant['tecnico']) ?></div>
                </div>
            </div>
            <div class="firma-box">
                <div class="linea">
                    <div class="rol">Entregado por</div>
                    <div class="nombre">&nbsp;</div>
                </div>
            </div>
            <div class="firma-box">
                <div class="linea">
                    <div class="rol">Autorizado por</div>
                    <div class="nombre">&nbsp;</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Pie ───────────────────────────────────────── -->
    <div class="footer">
        <span>Orden #<?= $folio ?> — Generado el <?= date('d/m/Y H:i') ?></span>
        <span>Electronicas — Sistema de Control de Mantenimiento</span>
    </div>

</div><!-- /page -->

</body>
</html>
