<?php
// 🔗 Includes e verificações de sessão e permissões
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
verificarPermissao('visualizar_objetivo');

// 🔗 Conexões
$dbForecast   = new Database();
$connForecast = $dbForecast->getConnection();
$dbOKR        = new OKRDatabase();
$connOKR      = $dbOKR->getConnection();
if (!$connForecast || !$connOKR) {
    die("<div class='alert alert-danger'>Erro de conexão com os bancos.</div>");
}

// 📦 Identificador do Objetivo
$idObjetivo = $_GET['id'] ?? '';
if (empty($idObjetivo)) {
    die("<div class='alert alert-danger'>ID do Objetivo não informado.</div>");
}

// 🔍 Carregar usuários ativos
$sqlUsers   = "SELECT id, name FROM users WHERE status = 'ativo'";
$stmtUsers  = sqlsrv_query($connForecast, $sqlUsers);
$usuarios   = [];
while ($u = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
    $usuarios[$u['id']] = $u['name'];
}

// 🔍 Carregar Objetivo
$sqlObjetivo = "SELECT * FROM objetivos WHERE id_objetivo = ?";
$stmtObj     = sqlsrv_query($connOKR, $sqlObjetivo, [$idObjetivo]);
$objetivo    = sqlsrv_fetch_array($stmtObj, SQLSRV_FETCH_ASSOC);
if (!$objetivo) {
    die("<div class='alert alert-danger'>Objetivo não encontrado.</div>");
}

// 🔍 Carregar KRs
$sqlKRs   = "SELECT * FROM key_results WHERE id_objetivo = ?";
$stmtKRs  = sqlsrv_query($connOKR, $sqlKRs, [$idObjetivo]);

$krs              = [];
$milestonesPorKR  = [];
$iniciativasPorKR = [];
$custosPorIniciativa = [];
$progressoTotal   = 0;

