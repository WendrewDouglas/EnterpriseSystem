<?php
require_once __DIR__ . '/../includes/db_connection.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_POST['id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if (!$user_id || !$new_status || !in_array($new_status, ['ativo', 'inativo'])) {
        echo json_encode(["success" => false, "message" => "Dados inválidos"]);
        exit();
    }

    $db = new Database();
    $conn = $db->getConnection();

    $sql = "UPDATE users SET status = ? WHERE id = ?";
    $params = [$new_status, $user_id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Erro ao atualizar no banco."]);
    } else {
        echo json_encode(["success" => true]);
    }

    exit();
}
?>
