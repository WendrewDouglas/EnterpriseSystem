<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';        // 🔥 <-- Faltava isso
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('visualizar_objetivo'); // ou pode criar uma permissão mais específica como 'alterar_status_iniciativa'

// 🔗 Conexão com o banco OKR
$dbOKR = new OKRDatabase();
$connOKR = $dbOKR->getConnection();
if (!$connOKR) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Falha na conexão com o banco.']);
    exit;
}

// 🧠 Dados do usuário logado
$usuarioId = $_SESSION['user_id'] ?? null;
if (!$usuarioId) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado.']);
    exit;
}

// 🔎 Ler dados do POST (JSON)
$input = json_decode(file_get_contents('php://input'), true);
$idIniciativa = $input['id_iniciativa'] ?? '';
$novoStatus = $input['novo_status'] ?? '';

if (!$idIniciativa || !$novoStatus) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos.']);
    exit;
}

// 🔧 Validar status permitido
$statusPermitidos = ['nao iniciado', 'em andamento', 'concluido', 'cancelado'];
if (!in_array(strtolower($novoStatus), $statusPermitidos)) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Status inválido.']);
    exit;
}

// 🔧 Atualizar no banco
$sql = "UPDATE iniciativas 
        SET status = ?, 
            dt_ultima_atualizacao = GETDATE(),
            id_user_ult_alteracao = ?
        WHERE id_iniciativa = ?";

$stmt = sqlsrv_query($connOKR, $sql, [$novoStatus, $usuarioId, $idIniciativa]);

if ($stmt) {
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Status da Iniciativa atualizado com sucesso.']);
} else {
    $error = sqlsrv_errors();
    echo json_encode(['status' => 'erro', 'mensagem' => 'Erro ao atualizar o status.', 'debug' => $error]);
}
?>