while ($kr = sqlsrv_fetch_array($stmtKRs, SQLSRV_FETCH_ASSOC)) {

    // 🔧 Inicializa os campos para evitar erros caso não existam milestones
    $kr['progresso'] = 0;
    $kr['ms_atual'] = null;
    $kr['ultimo_valor'] = null;
    $kr['ultima_data'] = null;

    // 🔍 Buscar Milestones deste KR
    $sqlMS = <<<'SQL'
SELECT
    id_milestone,
    id_kr,
    num_ordem,
    FORMAT(data_ref,'dd/MM/yyyy') as data_ref,
    valor_esperado,
    valor_real,
    diferenca_perc,
    FORMAT(dt_evidencia,'dd/MM/yyyy') as dt_evidencia,
    FORMAT(dt_apontamento,'dd/MM/yyyy') as dt_apontamento
FROM milestones_kr
WHERE id_kr = ?
ORDER BY num_ordem
SQL;

    $stmtMS = sqlsrv_query($connOKR, $sqlMS, [$kr['id_kr']]);
    $listaMS = [];
    while ($ms = sqlsrv_fetch_array($stmtMS, SQLSRV_FETCH_ASSOC)) {
        $listaMS[] = [
            'id_milestone'   => $ms['id_milestone'],
            'num_ordem'      => $ms['num_ordem'],
            'data_ref'       => $ms['data_ref'],
            'valor_esperado' => isset($ms['valor_esperado']) ? floatval($ms['valor_esperado']) : null,
            'valor_real'     => isset($ms['valor_real'])    ? floatval($ms['valor_real'])    : null,
            'diferenca_perc' => isset($ms['diferenca_perc'])? floatval($ms['diferenca_perc']): null,
            'dt_evidencia'   => $ms['dt_evidencia'],
            'dt_apontamento' => $ms['dt_apontamento'],
        ];
    }

    // 🔥 Definir cor da barra de progresso
    $corBarraProgresso = '#6c757d'; // Cinza padrão

    // === BLOCO NOVO: Cálculo do Farol de Confiança ===
    $farol_confianca = '-';
    // Se no banco está como 0.10 (10%)
    $margem = isset($kr['margem_confianca']) ? floatval($kr['margem_confianca']) * 100 : null;
    $direcao = $kr['direcao_metrica'] ?? 'maior'; // maior, menor, intervalo

    $baseline = null;
    $meta = null;
    $ultimoMs = null;

    if (count($listaMS) > 0) {
        $baseline = $listaMS[0]['valor_esperado'];
        $meta     = $listaMS[count($listaMS) - 1]['valor_esperado'];

        // Último milestone com valor_real preenchido
        foreach (array_reverse($listaMS) as $ms) {
            if ($ms['valor_real'] !== null) {
                $ultimoMs = $ms;
                break;
            }
        }

        // Cor da barra progresso
        if ($ultimoMs) {
            $real = $ultimoMs['valor_real'];
            $esperado = $ultimoMs['valor_esperado'];

            if ($direcao === 'maior') {
                $corBarraProgresso = $real >= $esperado ? '#198754' : '#c94444';
            } elseif ($direcao === 'menor') {
                $corBarraProgresso = $real <= $esperado ? '#198754' : '#c94444';
            } elseif ($direcao === 'intervalo') {
                $corBarraProgresso = ($real >= $baseline && $real <= $meta) ? '#198754' : '#c94444';
            }

            // Cálculo do farol de confiança
            if ($margem !== null && $esperado != 0) {
                if ($direcao === 'maior') {
                    $diff = (($real - $esperado) / $esperado) * 100;
                    if ($diff <= -$margem) {
                        $farol_confianca = 'Péssimo';
                    } elseif ($diff < 0) {
                        $farol_confianca = 'Ruim';
                    } elseif ($diff <= $margem) {
                        $farol_confianca = 'Bom';
                    } else {
                        $farol_confianca = 'Ótimo';
                    }
                } elseif ($direcao === 'menor') {
                    $diff = (($esperado - $real) / $esperado) * 100;
                    if ($diff <= -$margem) {
                        $farol_confianca = 'Péssimo';
                    } elseif ($diff < 0) {
                        $farol_confianca = 'Ruim';
                    } elseif ($diff <= $margem) {
                        $farol_confianca = 'Bom';
                    } else {
                        $farol_confianca = 'Ótimo';
                    }
                } elseif ($direcao === 'intervalo') {
                    $limiteInf = min($baseline, $meta) - $margem * abs($baseline) / 100;
                    $limiteSup = max($baseline, $meta) + $margem * abs($meta) / 100;
                    if ($real < $limiteInf || $real > $limiteSup) {
                        $farol_confianca = 'Péssimo';
                    } elseif (
                        ($real < min($baseline, $meta)) ||
                        ($real > max($baseline, $meta))
                    ) {
                        $farol_confianca = 'Ruim';
                    } elseif (
                        ($real >= min($baseline, $meta)) &&
                        ($real <= max($baseline, $meta))
                    ) {
                        // Dentro do intervalo ideal
                        $distanciaInferior = abs($real - min($baseline, $meta));
                        $distanciaSuperior = abs($real - max($baseline, $meta));
                        $margemLimite = $margem * abs($meta - $baseline) / 100;
                        if ($distanciaInferior < $margemLimite || $distanciaSuperior < $margemLimite) {
                            $farol_confianca = 'Bom';
                        } else {
                            $farol_confianca = 'Ótimo';
                        }
                    }
                }
            }
        }
    }
    $kr['cor_progresso'] = $corBarraProgresso;
    $kr['farol_confianca'] = $farol_confianca;
    // === FIM DO BLOCO NOVO ===

    // === Definir cor do farol de confiança ===
    switch(mb_strtolower($farol_confianca, 'UTF-8')) {
        case 'péssimo':   $cor_farol_confianca = 'bg-black'; break;
        case 'ruim':      $cor_farol_confianca = 'bg-danger'; break;
        case 'moderado':  $cor_farol_confianca = 'bg-warning text-dark'; break;
        case 'bom':       $cor_farol_confianca = 'bg-success'; break;
        case 'ótimo':     $cor_farol_confianca = 'bg-purple'; break;
        default:          $cor_farol_confianca = 'bg-secondary'; break;
    }
    $kr['cor_farol_confianca'] = $cor_farol_confianca;


    // ↘ Cálculo de baseline, meta e progresso do KR
    if (count($listaMS) > 0) {
        $baseline = $listaMS[0]['valor_esperado'];
        $meta     = $listaMS[count($listaMS)-1]['valor_esperado'];
        $currentMs = null;
        foreach ($listaMS as $ms) {
            if ($ms['valor_real'] !== null) {
                $currentMs = $ms;
            }
        }
        if ($currentMs !== null && ($meta - $baseline) != 0) {
            $kr['progresso'] = round((($currentMs['valor_real'] - $baseline) / ($meta - $baseline)) * 100, 1);
            $kr['ms_atual']  = $currentMs['num_ordem'];
        } else {
            $kr['progresso'] = 0;
            $kr['ms_atual']  = null;
        }

        if ($currentMs !== null) {
            $kr['ultimo_valor'] = $currentMs['valor_real'];
            $kr['ultima_data']  = $currentMs['data_ref'];
        } else {
            $kr['ultimo_valor'] = null;
            $kr['ultima_data']  = null;
        }
    }

    // ↘ Campos adicionais vindos de key_results
    $kr['margem_confianca'] = isset($kr['margem_confianca']) ? floatval($kr['margem_confianca']) * 100 : null;
    $kr['tipo_shot']                 = $kr['tipo_kr'] ?? null;
    $kr['tipo_frequencia_milestone'] = $kr['tipo_frequencia_milestone'] ?? null;

    // ↘ Observações formatadas
    $rawObs = $kr['observacoes'] ?? '[]';
    $obsArray = json_decode($rawObs, true);
    if (is_array($obsArray) && count($obsArray) > 0) {
        $formattedObs = '';
        foreach ($obsArray as $o) {
            $date = isset($o['date']) ? date('d/m/Y', strtotime($o['date'])) : '-';
            if (isset($o['origin']) && $o['origin'] === 'criador') {
                $originName = $usuarios[$kr['usuario_criador']] ?? 'Desconhecido';
            } else {
                $originName = htmlspecialchars(ucfirst($o['origin'] ?? ''));
            }
            $text = htmlspecialchars($o['observation'] ?? '');
            $formattedObs .= "&bull; [{$date}] {$text} ({$originName})<br>";
        }
    } else {
        $formattedObs = '-';
    }
    $kr['observacoes'] = $formattedObs;

    $key = str_replace(['/', ' '], '_', $kr['id_kr']);
    $milestonesPorKR[$key] = $listaMS;

    // 🔍 Buscar Iniciativas e seus Orçamentos
    $sqlIni = "SELECT * FROM iniciativas WHERE id_kr = ? ORDER BY num_iniciativa";
    $stmtIni = sqlsrv_query($connOKR, $sqlIni, [$kr['id_kr']]);

    $listaIni = [];
    $orcamentoKR = 0;
    $realizadoKR = 0;

    while ($ini = sqlsrv_fetch_array($stmtIni, SQLSRV_FETCH_ASSOC)) {
        $idIniciativa = $ini['id_iniciativa'];

        // 🔍 Buscar orçamento da iniciativa
        $sqlOrc = "SELECT 
                        ISNULL(SUM(valor), 0) AS valor_orcado, 
                        ISNULL(SUM(valor_realizado), 0) AS valor_realizado
                   FROM orcamentos 
                   WHERE id_iniciativa = ?";
        $stmtOrc = sqlsrv_query($connOKR, $sqlOrc, [$idIniciativa]);
        $orcamento = sqlsrv_fetch_array($stmtOrc, SQLSRV_FETCH_ASSOC);

        $valorOrcado = floatval($orcamento['valor_orcado']);
        $valorRealizado = floatval($orcamento['valor_realizado']);

        // 🔍 Buscar custos detalhados
        $sqlCustos = "SELECT * FROM orcamento_custos_detalhados WHERE id_iniciativa = ?";
        $stmtCustos = sqlsrv_query($connOKR, $sqlCustos, [$idIniciativa]);
        $listaCustos = [];
        while ($ct = sqlsrv_fetch_array($stmtCustos, SQLSRV_FETCH_ASSOC)) {
            $listaCustos[] = [
                'id_custo'         => $ct['id_custo'],
                'id_orcamento'     => $ct['id_orcamento'],
                'id_iniciativa'    => $ct['id_iniciativa'],
                'data_desembolso'  => isset($ct['data_desembolso']) ? date_format($ct['data_desembolso'], 'd/m/Y') : '-',
                'valor'            => floatval($ct['valor']),
                'descricao'        => $ct['descricao'] ?? '',
                'caminho_evidencia'=> $ct['caminho_evidencia'] ?? '',
                'id_user_criador'  => $ct['id_user_criador'] ?? '',
                'dt_criacao'       => isset($ct['dt_criacao']) ? date_format($ct['dt_criacao'], 'd/m/Y') : '-'
            ];
        }

        // Soma para orçamento do KR
        $orcamentoKR += $valorOrcado;
        $realizadoKR += $valorRealizado;

        // Prazo
        $prazo = isset($ini['dt_prazo']) ? date_format($ini['dt_prazo'], 'Y-m-d') : null;
        $prazoFormatado = isset($ini['dt_prazo']) ? date_format($ini['dt_prazo'], 'd/m/Y') : '-';

        $listaIni[] = [
            'id_iniciativa' => $idIniciativa,
            'descricao'     => $ini['descricao'],
            'status'        => $ini['status'],
            'responsavel'   => $usuarios[$ini['id_user_responsavel']] ?? 'Desconhecido',
            'prazo'         => $prazoFormatado,
            'prazo_data'    => $prazo,
            'valor_orcado'  => $valorOrcado,
            'valor_realizado' => $valorRealizado
        ];

        // 🔗 Adiciona os custos detalhados na estrutura
        $custosPorIniciativa[$idIniciativa] = $listaCustos;
    }

    $iniciativasPorKR[$key] = $listaIni;

    // 🔢 Acrescenta orçamento no KR
    $kr['orcamento'] = $orcamentoKR;
    $kr['realizado'] = $realizadoKR;

    // ⏫ Soma progresso total para cálculo da média
    $progressoTotal += $kr['progresso'];

    $krs[] = $kr;
}



