<?php
require_once __DIR__ . '/../includes/db_connection.php';

$db = new Database();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cd = $_POST['cd'] ?? null;
    $regional = $_POST['regional'] ?? null;
    $modelo = $_POST['modelo'] ?? null;

    if ($cd && $regional && $modelo) {
        $primeiroMes = (new DateTime('first day of next month'))->format('m/Y');

        $sql = "SELECT COUNT(*) AS total FROM Forecasts WHERE cd = ? AND regional = ? AND mes_referencia = ? AND modelo_produto = ?";
        $params = [$cd, $regional, $primeiroMes, $modelo];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt !== false) {
            $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            echo json_encode(['existe' => ($row['total'] > 0)]);
            exit;
        }
    }
}

echo json_encode(['existe' => false]);
exit;
