<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Forecast System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .logo {
            max-width: 200px;
            height: auto;
        }
    </style>
</head>
<body class="bg-light">

    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-lg p-4 text-center" style="width: 100%; max-width: 400px;">
            
            <!-- Logomarca -->
            <div class="text-center mb-4">
            <img src="/forecast/public/assets/img/logo_color1.png" alt="Logo da Empresa" class="logo">
            </div>

            <!-- Formulário de Login -->
            <form action="/forecast/auth/auth_login.php" method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label text-start w-100">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label text-start w-100">Senha</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Digite sua senha" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Entrar</button>
                <div class="mt-3 text-center">
                    <a href="index.php?page=password_reset_request">Não tem ou esqueceu a senha?</a>
                </div>
            </form>

            <hr>

            <div class="text-center">
                <p>Não tem uma conta? <a href="/forecast/auth/auth_register.php">Cadastre-se</a></p>
            </div>

            <!-- Exibir mensagens de erro ou sucesso -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger mt-3">
                    <?= $_SESSION['error_message']; ?>
                    <?php unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success mt-3">
                    <?= $_SESSION['success_message']; ?>
                    <?php unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