// 📊 Cálculo da média de progresso do Objetivo
$mediaProgresso = count($krs) > 0
    ? round($progressoTotal / count($krs), 1)
    : 0;

// 🧩 Preparar dados para o front-end
$jsMilestones = json_encode($milestonesPorKR, JSON_PRETTY_PRINT);

// 🔖 Título da página
$pageTitle = 'Detalhe do Objetivo - ' . htmlspecialchars($objetivo['descricao']);

// 🔗 Inclui header e sidebar
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>




<!-- 🔥 Cabeçalho da Página -->
<div class="content">
    <div class="container my-4">
        <h1 class="mb-4 fw-bold text-primary">
            <i class="bi bi-bullseye me-2"></i>Detalhe do Objetivo
        </h1>

        <!-- 🔥 Cabeçalho do Objetivo -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h3 class="fw-bold text-primary mb-2">🎯 <?= htmlspecialchars($objetivo['descricao']) ?></h3>
                <p class="text-muted">
                    🆔 <strong><?= htmlspecialchars($objetivo['id_objetivo']) ?></strong> |
                    🧭 Pilar: <span class="badge bg-dark"><?= ucfirst($objetivo['pilar_bsc']) ?></span> |
                    📊 Tipo: 
                    <span class="badge <?= $objetivo['tipo'] === 'estrategico' ? 'bg-primary' : ($objetivo['tipo'] === 'tatico' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                        <?= ucfirst($objetivo['tipo']) ?>
                    </span> |
                    🗂️ <strong><?= count($krs) ?> KRs</strong>
                </p>
                <div>
                    <label><strong>🚀 Progresso do Objetivo</strong></label>
                    <div class="d-flex justify-content-between">
                        <span>0%</span><span>100%</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 20px;">
                        <div class="progress-bar bg-success progress-bar-striped" style="width: <?= $mediaProgresso ?>%;">
                            <?= $mediaProgresso ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 📈 Lista de KRs -->
        <?php foreach ($krs as $kr): ?>
            <?php $krId = str_replace(['/', ' '], '_', $kr['id_kr']); ?>
        <div class="card mb-3 shadow-sm">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-<?= $kr['status'] === 'concluido' ? 'success' : 'secondary' ?>">
                            <?= htmlspecialchars($kr['status']) ?>
                        </span>
                        <strong class="ms-2"><?= htmlspecialchars($kr['descricao']) ?></strong>
                          <span class="badge <?= $kr['cor_farol_confianca'] ?> ms-2">
                              Farol Confiança: <?= htmlspecialchars($kr['farol_confianca'] ?? '-') ?>
                          </span>
                        <?php if (!empty($kr['ms_atual'])): ?>
                            <span class="ms-3 text-success">
                                ✓ Milestone Atual: 
                                <?= $kr['ms_atual'] ?> de <?= count($milestonesPorKR[$krId]) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#details-<?= $krId ?>">▼</button>
                </div>

                <!-- Barra de progresso do KR -->
                <div class="mt-3 px-3 pb-2 d-flex align-items-center gap-2">
                    <label class="mb-0 fw-semibold" style="min-width: 150px;">🚀 Progresso do KR:</label>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between" style="font-size: 0.75rem;">
                            <span>0%</span><span>100%</span>
                        </div>
                        <div class="progress rounded-pill" style="height: 14px;">
                            <div class="progress-bar progress-bar-striped" 
                                style="width: <?= $kr['progresso'] ?>%; background-color: <?= $kr['cor_progresso'] ?>;">
                                <?= $kr['progresso'] ?>%
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conteúdo detalhado -->
                <div class="collapse" id="details-<?= $krId ?>">
                    <div class="p-3 border-top">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <p>🧑‍💼 <strong>Dono:</strong> <?= htmlspecialchars($usuarios[$kr['responsavel']] ?? 'Desconhecido') ?></p>
                                <p>📅 <strong>Prazo:</strong> <?= isset($kr['data_fim']) ? date_format($kr['data_fim'], 'd/m/Y') : '-' ?></p>
                                <p>🕒 <strong>Último Check:</strong> <?= isset($kr['ultimo_valor']) ? htmlspecialchars($kr['ultimo_valor']) : '-' ?> em <?= isset($kr['ultima_data']) ? htmlspecialchars($kr['ultima_data']) : '-' ?></p>
                                <p>🛡️ <strong>Margem de Confiança:</strong> <?= isset($kr['margem_confianca']) ? htmlspecialchars($kr['margem_confianca']) . '%' : '-' ?></p>
                                <p>🚀 <strong>Tipo de Shot:</strong> <?= isset($kr['tipo_shot']) ? htmlspecialchars(ucfirst($kr['tipo_shot'])) : '-' ?></p>
                                <p>🔄 <strong>Frequência:</strong> <?= isset($kr['tipo_frequencia_milestone']) ? htmlspecialchars($kr['tipo_frequencia_milestone']) : '-' ?></p>
                                <p>💰 <strong>Orçamento do KR:</strong> 
                                    <?= $kr['orcamento'] > 0 ? 'R$ ' . number_format($kr['orcamento'], 2, ',', '.') : '-' ?> 
                                    (Utilizado: <?= $kr['realizado'] > 0 ? 'R$ ' . number_format($kr['realizado'], 2, ',', '.') : 'R$ 0,00' ?>)
                                </p>
                                <p>📝 <strong>Observações:</strong><br>
                                    <?= !empty($kr['observacoes']) ? $kr['observacoes'] : '-' ?>
                                </p>

                                <!-- Botões -->
                                <button class="btn btn-warning btn-sm fw-bold mt-2 px-3 shadow-sm"
                                    onclick="abrirModalApontamento(
                                        '<?= $krId ?>',
                                        '<?= $kr['id_kr'] ?>',
                                        <?= isset($kr['ms_atual']) ? $kr['ms_atual'] : 'null' ?>
                                    )">
                                    <i class="bi bi-graph-up-arrow me-1"></i> Apontar Progresso
                                </button>
                            </div>

                            <div class="col-md-6 d-flex">
                                <div id="chart-container-<?= $krId ?>" class="flex-fill d-flex" style="height:100%;">
                                    <canvas id="chart-<?= $krId ?>" class="flex-fill"></canvas>
                                </div>
                            </div>

                            <!-- Iniciativas -->
                            <?php if (!empty($iniciativasPorKR[$krId])): ?>
                                <div class="col-12 mt-3">
                                    <h6>📋 Iniciativas</h6>
                                    <ul class="list-group">
                                        <?php foreach ($iniciativasPorKR[$krId] as $ini): ?>
                                            <?php
                                                $prazo = $ini['prazo_data'];
                                                $hoje = date('Y-m-d');
                                                $diasRestantes = $prazo ? (strtotime($prazo) - strtotime($hoje)) / 86400 : null;

                                                $statusPrazo = '';
                                                if (in_array($ini['status'], ['concluido', 'cancelado'])) {
                                                    $statusPrazo = '';
                                                } elseif (!$prazo) {
                                                    $statusPrazo = '<span class="badge bg-secondary ms-2">Sem prazo</span>';
                                                } elseif ($diasRestantes < 0) {
                                                    $statusPrazo = '<span class="badge bg-danger ms-2">Em Atraso</span>';
                                                } elseif ($diasRestantes <= 7) {
                                                    $statusPrazo = '<span class="badge bg-warning text-dark ms-2">Próximo do Prazo</span>';
                                                } else {
                                                    $statusPrazo = '<span class="badge bg-success ms-2">Dentro do Prazo</span>';
                                                }
                                            ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong><?= htmlspecialchars($ini['descricao']) ?></strong><br>
                                                        <small>🧑‍💼 <?= htmlspecialchars($ini['responsavel']) ?> | 📅 <?= htmlspecialchars($ini['prazo']) ?><?= $statusPrazo ?></small>
                                                    </div>
                                                    <div class="text-end">
                                                        <select 
                                                            class="form-select form-select-sm"
                                                            onchange="alterarStatusIniciativa('<?= $ini['id_iniciativa'] ?>', this.value)">
                                                            <?php foreach (['nao iniciado', 'em andamento', 'concluido', 'cancelado'] as $statusOpt): ?>
                                                                <option value="<?= $statusOpt ?>" <?= $ini['status'] === $statusOpt ? 'selected' : '' ?>>
                                                                    <?= ucfirst($statusOpt) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="btn btn-info btn-sm fw-bold mt-2 px-3 shadow-sm"
                                                            onclick="abrirModalCustos('<?= $ini['id_iniciativa'] ?>', '<?= htmlspecialchars($ini['descricao']) ?>')">
                                                            <i class="bi bi-cash-coin me-1"></i> Apontar Despesas
                                                        </button>
                                                    </div>
                                                </div>

                                                <?php if ($ini['valor_orcado'] > 0): ?>
                                                    <div class="mt-2">
                                                        <small>💰 Orçamento: R$ <?= number_format($ini['valor_orcado'], 2, ',', '.') ?> | Utilizado: R$ <?= number_format($ini['valor_realizado'], 2, ',', '.') ?></small>
                                                        <div class="progress" style="height: 6px;">
                                                            <div class="progress-bar bg-info" role="progressbar"
                                                                style="width: <?= $ini['valor_orcado'] > 0 ? min(100, ($ini['valor_realizado'] / $ini['valor_orcado']) * 100) : 0 ?>%;">
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Apontamento de Progresso -->
<div class="modal fade" id="modalApontamento" tabindex="-1" aria-labelledby="modalApontamentoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalApontamentoLabel">📈 Apontamento de Progresso</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <h6>Milestones deste KR</h6>
        <div id="tabelaApontamento"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="btnSalvarApontamento">✔️ Enviar Atualização</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal de Custos -->
