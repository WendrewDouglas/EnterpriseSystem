<?php
require_once __DIR__ . '/../includes/auto_check.php';

// Configuração da página
$pageTitle = 'Academia T.I. - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-mortarboard"></i> Academia T.I.</h2>
    <p class="text-muted">Aqui você poderá acessar cursos e videoaulas exclusivas.</p>

    <div class="card shadow-sm p-4">
        <h4>📚 Em breve...</h4>
        <p>Estamos preparando conteúdos incríveis para aprimorar seus conhecimentos em tecnologia.</p>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
