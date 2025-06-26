<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['page'])) {
    error_log('Página solicitada: ' . $_GET['page']);
}

// Carregar a configuração global
require_once '../config.php';

// Verifica se existe o parâmetro "page" na URL amigável
$page = $_GET['page'] ?? 'dashboard';

// Lista de páginas permitidas para evitar acessos indevidos
$allowed_pages = [
    'login',
    '404', 
    'logout', 
    'users', 
    'add_user', 
    'dashboard',
    'configuracoes',
    'apontar_forecast',
    'consulta_lancamentos', 
    'auth_login', 
    'auth_logout', 
    'auth_register', 
    'password_reset',
    'delete_user',
    'edit_user', 
    'update_user',
    'update_user_status',
    'password_reset_request',  // Página para solicitar recuperação de senha
    'password_reset_form',     // Página para redefinir senha com token
    'process_password_reset_request',  // Processamento do envio de e-mail
    'process_password_reset',   // Processamento da redefinição de senha
    'academia_ti',
    'process_forecast',
    'consulta_lancamentos',
    'historico_forecast',
    'update_forecast',
    'depara_comercial',
    'process_update_comercial',
    'export_forecast',
    'enviar_sellout',
    'export_sellout',
    'financeiro',
    'process_forecast_sku',
    'export_forecast',
    'cursos',
    'forecast_geral',
    'OKR_novo_objetivo',
    'OKR_novo_iniciativa',
    'OKR_aprovacao',
    'process_approval.php',
    'OKR_novo_kr',
    'OKR_detalhe_objetivo',
    'OKR_consulta',
    'pdv_clientes',
    'editar_objetivo',
    'OKR_mapa'
];

if (in_array($page, $allowed_pages)) {
    require_once "../views/{$page}.php";
} else {
    require_once "../views/404.php"; // Página de erro caso não encontre a página
}

/*



// Carregar a configuração global
require_once '../config.php';
require_once '../includes/auto_check.php';

// Definir página padrão como login
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Definir páginas públicas (acessíveis sem login)
$public_pages = ['login', 'auth_login', 'auth_register', 'password_reset'];

// Definir páginas privadas (requerem login)
$private_pages = ['dashboard', 'users', 'configuracoes', 'apontar_forecast', 'auth_logout'];

// Lista de páginas válidas
$allowed_pages = array_merge($public_pages, $private_pages);

if (in_array($page, $allowed_pages)) {
    // Verifica se a página é privada e se o usuário está autenticado
    if (in_array($page, $private_pages) && !isset($_SESSION['user_id'])) {
        header("Location: index.php?page=login&error=unauthorized");
        exit();
    }

    require_once "../views/{$page}.php";
} else {
    require_once "../views/404.php"; // Página de erro caso não encontre a página
}

*/


?>