<div class="modal fade" id="modalCustos" tabindex="-1" aria-labelledby="modalCustosLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="modalCustosLabel">💰 Apontamento de Custos</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <h6 id="subTituloCustos"></h6>
        <div id="tabelaCustos"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" id="btnSalvarCustos">✔️ Registrar Custo</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Fechar</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// === Dados de milestones ===
const milestonesData = <?= $jsMilestones ?>;
const renderedCharts = {};

// === Renderização dos gráficos ===
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.collapse').forEach(collapse => {
    collapse.addEventListener('show.bs.collapse', function() {
      const krId = this.id.replace('details-', '');
      if (renderedCharts[krId]) return;

      const canvas = document.getElementById(`chart-${krId}`);
      const data = milestonesData[krId] || [];

      if (!data.length) {
        document.getElementById(`chart-container-${krId}`).innerHTML =
          `<div class="alert alert-warning mt-2">
            ⚠️ Não foi possível definir os milestones automaticamente. Aponte manualmente.
          </div>`;
        renderedCharts[krId] = true;
        return;
      }

      const labels = data.map(m => m.data_ref);
      const esperado = data.map(m => m.valor_esperado);
      const realizado = data.map(m => m.valor_real ?? null);

      // 🔍 Pegando baseline e meta
      const baseline = esperado[0];
      const meta = esperado[esperado.length - 1];

      // 🔍 Último milestone com valor_real
      const ultimoMsIndex = [...data].reverse().findIndex(m => m.valor_real !== null);
      const ultimoIndex = ultimoMsIndex !== -1 ? (data.length - 1 - ultimoMsIndex) : null;
      const ultimoMs = ultimoIndex !== null ? data[ultimoIndex] : null;

      // 🔍 Definindo direção (idealmente trazer do backend)
      const direcao = 'maior'; // 'maior', 'menor' ou 'intervalo'

      // 🔥 Definindo cor da linha com base no milestone específico
      let corLinha = 'gray'; // padrão sem dados

      if (ultimoMs) {
        const real = ultimoMs.valor_real;
        const esperadoMs = ultimoMs.valor_esperado;

        if (direcao === 'maior') {
          corLinha = real >= esperadoMs ? 'green' : 'red';
        } else if (direcao === 'menor') {
          corLinha = real <= esperadoMs ? 'green' : 'red';
        } else if (direcao === 'intervalo') {
          corLinha = (real >= baseline && real <= meta) ? 'green' : 'red';
        }
      }

    new Chart(canvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Projeção',
            data: esperado,
            tension: 0.3,
            pointRadius: 4,
            borderColor: '#6fa8dc',    // Azul claro
            backgroundColor: '#6fa8dc'
          },
          {
            label: 'Realizado',
            data: realizado,
            tension: 0.3,
            pointRadius: 4,
            borderColor: corLinha === 'green' ? '#82c291' :
                        corLinha === 'red'   ? '#d96b6b' :
                        '#bfbfbf', // cinza caso sem dados
            backgroundColor: corLinha === 'green' ? '#82c291' :
                            corLinha === 'red'   ? '#d96b6b' :
                            '#bfbfbf',
            spanGaps: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true } }
      }
    });

      renderedCharts[krId] = true;
    });
  });
});

