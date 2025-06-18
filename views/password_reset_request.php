<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



$pageTitle = 'Recuperar Senha - Forecast System';
include __DIR__ . '/../templates/header.php';
?>

<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-lg p-4" style="max-width: 400px; width: 100%;">
        <div class="text-center">
            <h3 class="mb-4">Recuperar Senha</h3>
        </div>

        <form action="index.php?page=process_password_reset_request" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">E-mail</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Digite seu e-mail" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Enviar link de senha</button>
        </form>

        <hr>

        <div class="text-center">
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger mt-3">
                    <?= htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <a href="index.php?page=login">Voltar ao login</a>
        </div>



    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
