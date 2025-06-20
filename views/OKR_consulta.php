<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('consultar_okr');

$pageTitle = 'Consulta de OKRs - Pilares e Objetivos';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

$dbOKR = new OKRDatabase();
$connOKR = $dbOKR->getConnection();
if (!$connOKR) {
    die("<div class='alert alert-danger'>Erro de conexão com o banco OKR.</div>");
}

$dbForecast = new Database();
$connForecast = $dbForecast->getConnection();
if (!$connForecast) {
    die("<div class='alert alert-danger'>Erro de conexão com banco ForecastDB.</div>");
}

$sqlUsers = "SELECT id, name FROM users WHERE status = 'ativo'";
$stmtUsers = sqlsrv_query($connForecast, $sqlUsers);
$usuarios = [];
if ($stmtUsers) {
    while ($rowUser = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
        $usuarios[$rowUser['id']] = $rowUser['name'];
    }
}

$pilares = [
    'Financeiro'                => ['icone' => 'bi-currency-dollar', 'cor' => '#B8860B'],
    'Clientes'                  => ['icone' => 'bi-people', 'cor' => '#006400'],
    'Processos Internos'        => ['icone' => 'bi-hammer', 'cor' => '#00008B'],
    'Aprendizado e Crescimento' => ['icone' => 'bi-mortarboard', 'cor' => '#FF1493']
];

function normalizaPilar($texto) {
    return ucfirst(mb_strtolower(trim($texto)));
}

$dadosPilares = [];
foreach ($pilares as $nome => $info) {
    $dadosPilares[$nome] = [];
}

// ✅ SQL Corrigido
$sql = "
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
    OUTER APPLY (
        SELECT TOP 1 * FROM milestones_kr 
        WHERE id_kr = kr.id_kr 
        ORDER BY num_ordem ASC
    ) msBase
    OUTER APPLY (
        SELECT TOP 1 * FROM milestones_kr 
        WHERE id_kr = kr.id_kr 
        ORDER BY num_ordem DESC
    ) msMeta
    OUTER APPLY (
        SELECT TOP 1 * FROM milestones_kr 
        WHERE id_kr = kr.id_kr AND valor_real IS NOT NULL
        ORDER BY num_ordem DESC
    ) msUlt
)

SELECT 
    obj.id_objetivo,
    obj.descricao,
    obj.pilar_bsc,
    obj.tipo,
    obj.dono,
    obj.status,
    obj.dt_prazo,
    obj.qualidade,

    (SELECT COUNT(*) FROM key_results kr WHERE kr.id_objetivo = obj.id_objetivo) AS qtd_kr,

    ISNULL((
        SELECT SUM(o.valor)
        FROM orcamentos o
        INNER JOIN iniciativas i ON o.id_iniciativa = i.id_iniciativa
        INNER JOIN key_results kr ON i.id_kr = kr.id_kr
        WHERE kr.id_objetivo = obj.id_objetivo
    ), 0) AS orcamento,

    0 AS orcamento_utilizado,

    ISNULL((
        SELECT AVG(p.progresso_kr) 
        FROM ProgressoKR p
        WHERE p.id_objetivo = obj.id_objetivo
    ), 0) AS progresso

FROM objetivos obj;
";

$stmt = sqlsrv_query($connOKR, $sql);
if ($stmt === false) {
    echo "<div class='alert alert-danger'>Erro na consulta de objetivos:<br><pre>";
    print_r(sqlsrv_errors());
    echo "</pre></div>";
    exit;
}

while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $pilar = normalizaPilar($row['pilar_bsc']);
    if (!array_key_exists($pilar, $dadosPilares)) {
        $dadosPilares[$pilar] = [];
    }

    $nomeDono = $usuarios[$row['dono']] ?? 'Desconhecido';

    $dadosPilares[$pilar][] = [
        'id' => $row['id_objetivo'],
        'nome' => $row['descricao'],
        'tipo' => ucfirst($row['tipo']),
        'dono' => $nomeDono,
        'status' => strtolower(str_replace(' ', '-', $row['status'])),
        'prazo' => $row['dt_prazo'],
        'qualidade' => ucfirst($row['qualidade']),
        'qtd_kr' => $row['qtd_kr'],
        'orcamento' => round($row['orcamento'], 2),
        'orcamento_utilizado' => round($row['orcamento_utilizado'], 2),
        'progresso' => round($row['progresso'], 1),
        'farol' => ($row['progresso'] >= 80) ? 'Alta' : (($row['progresso'] >= 50) ? 'Média' : 'Baixa')
    ];
}
?>