// === Função para alteração de status da Iniciativa ===
function alterarStatusIniciativa(idIniciativa, novoStatus) {
  if (!confirm(`Deseja alterar o status da Iniciativa para "${novoStatus}"?`)) return;

  fetch('../views/OKR_alterar_status_iniciativa.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id_iniciativa: idIniciativa,
      novo_status: novoStatus
    })
  })
  .then(res => res.json())
  .then(resp => {
    if (resp.status === 'sucesso') {
      alert(`✔️ ${resp.mensagem}`);
      location.reload();
    } else {
      alert(`❌ ${resp.mensagem}`);
    }
  })
  .catch(err => {
    console.error('Erro na alteração:', err);
    alert('❌ Falha na comunicação com o servidor.');
  });
}

let krAtivo = '';
let idKrAtivo = '';

// === Abrir modal de apontamento ===
function abrirModalApontamento(krSanitizado, idKrOriginal, msAtual) {
  krAtivo = krSanitizado;
  idKrAtivo = idKrOriginal;
  const container = document.getElementById('tabelaApontamento');
  const milestones = milestonesData[krSanitizado] || [];

  if (!milestones.length) {
    container.innerHTML = `<div class="alert alert-warning">
      ⚠️ Este KR não possui milestones cadastradas. Crie milestones para poder gerar apontamento.
    </div>`;
  } else {
    let tabela = `<div class="table-responsive">
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th>Ordem</th><th>Data Ref</th><th>Valor Esperado</th><th>Apontamento</th>
            <th>Data Evidência</th><th>Observação</th><th>Diferença (%)</th><th>Anexo</th>
          </tr>
        </thead>
        <tbody>`;

    milestones.forEach((m, idx) => {
      const diffInit = (m.valor_real != null && m.valor_esperado != null)
        ? ((m.valor_real - m.valor_esperado) / m.valor_esperado * 100).toFixed(2) + '%'
        : '-';

      tabela += `<tr>
        <td>${m.num_ordem}</td>
        <td>${m.data_ref}</td>
        <td>${m.valor_esperado ?? '-'}</td>
        <td><input type="number" step="0.01" class="form-control form-control-sm" id="apont_${idx}" value="${m.valor_real ?? ''}"></td>
        <td><input type="date" class="form-control form-control-sm" id="dt_evid_${idx}"></td>
        <td><textarea class="form-control form-control-sm" rows="1" id="obs_${idx}">${m.observacao ?? ''}</textarea></td>
        <td id="diff_${idx}">${diffInit}</td>
        <td>
          <input type="file" class="form-control form-control-sm" id="file_${idx}" accept=".jpg,.jpeg,.png,.pdf,.xls,.xlsx,.doc,.docx,.ppt,.pptx">
          <input type="text" class="form-control form-control-sm mt-1" id="desc_${idx}" placeholder="Descrição (opcional)">
        </td>
      </tr>`;
    });

    tabela += `</tbody></table></div>`;
    container.innerHTML = tabela;

    milestones.forEach((m, idx) => {
      const dateInp = document.getElementById(`dt_evid_${idx}`);
      if (m.dt_evidencia) {
        const [d, mo, y] = m.dt_evidencia.split('/');
        dateInp.value = `${y}-${mo.padStart(2, '0')}-${d.padStart(2, '0')}`;
      }

      const inp = document.getElementById(`apont_${idx}`);
      const cell = document.getElementById(`diff_${idx}`);
      inp.addEventListener('input', () => {
        const v = parseFloat(inp.value);
        cell.innerText = (!isNaN(v) && m.valor_esperado != null)
          ? ((v - m.valor_esperado) / m.valor_esperado * 100).toFixed(2) + '%'
          : '-';
      });
    }
  )}

  document.getElementById('modalApontamentoLabel').innerText =
    `📈 Apontamento - ${idKrOriginal}`;
  document.querySelectorAll('#tabelaApontamento tbody tr').forEach(r => r.classList.remove('table-success'));
  if (msAtual !== null) {
    const row = Array.from(document.querySelectorAll('#tabelaApontamento tbody tr'))
      .find(r => parseInt(r.cells[0].innerText, 10) === msAtual);
    if (row) row.classList.add('table-success');
  }

  new bootstrap.Modal(document.getElementById('modalApontamento')).show();
}

