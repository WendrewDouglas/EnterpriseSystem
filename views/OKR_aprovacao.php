<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

verificarPermissao('aprovacao_OKR'); // Verifica permissão (ajuste se necessário)

$loggedUserId = $_SESSION['user_id'];
$pageTitle = 'Aprovação OKR - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Conecta ao banco
$db   = new Database();
$conn = $db->getConnection();
if (!$conn) {
    die("<div class='alert alert-danger'>Erro de conexão com o banco: " . print_r(sqlsrv_errors(), true) . "</div>");
}

// Busca os Objetivos pendentes de aprovação
$sqlObjectives = "SELECT * FROM OKR_objetivos WHERE status_aprovacao = 'pendente'";
$stmtObjectives = sqlsrv_query($conn, $sqlObjectives);
if ($stmtObjectives === false) {
    die("<div class='alert alert-danger'>Erro ao buscar objetivos: " . print_r(sqlsrv_errors(), true) . "</div>");
}
$objectives = [];
while ($row = sqlsrv_fetch_array($stmtObjectives, SQLSRV_FETCH_ASSOC)) {
    $objectives[] = $row;
}

// Busca os Key Results (KRs) pendentes de aprovação
$sqlKRs = "SELECT * FROM OKR_key_results WHERE status_aprovacao = 'pendente'";
$stmtKRs = sqlsrv_query($conn, $sqlKRs);
if ($stmtKRs === false) {
    die("<div class='alert alert-danger'>Erro ao buscar KRs: " . print_r(sqlsrv_errors(), true) . "</div>");
}
$KRs = [];
while ($row = sqlsrv_fetch_array($stmtKRs, SQLSRV_FETCH_ASSOC)) {
    $KRs[] = $row;
}

// Mapeia os nomes dos usuários ativos para exibição
$sqlUsers  = "SELECT id, name FROM users WHERE status = 'ativo'";
$stmtUsers = sqlsrv_query($conn, $sqlUsers);
if ($stmtUsers === false) {
    die("<div class='alert alert-danger'>Erro ao buscar usuários: " . print_r(sqlsrv_errors(), true) . "</div>");
}
$users = [];
while ($row = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
    $users[$row['id']] = $row['name'];
}
?>

<div class="content">
    <!-- Título da Página com Ícone -->
    <h2 class="mb-4"><i class="bi bi-check2-square me-2 fs-4"></i> Aprovação OKR</h2>
    
    <!-- Card: Objetivos Pendentes -->
    <div class="card bg-light p-4 rounded mb-4">
        <h3 class="mb-3">Objetivos Pendentes</h3>
        <?php if (count($objectives) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Pilar</th>
                            <th>Responsável</th>
                            <th>Data Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($objectives as $obj): ?>
                        <tr>
                            <td><?= $obj['id_objetivo'] ?></td>
                            <td><?= htmlspecialchars($obj['nome_objetivo']) ?></td>
                            <td><?= htmlspecialchars($obj['pilar_bsc']) ?></td>
                            <td><?= isset($users[$obj['id_responsavel']]) ? htmlspecialchars($users[$obj['id_responsavel']]) : 'N/D' ?></td>
                            <td><?= date_format($obj['data_criacao'], 'd/m/Y H:i') ?></td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="openApprovalModal('objective', <?= $obj['id_objetivo'] ?>, 'aprovar')">Aprovar</button>
                                <button class="btn btn-danger btn-sm" onclick="openApprovalModal('objective', <?= $obj['id_objetivo'] ?>, 'rejeitar')">Rejeitar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>✅ Nenhum objetivo pendente.</p>
        <?php endif; ?>
    </div>
    
    <!-- Card: KRs Pendentes -->
    <div class="card bg-light p-4 rounded mb-4">
        <h3 class="mb-3">Key Results Pendentes</h3>
        <?php if (count($KRs) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Data Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($KRs as $kr): ?>
                        <tr>
                            <td><?= $kr['id_kr'] ?></td>
                            <td><?= htmlspecialchars($kr['nome_kr']) ?></td>
                            <td><?= htmlspecialchars($kr['tipo']) ?></td>
                            <td><?= date_format($kr['data_criacao'], 'd/m/Y H:i') ?></td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="openApprovalModal('kr', <?= $kr['id_kr'] ?>, 'aprovar')">Aprovar</button>
                                <button class="btn btn-danger btn-sm" onclick="openApprovalModal('kr', <?= $kr['id_kr'] ?>, 'rejeitar')">Rejeitar</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>✅ Nenhum KR pendente.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Aprovação/Rejeição com Feedback -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form id="approvalForm" action="process_approval.php" method="post">
      <div class="modal-content">
         <div class="modal-header">
            <h5 class="modal-title" id="approvalModalLabel">Confirmação</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
         </div>
         <div class="modal-body">
            <!-- Campos ocultos -->
            <input type="hidden" name="item_type" id="modalItemType">
            <input type="hidden" name="item_id" id="modalItemId">
            <input type="hidden" name="action" id="modalAction">
            <div class="mb-3">
              <label for="feedback" class="form-label">Observação (Feedback)</label>
              <textarea name="feedback" id="feedback" class="form-control" rows="3" placeholder="Deixe seu feedback para o responsável"></textarea>
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

<!-- Scripts: Bootstrap 5, jQuery, Select2 e inicialização de tooltips -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
    // Inicializa os tooltips do Bootstrap 5
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Função para abrir o modal e passar as informações do item a ser aprovado/rejeitado
    function openApprovalModal(itemType, itemId, action) {
        document.getElementById('modalItemType').value = itemType;
        document.getElementById('modalItemId').value = itemId;
        document.getElementById('modalAction').value = action;
        document.getElementById('feedback').value = "";
        var modal = new bootstrap.Modal(document.getElementById('approvalModal'));
        modal.show();
    }
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
