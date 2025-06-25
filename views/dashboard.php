<?php
// 🔒 Proteções
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Dashboard - Forecast System';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// 🔗 Conexões
$connForecast = (new Database())->getConnection();
$connOKR      = (new OKRDatabase())->getConnection();

if (!$connForecast || !$connOKR) {
    die("<div class='alert alert-danger'>❌ Erro: conexão com banco de dados não estabelecida.</div>");
}

// 🗓️ Função referência próximo mês
function getNextMonthReference() {
    return date('m/Y', strtotime('first day of next month'));
}
$nextMonth = getNextMonthReference();


// 🔍 Consulta Regionais
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
ORDER BY d.NomeRegional ASC;
";

$stmtRegionais = sqlsrv_query($connForecast, $sqlRegionais, [$nextMonth, $nextMonth]);
$regionais = [];
while ($r = sqlsrv_fetch_array($stmtRegionais, SQLSRV_FETCH_ASSOC)) {
    $regionais[] = $r;
}


// 🔍 Total de usuários
$stmtUsuarios = sqlsrv_query($connForecast, "SELECT COUNT(*) AS total FROM users");
$totalUsuarios = ($u = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) ? intval($u['total']) : 0;


// 🔥 🔍 Mapa Estratégico dos OKRs

// Carregar pilares
$pilares = [];
$sqlPilares = "SELECT id_pilar, descricao_exibicao FROM dom_pilar_bsc ORDER BY ordem_pilar ASC";
$stmtPilares = sqlsrv_query($connOKR, $sqlPilares);
while ($p = sqlsrv_fetch_array($stmtPilares, SQLSRV_FETCH_ASSOC)) {
    $id = mb_strtolower(trim($p['id_pilar']));
    $pilares[$id] = [
        'descricao' => $p['descricao_exibicao'],
        'icone'     => [
            'financeiro'               => 'bi-currency-dollar',
            'clientes'                 => 'bi-people',
            'processos internos'       => 'bi-hammer',
            'aprendizado e crescimento'=> 'bi-mortarboard'
        ][$id] ?? 'bi-diagram-3',
        'cor'       => [
            'financeiro'               => '#B8860B',
            'clientes'                 => '#006400',
            'processos internos'       => '#00008B',
            'aprendizado e crescimento'=> '#FF1493'
        ][$id] ?? '#6c757d'
    ];
}

// Query mapa estratégico
// 🔥 Consulta Mapa Estratégico dos OKRs com progresso, qtd de objetivos e qtd de KRs
$sqlMapa = "
WITH ProgressoKR AS (
    SELECT 
        kr.id_kr,
        kr.id_objetivo,
        CASE 
            WHEN (msMeta.valor_esperado - msBase.valor_esperado) <> 0 THEN
                ROUND( ((ISNULL(msUlt.valor_real, msBase.valor_esperado) - msBase.valor_esperado) 
                / (msMeta.valor_esperado - msBase.valor_esperado)) * 100, 1)
            ELSE 0
        END AS progresso_kr
    FROM key_results kr
    OUTER APPLY (SELECT TOP 1 * FROM milestones_kr WHERE id_kr = kr.id_kr ORDER BY num_ordem ASC) msBase
    OUTER APPLY (SELECT TOP 1 * FROM milestones_kr WHERE id_kr = kr.id_kr ORDER BY num_ordem DESC) msMeta
    OUTER APPLY (SELECT TOP 1 * FROM milestones_kr WHERE id_kr = kr.id_kr AND valor_real IS NOT NULL ORDER BY num_ordem DESC) msUlt
),
ProgressoObjetivo AS (
    SELECT 
        id_objetivo,
        AVG(progresso_kr) AS progresso_objetivo
    FROM ProgressoKR
    GROUP BY id_objetivo
)
SELECT 
    LOWER(o.pilar_bsc) AS pilar,
    COUNT(DISTINCT o.id_objetivo) AS qtd_objetivos,
    COUNT(kr.id_kr) AS qtd_krs,
    ISNULL(AVG(p.progresso_objetivo), 0) AS progresso_medio
FROM objetivos o
LEFT JOIN ProgressoObjetivo p ON o.id_objetivo = p.id_objetivo
LEFT JOIN key_results kr ON o.id_objetivo = kr.id_objetivo
GROUP BY o.pilar_bsc;
";

$stmtMapa = sqlsrv_query($connOKR, $sqlMapa);
$dadosPilares = [];
while ($row = sqlsrv_fetch_array($stmtMapa, SQLSRV_FETCH_ASSOC)) {
    $id = mb_strtolower(trim($row['pilar']));
    $dadosPilares[$id] = [
        'progresso'    => round($row['progresso_medio'], 1),
        'objetivos'    => intval($row['qtd_objetivos']),
        'krs'          => intval($row['qtd_krs']),
        'descricao'    => $pilares[$id]['descricao'] ?? ucfirst($id),
        'icone'        => $pilares[$id]['icone'] ?? 'bi-diagram-3',
        'cor'          => $pilares[$id]['cor'] ?? '#6c757d'
    ];
}
?>

