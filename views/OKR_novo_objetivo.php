<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';        // Banco principal (users)
require_once __DIR__ . '/../includes/db_connectionOKR.php';     // Banco OKR
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('novo_objetivo');

$loggedUserId = $_SESSION['user_id'] ?? null;
if (!$loggedUserId) {
    die("<div class='alert alert-danger'>Usuário não autenticado.</div>");
}

// Repopular dados do formulário, se necessário
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$mensagemSistema = '';
if (!empty($_SESSION['erro_objetivo'])) {
    $mensagemSistema = "<div class='alert alert-danger mt-3'>" . $_SESSION['erro_objetivo'] . "</div>";
    unset($_SESSION['erro_objetivo']);
}

if (!empty($_SESSION['sucesso_objetivo'])) {
    $mensagemSistema = "<div class='alert alert-success mt-3'>" . $_SESSION['sucesso_objetivo'] . "</div>";
    unset($_SESSION['sucesso_objetivo']);
}

$db = new Database();
$conn = $db->getConnection();
$dbOKR = new OKRDatabase();
$connOKR = $dbOKR->getConnection();

if (!$conn || !$connOKR) {
    die("<div class='alert alert-danger'>Erro de conexão com os bancos.</div>");
}

function fetchDropdownData($conn, $query, $idField, $textField, $label) {
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) {
        echo "<div class='alert alert-danger'>Erro ao buscar dados de $label: " . print_r(sqlsrv_errors(), true) . "</div>";
        return [];
    }

    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = ['id' => $row[$idField], 'text' => $row[$textField]];
    }
    return $data;
}

