<?php
// Inicia output buffering e configura log de erros
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/log_erro_OKR_aprovacao.log');

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

session_start();
verificarPermissao('OKR_aprovacao');

$loggedUserId = $_SESSION['user_id'];

// Verifica se o método é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Método inválido: " . $_SERVER['REQUEST_METHOD']);
    die("Método inválido.");
}

// Recebe os dados enviados
$item_type    = $_POST['item_type'] ?? null;
$item_id      = $_POST['item_id'] ?? null;
$action       = $_POST['action'] ?? null;
$feedbackText = trim($_POST['feedback'] ?? '');

if (!$item_type || !$item_id || !$action) {
    error_log("Dados incompletos: item_type=$item_type, item_id=$item_id, action=$action");
    die("Dados incompletos.");
}

// Define o novo status
if ($action === 'aprovar') {
    $novo_status = 'aprovado';
} elseif ($action === 'rejeitar') {
    $novo_status = 'reprovado';
} else {
    error_log("Ação inválida: $action");
    die("Ação inválida.");
}

// Define tabela e coluna de identificação
if ($item_type === 'objective') {
    $table = 'OKR_objetivos';
    $id_column = 'id_objetivo';
} elseif ($item_type === 'kr') {
    $table = 'OKR_key_results';
    $id_column = 'id_kr';
} else {
    error_log("Tipo de item inválido: $item_type");
    die("Tipo de item inválido.");
}

// Conecta ao banco
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    $err = print_r(sqlsrv_errors(), true);
    error_log("Erro de conexão com o banco: " . $err);
    die("Erro de conexão com o banco: " . $err);
}

// Busca o registro atual para obter as observações
$sqlSelect = "SELECT observacoes FROM $table WHERE $id_column = ?";
$paramsSelect = [$item_id];
$stmtSelect = sqlsrv_prepare($conn, $sqlSelect, $paramsSelect);
if ($stmtSelect === false) {
    $err = print_r(sqlsrv_errors(), true);
    error_log("Erro ao preparar a consulta: " . $err);
    die("Erro ao preparar a consulta: " . $err);
}
if (!sqlsrv_execute($stmtSelect)) {
    $err = print_r(sqlsrv_errors(), true);
    error_log("Erro ao executar a consulta: " . $err);
    die("Erro ao executar a consulta: " . $err);
}
$currentRecord = sqlsrv_fetch_array($stmtSelect, SQLSRV_FETCH_ASSOC);
$currentObservacoes = $currentRecord['observacoes'] ?? '';

error_log("Observações atuais: " . $currentObservacoes);

// Processa o campo de observações
$feedbacks = [];
if (!empty($currentObservacoes)) {
    $decoded = json_decode($currentObservacoes, true);
    if (is_array($decoded)) {
        $feedbacks = $decoded;
    }
}
$newFeedback = [
    "action"    => $action,
    "feedback"  => $feedbackText,
    "manager"   => $loggedUserId,
    "date"      => date('c')
];
$feedbacks[] = $newFeedback;
$observacoesJson = json_encode($feedbacks);
error_log("Observações JSON: " . $observacoesJson);

// Monta a query de atualização
if ($item_type === 'kr') {
    $sqlUpdate = "UPDATE $table 
                  SET observacoes = ?, status_aprovacao = ?, id_aprovador = ?, data_aprovacao = GETDATE(), id_edicao = ?, data_atualizacao = GETDATE()
                  WHERE $id_column = ?";
    $paramsUpdate = [$observacoesJson, $novo_status, $loggedUserId, $loggedUserId, $item_id];
} else {
    $sqlUpdate = "UPDATE $table 
                  SET observacoes = ?, status_aprovacao = ?, id_aprovador = ?, data_aprovacao = GETDATE()
                  WHERE $id_column = ?";
    $paramsUpdate = [$observacoesJson, $novo_status, $loggedUserId, $item_id];
}

error_log("SQL Update: " . $sqlUpdate);
error_log("Parâmetros: " . print_r($paramsUpdate, true));

$stmtUpdate = sqlsrv_prepare($conn, $sqlUpdate, $paramsUpdate);
if ($stmtUpdate === false) {
    $err = print_r(sqlsrv_errors(), true);
    error_log("Erro na preparação do update: " . $err);
    die("Erro na preparação do update: " . $err);
}
if (!sqlsrv_execute($stmtUpdate)) {
    $err = print_r(sqlsrv_errors(), true);
    error_log("Erro ao atualizar o registro: " . $err);
    die("Erro ao atualizar o registro: " . $err);
}

error_log("Registro atualizado com sucesso!");

// Redireciona para o front controller (index.php na pasta public)
header("Location: /forecast/public/index.php?page=OKR_aprovacao&msg=success");
exit;

ob_end_flush();
?>
