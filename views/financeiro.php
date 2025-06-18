<?php
// Inclua header e sidebar
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<!-- Área de Conteúdo -->
<div class="content">
    <!-- Card com o Gráfico do Dólar -->
    <div class="card mb-3" style="max-width: 100%;">
        <div class="card-header">
            <h3 class="card-title">Evolução do Dólar Comercial</h3>
        </div>
        <div class="card-body text-center">
            <img src="http://192.168.4.17:5000//market/dolar/" alt="Gráfico do Dólar" class="img-fluid" style="max-height:400px;">
        </div>
    </div>
    
    <!-- Conteúdo principal (por exemplo, iframe com a página de títulos e inadimplência) -->
    <iframe src="http://192.168.4.17:5000//financeiro/titulos_inadimplencia" style="width:100%; height:800px; border:none;"></iframe>
</div>

<?php
// Inclua o rodapé
include __DIR__ . '/../templates/footer.php';
?>
