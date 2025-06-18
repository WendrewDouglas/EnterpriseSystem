<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() == PHP_SESSION_NONE) session_start();
verificarPermissao('novo_kr');

// 🔗 Conexões
$db = new Database();
$conn = $db->getConnection();
$dbOKR = new OKRDatabase();
$connOKR = $dbOKR->getConnection();
if (!$conn || !$connOKR) die("<div class='alert alert-danger'>Erro de conexão com os bancos.</div>");

// 🧠 Dados da Sessão
$loggedUserId = $_SESSION['user_id'] ?? null;
if (!$loggedUserId) die("<div class='alert alert-danger'>Usuário não autenticado.</div>");

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$mensagemSistema = '';
if (!empty($_SESSION['erro_kr'])) {
    $mensagemSistema = "<div class='alert alert-danger mt-3'>" . $_SESSION['erro_kr'] . "</div>";
    unset($_SESSION['erro_kr']);
}
if (!empty($_SESSION['sucesso_kr'])) {
    $mensagemSistema = "<div class='alert alert-success mt-3'>" . $_SESSION['sucesso_kr'] . "</div>";
    unset($_SESSION['sucesso_kr']);
}

// 🔄 Função para Dropdowns
function fetchDropdownData($conn, $query, $idField, $textField) {
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) return [];
    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = ['id' => $row[$idField], 'text' => $row[$textField]];
    }
    return $data;
}

// 🔧 Função para gerar datas dos milestones
function gerarDatasMilestone($inicio, $fim, $frequencia) {
    $datas = [];
    $dataInicio = new DateTime($inicio);
    $dataFimObj = new DateTime($fim);

    while ($dataInicio <= $dataFimObj) {
        switch (strtolower($frequencia)) {
            case 'semanal':
                $periodoFim = (clone $dataInicio)->modify('next sunday');
                break;
            case 'quinzenal':
                $periodoFim = (clone $dataInicio)->modify('+13 days');
                break;
            case 'mensal':
                $periodoFim = (clone $dataInicio)->modify('last day of this month');
                break;
            case 'bimestral':
                $periodoFim = (clone $dataInicio)->modify('+1 month')->modify('last day of this month');
                break;
            case 'trimestral':
                $periodoFim = (clone $dataInicio)->modify('+2 months')->modify('last day of this month');
                break;
            case 'semestral':
                $periodoFim = (clone $dataInicio)->modify('+5 months')->modify('last day of this month');
                break;
            case 'anual':
                $periodoFim = (clone $dataInicio)->modify('last day of December');
                break;
            default:
                $periodoFim = (clone $dataInicio);
                break;
        }

        if ($periodoFim > $dataFimObj) $periodoFim = $dataFimObj;
        $datas[] = $periodoFim->format('Y-m-d');
        $dataInicio = (clone $periodoFim)->modify('+1 day');
    }

    return $datas;
}

// 🔧 Função para calcular valores dos milestones
function calcularValoresMilestone($baseline, $meta, $total, $direcao) {
    if (strtolower($direcao) === 'intervalo') {
        return array_fill(0, $total, $meta);
    }

    $valores = [];
    $step = ($meta - $baseline) / max(1, $total - 1);

    for ($i = 0; $i < $total; $i++) {
        $valores[] = round($baseline + $step * $i, 2);
    }
    return $valores;
}

// 🔍 Buscar Dados para os Dropdowns
$objetivos   = fetchDropdownData($connOKR, "SELECT id_objetivo, descricao FROM objetivos", 'id_objetivo', 'descricao');
$tiposKR     = fetchDropdownData($connOKR, "SELECT id_tipo, descricao_exibicao FROM dom_tipo_kr", 'id_tipo', 'descricao_exibicao');
$naturezasKR = fetchDropdownData($connOKR, "SELECT id_natureza, descricao_exibicao FROM dom_natureza_kr", 'id_natureza', 'descricao_exibicao');
$frequencias = fetchDropdownData($connOKR, "SELECT id_frequencia, descricao_exibicao FROM dom_tipo_frequencia_milestone", 'id_frequencia', 'descricao_exibicao');
$users       = fetchDropdownData($conn, "SELECT id, name FROM users WHERE status = 'ativo'", 'id', 'name');