// === Envio do apontamento ===
document.getElementById('btnSalvarApontamento').addEventListener('click', () => {
  const formData = new FormData();
  formData.append('id_kr', idKrAtivo);

  const registros = [];
  const milestones = milestonesData[krAtivo] || [];

  milestones.forEach((m, idx) => {
    const apont = document.getElementById(`apont_${idx}`).value;
    const dtEvid = document.getElementById(`dt_evid_${idx}`).value;
    const obsCampo = document.getElementById(`obs_${idx}`).value;

    if (apont !== '' && dtEvid) {
      const num = parseFloat(apont);
      const diffPerc = m.valor_esperado != null
        ? parseFloat(((num - m.valor_esperado) / m.valor_esperado * 100).toFixed(2))
        : null;

      registros.push({
        id_milestone: m.id_milestone,
        novo_valor: num,
        data_evidencia: dtEvid,
        observacao: obsCampo,
        diferenca_perc: diffPerc
      });

      const fileInput = document.getElementById(`file_${idx}`);
      const descInput = document.getElementById(`desc_${idx}`);
      if (fileInput.files.length > 0) {
        formData.append(`anexo_file_${m.id_milestone}`, fileInput.files[0]);
        formData.append(`anexo_desc_${m.id_milestone}`, descInput.value);
      }
    }
  });

  if (!registros.length) {
    return alert('⚠️ Preencha pelo menos um apontamento com valor e data.');
  }

  formData.append('registros', JSON.stringify(registros));

  fetch('../views/OKR_salvar_apontamento.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(text => {
    try {
      const d = JSON.parse(text);
      alert(d.status === 'sucesso'
        ? `✔️ ${d.mensagem}`
        : `❌ ${d.mensagem}`);
      if (d.status === 'sucesso') location.reload();
    } catch (e) {
      console.error('Falha ao parsear JSON:', e);
      alert('❌ Resposta inválida do servidor.');
    }
  })
  .catch(err => {
    console.error('Erro no fetch:', err);
    alert(`❌ Erro de rede ou servidor: ${err.message}`);
  });
});

