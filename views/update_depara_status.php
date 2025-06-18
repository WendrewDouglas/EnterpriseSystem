<?php
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $regional = $_POST['regional'] ?? null;
    $status = $_POST['status'] ?? null;

    if (!$regional || !$status) {
        echo json_encode(["success" => false, "message" => "Dados inválidos."]);
        exit();
    }

    // Criar conexão com o banco
    $db = new Database();
    $conn = $db->getConnection();

    // Atualizar o status no banco
    $sql = "UPDATE DW..DEPARA_COMERCIAL SET status_regional = ? WHERE Regional = ?";
    $params = [$status, $regional];

    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Erro ao atualizar no banco."]);
    } else {
        echo json_encode(["success" => true]);
    }

    exit();
}
?>