// 🟩 Processamento do POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 🔗 Coletar Dados do Formulário
    $descricao       = trim($_POST['descricao'] ?? '');
    $id_objetivo     = $_POST['id_objetivo'] ?? '';
    $tipo_kr         = $_POST['tipo_kr'] ?? '';
    $natureza_kr     = $_POST['natureza_kr'] ?? '';
    $baseline        = (float)($_POST['baseline'] ?? 0);
    $meta            = (float)($_POST['meta'] ?? 0);
    $unidade         = $_POST['unidade_medida'] ?? '';
    $direcao         = $_POST['direcao_metrica'] ?? '';
    $frequencia      = $_POST['tipo_frequencia_milestone'] ?? '';
    $data_inicio     = trim($_POST['data_inicio'] ?? '') ?: date('Y-m-d');
    $data_fim        = $_POST['data_fim'] ?? '';
    $responsavel     = $_POST['responsavel'] ?? '';
    $qualidade       = $_POST['qualidade_kr'] ?? '';
    $margem_raw      = trim($_POST['margem_confianca'] ?? '');
    $margem_confianca = is_numeric($margem_raw) ? ((float)$margem_raw) / 100 : 0.05;
    $status          = strtolower(trim($_POST['status'] ?? 'nao iniciado'));
    $obs             = trim($_POST['observacao'] ?? '');

    $observacao = json_encode(!empty($obs) ? [[
        "origin" => "criador",
        "observation" => $obs,
        "date" => date('c')
    ]] : []);

    // 🛑 Validação de Status
    $validStatus = ['nao iniciado', 'em andamento', 'concluido', 'cancelado'];
    if (!in_array($status, $validStatus)) $status = 'nao iniciado';

    // 🚦 Validação de Campos Obrigatórios
    if (
        $descricao === '' || $id_objetivo === '' || $tipo_kr === '' ||
        $unidade === '' || $direcao === '' || $frequencia === '' ||
        $data_fim === '' || $responsavel === '' || $qualidade === '' ||
        $baseline === '' || $meta === ''
    ) {
        $_SESSION['erro_kr'] = 'Todos os campos obrigatórios devem ser preenchidos.';
        $_SESSION['form_data'] = $_POST;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 🔢 Gerar ID do KR
    $sqlBusca = "SELECT COUNT(*) AS total FROM key_results WHERE id_objetivo = ?";
    $stmtBusca = sqlsrv_query($connOKR, $sqlBusca, [$id_objetivo]);
    $numKR = 1;
    if ($stmtBusca && $row = sqlsrv_fetch_array($stmtBusca, SQLSRV_FETCH_ASSOC)) {
        $numKR = (int)$row['total'] + 1;
    }
    $id_kr = "$id_objetivo - KR" . str_pad($numKR, 4, '0', STR_PAD_LEFT);

    // 🔒 Iniciar Transação
    sqlsrv_begin_transaction($connOKR);

    try {
        // 📝 Inserir o KR
        $sqlKR = "INSERT INTO key_results (id_kr, id_objetivo, key_result_num, descricao, tipo_kr, natureza_kr, baseline, meta, unidade_medida, direcao_metrica, tipo_frequencia_milestone, data_inicio, data_fim, responsavel, margem_confianca, status, usuario_criador, dt_criacao, qualidade, observacoes)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $paramsKR = [
            $id_kr, $id_objetivo, $numKR, $descricao, $tipo_kr, $natureza_kr,
            $baseline, $meta, $unidade, $direcao, $frequencia, $data_inicio,
            $data_fim, $responsavel, $margem_confianca, $status, $loggedUserId,
            date('Y-m-d'), $qualidade, $observacao
        ];

        $stmtKR = sqlsrv_prepare($connOKR, $sqlKR, $paramsKR);
        if (!$stmtKR || !sqlsrv_execute($stmtKR)) {
            $error = sqlsrv_errors();
            throw new Exception('Erro ao inserir KR: ' . print_r($error, true));
        }

        // 🏗️ Gerar e Inserir Milestones
        $datas   = gerarDatasMilestone($data_inicio, $data_fim, $frequencia);
        $valores = calcularValoresMilestone($baseline, $meta, count($datas), $direcao);

        foreach ($datas as $i => $dataRef) {
            $sqlM = "INSERT INTO milestones_kr (id_kr, num_ordem, data_ref, valor_esperado, valor_real, diferenca_perc, gerado_automatico, editado_manual, status_aprovacao, bloqueado_para_edicao)
                     VALUES (?, ?, ?, ?, NULL, NULL, 1, 0, 'pendente', 0)";

            $paramsM = [$id_kr, $i + 1, $dataRef, $valores[$i]];

            $stmtM = sqlsrv_prepare($connOKR, $sqlM, $paramsM);
            if (!$stmtM || !sqlsrv_execute($stmtM)) {
                $error = sqlsrv_errors();
                throw new Exception('Erro ao inserir milestone: ' . print_r($error, true));
            }
        }

        sqlsrv_commit($connOKR);
        $_SESSION['sucesso_kr'] = 'Key Result e Milestones criados com sucesso!';
    } catch (Exception $e) {
        sqlsrv_rollback($connOKR);
        $_SESSION['erro_kr'] = 'Erro ao inserir: ' . $e->getMessage();
        $_SESSION['form_data'] = $_POST;
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// 🔥 Front-End continua normalmente
$pageTitle = 'Novo Key Result - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>


<!-- Estilos do Select2 -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

<div class="content">
    <h2 class="mb-4"><i class="bi bi-bar-chart-line me-2 fs-4"></i> Novo Key Result</h2>

    <?php if (!empty($mensagemSistema)) echo $mensagemSistema; ?>

    <!-- Seção de orientações -->
    <div class="mb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h4 class="card-title" style="color: rgba(0,0,0,0.7);">
                    <i class="bi bi-info-circle me-2"></i> Orientações para Criação de Key Result
                </h4>
                <ul>
                    <li>Defina uma métrica clara e mensurável.</li>
                    <li>Associe o KR a um objetivo estratégico existente.</li>
                    <li>Informe baseline, meta, unidade, direção e frequência.</li>
                </ul>
                <p class="card-text">
                    💡 <strong>"Avalie o que importa."</strong><br>
                    <small class="text-muted">- John Doerr</small>
                </p>
            </div>
        </div>
    </div>

    <?php $f = fn($k) => htmlspecialchars($formData[$k] ?? ''); ?>
    <form action="" method="post" class="bg-light p-4 border rounded">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="id_objetivo" class="form-label fw-semibold">Objetivo Associado</label>
                <select name="id_objetivo" id="id_objetivo" class="form-select select2" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($objetivos as $obj): ?>
                        <option value="<?= $obj['id'] ?>" <?= ($formData['id_objetivo'] ?? '') === $obj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($obj['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="descricao" class="form-label fw-semibold">Nome do Key Result</label>
                <input type="text" name="descricao" id="descricao" class="form-control" value="<?= $f('descricao') ?>" required>
            </div>

            <div class="col-md-6">
                <label for="tipo_kr" class="form-label fw-semibold">Tipo do KR</label>
                <select name="tipo_kr" id="tipo_kr" class="form-select select2" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($tiposKR as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>" <?= ($formData['tipo_kr'] ?? '') === $tipo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="natureza_kr" class="form-label fw-semibold">Natureza do KR</label>
                <select name="natureza_kr" id="natureza_kr" class="form-select select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($naturezasKR as $nat): ?>
                        <option value="<?= $nat['id'] ?>" <?= ($formData['natureza_kr'] ?? '') === $nat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($nat['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="baseline" class="form-label">Baseline</label>
                <input type="number" step="any" name="baseline" id="baseline" class="form-control" value="<?= $f('baseline') ?>" required>
            </div>

            <div class="col-md-3">
                <label for="meta" class="form-label">Meta</label>
                <input type="number" step="any" name="meta" id="meta" class="form-control" value="<?= $f('meta') ?>" required>
            </div>

            <div class="col-md-3">
                <label for="unidade_medida" class="form-label">Unidade</label>
                <input type="text" name="unidade_medida" id="unidade_medida" class="form-control" value="<?= $f('unidade_medida') ?>" required>
            </div>

            <div class="col-md-3">
                <label for="direcao_metrica" class="form-label">Direção da Métrica</label>
                <select name="direcao_metrica" id="direcao_metrica" class="form-select" required>
                    <option value="">Selecione...</option>
                    <option value="maior" <?= ($f('direcao_metrica') === 'maior') ? 'selected' : '' ?>>Maior é melhor</option>
                    <option value="menor" <?= ($f('direcao_metrica') === 'menor') ? 'selected' : '' ?>>Menor é melhor</option>
                    <option value="intervalo" <?= ($f('direcao_metrica') === 'intervalo') ? 'selected' : '' ?>>Intervalo ideal</option>
                </select>
            </div>

            <div class="col-md-6">
                <label for="tipo_frequencia_milestone" class="form-label">Frequência de Acompanhamento</label>
                <select name="tipo_frequencia_milestone" id="tipo_frequencia_milestone" class="form-select select2" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($frequencias as $freq): ?>
                        <option value="<?= $freq['id'] ?>" <?= ($formData['tipo_frequencia_milestone'] ?? '') === $freq['id'] ? 'selected' : '' ?>><?= htmlspecialchars($freq['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="data_inicio" class="form-label">Data Início</label>
                <input type="date" name="data_inicio" id="data_inicio" class="form-control"
                    value="<?= $f('data_inicio') ?: date('Y-m-d') ?>">
            </div>

            <div class="col-md-3">
                <label for="data_fim" class="form-label">Data Fim</label>
                <input type="date" name="data_fim" id="data_fim" class="form-control" value="<?= $f('data_fim') ?>" required>
            </div>

            <div class="col-md-6">
                <label for="responsavel" class="form-label">Responsável</label>
                <select name="responsavel" id="responsavel" class="form-select select2" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ($formData['responsavel'] ?? '') === $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="margem_confianca" class="form-label">Margem de Confiança (%)</label>
                <input type="number" step="0.01" name="margem_confianca" id="margem_confianca" class="form-control" value="<?= $f('margem_confianca') ?>">
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status Inicial</label>
            <select name="status" id="status" class="form-select">
                <option value="nao iniciado">Não Iniciado</option>
                <option value="em andamento">Em Andamento</option>
                <option value="concluido">Concluído</option>
                <option value="cancelado">Cancelado</option>
            </select>
            </div>

            <div class="col-md-6">
                <label for="qualidade_kr" class="form-label">Qualidade (IA)</label>
                <input type="text" name="qualidade_kr" id="qualidade_kr" class="form-control bg-light" value="<?= $f('qualidade_kr') ?>" readonly required>
            </div>

            <div class="col-md-12">
                <label for="observacao" class="form-label">Observações</label>
                <textarea name="observacao" id="observacao" class="form-control" rows="3"><?= $f('observacao') ?></textarea>
            </div>

            <div class="col-md-12">
                <button type="button" id="analisarKR" class="btn btn-outline-primary mt-3">
                    <i class="bi bi-robot me-1"></i> Analisar com o OKR Master
                </button>
                <div id="resultadoAnaliseKR" class="mt-3"></div>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-success px-4 py-2 mt-3">
                    <i class="bi bi-save me-1"></i> Salvar KR
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
document.querySelector("form").addEventListener("submit", function (e) {
    const form = e.target;
    const requiredFields = form.querySelectorAll("[required]");
    let valid = true;
    let firstInvalid = null;

    requiredFields.forEach(field => {
        const container = field.closest('.col-md-6, .col-md-3, .col-md-12, .mb-3');
        field.classList.remove('is-invalid');
        if (!field.value || field.value.trim() === '') {
            field.classList.add('is-invalid');
            valid = false;
            if (!firstInvalid) firstInvalid = field;
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.innerText = 'Campo obrigatório';
            if (!container.querySelector('.invalid-feedback')) {
                container.appendChild(feedback);
            }
        } else {
            const existing = container.querySelector('.invalid-feedback');
            if (existing) existing.remove();
        }
    });

    if (!valid) {
        e.preventDefault();
        if (firstInvalid) firstInvalid.focus();
    }
});

document.getElementById("analisarKR").addEventListener("click", async () => {
    const descricao = document.getElementById("descricao").value.trim();

    if (!descricao) {
        alert("Preencha o campo de descrição do KR antes de analisar.");
        return;
    }

    const botao = document.getElementById("analisarKR");
    const resultado = document.getElementById("resultadoAnaliseKR");
    const qualidadeInput = document.getElementById("qualidade_kr");

    botao.disabled = true;
    resultado.innerHTML = "<div class='text-muted'><i class='bi bi-chat-dots me-1'></i> Analisando com o OKR Master...</div>";

    try {
        const resposta = await fetch('../api/analise_key_result.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ descricao })
        });

        let data;
        try {
            data = await resposta.json();
        } catch (e) {
            resultado.innerHTML = "<div class='text-danger'>Erro ao interpretar resposta da IA.</div>";
            botao.disabled = false;
            return;
        }
        if (data.sucesso) {
            qualidadeInput.value = data.qualidade;
            resultado.innerHTML = `
                <div class="d-flex align-items-start gap-3">
                    <img src="../img/avatar1.png" width="50" height="50" class="rounded-circle" style="border: 1px solid #ccc;">
                    <div class="bg-white shadow-sm p-3 rounded border" style="max-width: 100%;">
                        <div class="fw-bold text-primary mb-1">OKR Master:</div>
                        ${data.resposta_html}
                    </div>
                </div>
            `;
        } else {
            resultado.innerHTML = `<div class="text-danger">Erro: ${data.mensagem}</div>`;
        }
    } catch (err) {
        resultado.innerHTML = `<div class="text-danger">Erro ao conectar com a IA.</div>`;
    } finally {
        botao.disabled = false;
    }
});

</script>