// === Abrir Modal de Custos ===
function abrirModalCustos(idIniciativa, nomeIniciativa) {
  const container = document.getElementById('tabelaCustos');
  const subTitulo = document.getElementById('subTituloCustos');
  subTitulo.innerHTML = `Iniciativa: <strong>${nomeIniciativa}</strong>`;

  const custos = <?= json_encode($custosPorIniciativa) ?>;
  const dados = custos[idIniciativa] || [];

  let tabela = `<div class="table-responsive">
    <table class="table table-bordered table-sm tabela-custos">
      <thead>
        <tr>
          <th>Data</th>
          <th>Valor (R$)</th>
          <th>Descrição</th>
          <th>Evidência</th>
        </tr>
      </thead>
      <tbody>`;

  if (dados.length === 0) {
    tabela += `<tr><td colspan="4">Nenhum custo lançado.</td></tr>`;
  } else {
    dados.forEach(c => {
      tabela += `<tr>
        <td>${c.data_desembolso}</td>
        <td>R$ ${c.valor.toFixed(2)}</td>
        <td>${c.descricao}</td>
        <td>
          ${c.caminho_evidencia ? `<a href="${c.caminho_evidencia}" target="_blank">📎 Ver Anexo</a>` : '-'}
        </td>
      </tr>`;
    });
  }

  tabela += `
      <tr class="table-success">
        <td><input type="date" class="form-control form-control-sm" id="custo_data"></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm" id="custo_valor"></td>
        <td><input type="text" class="form-control form-control-sm" id="custo_desc" placeholder="Descrição"></td>
        <td>
          <input type="file" class="form-control form-control-sm" id="custo_file" accept=".jpg,.jpeg,.png,.pdf,.xls,.xlsx,.doc,.docx">
        </td>
      </tr>
    </tbody></table></div>`;

  container.innerHTML = tabela;

  // Armazena a iniciativa ativa para o botão salvar
  container.setAttribute('data-id-iniciativa', idIniciativa);

  // Abre o modal
  new bootstrap.Modal(document.getElementById('modalCustos')).show();
}

