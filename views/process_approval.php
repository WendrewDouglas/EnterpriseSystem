<?php
// view/process_approval.php

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';       // para orçamentos
require_once __DIR__ . '/../includes/db_connectionOKR.php';   // para objetivos/KRs
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('aprovacao_OKR');

// Recebe dados do formulário
$itemType = $_POST['item_type']   ?? null;   // 'objective', 'kr' ou 'orcamento'
$itemId   = $_POST['item_id']     ?? null;
$action   = $_POST['action']      ?? null;   // 'aprovar' ou 'rejeitar'
$feedback = trim($_POST['feedback'] ?? '');
$userId   = $_SESSION['user_id']  ?? null;

if (!$itemType || !$itemId || !$action || !$userId) {
    die("Parâmetros insuficientes para processar a aprovação.");
}

// Traduz ação em status
$status = ($action === 'aprovar') ? 'aprovado' : 'reprovado';

// Define tabela, colunas e conexão conforme tipo de item
switch ($itemType) {
    case 'objective':
        $table          = 'objetivos';
        $idColumn       = 'id_objetivo';
        $statusColumn   = 'status_aprovacao';
        $approverColumn = 'aprovador';
        $dateColumn     = 'dt_aprovacao';
        $commentColumn  = 'comentarios_aprovacao';
        $db             = new OKRDatabase();
        $conn           = $db->getConnection();
        break;

    case 'kr':
        $table          = 'key_results';
        $idColumn       = 'id_kr';
        $statusColumn   = 'status_aprovacao';
        $approverColumn = 'aprovador';
        $dateColumn     = 'dt_aprovacao';
        $commentColumn  = 'comentarios_aprovacao';
        $db             = new OKRDatabase();
        $conn           = $db->getConnection();
        break;

    case 'orcamento':
        $table          = 'orcamentos';
        $idColumn       = 'id_orcamento';
        $statusColumn   = 'status_aprovacao';
        $approverColumn = 'id_user_aprovador';
        $dateColumn     = 'dt_aprovacao';
        $commentColumn  = 'comentarios_aprovacao';
        $db             = new OKRDatabase();
        $conn           = $db->getConnection();
        break;

    default:
        die("Tipo de item inválido: {$itemType}");
}

if (!$conn) {
    die("<div class='alert alert-danger'>Erro de conexão com o banco de dados.</div>");
}

// Prepara e executa o UPDATE
$sql = "
    UPDATE {$table}
       SET {$statusColumn}   = ?,
           {$approverColumn} = ?,
           {$dateColumn}     = GETDATE(),
           {$commentColumn}  = ?
     WHERE {$idColumn} = ?
";
$params = [
    $status,
    $userId,
    $feedback,
    $itemId
];

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    $errors = sqlsrv_errors();
    $msg    = $errors[0]['message'] ?? print_r($errors, true);
    die("<div class='alert alert-danger'>Erro ao atualizar registro: {$msg}</div>");
}

// Flash message e redirecionamento
$_SESSION['flash'] = ucfirst($itemType)
    . " #{$itemId} "
    . ($action === 'aprovar' ? 'aprovado' : 'reprovado')
    . " com sucesso.";

header('Location: index.php?page=OKR_aprovacao');
exit;
