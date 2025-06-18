<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Verificar se foi passado um ID válido via GET
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // Impedir a exclusão do próprio usuário logado
    if ($_SESSION['user_id'] == $user_id) {
        $_SESSION['error_message'] = "Você não pode excluir sua própria conta.";
        header("Location: ../public/index.php?page=users");
        exit();
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Query para deletar o usuário
    $sql = "DELETE FROM users WHERE id = ?";
    $params = [$user_id];
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        $_SESSION['success_message'] = "Usuário excluído com sucesso.";
    } else {
        $_SESSION['error_message'] = "Erro ao excluir o usuário.";
    }
} else {
    $_SESSION['error_message'] = "ID de usuário inválido.";
}

// Redirecionar de volta para a página de usuários
header("Location: ../public/index.php?page=users");
exit();
?>
