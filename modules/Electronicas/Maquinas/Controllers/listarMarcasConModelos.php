<?php
include_once "../../../../config/Connection.php";

header('Content-Type: application/json');

$database = new Connection();
$conn = $database->dbConnect();

$sql = "SELECT id_modelo, id_marca
        FROM electronicas.Modelos";

$stmt = $conn->prepare($sql);

try {
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
} catch (PDOException $e) {
    echo json_encode([
        "error" => $e->getMessage()
    ]);
    exit;
}
?>