$tiposObjetivo = fetchDropdownData($connOKR, "SELECT id_tipo, descricao_exibicao FROM dom_tipo_objetivo", 'id_tipo', 'descricao_exibicao', 'Tipo de Objetivo');
$pilaresBSC    = fetchDropdownData($connOKR, "SELECT id_pilar, descricao_exibicao FROM dom_pilar_bsc", 'id_pilar', 'descricao_exibicao', 'Pilar BSC');
$users         = fetchDropdownData($conn, "SELECT id, name FROM users WHERE status = 'ativo'", 'id', 'name', 'Usuários Ativos');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_objetivo'] ?? '');
    $desc = $nome;
    $tipo = $_POST['tipo'] ?? '';
    $pilar = $_POST['pilar_bsc'] ?? '';
    $prazo = !empty($_POST['dt_prazo']) ? $_POST['dt_prazo'] : null;
    $resp = $_POST['id_responsavel'] ?? null;
    $obs = trim($_POST['observacoes'] ?? '');
    $ano = $_POST['ano_referencia'] ?? date('Y');

    $observacoes = json_encode(!empty($obs) ? [[
        "origin" => "criador",
        "observation" => $obs,
        "date" => date('c')
    ]] : []);

    if (empty($_POST['qualidade_objetivo'])) {
        $_SESSION['erro_objetivo'] = "
            <div class='alert alert-danger d-flex align-items-start' role='alert'>
                <i class='bi bi-x-circle-fill me-2 fs-4'></i>
                <div>
                    <strong>O OBJETIVO NÃO FOI CADASTRADO.</strong><br>
                    Antes de salvar, realize a análise do objetivo com o KR Master.
                </div>
            </div>
        ";
        $_SESSION['form_data'] = $_POST;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($prazo < date('Y-m-d')) {
        $_SESSION['erro_objetivo'] = 'A data de prazo não pode ser anterior à data atual.';
        $_SESSION['form_data'] = $_POST;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $sqlBusca = "SELECT MAX(CAST(LEFT(id_objetivo, 3) AS INT)) AS ultimo FROM objetivos WHERE RIGHT(id_objetivo, 4) = ?";
    $stmtBusca = sqlsrv_query($connOKR, $sqlBusca, [$ano]);
    $ultimo = 0;
    if ($stmtBusca && $row = sqlsrv_fetch_array($stmtBusca, SQLSRV_FETCH_ASSOC)) {
        $ultimo = (int)$row['ultimo'];
    }
    $proximoNumero = str_pad($ultimo + 1, 3, '0', STR_PAD_LEFT);
    $id_objetivo = "$proximoNumero/$ano";

    $sql = "INSERT INTO objetivos (id_objetivo, descricao, tipo, pilar_bsc, dono, usuario_criador, status, status_aprovacao, dt_criacao, dt_prazo, observacoes, qualidade)
            VALUES (?, ?, ?, ?, ?, ?, 'nao iniciado', 'pendente', ?, ?, ?, ?)";
    $params = [$id_objetivo, $desc, $tipo, $pilar, $resp, $loggedUserId, date('Y-m-d'), $prazo, $observacoes, $_POST['qualidade_objetivo'] ?? ''];

    $stmt = sqlsrv_prepare($connOKR, $sql, $params);
    if (!$stmt || !sqlsrv_execute($stmt)) {
        $_SESSION['erro_objetivo'] = 'Erro ao inserir: ' . print_r(sqlsrv_errors(), true);
        $_SESSION['form_data'] = $_POST;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $_SESSION['sucesso_objetivo'] = 'Objetivo criado com sucesso!';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

$pageTitle = 'Gestores Comerciais - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

?>


<!-- Estilos -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
.okr-master-feedback {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-top: 10px;
    max-width: 100%;
}

.okr-avatar-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 60px; /* Define a largura total do bloco */
    text-align: center;
    flex-shrink: 0;
}

.okr-avatar {
    width: 50px; /* Aumentado levemente */
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 4px;
}

.okr-avatar-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 500;
}

.speech-bubble {
    position: relative;
    background: #f0f0f0;
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 0.95rem;
    color: #333;
    max-width: 100%;
    line-height: 1.4;
}

.speech-bubble::before {
    content: "";
    position: absolute;
    top: 16px;
    left: -8px;
    width: 0;
    height: 0;
    border-top: 8px solid transparent;
    border-bottom: 8px solid transparent;
    border-right: 8px solid #f0f0f0;
}
</style>

<div class="content">
    <!-- Título da página com ícone -->
    <h2 class="mb-4"><i class="bi bi-clipboard-check me-2 fs-4"></i> Novo Objetivo</h2>
    <?php if (!empty($mensagemSistema)): ?>
    <div class="col-12">
        <?= $mensagemSistema ?>
    </div>
<?php endif; ?>
    <!-- Nova Seção: Orientações para Criação de Objetivo Estratégico -->
    <div class="mb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
            <h4 class="card-title" style="color:rgb(124, 124, 124);">
    <i class="bi bi-info-circle me-2"></i> Orientações para Criação de Objetivo Estratégico
</h4>
                <p class="card-text">
                    📌 Antes de definir seu objetivo, considere os seguintes pontos:
                </p>
                <ul>
                    <li>Defina uma meta clara e mensurável.</li>
                    <li>Conecte o objetivo aos pilares do Balanced Scorecard.</li>
                    <li>Estabeleça indicadores (Key Results) que permitam monitorar o progresso.</li>
                    <li>Planeje iniciativas que suportem a execução do objetivo.</li>
                </ul>
                <p class="card-text">
                    💡 <strong>"Um objetivo sem um plano é apenas um desejo – OKRs transformam desejos em resultados mensuráveis."</strong>
                    <br>
                    <small class="text-muted">- John Doerr (Criador da metodologia OKR)</small>
                </p>
            </div>
        </div>
    </div>


    <!-- Seção Ilustrativa: Missão e Visão -->
    <div class="mb-5">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title text-primary"><i class="bi bi-eye me-2"></i>Visão</h4>
                        <p class="card-text">
                            Ser referência em custo-benefício no segmento em que atua, estando presente na maior parte dos lares brasileiros, buscando a expansão no mercado internacional.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-end">
                        <small class="text-muted">Nossa visão para o futuro</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title text-success"><i class="bi bi-flag me-2"></i>Missão</h4>
                        <p class="card-text">
                            Facilitar a vida das pessoas, oferecendo produtos acessíveis, com qualidade e sustentabilidade.
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 text-end">
                        <small class="text-muted">Nossa missão diária</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="container-fluid px-4 mt-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4 text-dark">
                <i class="bi bi-clipboard-check me-2 fs-3 text-success"></i> Criar Novo Objetivo
            </h2>

    <form method="post" class="row g-4 bg-white p-4 rounded shadow-sm border">

<!-- Nome do Objetivo e Ano de Referência lado a lado -->
<div class="row g-2">
    <div class="col-md-10">
        <div class="form-floating">
            <input type="text" name="nome_objetivo" id="nome_objetivo" class="form-control" placeholder="Nome do Objetivo" value="<?= htmlspecialchars($formData['nome_objetivo'] ?? '') ?>" required>
            <label for="nome_objetivo">Nome do Objetivo</label>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-floating">
            <input type="number" name="ano_referencia" id="ano_referencia" class="form-control" placeholder="Ano de Referência" value="<?= htmlspecialchars($formData['ano_referencia'] ?? date('Y')) ?>" required>
            <label for="ano_referencia">Ano Referência</label>
        </div>
    </div>
</div>

<div class="row g-2 align-items-end">
    <div class="col-md-8">
        <button type="button" id="analisarNomeIA" class="btn btn-outline-primary btn-sm mt-2">
            <i class="bi bi-robot me-1"></i> Analise com o OKR Master
        </button>
        <div id="resultadoAnaliseNome" class="alert alert-secondary d-none mt-2" role="alert">
            <div class="text-muted"><i class="bi bi-hourglass-split me-1"></i> Aguarde, analisando nome com IA...</div>
        </div>
    </div>
</div>


<div class="col-md-6">
    <div class="mb-3">
        <label for="tipo" class="form-label fw-semibold" value="<?= htmlspecialchars($formData['tipo'] ?? '') ?>" >Tipo de Objetivo</label>
        <select name="tipo" id="tipo" class="form-select select2" required>
            <option value=""></option>
            <?php foreach ($tiposObjetivo as $item): ?>
                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['text']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="col-md-6">
    <div class="form-floating">
        <input type="text" name="qualidade_objetivo" id="qualidade_objetivo" class="form-control bg-light" placeholder="Qualidade da IA" readonly>
        <label for="qualidade_objetivo">Qualidade do Objetivo (IA) *</label>
    </div>
</div>

<div class="col-md-6">
    <div class="mb-3">
        <label for="pilar_bsc" class="form-label fw-semibold" value="<?= htmlspecialchars($formData['pilar_bsc'] ?? '') ?>" >Pilar BSC</label>
        <select name="pilar_bsc" id="pilar_bsc" class="form-select select2" required>
            <option value=""></option>
            <?php foreach ($pilaresBSC as $item): ?>
                <option value="<?= $item['id'] ?>"><?= htmlspecialchars($item['text']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

    <div class="col-md-6">
        <div class="form-floating">
            <input type="date" name="dt_prazo" id="dt_prazo" class="form-control" 
                placeholder="Prazo Final"
                value="<?= htmlspecialchars($formData['dt_prazo'] ?? '') ?>"
                min="<?= date('Y-m-d') ?>" required>
            <label for="dt_prazo">Prazo Final</label>
        </div>
    </div>

<div class="col-md-6">
    <div class="mb-3">
        <label for="id_responsavel" class="form-label fw-semibold" value="<?= htmlspecialchars($formData['id_responsavel'] ?? '') ?>" >Responsável</label>
        <select name="id_responsavel" id="id_responsavel" class="form-select select2" required>
            <option value=""></option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['text']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

    <div class="col-md-6">
        <div class="form-floating">
            <textarea name="observacoes" id="observacoes" class="form-control" style="height: 100px;" placeholder="Observações adicionais" value="<?= htmlspecialchars($formData['observacoes'] ?? '') ?>" ></textarea>
            <label for="observacoes">Observações</label>
        </div>
    </div>

    <div class="col-12 text-end">
        <button type="submit" class="btn btn-success px-4 py-2 shadow-sm">
            <i class="bi bi-save me-1"></i>Salvar Objetivo
        </button>
    </div>
</form>


</div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('.select2').select2({
        placeholder: "Selecione...",
        allowClear: true,
        width: '100%'
    });

    $('#analisarNomeIA').click(function () {
        const nome = $('#nome_objetivo').val().trim();
        const resultado = $('#resultadoAnaliseNome');

        if (!nome) {
            resultado.removeClass('d-none').html(`
                <div class="okr-master-feedback">
                    <div class="okr-avatar-container">
                        <img src="../img/avatar1.png" class="okr-avatar" alt="OKR Master">
                        <div class="okr-avatar-label">OKR Master</div>
                    </div>
                    <div class="speech-bubble text-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i> Por favor, preencha o nome do objetivo antes de analisar.
                    </div>
                </div>
            `);
            return;
        }

        resultado.removeClass('d-none').html(`
            <div class="okr-master-feedback">
                <div class="okr-avatar-container">
                    <img src="../img/avatar1.png" class="okr-avatar" alt="OKR Master">
                    <div class="okr-avatar-label">OKR Master</div>
                </div>
                <div class="speech-bubble text-muted">
                    <i class="bi bi-hourglass-split me-1"></i> Aguarde, analisando nome com IA...
                </div>
            </div>
        `);
// Simulando a análise com IA
        fetch('../api/analise_okr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ nome: nome })
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.resposta) {
                resultado.html(`
                    <div class="okr-master-feedback">
                        <div class="okr-avatar-container">
                            <img src="../img/avatar1.png" class="okr-avatar" alt="OKR Master">
                            <div class="okr-avatar-label">OKR Master</div>
                        </div>
                        <div class="speech-bubble">
                            <i class="bi bi-chat-left-text me-1"></i> ${data.resposta}
                        </div>
                    </div>
                `);
                    // Preencher o campo qualidade automaticamente
                    $('#qualidade_objetivo').val(data.qualidade || '');
                } else {                
                    resultado.html(`
                    <div class="okr-master-feedback">
                        <div class="okr-avatar-container">
                            <img src="../img/avatar1.png" class="okr-avatar" alt="OKR Master">
                            <div class="okr-avatar-label">OKR Master</div>
                        </div>
                        <div class="speech-bubble text-danger">
                            <i class="bi bi-x-circle me-1"></i> A IA não retornou nenhuma resposta.
                        </div>
                    </div>
                `);                
            }
        })
        .catch(() => {
            resultado.html(`
                <div class="okr-master-feedback">
                    <div class="okr-avatar-container">
                        <img src="../img/avatar1.png" class="okr-avatar" alt="OKR Master">
                        <div class="okr-avatar-label">OKR Master</div>
                    </div>
                    <div class="speech-bubble text-danger">
                        <i class="bi bi-x-circle me-1"></i> Erro ao comunicar com a IA.
                    </div>
                </div>
            `);
        });
    });
});
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
