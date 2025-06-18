<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('apontar_forecast');

// Função para capturar o IP do usuário
function obterIPUsuario() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
$ipUsuario = obterIPUsuario();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Capturar filtros e usuário
    $cdSelecionado = $_POST['cd'] ?? null;
    $regionalSelecionado = $_POST['regional'] ?? null; // Aqui será usado como código do gestor
    $usuarioApontamento = $_POST['usuario_apontamento'] ?? null;
    if (!$cdSelecionado || !$regionalSelecionado || !$usuarioApontamento) {
        $_SESSION['error_message'] = "Erro: Centro de Distribuição, Código Regional ou Usuário não identificados.";
        header("Location: index.php?page=apontar_forecast");
        exit();
    }
    
    // Criar conexão com o banco
    $db = new Database();
    $conn = $db->getConnection();
    
    // Capturar os dados enviados pelo formulário de SKU (novo formato)
    $skuForecast = $_POST['skuForecast'] ?? [];
    if (empty($skuForecast)) {
        $_SESSION['error_message'] = "Nenhum apontamento por SKU foi enviado.";
        header("Location: index.php?page=apontar_forecast");
        exit();
    }
    
    $errosSQL = [];
    
    // Iterar sobre cada SKU enviado
    foreach ($skuForecast as $sku => $forecastData) {
        // Para cada mês definido para este SKU
        foreach ($forecastData as $mesReferencia => $quantidade) {
            // Converter quantidade para inteiro e ignorar se for 0
            $quantidade = is_numeric($quantidade) ? intval($quantidade) : 0;
            if ($quantidade <= 0) {
                continue;
            }
            
            // Recuperar os detalhes do produto a partir da V_DEPARA_ITEM
            $sqlProduto = "SELECT DESCITEM, LINHA, MODELO FROM V_DEPARA_ITEM WHERE CODITEM = ?";
            $paramsProduto = [$sku];
            $stmtProduto = sqlsrv_query($conn, $sqlProduto, $paramsProduto);
            if ($stmtProduto !== false && $produto = sqlsrv_fetch_array($stmtProduto, SQLSRV_FETCH_ASSOC)) {
                $descricao = $produto['DESCITEM'] ?? "";
                $linha = $produto['LINHA'] ?? "";
                $modelo = $produto['MODELO'] ?? "";
            } else {
                $descricao = "";
                $linha = "";
                $modelo = "";
                $errosSQL[] = "Produto com SKU $sku não encontrado.";
            }
            
            // Inserir o apontamento na tabela forecast_entries_sku
            $sqlInsert = "INSERT INTO forecast_entries_sku 
                          (sku, descricao, mes_referencia, empresa, linha, modelo, quantidade, codigo_gestor, ip_usuario, data_criacao)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, GETDATE())";
            $paramsInsert = [$sku, $descricao, $mesReferencia, $cdSelecionado, $linha, $modelo, $quantidade, $regionalSelecionado, $ipUsuario];
            $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
            if ($stmtInsert === false) {
                $errosSQL[] = print_r(sqlsrv_errors(), true);
                error_log("Erro ao inserir apontamento por SKU: " . print_r(sqlsrv_errors(), true));
            }
        }
    }
    
    if (!empty($errosSQL)) {
        $_SESSION['error_message'] = "Houve erros ao salvar os apontamentos por SKU. " . implode(" ", $errosSQL);
    } else {
        $_SESSION['success_message'] = "Forecast por SKU enviado com sucesso!";
    }
    header("Location: index.php?page=apontar_forecast");
    exit();
} else {
    header("Location: index.php?page=apontar_forecast");
    exit();
}
?>
