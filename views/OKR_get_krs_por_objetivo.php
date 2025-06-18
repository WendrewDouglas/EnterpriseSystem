<?php
require_once __DIR__ . '/../includes/db_connectionOKR.php';

header('Content-Type: application/json');

$id_objetivo = urldecode($_GET['id_objetivo'] ?? '');

if (!$id_objetivo) {
    echo json_encode([]);
    exit;
}

$dbOKR = new OKRDatabase();
$connOKR = $dbOKR->getConnection();

if ($connOKR === false) {
    echo json_encode(["erro" => "Erro na conexão com o banco OKR", "detalhe" => sqlsrv_errors()]);
    exit;
}


if (!$connOKR) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT id_kr, descricao FROM key_results WHERE LTRIM(RTRIM(id_objetivo)) = LTRIM(RTRIM(?))";
$stmt = sqlsrv_query($connOKR, $sql, [$id_objetivo]);

$result = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $result[] = [
            'id' => $row['id_kr'],
            'text' => $row['descricao']
        ];
    }
}

if (empty($result)) {
    echo json_encode(["erro" => "Nenhum KR encontrado para", "id_objetivo" => $id_objetivo]);
    exit;
}


echo json_encode($result);
