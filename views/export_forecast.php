<?php
// export_forecast.php - versão atualizada para exportação usando forecast_system (aplicando o mesmo filtro de mes_referencia da tabela)

// Inicia o buffer de saída
ob_start();

// Inclua apenas o necessário para conexão e permissões
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Verifica a permissão
verificarPermissao('consulta_lancamentos');

require_once __DIR__ . '/../vendor/autoload.php'; // Carrega PHPSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Cria a conexão
$db = new Database();
$conn = $db->getConnection();

// Configurações de memória e tempo
ini_set('memory_limit', '512M');
set_time_limit(300);

// Captura os filtros via GET
$mesReferencia = $_GET['mesReferencia'] ?? '';
$gestor       = $_GET['gestor'] ?? '';
$empresa      = $_GET['empresa'] ?? '';
$linha        = $_GET['linha'] ?? '';
$modelo       = $_GET['modelo'] ?? '';
$sku          = $_GET['sku'] ?? '';
$descitem     = $_GET['descitem'] ?? '';

// Consulta sem paginação para exportar todos os dados filtrados
$sql = "SELECT 
            f.mes_referencia, 
            f.sku, 
            f.empresa, 
            f.quantidade,
            f.codigo_gestor AS gestor,
            f.descricao,
            f.linha,
            f.modelo
        FROM forecast_system f";

$params = [];
$conditions = [];

// Aplica o filtro de mes_referencia utilizando o mesmo formato da consulta da tabela online
if (!empty($mesReferencia)) {
    $conditions[] = "FORMAT(TRY_PARSE('01/' + f.mes_referencia AS date USING 'pt-BR'), 'MMMM/yyyy', 'pt-BR') = ?";
    $params[] = $mesReferencia;
}
if (!empty($gestor)) {
    $conditions[] = "f.codigo_gestor = ?";
    $params[] = $gestor;
}
if (!empty($empresa)) {
    $conditions[] = "f.empresa = ?";
    $params[] = $empresa;
}
if (!empty($linha)) {
    $conditions[] = "f.linha = ?";
    $params[] = $linha;
}
if (!empty($modelo)) {
    $conditions[] = "f.modelo = ?";
    $params[] = $modelo;
}
if (!empty($sku)) {
    $conditions[] = "f.sku = ?";
    $params[] = $sku;
}
if (!empty($descitem)) {
    $conditions[] = "f.descricao = ?";
    $params[] = $descitem;
}

// Adiciona condição para não trazer registros com quantidade 0
$conditions[] = "f.quantidade > 0";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Ordena primeiro pelo ano (últimos 4 caracteres) e depois pelo mês (primeiros 2 caracteres) em ordem decrescente
$sql .= " ORDER BY RIGHT(f.mes_referencia, 4) DESC, LEFT(f.mes_referencia, 2) DESC";

// Executa a consulta
$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    ob_end_clean();
    die("Erro ao carregar os dados: " . print_r(sqlsrv_errors(), true));
}

$rows = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $rows[] = $row;
}

if (empty($rows)) {
    ob_end_clean();
    die("Nenhum dado encontrado para exportação.");
}

// Cria a planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
// Define os headers da planilha
$headers = ["Mês Referência", "Gestor", "Empresa", "SKU", "Linha", "Modelo", "Descrição", "Quantidade"];
$sheet->fromArray([$headers], NULL, 'A1');

$rowIndex = 2;
foreach ($rows as $row) {
    $data = [
        $row['mes_referencia'],
        $row['gestor'],
        $row['empresa'],
        $row['sku'],
        $row['linha'],
        $row['modelo'],
        $row['descricao'],
        (int)$row['quantidade']
    ];
    $sheet->fromArray([$data], NULL, "A{$rowIndex}");
    $rowIndex++;
}

// Cria o arquivo temporário
$temp_file = tempnam(sys_get_temp_dir(), 'forecast_export_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

// Limpa o buffer, se houver
if (ob_get_length()) {
    ob_end_clean();
}

// Envia os cabeçalhos e o arquivo para download
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="forecast_export.xlsx"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($temp_file));

readfile($temp_file);
unlink($temp_file);
exit;
