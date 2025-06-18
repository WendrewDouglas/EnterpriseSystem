<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db_connection.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';


use Dotenv\Dotenv;

// Carregar variáveis de ambiente
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuração de sessão segura
ini_set('session.use_only_cookies', 1);  // Apenas cookies para evitar ataque via URL
ini_set('session.cookie_httponly', 1);   // Impedir acesso via JavaScript
ini_set('session.cookie_secure', 0);    // Definir como 1 para produção em HTTPS
ini_set('session.gc_maxlifetime', 1800); // Tempo máximo de sessão (30 minutos)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Definir tempo de expiração
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: public/?page=login&message=session_expired");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();





?>
