<?php
require_once '../../../../config/auth.php';
requireLogin(true);

// ── Encabezados para forzar descarga ──────────────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="plantilla_repuestos.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

// BOM para que Excel abra correctamente caracteres especiales
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ── Encabezados ───────────────────────────────────────────────────────────────
fputcsv($out, [
    'nombre',           // Requerido
    'numero_parte',     // Opcional
    'tipo',             // Nombre exacto del tipo (ej: Electrónico)
    'marca',            // Nombre exacto de la marca (ej: Dell)
    'modelo',           // Nombre exacto del modelo (ej: OptiPlex 3080)
    'proveedor',        // Nombre exacto del proveedor — Requerido
    'maneja_serie',     // 0 = Por cantidad | 1 = Por serie
    'stock_minimo',     // Número entero (ej: 5)
    'divisa_codigo',    // Código de divisa: HNL, USD, EUR
    'costo_promedio',   // Decimal (ej: 250.00)
    'comentarios',      // Texto libre
]);

// ── Filas de ejemplo ──────────────────────────────────────────────────────────
fputcsv($out, [
    'Fuente de poder 450W',
    'PSU-450W-01',
    '',         // vacío = sin clasificar
    'Dell',
    '',         // vacío = sin modelo
    'Proveedor General',
    '0',        // por cantidad
    '3',
    'HNL',
    '850.00',
    'Compatible con OptiPlex serie 3000',
]);

fputcsv($out, [
    'Disco duro SSD 256GB',
    'SSD-256-SAM',
    '',
    'Samsung',
    '',
    'Proveedor General',
    '1',        // por serie
    '2',
    'USD',
    '45.00',
    '',
]);

fclose($out);
exit;
