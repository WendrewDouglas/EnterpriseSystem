<?php
ob_start();

require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = new Database();
$conn = $db->getConnection();

ini_set('memory_limit', '512M');
set_time_limit(300);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Capturar filtros via GET
$filtroCodigo = $_GET['codigo_martins'] ?? '';
$filtroMercadoria = $_GET['mercadoria'] ?? '';
$filtroFilial = $_GET['filial'] ?? '';

// Construir a consulta SQL
$sql = "SELECT * FROM SellOut WHERE 1=1";
$params = [];
if (!empty($filtroCodigo)) {
    $sql .= " AND codigo_martins = ?";
    $params[] = $filtroCodigo;
}
if (!empty($filtroMercadoria)) {
    $sql .= " AND mercadoria = ?";
    $params[] = $filtroMercadoria;
}
if (!empty($filtroFilial)) {
    $sql .= " AND filial = ?";
    $params[] = $filtroFilial;
}
$sql .= " ORDER BY data_importacao DESC";

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

// Cria a planilha do Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Define os cabeçalhos
$headers = [
    "ID", "Código Martins", "Mercadoria", "Filial", "Qde Estoque Disp", "Média Mensal Venda",
    "QdeVenda", "Cobertura", "Qde SaldoPedido", "Lost Sales", "Data Importação"
];
$sheet->fromArray([$headers], NULL, 'A1');

// Preenche os dados
$rowIndex = 2;
foreach ($rows as $row) {
    $data = [
        $row['id'],
        $row['codigo_martins'],
        $row['mercadoria'],
        $row['filial'],
        $row['qde_estoque_disp'],
        $row['media_mensal_venda'],
        $row['qde_venda'],
        $row['cobertura'],
        $row['qde_saldo_pedido'],
        $row['lost_sales'],
        date_format($row['data_importacao'], 'd/m/Y H:i:s')
    ];
    $sheet->fromArray([$data], NULL, "A{$rowIndex}");
    $rowIndex++;
}

$temp_file = tempnam(sys_get_temp_dir(), 'sellout_export_') . '.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($temp_file);

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sellout_export.xlsx"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($temp_file));

readfile($temp_file);
unlink($temp_file);
exit;
