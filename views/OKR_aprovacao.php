<?php
// view/OKR_aprovacao.php

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('aprovacao_OKR');

$loggedUserId = $_SESSION['user_id'];
$pageTitle    = 'Aprovação OKR & Orçamentos';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Conecta aos bancos
$connOKR      = (new OKRDatabase())->getConnection();
$connForecast = (new Database())->getConnection();
if (!$connOKR || !$connForecast) {
    die("<div class='alert alert-danger'>Erro de conexão com os bancos.</div>");
}

// Usuários ativos com nome e imagem (base64)
$sqlUsers  = "SELECT id, name, imagem FROM users WHERE status = 'ativo'";
$stmtUsers = sqlsrv_query($connForecast, $sqlUsers);
if ($stmtUsers === false) {
    die("<div class='alert alert-danger'>Erro ao buscar usuários: " . print_r(sqlsrv_errors(), true) . "</div>");
}
$users = [];
while ($row = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
    $users[$row['id']] = [
        'name'  => $row['name'],
        'image' => $row['imagem']
    ];
}

// Objetivos pendentes
$sqlObjectives  = "SELECT * FROM objetivos WHERE status_aprovacao = 'pendente'";
$stmtObjectives = sqlsrv_query($connOKR, $sqlObjectives);
$objectives = [];
while ($row = sqlsrv_fetch_array($stmtObjectives, SQLSRV_FETCH_ASSOC)) {
    $objectives[] = $row;
}

// KRs pendentes
$sqlKRs = "SELECT * FROM key_results WHERE status_aprovacao = 'pendente'";
$stmtKRs = sqlsrv_query($connOKR, $sqlKRs);
$KRs = [];
while ($row = sqlsrv_fetch_array($stmtKRs, SQLSRV_FETCH_ASSOC)) {
    $KRs[] = $row;
}

// Iniciativas pendentes (para orçamentos)
$sqlInits = "
  SELECT id_iniciativa,
         id_kr,
         descricao,
         id_user_responsavel
    FROM iniciativas
   WHERE status_aprovacao = 'pendente'";
$stmtInits = sqlsrv_query($connOKR, $sqlInits);
$inits = [];
while ($row = sqlsrv_fetch_array($stmtInits, SQLSRV_FETCH_ASSOC)) {
    $inits[$row['id_iniciativa']] = [
        'descricao' => $row['descricao'],
        'id_kr'      => $row['id_kr']
    ];
}

// Orçamentos pendentes
$sqlBudgets = "
  SELECT 
    o.id_orcamento,
    i.descricao,
    o.valor,
    o.data_desembolso,
    kr.responsavel AS respUid,
    o.justificativa_orcamento
  FROM orcamentos o
  JOIN iniciativas i
    ON o.id_iniciativa = i.id_iniciativa
  JOIN key_results kr
    ON i.id_kr = kr.id_kr
  WHERE o.status_aprovacao = 'pendente'
