<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('apontar_forecast');

// Verificar se a solicitação é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Receber os dados do POST
$id = $_POST['id'] ?? null;
$tipo = $_POST['tipo'] ?? null;
$novaQuantidade = $_POST['nova_quantidade'] ?? null;

// Validar os dados recebidos
if (!$id || !$tipo || $novaQuantidade === null || !is_numeric($novaQuantidade) || $novaQuantidade < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos: parâmetros ausentes']);
    exit();
}

// Capturar o usuário logado que está realizando a alteração e o IP do usuário
$usuarioLogado = $_SESSION['user_name'] ?? 'Desconhecido';
$ipUsuario = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';

// Conectar ao banco
$db = new Database();
$conn = $db->getConnection();

if ($tipo === 'modelo') {
    // Atualizar na tabela forecast_entries
    $sql = "UPDATE forecast_entries 
            SET quantidade = ?, 
                ultimo_usuario_editou = ?, 
                data_ultima_alteracao = GETDATE(), 
                ip_ultima_alteracao = ?
            WHERE id = ?";
    $params = [$novaQuantidade, $usuarioLogado, $ipUsuario, $id];
} elseif ($tipo === 'sku') {
    // Atualizar na tabela forecast_entries_sku
    $sql = "UPDATE forecast_entries_sku 
            SET quantidade = ?, 
                data_edicao = GETDATE(), 
                ip_usuario = ?
            WHERE id = ?";
    $params = [$novaQuantidade, $ipUsuario, $id];
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    exit();
}

$stmt = sqlsrv_query($conn, $sql, $params);
if ($stmt === false) {
    http_response_code(500);
    $errorDetails = print_r(sqlsrv_errors(), true);
    error_log($errorDetails);
    echo json_encode([
        'success'   => false, 
        'message'   => 'Erro ao atualizar o banco de dados', 
        'error_log' => $errorDetails
    ]);
    exit();
}

// Retornar sucesso
echo json_encode(['success' => true, 'message' => 'Registro atualizado com sucesso!']);
exit();
?>
