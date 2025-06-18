<?php
// Proteção de acesso não autorizado
require_once __DIR__ . '/../includes/auto_check.php';

// Inclusão da conexão com o banco de dados
require_once __DIR__ . '/../includes/db_connection.php';

// Recupera a conexão a partir da instância $db definida no db_connection.php
$conn = $db->getConnection();

// Verifica se a conexão foi estabelecida corretamente
if (!isset($conn) || $conn === null) {
    error_log("Erro: Variável \$conn não definida. Verifique o arquivo db_connection.php.");
    die("Erro interno: conexão com banco de dados não estabelecida.");
}

// Definição do título da página
$pageTitle = 'Dashboard - Forecast System';

// Inclusão do header e sidebar
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Função para obter a referência do próximo mês (formato m/Y)
function getNextMonthReference() {
    return date('m/Y', strtotime('first day of next month'));
}

$nextMonth = getNextMonthReference();

// BUSCA: Lista de regionais e status de forecast
$mesRef = $nextMonth; // $nextMonth já vem no formato "m/Y", por exemplo, "03/2025"
$sqlRegionais = "
    SELECT d.Regional AS codigo, d.NomeRegional AS nome,
           CASE WHEN m.cod_gestor IS NOT NULL THEN 'enviado' ELSE 'não enviado' END AS matriz_status,
           CASE WHEN f.cod_gestor IS NOT NULL THEN 'enviado' ELSE 'não enviado' END AS filial_status
    FROM DW..DEPARA_COMERCIAL d
    LEFT JOIN (
         SELECT DISTINCT cod_gestor 
         FROM forecast_entries 
         WHERE mes_referencia = ? AND finalizado = 1 AND empresa = 1001
    ) m ON d.Regional = m.cod_gestor
    LEFT JOIN (
         SELECT DISTINCT cod_gestor 
         FROM forecast_entries 
         WHERE mes_referencia = ? AND finalizado = 1 AND empresa = 1002
    ) f ON d.Regional = f.cod_gestor
    WHERE d.Regional IS NOT NULL AND LOWER(d.status_regional) = 'ativo'
    ORDER BY d.NomeRegional ASC
";
$stmtRegionais = sqlsrv_query($conn, $sqlRegionais, [$mesRef, $mesRef]);
if ($stmtRegionais === false) {
    error_log("Erro ao consultar regionais (join): " . print_r(sqlsrv_errors(), true));
    $regionais = [];
} else {
    $regionais = [];
    while ($row = sqlsrv_fetch_array($stmtRegionais, SQLSRV_FETCH_ASSOC)) {
        $regionais[] = $row;
    }
}


// BUSCA: Total de usuários cadastrados
$sqlUsuarios = "SELECT COUNT(*) as total FROM users";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);
if ($stmtUsuarios === false) {
    error_log("Erro ao consultar usuários: " . print_r(sqlsrv_errors(), true));
    $totalUsuarios = 0;
} else {
    $totalUsuarios = 0;
    if ($row = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
        $totalUsuarios = $row['total'];
    }
}
?>

<div class="content">
    <h2 class="mb-4">Bem-vindo ao Dashboard</h2>

    <div class="row">
        <!-- Card de Regionais -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-map-marker-alt"></i> Apontamentos de Forecast
                </div>
                <div class="card-body">
                    <?php 
                    // Contagem regressiva: prazo até o dia 15 deste mês às 23h30
                    $now = new DateTime();
                    $deadline = new DateTime(date('Y-m-15 23:59:59'));
                    if ($now > $deadline) {
                        $countdownText = "Prazo encerrado";
                    } else {
                        $interval = $now->diff($deadline);
                        $countdownText = $interval->format('%d dias, %h horas e %i minutos');
                    }
                    ?>
                    <div class="mb-3">
                        <strong>Prazo para informar o forecast do próximo trimestre:</strong><br>
                        <small>Data/Hora limite: <?php echo $deadline->format('d/m/Y H:i'); ?></small><br>
                        <small>Faltam <?php echo $countdownText; ?> para fechar os apontamentos</small>
                    </div>

                    <!-- Botões de Ação -->
                    <div class="mt-4">
                        <a href="index.php?page=apontar_forecast" class="btn btn-primary me-2">
                            <i class="fas fa-chart-line"></i> Apontar Forecast
                        </a>
                        <a href="index.php?page=configuracoes" class="btn btn-info me-2">
                            <i class="fas fa-cogs"></i> Configurações
                        </a>
                    </div>


                    <?php if (!empty($regionais)): ?>
                        <ul class="mt-4 list-group">
                        <strong>Lista de Gestores</strong><br>
                        <?php foreach ($regionais as $regional): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($regional['codigo']); ?></strong> - <?php echo htmlspecialchars($regional['nome']); ?>
                                </div>
                                <div>
                                    <span class="badge <?php echo ($regional['matriz_status'] == 'enviado' ? 'bg-success' : 'bg-danger'); ?>">
                                        <i class="fas <?php echo ($regional['matriz_status'] == 'enviado' ? 'fa-check' : 'fa-exclamation-triangle'); ?>"></i> Matriz
                                    </span>
                                    <span class="badge <?php echo ($regional['filial_status'] == 'enviado' ? 'bg-success' : 'bg-danger'); ?> ms-2">
                                        <i class="fas <?php echo ($regional['filial_status'] == 'enviado' ? 'fa-check' : 'fa-exclamation-triangle'); ?>"></i> Filial
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Nenhum regional cadastrado.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card de Usuários -->
        <div class="col-md-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <i class="fas fa-users"></i> Usuários Cadastrados
                </div>
                <div class="card-body">
                    <h3><?php echo $totalUsuarios; ?></h3>
                    <p>Total de usuários cadastrados no sistema.</p>
                    <a href="index.php?page=users" class="btn btn-outline-secondary">Gerenciar Usuários</a>
                </div>
            </div>

 <!-- Card de Aviso de Sell-Out Disponível -->
 <div class="mt-4 card shadow-sm border-success">
                <div class="card-body">
                    <h5 class="card-title text-success">
                        <i class="fas fa-check-circle"></i> Novo: Envio de Sell-Out Disponível!
                    </h5>
                    <p class="card-text">
                        O envio de registros de Sell-Out já está disponível! Agora você pode importar seus dados diretamente para o sistema de forma rápida e prática.
                    </p>
                    <a href="index.php?page=enviar_sellout" class="btn btn-success">
                        <i class="fas fa-upload"></i> Enviar Sell-Out
                    </a>
                </div>
            </div>            
        </div>
    </div>

   

</div>

<!-- Inclusão dos scripts do Bootstrap e Font Awesome -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Substitua "seu-kit-fontawesome.js" pelo seu kit real do Font Awesome -->
<script src="https://kit.fontawesome.com/seu-kit-fontawesome.js" crossorigin="anonymous"></script>
</body>
</html>