";
$stmtBudgets = sqlsrv_query($connOKR, $sqlBudgets);
if ($stmtBudgets === false) {
    die("Erro ao buscar orçamentos: " . print_r(sqlsrv_errors(), true));
}
$budgets = [];
while ($row = sqlsrv_fetch_array($stmtBudgets, SQLSRV_FETCH_ASSOC)) {
    $budgets[] = $row;
}
?>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-check2-square me-2 fs-4"></i> Aprovação OKR & Orçamentos</h2>

    <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash']) ?></div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <!-- Objetivos -->
    <div class="card bg-white p-4 rounded shadow-sm mb-4">
        <h3 class="mb-3 text-primary">Objetivos Pendentes</h3>
        <?php if (count($objectives)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th style="width:30%;">Nome</th>
                            <th>Pilar</th>
                            <th>Responsável</th>
                            <th>Data Criação</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($objectives as $o): ?>
                            <tr>
                                <td><?= $o['id_objetivo'] ?></td>
                                <td><?= htmlspecialchars($o['descricao']) ?></td>
                                <td><?= htmlspecialchars($o['pilar_bsc']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($users[$o['dono']]['image'])): ?>
                                            <img src="data:image/png;base64,<?= $users[$o['dono']]['image'] ?>" class="rounded-circle me-2" width="40" height="40">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-2 text-secondary me-2"></i>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($users[$o['dono']]['name'] ?? 'N/D') ?></span>
                                    </div>
                                </td>
                                <td><?= date_format($o['dt_criacao'], 'd/m/Y') ?></td>
                                <td class="text-center">
                                    <button class="btn btn-success btn-sm me-2" onclick="openApprovalModal('objective','<?= $o['id_objetivo'] ?>','aprovar')">Aprovar</button>
                                    <button class="btn btn-danger btn-sm" onclick="openApprovalModal('objective','<?= $o['id_objetivo'] ?>','rejeitar')">Rejeitar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">✅ Nenhum objetivo pendente.</p>
        <?php endif; ?>
    </div>

    <!-- KRs -->
    <div class="card bg-white p-4 rounded shadow-sm mb-4">
        <h3 class="mb-3 text-primary">Key Results Pendentes</h3>
        <?php if (count($KRs)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Data Criação</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($KRs as $kr): ?>
                            <tr>
                                <td><?= $kr['id_kr'] ?></td>
                                <td><?= htmlspecialchars($kr['descricao']) ?></td>
                                <td><?= htmlspecialchars($kr['tipo_kr']) ?></td>
                                <td><?= date_format($kr['dt_criacao'], 'd/m/Y') ?></td>
                                <td class="text-center">
                                    <button class="btn btn-success btn-sm me-2" onclick="openApprovalModal('kr','<?= $kr['id_kr'] ?>','aprovar')">Aprovar</button>
                                    <button class="btn btn-danger btn-sm" onclick="openApprovalModal('kr','<?= $kr['id_kr'] ?>','rejeitar')">Rejeitar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">✅ Nenhum KR pendente.</p>
        <?php endif; ?>
    </div>

    <!-- Orçamentos -->
    <div class="card bg-white p-4 rounded shadow-sm">
        <h3 class="mb-3 text-primary">Orçamentos Pendentes</h3>
        <?php if (count($budgets)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:30%;">Descrição</th>
                            <th>Valor</th>
                            <th>Data Desembolso</th>
                            <th>Responsável</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($budgets as $b):
                            $resp = isset($users[$b['respUid']]) ? $users[$b['respUid']] : null;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($b['descricao']) ?></td>
                                <td>R$ <?= number_format($b['valor'], 2, ',', '.') ?></td>
                                <td><?= date_format($b['data_desembolso'], 'd/m/Y') ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($resp && $resp['image']): ?>
                                            <img src="data:image/png;base64,<?= $resp['image'] ?>" class="rounded-circle me-2" width="40" height="40">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-2 text-secondary me-2"></i>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($resp['name'] ?? 'N/D') ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-success btn-sm me-2"
                                        onclick="openApprovalModal('orcamento','<?= $b['id_orcamento'] ?>','aprovar')">
                                        Aprovar
                                    </button>
                                    <button class="btn btn-danger btn-sm me-2"
                                        onclick="openApprovalModal('orcamento','<?= $b['id_orcamento'] ?>','rejeitar')">
                                        Rejeitar
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="openJustificativaModal('<?= $b['id_orcamento'] ?>')">
                                        Justificativa
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">✅ Nenhum orçamento pendente.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Feedback -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="approvalForm" action="index.php?page=process_approval" method="post">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approvalModalLabel">Confirmação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_type" id="modalItemType">
                    <input type="hidden" name="item_id" id="modalItemId">
                    <input type="hidden" name="action" id="modalAction">
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Observação (Feedback)</label>
                        <textarea name="feedback" id="feedback" class="form-control" rows="3" placeholder="Deixe seu feedback"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Justificativa -->
<div class="modal fade" id="justificativaModal" tabindex="-1" aria-labelledby="justificativaModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="justificativaModalLabel">Justificativa do Orçamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="justificativaContent"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openApprovalModal(itemType, itemId, action) {
        document.getElementById('modalItemType').value = itemType;
        document.getElementById('modalItemId').value = itemId;
        document.getElementById('modalAction').value = action;
        document.getElementById('feedback').value = "";
        new bootstrap.Modal(document.getElementById('approvalModal')).show();
    }

    // Mapa de justificativas
    var justificativas = {};
    <?php foreach ($budgets as $b): ?>
      justificativas['<?= $b['id_orcamento'] ?>'] = <?= json_encode($b['justificativa_orcamento']) ?>;
    <?php endforeach; ?>

    function openJustificativaModal(id) {
      var texto = justificativas[id] || '— Sem justificativa registrada —';
      document.getElementById('justificativaContent').textContent = texto;
      new bootstrap.Modal(document.getElementById('justificativaModal')).show();
    }
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>