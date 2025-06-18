<?php
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $db = new Database();
    $conn = $db->getConnection();

    if (isset($_POST['delete_id'])) {
        $id = $_POST['delete_id'];
        sqlsrv_query($conn, "DELETE FROM SellOutColor WHERE id = ?", [$id]);
    }

    if (!empty($_POST['delete_ids'])) {
        $ids = implode(",", array_map('intval', $_POST['delete_ids']));
        sqlsrv_query($conn, "DELETE FROM SellOutColor WHERE id IN ($ids)");
    }

    header("Location: index.php?page=enviar_sellout");
    exit();
}
?>
