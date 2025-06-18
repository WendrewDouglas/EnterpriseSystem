<?php
require_once __DIR__ . '/../includes/auto_check.php';

$pageTitle = 'Configurações - Forecast System';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<div class="content">
    <h2>⚙️ Configurações</h2>
    <p>Ajuste as configurações do sistema aqui.</p>

    <form action="index.php?page=save_config" method="POST">
        <div class="mb-3">
            <label for="timezone" class="form-label">Fuso Horário</label>
            <input type="text" class="form-control" id="timezone" name="timezone" value="<?= htmlspecialchars($_SESSION['timezone'] ?? 'America/Sao_Paulo'); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Salvar</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
