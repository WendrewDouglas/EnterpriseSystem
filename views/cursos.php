<?php
// Verifica sessão, conexão e permissões
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Verifica se o usuário tem permissão para acessar a área de cursos
verificarPermissao('cursos');

// Configuração da página
$pageTitle = 'Cursos de Treinamento - Ambiente Web TI';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Cria a conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Forçar o locale para exibir meses em português
setlocale(LC_TIME, 'ptb.UTF-8', 'ptb', 'portuguese', 'portuguese_brazil');

// Obtém o usuário logado
 $userName = $_SESSION['user_name'] ?? null;
if (!$userName) {
    die("<div class='alert alert-danger'>Erro: Usuário não identificado. Faça login novamente.</div>");
}

?>