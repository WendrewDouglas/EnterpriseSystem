<?php
require_once __DIR__ . '../includes/db_connection.php';

// Criar conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Verificar se a conexão foi bem-sucedida
if (!$conn) {
    die("<div class='alert alert-danger'>Erro de conexão com o banco: " . print_r(sqlsrv_errors(), true) . "</div>");
}

// Testar se a tabela DW..DEPARA_COMERCIAL existe e pode ser acessada
$sqlTest = "SELECT TOP 1 * FROM DW..DEPARA_COMERCIAL";
$stmtTest = sqlsrv_query($conn, $sqlTest);

if ($stmtTest === false) {
    die("<div class='alert alert-danger'>Erro ao acessar a tabela: " . print_r(sqlsrv_errors(), true) . "</div>");
} else {
    echo "<div class='alert alert-success'>Tabela acessada com sucesso!</div>";
}

// Exibir os dados da tabela se estiver tudo certo
while ($row = sqlsrv_fetch_array($stmtTest, SQLSRV_FETCH_ASSOC)) {
    echo "<pre>" . print_r($row, true) . "</pre>";
}
?>
