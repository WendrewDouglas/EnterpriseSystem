<?php
require_once __DIR__ . '/../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Coletar os dados enviados pelo formulário
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Verificar se as senhas coincidem
    if ($password !== $confirm_password) {
        $_SESSION['error_message'] = "As senhas não coincidem.";
        header("Location: index.php?page=password_reset_form&token=$token");
        exit();
    }

    // Criar conexão com o banco
    $db = new Database();
    $conn = $db->getConnection();

    // Verifica se o token é válido
    $sql = "SELECT email FROM users WHERE reset_token = ? AND reset_token_expiration > GETDATE()";
    $stmt = sqlsrv_query($conn, $sql, array($token));

    if ($stmt === false) {
        $_SESSION['error_message'] = "Erro ao verificar o token: " . print_r(sqlsrv_errors(), true);
        header("Location: index.php?page=login");
        exit();
    }

    if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Token válido, atualizar senha
        $email = $row['email'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Atualizar a senha no banco de dados
        $updateSql = "UPDATE users SET password = ? WHERE email = ?";
        $stmtUpdate = sqlsrv_query($conn, $updateSql, array($hashed_password, $email));

        if ($stmtUpdate === false) {
            $_SESSION['error_message'] = "Erro ao atualizar a senha: " . print_r(sqlsrv_errors(), true);
            header("Location: index.php?page=login");
            exit();
        }

        // Limpar os campos reset_token e reset_token_expiration
        $deleteSql = "UPDATE users SET reset_token = NULL, reset_token_expiration = NULL WHERE email = ?";
        $stmtDelete = sqlsrv_query($conn, $deleteSql, array($email));

        if ($stmtDelete === false) {
            $_SESSION['error_message'] = "Erro ao limpar os tokens: " . print_r(sqlsrv_errors(), true);
            header("Location: index.php?page=login");
            exit();
        }

        // Redirecionar com mensagem de sucesso
        $_SESSION['success_message'] = "Senha redefinida com sucesso!";
        header("Location: index.php?page=login");
    } else {
        // Token inválido ou expirado
        $_SESSION['error_message'] = "Token inválido ou expirado.";
        header("Location: index.php?page=login");
    }

    exit();
}
?>
