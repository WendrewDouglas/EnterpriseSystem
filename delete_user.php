<?php
require_once __DIR__ . '/includes/auto_check.php';
require_once __DIR__ . '/includes/db_connection.php';
require_once __DIR__ . '/includes/permissions.php';

// Verificar permissão
verificarPermissao('users');

// Verificar se ID foi fornecido
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID do usuário não fornecido.";
    header("Location: views/index.php?page=users");
    exit;
}

$user_id = (int)$_GET['id'];

// Criar conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Verificar se o usuário existe
$check_sql = "SELECT id FROM users WHERE id = ?";
$check_stmt = sqlsrv_query($conn, $check_sql, [$user_id]);

if ($check_stmt === false || !sqlsrv_fetch_array($check_stmt)) {
    $_SESSION['error_message'] = "Usuário não encontrado.";
    header("Location: views/index.php?page=users");
    exit;
}

// Primeiro, excluir registros relacionados na tabela user_permissions
$delete_permissions_sql = "DELETE FROM user_permissions WHERE user_id = ?";
$delete_permissions_stmt = sqlsrv_query($conn, $delete_permissions_sql, [$user_id]);

if ($delete_permissions_stmt === false) {
    $_SESSION['error_message'] = "Erro ao excluir permissões do usuário.";
    header("Location: views/index.php?page=users");
    exit;
}

// Agora, excluir o usuário
$delete_user_sql = "DELETE FROM users WHERE id = ?";
$delete_user_stmt = sqlsrv_query($conn, $delete_user_sql, [$user_id]);

if ($delete_user_stmt === false) {
    $_SESSION['error_message'] = "Erro ao excluir usuário.";
    header("Location: views/index.php?page=users");
    exit;
}

// Sucesso
$_SESSION['success_message'] = "Usuário excluído com sucesso.";
header("Location: views/index.php?page=users");
exit; 