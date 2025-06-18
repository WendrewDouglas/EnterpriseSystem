<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('nova_iniciativa');

$loggedUserId = $_SESSION['user_id'] ?? null;
if (!$loggedUserId) {
    die("<div class='alert alert-danger'>Usuário não autenticado.</div>");
}

// Conexões
$db = new Database();
$conn = $db->getConnection();
$dbOKR = new OKRDatabase();
$connOKR = $dbOKR->getConnection();

if (!$conn || !$connOKR) {
    die("<div class='alert alert-danger'>Erro de conexão com os bancos.</div>");
}

// 🔽 Função para dropdown
function fetchDropdownData($conn, $query, $idField, $textField) {
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt === false) return [];
    $data = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $data[] = ['id' => $row[$idField], 'text' => $row[$textField]];
    }
    return $data;
}

// 🔽 Dados dos dropdowns
$objetivos = fetchDropdownData($connOKR, "SELECT id_objetivo, descricao FROM objetivos", 'id_objetivo', 'descricao');
$users = fetchDropdownData($conn, "SELECT id, name FROM users WHERE status = 'ativo'", 'id', 'name');
$statusKR = fetchDropdownData($connOKR, "SELECT id_status, descricao_exibicao FROM dom_status_kr", 'id_status', 'descricao_exibicao');

// 🔔 Mensagens do sistema
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$mensagemSistema = '';
if (!empty($_SESSION['erro_iniciativa'])) {
    $mensagemSistema = "<div class='alert alert-danger mt-3'>" . $_SESSION['erro_iniciativa'] . "</div>";
    unset($_SESSION['erro_iniciativa']);
}
if (!empty($_SESSION['sucesso_iniciativa'])) {
    $mensagemSistema = "<div class='alert alert-success mt-3'>" . $_SESSION['sucesso_iniciativa'] . "</div>";
    unset($_SESSION['sucesso_iniciativa']);
}

