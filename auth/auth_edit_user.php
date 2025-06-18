<?php
require_once '../includes/db_connection.php';

$db = new Database();
$conn = $db->getConnection();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $name = htmlspecialchars($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $role = $_POST['role'];

    $sql = "UPDATE users SET name=?, email=?, role=? WHERE id=?";
    $params = array($name, $email, $role, $id);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt) {
        header("Location: ../views/users.php?success=2");
        exit();
    } else {
        echo "Erro ao atualizar usuário.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário</title>
</head>
<body>
    <div class="container mt-5">
        <h2>Editar Usuário</h2>
        <form action="" method="POST">
            <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Nome</label>
                <input type="text" class="form-control" name="name" value="<?= $user['name'] ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" name="email" value="<?= $user['email'] ?>" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Perfil</label>
                <select class="form-select" name="role" required>
                    <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrador</option>
                    <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>Usuário</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="../views/users.php" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</body>
</html>
