<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('editar_objetivo');

$loggedUserId = $_SESSION['user_id'] ?? null;
if (!$loggedUserId) {
    die("<div class='alert alert-danger'>Usuário não autenticado.</div>");
}

$idObjetivo = $_GET['id'] ?? '';
if (empty($idObjetivo)) {
    die("<div class='alert alert-danger'>ID do Objetivo não informado.</div>");
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

$sqlObjetivo = "SELECT * FROM objetivos WHERE id_objetivo = ?";
$stmtObj = sqlsrv_query($connOKR, $sqlObjetivo, [$idObjetivo]);
$objetivo = sqlsrv_fetch_array($stmtObj, SQLSRV_FETCH_ASSOC);

if (!$objetivo) {
    die("<div class='alert alert-danger'>Objetivo não encontrado.</div>");
}

// Processamento do POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_objetivo'] ?? '');
    $tipo = $_POST['tipo'] ?? '';
    $pilar = $_POST['pilar_bsc'] ?? '';
    $prazo = $_POST['dt_prazo'] ?? null;
    $resp = $_POST['id_responsavel'] ?? null;
    $obs = trim($_POST['observacoes'] ?? '');
    $qualidade = $_POST['qualidade_objetivo'] ?? '';

    $observacoes = json_encode(!empty($obs) ? [[
        "origin" => "criador",
        "observation" => $obs,
        "date" => date('c')
    ]] : []);

    if (empty($qualidade)) {
        $_SESSION['erro_objetivo'] = '
            <div class="alert alert-danger d-flex align-items-start" role="alert">
                <i class="bi bi-x-circle-fill me-2 fs-4"></i>
                <div><strong>O OBJETIVO NÃO FOI ATUALIZADO.</strong><br>Antes de salvar, realize a análise do objetivo com o OKR Master.</div>
            </div>';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($prazo < date('Y-m-d')) {
        $_SESSION['erro_objetivo'] = 'A data de prazo não pode ser anterior à data atual.';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    $sqlUpdate = "UPDATE objetivos 
                  SET descricao = ?, tipo = ?, pilar_bsc = ?, dono = ?, dt_prazo = ?, observacoes = ?, qualidade = ?, dt_ultima_atualizacao = GETDATE(), usuario_ult_alteracao = ? 
                  WHERE id_objetivo = ?";

    $params = [$nome, $tipo, $pilar, $resp, $prazo, $observacoes, $qualidade, $loggedUserId, $idObjetivo];

    $stmt = sqlsrv_prepare($connOKR, $sqlUpdate, $params);
    if (!$stmt || !sqlsrv_execute($stmt)) {
        $_SESSION['erro_objetivo'] = 'Erro ao atualizar: ' . print_r(sqlsrv_errors(), true);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $_SESSION['sucesso_objetivo'] = 'Objetivo atualizado com sucesso!';
        header("Location: OKR_detalhe_objetivo.php?id=" . urlencode($idObjetivo));
        exit;
    }
}

$pageTitle = 'Editar Objetivo';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-pencil-square me-2 fs-4"></i> Editar Objetivo</h2>

    <?php if (!empty($_SESSION['erro_objetivo'])): ?>
        <div><?= $_SESSION['erro_objetivo']; unset($_SESSION['erro_objetivo']); ?></div>
    <?php elseif (!empty($_SESSION['sucesso_objetivo'])): ?>
        <div class="alert alert-success"><?= $_SESSION['sucesso_objetivo']; unset($_SESSION['sucesso_objetivo']); ?></div>
    <?php endif; ?>

    <form method="post" class="row g-4 bg-white p-4 rounded shadow-sm border">

        <div class="row g-2">
            <div class="col-md-10">
                <div class="form-floating">
                    <input type="text" name="nome_objetivo" id="nome_objetivo" class="form-control" placeholder="Nome do Objetivo" value="<?= htmlspecialchars($objetivo['descricao']) ?>" required>
                    <label for="nome_objetivo">Nome do Objetivo</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-floating">
                    <input type="text" class="form-control bg-light" readonly value="<?= substr($idObjetivo, -4) ?>">
                    <label>ID</label>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Tipo de Objetivo</label>
            <select name="tipo" id="tipo" class="form-select select2" required>
                <option value=""></option>
                <?php foreach ($tiposObjetivo as $item): ?>
                    <option value="<?= $item['id'] ?>" <?= $objetivo['tipo'] === $item['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($item['text']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <div class="form-floating">
                <input type="text" name="qualidade_objetivo" id="qualidade_objetivo" class="form-control bg-light" placeholder="Qualidade da IA" value="<?= htmlspecialchars($objetivo['qualidade'] ?? '') ?>" readonly>
                <label for="qualidade_objetivo">Qualidade do Objetivo (IA) *</label>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Pilar BSC</label>
            <select name="pilar_bsc" id="pilar_bsc" class="form-select select2" required>
                <option value=""></option>
                <?php foreach ($pilaresBSC as $item): ?>
                    <option value="<?= $item['id'] ?>" <?= $objetivo['pilar_bsc'] === $item['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($item['text']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <div class="form-floating">
                <input type="date" name="dt_prazo" id="dt_prazo" class="form-control" value="<?= isset($objetivo['dt_prazo']) ? date_format($objetivo['dt_prazo'], 'Y-m-d') : '' ?>" required>
                <label for="dt_prazo">Prazo Final</label>
            </div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">Responsável</label>
            <select name="id_responsavel" id="id_responsavel" class="form-select select2" required>
                <option value=""></option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $objetivo['dono'] == $user['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['text']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <div class="form-floating">
                <textarea name="observacoes" id="observacoes" class="form-control" style="height: 100px;" placeholder="Observações adicionais"><?= htmlspecialchars($objetivo['observacoes']) ?></textarea>
                <label for="observacoes">Observações</label>
            </div>
        </div>

        <div class="col-12 text-end">
            <a href="OKR_detalhe_objetivo.php?id=<?= urlencode($idObjetivo) ?>" 
            class="btn btn-secondary px-4 py-2 shadow-sm me-2">
                <i class="bi bi-x-circle me-1"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                <i class="bi bi-save me-1"></i> Atualizar Objetivo
            </button>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('.select2').select2({
        placeholder: "Selecione...",
        allowClear: true,
        width: '100%'
    });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