// === Salvar Custo ===
document.getElementById('btnSalvarCustos').addEventListener('click', () => {
  const container = document.getElementById('tabelaCustos');
  const idIniciativa = container.getAttribute('data-id-iniciativa');

  const data = document.getElementById('custo_data').value;
  const valor = parseFloat(document.getElementById('custo_valor').value);
  const descricao = document.getElementById('custo_desc').value;
  const file = document.getElementById('custo_file').files[0];

  if (!data || isNaN(valor)) {
    alert('⚠️ Preencha data e valor.');
    return;
  }

  const formData = new FormData();
  formData.append('id_iniciativa', idIniciativa);
  formData.append('data_desembolso', data);
  formData.append('valor', valor);
  formData.append('descricao', descricao);
  if (file) {
    formData.append('anexo', file);
  }

  fetch('../views/OKR_salvar_custo.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(resp => {
    if (resp.status === 'sucesso') {
      alert(`✔️ ${resp.mensagem}`);
      location.reload();
    } else {
      alert(`❌ ${resp.mensagem}`);
    }
  })
  .catch(err => {
    console.error('Erro no salvamento de custo:', err);
    alert('❌ Erro na comunicação com o servidor.');
  });
});

</script>

<style>

  .bg-purple {
    background-color: #4B0082 !important; /* Roxo */
    color: #fff !important;
}
.bg-black {
    background-color: #000 !important;   /* Preto */
    color: #fff !important;
}

/* === Layout e Tabela do Modal === */
#tabelaApontamento table {
  width: 100%;
  border-collapse: collapse;
}
#tabelaApontamento th,
#tabelaApontamento td {
  padding: 8px;
  text-align: center;
  vertical-align: middle;
  border: 1px solid #dee2e6;
}
#tabelaApontamento thead {
  background-color: #f8f9fa;
}
.table-success {
  background-color: #d4edda;
}

/* Modal */
#modalApontamento .modal-dialog.modal-xl {
  max-width: 90%;
}
#modalApontamento .modal-body {
  overflow-x: auto;
}

/* Inputs padrão */
input[type="number"] {
  width: 100px;
}
input[type="date"] {
  width: 140px;
}
textarea {
  width: 100%;
  resize: none;
}

/* Estilo específico para campo de anexo */
input[type="file"] {
  width: 100%;
}
input[type="text"].anexo-descricao {
  width: 100%;
  margin-top: 4px;
}

/* Gráfico responsivo */
[id^="chart-container-"] {
  flex: 1 1 0;
  display: flex;
  height: 100%;
  min-height: 0;
}
[id^="chart-container-"] canvas {
  flex: 1 1 0;
  width: 100% !important;
  height: 100% !important;
}

/* Collapse */
.collapse .card-body {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}
.collapse .card-body .col-md-6 {
  display: flex;
  flex-direction: column;
}

/* ===== Estilo adicional para controle de custos futuros (ajuste opcional) ===== */
.tabela-custos th,
.tabela-custos td {
  padding: 6px;
  text-align: center;
  vertical-align: middle;
  border: 1px solid #dee2e6;
}
.tabela-custos {
  width: 100%;
  border-collapse: collapse;
}
.tabela-custos thead {
  background-color: #f1f1f1;
}
.custo-realizado {
  background-color: #e8f7e4;
}
.custo-planejado {
  background-color: #fef9e7;
}
</style>




<?php include __DIR__ . '/../templates/footer.php'; ?>
