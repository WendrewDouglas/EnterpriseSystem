<?php

ob_start();


require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

$pageTitle = 'Adicionar Novo Usuário - Forecast System';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

$roles = ['admin' => 'Administrador', 'gestor' => 'Gestor', 'consulta' => 'Consulta'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $role = 'custom'; // Perfil padrão sem acessos

    // Validação de campos obrigatórios
    if (empty($name) || !$email || empty($password)) {
        $error_message = "Todos os campos são obrigatórios e devem ser preenchidos corretamente.";
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
        $params = [$name, $email, $hashedPassword, $role];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            $_SESSION['success_message'] = "Usuário cadastrado com sucesso!";
            header("Location: index.php?page=users");
            exit();
        } else {
            $errors = sqlsrv_errors();
            $error_message = "Erro ao cadastrar o usuário: " . ($errors ? $errors[0]['message'] : 'Erro desconhecido');
        }
    }
}
?>

<div class="content">
    <h2>👥 Adicionar Novo Usuário</h2>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=add_user">
        <div class="mb-3">
            <label for="name" class="form-label">Nome</label>
            <input type="text" class="form-control" id="name" name="name" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Senha</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-success">Cadastrar Usuário</button>
        <a href="index.php?page=users" class="btn btn-secondary">Voltar</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
