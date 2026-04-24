<?php
/**
 * Seed de usuarios iniciales — EJECUTAR UNA SOLA VEZ.
 * Acceder a: http://localhost/Electronicas/BD/seed_usuarios.php
 * Eliminar o restringir este archivo después de usarlo.
 */

require_once '../config/Connection.php';

$conn = (new Connection())->dbConnect();

// ── Usuarios iniciales ──────────────────────────────────────
$usuarios = [
    ['msorto', 'proDuX10n_03', 'M. Sorto'],
    ['dpalma', 'Hpo051!',       'D. Palma'],
    ['Sania',  '$plan2024',     'Sania Alvarenga'],
];

$insertados = 0;
$omitidos   = 0;

foreach ($usuarios as [$username, $password, $nombre]) {
    // Verificar si ya existe
    $check = $conn->prepare(
        "SELECT COUNT(*) FROM electronicas.Usuarios WHERE username = ?"
    );
    $check->execute([$username]);

    if ((int)$check->fetchColumn() > 0) {
        $omitidos++;
        continue;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare(
        "INSERT INTO electronicas.Usuarios (username, password_hash, nombre)
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$username, $hash, $nombre]);
    $insertados++;
}

echo "<h2 style='font-family:monospace'>Seed completado</h2>";
echo "<p>Insertados: <strong>$insertados</strong></p>";
echo "<p>Omitidos (ya existían): <strong>$omitidos</strong></p>";
echo "<hr><p style='color:red'><strong>Elimina o protege este archivo ahora que ya lo ejecutaste.</strong></p>";
