<?php
require_once 'includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_GET['token'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $query = "UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?";
    $stmt = $conn->prepare($query);
    if ($stmt->execute([$new_password, $token])) {
        echo "Senha redefinida com sucesso!";
        header("Location: auth_login.php");
    } else {
        echo "Token inválido ou expirado.";
    }
}
?>

<form action="" method="POST">
    <input type="password" name="new_password" placeholder="Nova Senha" required>
    <button type="submit">Redefinir Senha</button>
</form>
