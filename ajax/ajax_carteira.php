<?php
// ajax/ajax_carteira.php

// Este arquivo NÃO deve incluir header.php, sidebar.php ou footer.php!
// Ele retorna APENAS a tabela HTML com os dados da carteira.

require_once __DIR__ . '/../includes/db_connection.php';

// Define o cabeçalho para saída HTML com UTF-8
header("Content-Type: text/html; charset=utf-8");

// Forçar o locale para exibir meses em português
setlocale(LC_TIME, 'ptb.UTF-8', 'ptb', 'portuguese', 'portuguese_brazil');

// Cria a conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Recebe os dados enviados via JSON pela requisição AJAX
$data = file_get_contents('php://input');
$dados = json_decode($data, true);

// Extrai os parâmetros: CD, Código Regional, Linha e Modelo
$cd = $dados['cd'] ?? '';
$regional = $dados['regional'] ?? '';
$modelo = $dados['modelo'] ?? '';

// Se algum parâmetro estiver faltando, retorna uma mensagem de aviso
if (empty($cd) || empty($regional) || empty($modelo)) {
    echo "<div class='alert alert-warning'>Dados insuficientes para carregar a carteira.</div>";
    exit();
}

// Consulta para buscar os registros da carteira, agrupando por DataAgendada (formato MM/YYYY)
// Usamos a função FORMAT para extrair somente mês/ano
$sql = "SELECT 
            FORMAT(Data_agendada, 'MM/yyyy') AS DataAgendada,
            SUM(Quantidade) AS Quantidade
        FROM V_CARTEIRA_PEDIDOS
        WHERE Empresa = ? 
          AND Cod_regional = ? 
          AND Cod_produto = ?
        GROUP BY FORMAT(Data_agendada, 'MM/yyyy')
        ORDER BY FORMAT(Data_agendada, 'MM/yyyy')";

$params = [$cd, $regional, $modelo];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    echo "<div class='alert alert-danger'>Erro ao carregar dados da carteira.</div>";
    exit();
}

// Armazena os resultados em um array associativo: chave = DataAgendada e valor = Quantidade
$dadosCarteira = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $dadosCarteira[$row['DataAgendada']] = $row['Quantidade'];
}

// Obter os próximos 3 meses a partir do próximo mês
$mesesForecast = [];
$data = new DateTime('first day of next month');
for ($i = 0; $i < 3; $i++) {
    // Usa o formato "m/Y" (ex.: "03/2025"), igual à query
    $mesFormat = $data->format('m/Y');
    $mesesForecast[] = $mesFormat;
    $data->modify("+1 month");
}

// Montar a tabela HTML de saída
$output = "<div class='bg-secondary text-white p-2 text-center fw-bold' style='margin-bottom: 0; font-size: 1.2em;'>Quantidades em Carteira</div>";
$output .= "<table class='table table-bordered table-striped mb-0' style='margin-top: 0;'>";
$output .= "<thead><tr>";
$output .= "<th>Modelo</th>";
$output .= "<th>Empresa</th>";
foreach ($mesesForecast as $mes) {
    $output .= "<th>{$mes}</th>";
}
$output .= "</tr></thead><tbody>";
$output .= "<tr>";
$output .= "<td>" . htmlspecialchars($modelo) . "</td>";
$output .= "<td>" . htmlspecialchars($cd) . "</td>";
foreach ($mesesForecast as $mes) {
    // Se não houver registro para o mês, define como 0
    $quant = isset($dadosCarteira[$mes]) ? $dadosCarteira[$mes] : 0;
    $output .= "<td>" . htmlspecialchars($quant) . "</td>";
}
$output .= "</tr>";
$output .= "</tbody></table>";

echo $output;
?>
