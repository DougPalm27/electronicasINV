<?php
require_once "../../../../config/auth.php";
requireLogin(true);

include_once "../../../../config/Connection.php";

header('Content-Type: application/json');

// Devuelve los id_modelo que tienen configuración de eyectores activa.
// Tolera que la tabla aún no exista (migración pendiente) devolviendo [].
try {
    $conn = (new Connection())->dbConnect();
    if (!$conn) {
        echo json_encode([]);
        exit;
    }

    $rows = $conn
        ->query("SELECT DISTINCT id_modelo
                 FROM electronicas.ConfiguracionEyectores
                 WHERE activo = 1")
        ->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($rows);
} catch (Throwable $e) {
    echo json_encode([]);
}
?>