<div class="content">
    <div class="container my-4">
        <h1 class="mb-5 text-center text-primary fw-bold">
            <i class="bi bi-diagram-3 me-2"></i>Consulta de OKRs - Pilares e Objetivos
        </h1>

        <?php foreach ($dadosPilares as $pilar => $objetivos):
            $icone = $pilares[$pilar]['icone'] ?? 'bi-diagram-3';
            $cor = $pilares[$pilar]['cor'] ?? '#6c757d';
        ?>
        <section class="mb-5">
            <header class="d-flex align-items-center gap-2 mb-4 rounded p-3" style="background-color: <?= $cor ?>;">
                <i class="bi <?= $icone ?> fs-3 text-white"></i>
                <h2 class="m-0 fs-4 fw-semibold text-white"><?= htmlspecialchars($pilar) ?></h2>
            </header>

            <?php if (empty($objetivos)): ?>
                <div class="alert alert-light text-center">Nenhum objetivo cadastrado.</div>
            <?php else: ?>
                <div class="row g-4" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
                    <?php foreach ($objetivos as $obj):
                        $prazoFormatado = (!empty($obj['prazo']) && $obj['prazo'] instanceof DateTime)
                            ? $obj['prazo']->format('d/m/Y')
                            : (is_string($obj['prazo']) ? date('d/m/Y', strtotime($obj['prazo'])) : 'Sem Prazo');
                    ?>
                    <article tabindex="0" role="article" class="card objetivo-card p-3 shadow-sm <?= $obj['status'] ?>" aria-label="Objetivo <?= htmlspecialchars($obj['nome']) ?>">
                        <header class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge 
                                <?= $obj['tipo'] === 'Estratégico' ? 'bg-primary' : ($obj['tipo'] === 'Tático' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                <?= htmlspecialchars($obj['tipo']) ?>
                            </span>
                            <small class="text-muted fw-semibold">Prazo: <?= $prazoFormatado ?></small>
                        </header>

                        <h3 class="fs-5 fw-bold text-truncate" title="<?= htmlspecialchars($obj['nome']) ?>">
                            <a href="index.php?page=OKR_detalhe_objetivo&id=<?= urlencode($obj['id']) ?>" 
                            class="stretched-link text-decoration-none text-dark">
                                <?= htmlspecialchars($obj['nome']) ?>
                            </a>
                        </h3>

                        <div class="d-flex flex-column gap-2 mt-3 flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Dono</small>
                                <strong><?= htmlspecialchars($obj['dono']) ?></strong>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">Status</small>
                                <span class="badge
                                    <?= $obj['status'] === 'cancelado' ? 'bg-danger' : 'bg-info' ?>">
                                    <?= ucfirst(str_replace('-', ' ', $obj['status'])) ?>
                                </span>
                            </div>

                            <!-- 🔥 Barra de Progresso do Objetivo -->
                            <div>
                                <small class="text-muted">🚀 Progresso do Objetivo</small>
                                <div class="d-flex justify-content-between">
                                    <span>0%</span><span>100%</span>
                                </div>
                                <div class="progress rounded-pill" style="height: 18px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated
                                        <?= $obj['progresso'] >= 80 ? 'bg-success' : ($obj['progresso'] >= 50 ? 'bg-warning text-dark' : 'bg-danger') ?>"
                                        role="progressbar" style="width: <?= $obj['progresso'] ?>%"
                                        aria-valuenow="<?= $obj['progresso'] ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?= $obj['progresso'] ?>%
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Orçamento</small>
                                <strong>R$ <?= number_format($obj['orcamento'], 2, ',', '.') ?></strong>
                            </div>

                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Utilizado</small>
                                <strong>R$ <?= number_format($obj['orcamento_utilizado'], 2, ',', '.') ?></strong>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <small class="text-muted">Farol de Confiança</small>
                                <?php if ($obj['farol'] === 'Alta'): ?>
                                    <span class="badge bg-success px-3 py-2 fs-6 fw-semibold">Alta</span>
                                <?php elseif ($obj['farol'] === 'Média'): ?>
                                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 fw-semibold">Média</span>
                                <?php else: ?>
                                    <span class="badge bg-danger px-3 py-2 fs-6 fw-semibold">Baixa</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
    </div>
</div>

<style>
.objetivo-card {
    border: 1px solid #ddd;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 360px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}

.objetivo-card:hover,
.objetivo-card:focus-visible {
    transform: scale(1.04);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    outline: none;
    z-index: 10;
}

h1, h2, h3 {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.badge {
    font-size: 0.9rem;
    padding: 0.4em 0.8em;
    border-radius: 0.375rem;
}

.progress {
    background-color: #f0f0f0;
}

.progress-bar {
    font-weight: 600;
}

section > header {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    letter-spacing: 0.02em;
}

.alert-light {
    font-size: 1rem;
}

@media (max-width: 576px) {
    .objetivo-card {
        min-height: 380px;
        padding: 1rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
