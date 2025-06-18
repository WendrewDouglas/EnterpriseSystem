<?php
require_once '../includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $token = bin2hex(random_bytes(50));
    $expiration = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $db = new Database();
    $conn = $db->getConnection();

    if ($conn) {
        $query = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?";
        $params = array($token, $expiration, $email);
        $stmt = sqlsrv_prepare($conn, $query, $params);

        if (sqlsrv_execute($stmt)) {
            // Enviar email com link de redefinição
            $resetLink = "http://localhost/forecast/views/password_reset_form.php?token=$token";
            mail($email, "Recuperação de Senha", "Clique no link para redefinir: $resetLink");
            echo "Verifique seu e-mail para redefinir a senha.";
        } else {
            echo "E-mail não encontrado.";
        }
    }
}
?>
