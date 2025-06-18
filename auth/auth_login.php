<?php

session_start();
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    $db = new Database();
    $conn = $db->getConnection();

    // Adicionando a coluna 'role' para identificar o perfil do usuário
    $sql = "SELECT id, name, email, password, role FROM users WHERE email = ?";
    $params = array($email);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $_SESSION['error_message'] = "Erro na consulta ao banco de dados.";
        header("Location: ../public/index.php?page=login");
        exit();
    }

    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Regenerar ID da sessão para segurança contra ataques de fixação de sessão
        session_regenerate_id(true);
        
        // Salvando informações do usuário na sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];  // Adicionando a role do usuário
        $_SESSION['login_time'] = time(); // Salva o horário de login
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT']; // Proteção contra hijacking

        // Redirecionar para o dashboard
        header("Location: ../public/index.php?page=dashboard");
        exit();
    } else {
        $_SESSION['error_message'] = "E-mail ou senha incorretos.";
        header("Location: ../public/index.php?page=login");
        exit();
    }
}
?>