<!-- ===================== HTML ========================= -->

<div class="content">
    <h2 class="mb-4 fw-bold text-primary">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </h2>

    <div class="row g-4">

        <!-- 🔥 Planejamento Estratégico -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-diagram-3 me-2"></i>Planejamento Estratégico BSC & OKRs
                </div>
                <div class="card-body">
                    <div class="row text-center g-4">
                        <?php foreach ($pilares as $id => $pilar): ?>
                            <?php if (isset($dadosPilares[$id])): 
                                $dados = $dadosPilares[$id];
                            ?>
                                <div class="col-md-3 col-sm-6">
                                    <a href="index.php?page=OKR_consulta" class="text-decoration-none">
                                        <div class="border rounded p-3 shadow-sm h-100 d-flex flex-column justify-content-between card-pilar"
                                            style="border-top: 4px solid <?= $dados['cor'] ?>;">
                                            <div>
                                                <i class="bi <?= $dados['icone'] ?> fs-2" style="color: <?= $dados['cor'] ?>;"></i>
                                                <h5 class="fw-bold mt-2 text-dark"><?= htmlspecialchars($dados['descricao']) ?></h5>
                                            </div>
                                            <div>
                                                <div class="progress rounded-pill mb-1" style="height: 14px;">
                                                    <div class="progress-bar" role="progressbar"
                                                        style="width: <?= $dados['progresso'] ?>%; background-color: <?= $dados['cor'] ?>;"
                                                        aria-valuenow="<?= $dados['progresso'] ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?= $dados['progresso'] ?>%
                                                    </div>
                                                </div>
                                                <small class="text-dark">
                                                    <?= $dados['objetivos'] ?> objetivos | <?= $dados['krs'] ?> KRs
                                                </small>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🔥 Card Forecast -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-building me-2"></i>Apontamentos de Forecast
                </div>
                <div class="card-body">
                    <?php
                        $now = new DateTime();
                        $deadline = new DateTime(date('Y-m-15 23:59:59'));
                        $countdownText = ($now > $deadline) ? "Prazo encerrado"
                            : $now->diff($deadline)->format('%d dias, %h horas e %i minutos');
                    ?>
                    <div class="mb-3">
                        <strong>Prazo para informar o forecast do próximo trimestre:</strong><br>
                        <small>Data/Hora limite: <?= $deadline->format('d/m/Y H:i'); ?></small><br>
                        <small>Faltam <?= $countdownText; ?> para fechar os apontamentos.</small>
                    </div>

                    <div class="mt-3">
                        <a href="index.php?page=apontar_forecast" class="btn btn-success me-2">
                            <i class="bi bi-graph-up"></i> Apontar Forecast
                        </a>
                        <a href="index.php?page=configuracoes" class="btn btn-outline-secondary">
                            <i class="bi bi-gear"></i> Configurações
                        </a>
                    </div>

                    <ul class="mt-4 list-group">
                        <strong>Lista de Regionais</strong>
                        <?php foreach ($regionais as $regional): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($regional['codigo']); ?></strong> — <?= htmlspecialchars($regional['nome']); ?>
                                </div>
                                <div>
                                    <span class="badge <?= ($regional['matriz_status'] == 'enviado' ? 'bg-success' : 'bg-danger'); ?>">
                                        Matriz
                                    </span>
                                    <span class="badge <?= ($regional['filial_status'] == 'enviado' ? 'bg-success' : 'bg-danger'); ?> ms-2">
                                        Filial
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 🔥 Card Usuários e Sell-Out -->
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white fw-bold">
                    <i class="bi bi-people-fill me-2"></i>Usuários Cadastrados
                </div>
                <div class="card-body">
                    <h3 class="fw-bold"><?= $totalUsuarios ?></h3>
                    <p>Total de usuários cadastrados no sistema.</p>
                    <a href="index.php?page=users" class="btn btn-outline-secondary">
                        <i class="bi bi-person-gear"></i> Gerenciar Usuários
                    </a>
                </div>
            </div>

            <div class="card mt-4 shadow-sm border-success">
                <div class="card-body">
                    <h5 class="fw-bold text-success">
                        <i class="bi bi-box-arrow-up"></i> Envio de Sell-Out Disponível!
                    </h5>
                    <p>Agora você pode importar seus dados diretamente no sistema de forma rápida e prática.</p>
                    <a href="index.php?page=enviar_sellout" class="btn btn-success">
                        <i class="bi bi-upload"></i> Enviar Sell-Out
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .card-pilar {
        cursor: pointer;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .card-pilar:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
        z-index: 5;
    }
</style>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
