<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Verifica a permissão
verificarPermissao('consulta_lancamentos');

// Define o cabeçalho como JSON com charset UTF-8
header('Content-Type: application/json; charset=utf-8');

// Captura o parâmetro "mesReferencia" (obrigatório)
$mesReferencia = $_GET['mesReferencia'] ?? '';
if (empty($mesReferencia)) {
    echo json_encode([
        "error" => "O Mês de Referência é obrigatório."
    ]);
    exit;
}

// Define o diretório de trabalho para o script Python
chdir(__DIR__ . '/../DSA/');

// Caminho completo para o Python
$python = "C:/Users/wendrewgomes/AppData/Local/Programs/Python/Python313/python.exe";

// Configura o PYTHONPATH, se necessário
putenv("PYTHONPATH=C:/Users/wendrewgomes/AppData/Local/Programs/Python/Python313/Lib/site-packages");

// Caminho para o script Python (eda_distribuicao_forecast2.py)
$script = escapeshellarg(__DIR__ . '/../DSA/eda_distribuicao_forecast2.py');

// Escapa o parâmetro recebido para evitar injeção e adicione como argumento
$mes_arg = escapeshellarg($mesReferencia);

// Monta o comando incluindo o parâmetro do mês
$cmd = "$python $script $mes_arg 2>&1";

// Executa o comando e captura a saída
$output_array = [];
$return_var = 0;
exec($cmd, $output_array, $return_var);
$output = implode("\n", $output_array);

// Caminho do arquivo de log para saída do Python
$log_file = __DIR__ . '/python_output.log';

// Se o arquivo de log não existir, cria-o
if (!file_exists($log_file)) {
    file_put_contents($log_file, '');
}

// Grava a saída do script Python no arquivo de log
file_put_contents($log_file, $output);

// Se não houver saída, retorna erro
if (empty($output)) {
    error_log("Nenhuma saída retornada pelo script Python. Comando: $cmd | Código de retorno: $return_var");
    echo json_encode([
        "error" => "Nenhuma saída retornada pelo script Python. Verifique permissões, caminho e ambiente. Código de retorno: $return_var"
    ]);
    exit;
}

// Detecta a codificação da saída e converte para UTF-8, se necessário
$encoding = mb_detect_encoding($output, 'UTF-8, ISO-8859-1, Windows-1252', true);
$output_utf8 = ($encoding !== 'UTF-8') ? mb_convert_encoding($output, 'UTF-8', $encoding) : $output;

// Tenta decodificar o JSON retornado pelo script Python
$resultado = json_decode($output_utf8, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Erro ao decodificar o JSON. Erro: " . json_last_error_msg() . " | Saída do script: $output_utf8");
    echo json_encode([
        "error" => "Erro ao decodificar o JSON. Saída do script: " . $output_utf8
    ]);
    exit;
}

// Retorna o JSON final sem escapar caracteres Unicode
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
exit;
?>
