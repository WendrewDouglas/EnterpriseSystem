<?php
$pageTitle = 'Redefinir Senha - Forecast System';
include __DIR__ . '/../templates/header.php';

$token = $_GET['token'] ?? '';
?>

<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center">
            <h3 class="mb-4">Criar ou Redefinir Senha</h3>
        </div>

        <form action="index.php?page=process_password_reset" method="POST">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">

            <div class="mb-3">
                <label for="password" class="form-label">Nova Senha</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirme a Senha</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Definir Senha</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
