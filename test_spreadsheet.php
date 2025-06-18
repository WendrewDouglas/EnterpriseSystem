<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

try {
    // Criando uma nova planilha
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Adicionando dados à planilha
    $sheet->setCellValue('A1', 'Teste de PhpSpreadsheet');
    $sheet->setCellValue('A2', 'Se você está vendo isso, o PhpSpreadsheet está funcionando corretamente.');

    // Caminho onde o arquivo será salvo
    $filePath = __DIR__ . "/teste_spreadsheet.xlsx";

    // Criando e salvando o arquivo
    $writer = new Xlsx($spreadsheet);
    $writer->save($filePath);

    echo "Arquivo gerado com sucesso: <a href='teste_spreadsheet.xlsx'>Clique aqui para baixar</a>";
} catch (Exception $e) {
    echo "Erro ao testar PhpSpreadsheet: " . $e->getMessage();
}

?>
