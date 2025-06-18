<?php
require_once 'includes/db_connection.php';

$sql = "INSERT INTO apontamento_comercial (data, id_gestor, centro_distribuicao, modelo, quantidade, mes_referencia)
        VALUES (?, ?, ?, ?, ?, ?)";

$params = [
    '2025-01-25', 
    1, 
    'CD001', 
    'ModeloX', 
    100, 
    '2025-01'
];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo "Dados inseridos com sucesso!";
} else {
    echo "Erro ao inserir dados: ";
    print_r(sqlsrv_errors());
}
?>
