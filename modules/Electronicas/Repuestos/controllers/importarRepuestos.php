<?php
require_once '../../../../config/auth.php';
requireLogin(true);

header('Content-Type: application/json');
require_once '../../../../config/Connection.php';

// ── Solo acepta POST con archivo ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['archivo'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se recibió ningún archivo.']);
    exit;
}

$archivo = $_FILES['archivo'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error al subir el archivo.']);
    exit;
}

$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Solo se aceptan archivos CSV.']);
    exit;
}

// ── Leer el CSV ───────────────────────────────────────────────────────────────
$handle = fopen($archivo['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo leer el archivo.']);
    exit;
}

// Detectar y quitar BOM UTF-8
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$conn      = (new Connection())->dbConnect();
$insertados = 0;
$errores    = [];
$fila       = 0;

// Cargar catálogos en memoria para resolver nombres → IDs
function cargarCatalogo(PDO $conn, string $sql): array {
    $stmt = $conn->query($sql);
    $mapa = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mapa[mb_strtolower(trim($r['nombre']))] = $r['id'];
    }
    return $mapa;
}

$mapaTipos      = cargarCatalogo($conn, "SELECT id_tipo      AS id, nombre FROM electronicas.TiposRepuesto");
$mapaMarcas     = cargarCatalogo($conn, "SELECT id_marca     AS id, nombre FROM electronicas.Marcas");
$mapaModelos    = cargarCatalogo($conn, "SELECT id_modelo    AS id, nombre FROM electronicas.Modelos");
$mapaProveedores = cargarCatalogo($conn, "SELECT id_proveedor AS id, nombre FROM electronicas.Proveedores WHERE estado = 1");

// Divisas por código
$stmtDiv = $conn->query("SELECT id_divisa, codigo FROM electronicas.Divisas WHERE activo = 1");
$mapaDivisas = [];
foreach ($stmtDiv->fetchAll(PDO::FETCH_ASSOC) as $d) {
    $mapaDivisas[strtoupper(trim($d['codigo']))] = $d['id_divisa'];
}

// Divisa predeterminada como fallback
$stmtPred = $conn->query("SELECT TOP 1 id_divisa FROM electronicas.Divisas WHERE predeterminada = 1 AND activo = 1");
$divisaPred = $stmtPred->fetchColumn();

// ── SQL de inserción ──────────────────────────────────────────────────────────
$sqlInsert = "INSERT INTO electronicas.Repuestos
                  (nombre, numero_parte, id_proveedor, costo_promedio, stock_minimo,
                   comentarios, id_tipo, id_marca, id_modelo, maneja_serie, id_divisa, stock)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
$stmtInsert = $conn->prepare($sqlInsert);

$sqlCheckParte = "SELECT COUNT(*) FROM electronicas.Repuestos WHERE numero_parte = ? AND id_estado != 5";
$stmtCheckParte = $conn->prepare($sqlCheckParte);

// ── Columnas esperadas (índices del CSV) ──────────────────────────────────────
// nombre, numero_parte, tipo, marca, modelo, proveedor, maneja_serie,
// stock_minimo, divisa_codigo, costo_promedio, comentarios

$primeraFila = true;

while (($cols = fgetcsv($handle, 2000, ',')) !== false) {
    $fila++;

    // Saltar cabecera
    if ($primeraFila) {
        $primeraFila = false;
        // Normalizar: si la primera columna empieza con 'nombre' es cabecera
        if (isset($cols[0]) && mb_strtolower(trim($cols[0])) === 'nombre') {
            continue;
        }
    }

    // Ignorar filas completamente vacías
    if (count(array_filter($cols, fn($c) => trim($c) !== '')) === 0) continue;

    // Extraer valores con fallback a cadena vacía
    $nombre        = trim($cols[0]  ?? '');
    $numero_parte  = trim($cols[1]  ?? '');
    $tipoNombre    = trim($cols[2]  ?? '');
    $marcaNombre   = trim($cols[3]  ?? '');
    $modeloNombre  = trim($cols[4]  ?? '');
    $provNombre    = trim($cols[5]  ?? '');
    $manejaSerieRaw = trim($cols[6] ?? '0');
    $stockMinimo   = (int)($cols[7] ?? 0);
    $divisaCodigo  = strtoupper(trim($cols[8] ?? ''));
    $costo         = (float)str_replace(',', '.', $cols[9] ?? '0');
    $comentarios   = trim($cols[10] ?? '');

    // ── Validaciones obligatorias ─────────────────────────────────────────────
    if ($nombre === '') {
        $errores[] = "Fila $fila: el campo 'nombre' es obligatorio.";
        continue;
    }

    if ($provNombre === '') {
        $errores[] = "Fila $fila ($nombre): el campo 'proveedor' es obligatorio.";
        continue;
    }

    $id_proveedor = $mapaProveedores[mb_strtolower($provNombre)] ?? null;
    if (!$id_proveedor) {
        $errores[] = "Fila $fila ($nombre): proveedor '$provNombre' no encontrado.";
        continue;
    }

    // ── Resolver IDs opcionales ────────────────────────────────────────────────
    $id_tipo   = $tipoNombre   !== '' ? ($mapaTipos[mb_strtolower($tipoNombre)]     ?? null) : null;
    $id_marca  = $marcaNombre  !== '' ? ($mapaMarcas[mb_strtolower($marcaNombre)]   ?? null) : null;
    $id_modelo = $modeloNombre !== '' ? ($mapaModelos[mb_strtolower($modeloNombre)] ?? null) : null;

    if ($tipoNombre !== '' && !$id_tipo) {
        $errores[] = "Fila $fila ($nombre): tipo '$tipoNombre' no encontrado (se omitió).";
    }
    if ($marcaNombre !== '' && !$id_marca) {
        $errores[] = "Fila $fila ($nombre): marca '$marcaNombre' no encontrada (se omitió).";
    }
    if ($modeloNombre !== '' && !$id_modelo) {
        $errores[] = "Fila $fila ($nombre): modelo '$modeloNombre' no encontrado (se omitió).";
    }

    $maneja_serie = in_array($manejaSerieRaw, ['1', 'si', 'sí', 'yes', 'true']) ? 1 : 0;
    $id_divisa    = $divisaCodigo !== '' ? ($mapaDivisas[$divisaCodigo] ?? $divisaPred) : $divisaPred;

    // ── Número de parte duplicado ─────────────────────────────────────────────
    if ($numero_parte !== '') {
        $stmtCheckParte->execute([$numero_parte]);
        if ($stmtCheckParte->fetchColumn() > 0) {
            $errores[] = "Fila $fila ($nombre): número de parte '$numero_parte' ya existe (se omitió).";
            continue;
        }
    }

    // ── Insertar ──────────────────────────────────────────────────────────────
    try {
        $stmtInsert->execute([
            $nombre,
            $numero_parte ?: null,
            $id_proveedor,
            $costo,
            $stockMinimo,
            $comentarios ?: null,
            $id_tipo,
            $id_marca,
            $id_modelo,
            $maneja_serie,
            $id_divisa,
        ]);
        $insertados++;
    } catch (PDOException $e) {
        $errores[] = "Fila $fila ($nombre): error al insertar — " . $e->getMessage();
    }
}

fclose($handle);

echo json_encode([
    'ok'         => true,
    'insertados' => $insertados,
    'errores'    => $errores,
    'total_filas' => $fila - 1, // sin contar cabecera
]);