// 🔥 Processamento do Formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 📦 Dados principais
    $id_objetivo = $_POST['id_objetivo'] ?? '';
    $id_kr = $_POST['id_kr'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $status = $_POST['status'] ?? 'nao iniciado';
    $dt_prazo = $_POST['dt_prazo'] ?? null;
    $id_user_responsavel = $_POST['id_user_responsavel'] ?? '';
    $envolvidos = $_POST['envolvidos'] ?? [];
    $observacoes = trim($_POST['observacoes'] ?? '');

    // 📦 Dados de orçamento
    $temOrcamento = isset($_POST['tem_orcamento']);
    $valorOrcamento = $_POST['valor_orcamento'] ?? null;
    $dataDesembolso = $_POST['data_desembolso'] ?? null;
    $justificativaOrcamento = trim($_POST['justificativa_orcamento'] ?? '');

    // 🏷️ Status financeiro inicial
    $statusFinanceiro = 'ativo'; // Pode ser: ativo, em execução, nao executado, etc.

    // 🛑 Validação dos campos obrigatórios
    if (!$id_objetivo || !$id_kr || !$descricao || !$id_user_responsavel) {
        $_SESSION['erro_iniciativa'] = 'Preencha todos os campos obrigatórios.';
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 🛑 Validação dos campos de orçamento
    if ($temOrcamento) {
        if (
            $valorOrcamento === '' || $valorOrcamento <= 0 ||
            !$dataDesembolso ||
            $justificativaOrcamento === ''
        ) {
            $_SESSION['erro_iniciativa'] = 'Para incluir orçamento, preencha Valor, Data de Desembolso e Justificativa corretamente.';
            $_SESSION['form_data'] = $_POST;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    // 🔧 Geração do ID da Iniciativa
    $sqlBusca = "SELECT COUNT(*) AS total FROM iniciativas WHERE id_kr = ?";
    $stmtBusca = sqlsrv_query($connOKR, $sqlBusca, [$id_kr]);
    $numIni = 1;
    if ($stmtBusca && $row = sqlsrv_fetch_array($stmtBusca, SQLSRV_FETCH_ASSOC)) {
        $numIni = (int)$row['total'] + 1;
    }
    $id_iniciativa = "$id_kr - INI" . str_pad($numIni, 4, '0', STR_PAD_LEFT);

    // 🔨 Insert Iniciativa
    $sql = "INSERT INTO iniciativas 
        (id_iniciativa, id_kr, num_iniciativa, descricao, status, id_user_responsavel, id_user_criador, dt_criacao, dt_prazo, observacoes)
        VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE(), ?, ?)";
    $params = [
        $id_iniciativa, $id_kr, $numIni, $descricao, $status, 
        $id_user_responsavel, $loggedUserId, $dt_prazo, $observacoes
    ];
    $stmt = sqlsrv_prepare($connOKR, $sql, $params);

    if (!$stmt || !sqlsrv_execute($stmt)) {
        $_SESSION['erro_iniciativa'] = 'Erro ao inserir iniciativa: ' . print_r(sqlsrv_errors(), true);
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // 🔗 Insert Envolvidos
    foreach ($envolvidos as $idUser) {
        $sqlEnv = "INSERT INTO iniciativas_envolvidos (id_iniciativa, id_user, dt_inclusao) VALUES (?, ?, GETDATE())";
        sqlsrv_query($connOKR, $sqlEnv, [$id_iniciativa, $idUser]);
    }

    // 💰 Insert Orçamento (se houver)
    if ($temOrcamento) {
        $sqlOrc = "INSERT INTO orcamentos 
            (id_iniciativa, valor, data_desembolso, status_aprovacao, id_user_criador, dt_criacao, justificativa_orcamento, status_financeiro) 
            VALUES (?, ?, ?, 'pendente', ?, GETDATE(), ?, ?)";
        $paramsOrc = [
            $id_iniciativa, $valorOrcamento, $dataDesembolso, $loggedUserId, $justificativaOrcamento, $statusFinanceiro
        ];
        $stmtOrc = sqlsrv_prepare($connOKR, $sqlOrc, $paramsOrc);
        if (!$stmtOrc || !sqlsrv_execute($stmtOrc)) {
            $_SESSION['erro_iniciativa'] = 'Erro ao inserir orçamento: ' . print_r(sqlsrv_errors(), true);
            $_SESSION['form_data'] = $_POST;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }

    $_SESSION['sucesso_iniciativa'] = 'Iniciativa criada com sucesso!';
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// 🔥 Cabeçalho
$pageTitle = 'Nova Iniciativa - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>




<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />



<div class="content">
    <div class="mb-5">
        <div class="card border-0 shadow-sm rounded-4" style="background: linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);">
            <div class="card-body">
                <h4 class="card-title fw-bold mb-3" style="color: #333;">
                    <i class="bi bi-lightbulb-fill me-2 text-warning"></i> Nova Iniciativa
                </h4>
                <p class="fs-6 mb-3">
                    🔧 <strong>Iniciativas</strong> são <span class="text-primary">ações práticas, projetos ou atividades</span> que você executa para atingir um Key Result.<br>
                    Elas ajudam a transformar <strong>estratégia em execução</strong>, com clareza, prazos e responsáveis.
                </p>

                <div class="border-start border-4 ps-3 mb-4" style="border-color: #0d6efd;">
                    <h5 class="fw-semibold mb-2"><i class="bi bi-clipboard-check text-primary me-2"></i> Como criar uma boa Iniciativa?</h5>
                    <ul class="mb-0">
                        <li>🔗 Vincule a um <strong>Objetivo</strong> e <strong>Key Result</strong>.</li>
                        <li>✍️ Descreva <span class="text-primary fw-semibold">claramente</span> o que será feito.</li>
                        <li>👤 Defina um <strong>responsável</strong> e, se desejar, adicione <strong>envolvidos</strong>.</li>
                        <li>🎯 Utilize para acompanhar e <strong>executar as entregas</strong> do seu KR.</li>
                    </ul>
                </div>

                <div class="border-start border-4 ps-3 mb-4" style="border-color: #198754;">
                    <h5 class="fw-semibold mb-2"><i class="bi bi-cash-stack text-success me-2"></i> E se envolver orçamento?</h5>
                    <p class="mb-2">
                        💰 Se a sua iniciativa exige um <strong>desembolso financeiro</strong>, ative a opção:
                        <span class="badge rounded-pill bg-success">Esta iniciativa envolve orçamento</span>
                    </p>
                    <ul class="mb-0">
                        <li>💸 Informe o <strong>valor</strong> estimado.</li>
                        <li>📅 Defina a <strong>data prevista</strong> de desembolso.</li>
                        <li>📝 Preencha uma <strong>justificativa</strong> clara e objetiva.</li>
                    </ul>
                    <p class="mt-2 mb-0">
                        🔒 <strong>Importante:</strong> Iniciativas com orçamento passam por um <span class="text-success fw-semibold">fluxo de aprovação financeira</span> antes da execução.
                    </p>
                </div>

                <div class="bg-light p-3 rounded-3 shadow-sm">
                    <p class="mb-1">
                        🚀 <strong>DICA DE OURO:</strong> Quanto mais claras suas iniciativas, mais previsível e eficiente será a execução do seu plano.
                    </p>
                    <p class="mb-0 text-muted fst-italic">
                        “A estratégia sem execução é apenas intenção.” — Peter Drucker
                    </p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="mb-4"><i class="bi bi-clipboard-plus me-2 fs-4"></i> Cadastrar Iniciativa</h2>
    <?php if (!empty($mensagemSistema)) echo $mensagemSistema; ?>

    <form action="" method="post" class="bg-light p-4 border rounded">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="id_objetivo" class="form-label">Objetivo</label>
                <select name="id_objetivo" id="id_objetivo" class="form-select select2" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($objetivos as $obj): ?>
                        <option value="<?= $obj['id'] ?>"><?= htmlspecialchars($obj['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="id_kr" class="form-label">Key Result</label>
                <select name="id_kr" id="id_kr" class="form-select select2" required>
                    <option value="">Selecione...</option>
                </select>
            </div>

            <div class="col-md-12">
                <label for="descricao" class="form-label">Descrição da Iniciativa</label>
                <textarea name="descricao" id="descricao" class="form-control" rows="3" required></textarea>
            </div>

            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select" required>
                    <?php foreach ($statusKR as $st): ?>
                        <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="dt_prazo" class="form-label">Prazo (Opcional)</label>
                <input type="date" name="dt_prazo" id="dt_prazo" class="form-control">
            </div>

            <div class="col-md-6">
                <label for="id_user_responsavel" class="form-label">Responsável</label>
                <select name="id_user_responsavel" id="id_user_responsavel" class="form-select select2" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="envolvidos" class="form-label">Envolvidos</label>
                <select name="envolvidos[]" id="envolvidos" class="form-select select2" multiple>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['text']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-12">
                <label for="observacoes" class="form-label">Observações</label>
                <textarea name="observacoes" id="observacoes" class="form-control" rows="2"></textarea>
            </div>

            <div class="col-md-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="tem_orcamento" name="tem_orcamento">
                    <label class="form-check-label" for="tem_orcamento">
                        Esta iniciativa envolve orçamento
                    </label>
                </div>
            </div>

            <div id="orcamento_fields" style="display: none;">
                <div class="col-md-4">
                    <label for="valor_orcamento" class="form-label">Valor do Orçamento (R$)</label>
                    <input type="number" step="0.01" name="valor_orcamento" id="valor_orcamento" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="data_desembolso" class="form-label">Data Desembolso</label>
                    <input type="date" name="data_desembolso" id="data_desembolso" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="justificativa_orcamento" class="form-label">Justificativa</label>
                    <textarea name="justificativa_orcamento" id="justificativa_orcamento" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-success px-4 py-2 mt-3">
                    <i class="bi bi-save me-1"></i> Salvar Iniciativa
                </button>
            </div>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2();

    $('#tem_orcamento').on('change', function() {
        if (this.checked) {
            $('#orcamento_fields').show();
        } else {
            $('#orcamento_fields').hide();
        }
    });

    $('#id_objetivo').on('change', function() {
        const idObjetivo = $(this).val();
        const $krSelect = $('#id_kr');

        $krSelect.html('<option value="">Carregando...</option>');

        if (idObjetivo) {
            $.ajax({
                url: '../views/OKR_get_krs_por_objetivo.php?id_objetivo=' + encodeURIComponent(idObjetivo),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (Array.isArray(response)) {
                        $krSelect.empty();
                        $krSelect.append('<option value="">Selecione...</option>');
                        response.forEach(function(kr) {
                            $krSelect.append('<option value="' + kr.id + '">' + kr.text + '</option>');
                        });
                    } else {
                        $krSelect.html('<option value="">Nenhum KR encontrado</option>');
                    }
                },
                error: function() {
                    $krSelect.html('<option value="">Erro ao carregar KRs</option>');
                }
            });
        } else {
            $krSelect.html('<option value="">Selecione o Objetivo primeiro</option>');
        }
    });
});
</script>

