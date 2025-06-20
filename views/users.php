<?php
// views/users.php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

verificarPermissao('users');

$pageTitle = 'Gerenciar Usuários - Forecast System';

// Conexão SQL Server
$db = new Database();
$conn = $db->getConnection();

// ===== Parâmetros de paginação =====
$validSizes = [25, 50, 100];
$pageSize = 25;
if (isset($_GET['pageSize']) && in_array((int)$_GET['pageSize'], $validSizes, true)) {
    $pageSize = (int)$_GET['pageSize'];
}
$pageNum = 1;
if (isset($_GET['pageNum']) && is_numeric($_GET['pageNum']) && (int)$_GET['pageNum'] >= 1) {
    $pageNum = (int)$_GET['pageNum'];
}
if ($pageNum < 1) {
    $pageNum = 1;
}

function getTotalUsers($conn) {
    $total = 0;
    $tsqlCount = "SELECT COUNT(*) AS total FROM users";
    $stmtCount = sqlsrv_query($conn, $tsqlCount);
    if ($stmtCount !== false) {
        $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
        if ($rowCount && isset($rowCount['total'])) {
            $total = (int)$rowCount['total'];
        }
        sqlsrv_free_stmt($stmtCount);
    }
    return $total;
}

function getUsersPageStmt($conn, $pageNum, $pageSize) {
    $offset = ($pageNum - 1) * $pageSize;
    $tsql = "SELECT id, name, email, role, status, imagem 
             FROM users 
             ORDER BY name 
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
    $params = [ $offset, $pageSize ];
    $stmt = sqlsrv_prepare($conn, $tsql, $params);
    if ($stmt === false) return false;
    if (!sqlsrv_execute($stmt)) return false;
    return $stmt;
}

function renderTableBody($conn, $stmt) {
    $html = '';
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Contar permissões customizadas
        $qtd_permissoes = 0;
        $tsql_perm_count = "SELECT COUNT(*) AS total FROM user_permissions WHERE user_id = ?";
        $params_perm = [ $row['id'] ];
        $stmt_perm_count = sqlsrv_prepare($conn, $tsql_perm_count, $params_perm);
        if ($stmt_perm_count !== false && sqlsrv_execute($stmt_perm_count) !== false) {
            $perm_row = sqlsrv_fetch_array($stmt_perm_count, SQLSRV_FETCH_ASSOC);
            if ($perm_row && isset($perm_row['total'])) {
                $qtd_permissoes = (int)$perm_row['total'];
            }
        }
        if (isset($stmt_perm_count) && $stmt_perm_count !== false) {
            sqlsrv_free_stmt($stmt_perm_count);
        }
        // Avatar ou ícone
        if (!empty($row['imagem'])) {
            $imgHtml = '<img src="'. htmlspecialchars($row['imagem'], ENT_QUOTES, 'UTF-8') .'" alt="Foto" class="rounded-circle" style="width:40px; height:40px; object-fit:cover;">';
        } else {
            $imgHtml = '<div class="bg-secondary d-flex align-items-center justify-content-center rounded-circle" style="width:40px; height:40px;">'
                     . '<i class="bi bi-person-fill text-white" style="font-size:1.2rem;"></i>'
                     . '</div>';
        }
        // Badge de role (primeira maiúscula, resto minúsculo)
        $roleBadge = 'secondary';
        $roleLower = strtolower($row['role'] ?? '');
        switch ($roleLower) {
            case 'admin':    $roleBadge = 'danger';  break;
            case 'gestor':   $roleBadge = 'primary'; break;
            case 'analista': $roleBadge = 'success'; break;
            default:         $roleBadge = 'secondary'; break;
        }
        $roleText = ucfirst($roleLower);
        $roleHtml = '<span class="badge bg-'. $roleBadge .'">'. htmlspecialchars($roleText, ENT_QUOTES, 'UTF-8') .'</span>';
        // Status toggle
        $status = $row['status'] ?? '';
        $isActive = (strtolower($status) === 'ativo');
        $iconClass = $isActive ? 'bi-toggle-on' : 'bi-toggle-off';
        $color = $isActive ? '#198754' : '#6c757d';
        $toggleHtml = '<div class="d-flex align-items-center justify-content-center">';
        $toggleHtml .= '<span class="toggle-status d-flex align-items-center" data-id="'. htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8') .'" data-status="'. htmlspecialchars($status, ENT_QUOTES, 'UTF-8') .'" style="cursor:pointer; font-size:1.2rem; color:'. $color .';"><i class="bi '. $iconClass .'"></i></span>';
        $toggleHtml .= '<span class="ms-1" style="color:'. $color .'; font-weight:500;">'. htmlspecialchars(ucfirst(strtolower($status)), ENT_QUOTES, 'UTF-8') .'</span>';
        $toggleHtml .= '</div>';
        // Ações
        $editUrl = 'index.php?page=edit_user&id=' . urlencode($row['id']);
        $delUrl = '../auth/delete_user.php?id=' . urlencode($row['id']);
        $nameEsc = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        $actionsHtml = '<a href="'. $editUrl .'" class="btn btn-sm btn-outline-primary me-1" title="Editar"><i class="bi bi-pencil"></i></a>'
                     . '<a href="'. $delUrl .'" class="btn btn-sm btn-outline-danger" title="Excluir" onclick="return confirm(\'Excluir '. addslashes($nameEsc) .'?\')"><i class="bi bi-trash"></i></a>';

        // Montar row SEM coluna ID
        $html .= '<tr class="align-middle">'
               // Avatar
               . '<td class="text-center">'. $imgHtml .'</td>'
               // Nome com fonte reduzida
               . '<td style="font-size:0.875rem;">'. htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') .'</td>'
               // E-mail com fonte reduzida
               . '<td style="font-size:0.875rem;">'. htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') .'</td>'
               // Role
               . '<td class="text-center">'. $roleHtml .'</td>'
               // Permissões
               . '<td class="text-center">'. ($qtd_permissoes>0? '<span class="badge bg-info">'.htmlspecialchars($qtd_permissoes, ENT_QUOTES,'UTF-8').' páginas</span>' : '<span class="badge bg-secondary">Padrão</span>') .'</td>'
               // Status
               . '<td class="text-center">'. $toggleHtml .'</td>'
               // Ações
               . '<td class="text-center">'. $actionsHtml .'</td>'
               . '</tr>';
    }
    return $html;
}

function renderPaginationControls($pageNum, $totalPages) {
    $html = '';
    if ($totalPages <= 1) return $html;
    $html .= '<nav aria-label="Paginação usuários"><ul class="pagination pagination-sm mb-0">';
    $disabledPrev = $pageNum <= 1 ? 'disabled' : '';
    $prevPage = max(1, $pageNum - 1);
    $html .= '<li class="page-item '. $disabledPrev .'"><a href="#" class="page-link" data-page="'. $prevPage .'"><i class="bi bi-chevron-left"></i></a></li>';
    $start = max(1, $pageNum - 2);
    $end = min($totalPages, $pageNum + 2);
    if ($start > 1) {
        $html .= '<li class="page-item"><a href="#" class="page-link" data-page="1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pageNum ? 'active' : '';
        $html .= '<li class="page-item '. $active .'"><a href="#" class="page-link" data-page="'. $i .'">'. $i .'</a></li>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a href="#" class="page-link" data-page="'. $totalPages .'">'. $totalPages .'</a></li>';
    }
    $disabledNext = $pageNum >= $totalPages ? 'disabled' : '';
    $nextPage = min($totalPages, $pageNum + 1);
    $html .= '<li class="page-item '. $disabledNext .'"><a href="#" class="page-link" data-page="'. $nextPage .'"><i class="bi bi-chevron-right"></i></a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// AJAX: resposta JSON para paginação
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $totalUsers = getTotalUsers($conn);
    $totalPages = ($pageSize>0)? (int)ceil($totalUsers/$pageSize) : 1;
    if ($totalPages<1) $totalPages=1;
    if ($pageNum > $totalPages) $pageNum = $totalPages;

    $stmtPage = getUsersPageStmt($conn, $pageNum, $pageSize);
    if ($stmtPage === false) {
        echo json_encode(['error'=>'Erro ao buscar usuários.']);
        exit;
    }
    $tbodyHtml = renderTableBody($conn, $stmtPage);
    sqlsrv_free_stmt($stmtPage);

    $paginationHtml = renderPaginationControls($pageNum, $totalPages);

    echo json_encode(['tbody'=>$tbodyHtml, 'pagination'=>$paginationHtml, 'totalUsers'=>$totalUsers], JSON_UNESCAPED_UNICODE);
    exit;
}

// Renderização inicial
$totalUsers = getTotalUsers($conn);
$totalPages = ($pageSize>0)? (int)ceil($totalUsers/$pageSize) : 1;
if ($totalPages<1) $totalPages=1;
if ($pageNum > $totalPages) $pageNum = $totalPages;

$stmt = getUsersPageStmt($conn, $pageNum, $pageSize);
if ($stmt === false) {
    die("<div class='alert alert-danger'>Erro ao consultar usuários.</div>");
}

// Inclui cabeçalho e sidebar
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<!-- Import de fonte moderna -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
body, .main-content { font-family: 'Inter', sans-serif; }

/* Defina a largura da sidebar aqui: ajuste conforme seu layout */
:root {
    --sidebar-width: 200px; /* antes era 260px; diminua ou aumente conforme a largura real da sidebar */
}

/* Área principal (conteúdo) fica à direita da sidebar */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 1rem; /* reduzido de 2rem para 1rem para menos espaçamento interno */
    background: #f5f7fa;
    min-height: 100vh;
}

/* Ajuste responsivo se sidebar colapsa ou em telas menores */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 0.75rem;
    }
}

/* Card look para seções */
.card-custom {
    border: none;
    border-radius: .75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

/* Input group com ícone */
.input-with-icon {
    position: relative;
}
.input-with-icon .bi {
    position: absolute;
    top: 50%;
    left: 0.75rem;
    transform: translateY(-50%);
    color: #6c757d;
}
.input-with-icon input {
    padding-left: 2.5rem;
}

/* Spinner overlay */
#loadingOverlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(255,255,255,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    border-radius: .75rem;
    display: none;
}

/* Table enhancements */
#usersTable thead {
    background: #343a40;
}
#usersTable thead th {
    color: #fff;
    border-bottom: none;
}
#usersTable tbody tr:hover {
    background: #e9ecef;
    transition: background 0.2s;
}

/* Paginação centralizada */
.pagination-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
}
.pagination .page-link {
    color: #495057;
}
.pagination .page-item.active .page-link {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
.pagination .page-link:hover {
    background-color: #e2e6ea;
}

/* Botões de ações mais sutis */
.btn-outline-primary {
    border-color: #0d6efd;
    color: #0d6efd;
}
.btn-outline-primary:hover {
    background-color: #0d6efd;
    color: #fff;
}
.btn-outline-danger:hover {
    background-color: #dc3545;
    color: #fff;
}

/* Reduzir fonte do nome e email na tabela */
#usersTable tbody td:nth-child(2),
#usersTable tbody td:nth-child(3) {
    font-size: 0.875rem;
}
</style>

<div class="main-content position-relative">
  <!-- Spinner overlay durante AJAX -->
  <div id="loadingOverlay"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>

  <!-- Removi padding extra do container-fluid para aproximar conteúdo -->
  <div class="container-fluid px-0">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h4 text-primary"><i class="bi bi-people-fill me-2"></i>Gerenciar Usuários</h1>
      <a href="index.php?page=add_user" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i>Adicionar Usuário
      </a>
    </div>

    <!-- Alertas -->
    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['success_message'],ENT_QUOTES,'UTF-8'); unset($_SESSION['success_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_SESSION['error_message'],ENT_QUOTES,'UTF-8'); unset($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Card com controles de paginação e busca -->
    <div class="card card-custom mb-4 p-3">
      <div class="row g-3 align-items-center">
        <div class="col-auto">
          <label for="pageSizeSelect" class="col-form-label">Exibir:</label>
        </div>
        <div class="col-auto">
          <select id="pageSizeSelect" class="form-select form-select-sm">
            <?php foreach($validSizes as $size): ?>
              <option value="<?= $size ?>" <?= $pageSize=== $size ? 'selected':''; ?>><?= $size ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <span>de <?= $totalUsers ?? getTotalUsers($conn) ?> usuário<?= ($totalUsers ?? 0)===1?'':'s'?></span>
        </div>
        <div class="col-auto ms-auto">
          <div class="input-with-icon">
            <i class="bi bi-search"></i>
            <input type="text" id="searchName" class="form-control form-control-sm" placeholder="Buscar por nome">
          </div>
        </div>
        <div class="col-auto">
          <div class="input-with-icon">
            <i class="bi bi-envelope"></i>
            <input type="text" id="searchEmail" class="form-control form-control-sm" placeholder="Buscar por e-mail">
          </div>
        </div>
      </div>
      <!-- Paginação -->
      <div class="mt-3 pagination-wrapper" id="paginationContainer">
        <?= renderPaginationControls($pageNum, $totalPages); ?>
      </div>
    </div>

    <!-- Tabela -->
    <div class="card card-custom">
      <div class="table-responsive">
        <table id="usersTable" class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr class="text-white">
              <!-- Coluna ID removida -->
              <th class="text-center" style="width:60px;">Foto</th>
              <th style="min-width:150px;">Nome</th>
              <th style="min-width:200px;">E-mail</th>
              <th class="text-center">Perfil</th>
              <th class="text-center">Permissões</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="width:120px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?= renderTableBody($conn, $stmt); sqlsrv_free_stmt($stmt); ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchNameInput = document.getElementById('searchName');
    const searchEmailInput = document.getElementById('searchEmail');
    const pageSizeSelect = document.getElementById('pageSizeSelect');
    const paginationContainer = document.getElementById('paginationContainer');
    const usersTableBody = document.querySelector('#usersTable tbody');
    const loadingOverlay = document.getElementById('loadingOverlay');

    function showLoading() { loadingOverlay.style.display = 'flex'; }
    function hideLoading() { loadingOverlay.style.display = 'none'; }

    function filterTable() {
        const nameTerm = searchNameInput.value.trim().toLowerCase();
        const emailTerm = searchEmailInput.value.trim().toLowerCase();
        Array.from(usersTableBody.rows).forEach(row => {
            // Agora a coluna Nome é cell index 1, e E-mail é index 2
            const nameCell = row.cells[1];
            const emailCell = row.cells[2];
            const nameText = nameCell? nameCell.textContent.trim().toLowerCase():'';
            const emailText = emailCell? emailCell.textContent.trim().toLowerCase():'';
            const show = (nameTerm===''|| nameText.includes(nameTerm)) && (emailTerm===''|| emailText.includes(emailTerm));
            row.style.display = show?'':'none';
        });
    }

    function loadData(pageNum, pageSize) {
        showLoading();
        // Monta URL de AJAX, preservando route page=users
        const urlObj = new URL(window.location.href);
        const routePage = urlObj.searchParams.get('page'); // ex: 'users'
        let ajaxUrl = window.location.pathname + '?ajax=1';
        if (routePage) {
            ajaxUrl += '&page=' + encodeURIComponent(routePage);
        }
        ajaxUrl += '&pageNum=' + encodeURIComponent(pageNum) + '&pageSize=' + encodeURIComponent(pageSize);
        fetch(ajaxUrl, { headers: {'X-Requested-With':'XMLHttpRequest'} })
          .then(resp => resp.json())
          .then(data => {
            if (data.error) {
                console.error('Erro ao carregar dados:', data.error);
            } else {
                usersTableBody.innerHTML = data.tbody;
                paginationContainer.innerHTML = data.pagination;
                filterTable();
            }
        }).catch(err=>{
            console.error('Erro AJAX loadData:', err);
        }).finally(()=>{
            hideLoading();
        });
    }

    // Paginação via delegação
    paginationContainer.addEventListener('click', function(e) {
        const link = e.target.closest('a.page-link');
        if (link && link.dataset.page) {
            e.preventDefault();
            const pageNum = link.dataset.page;
            const size = parseInt(pageSizeSelect.value,10) || 25;
            loadData(pageNum, size);
        }
    });
    // Toggle-status via delegação
    usersTableBody.addEventListener('click', function(e) {
        const toggleElem = e.target.closest('.toggle-status');
        if (toggleElem) {
            let userId = toggleElem.getAttribute("data-id");
            let currentStatus = toggleElem.getAttribute("data-status");
            let newStatus = (currentStatus==='ativo') ? 'inativo' : 'ativo';
            fetch("index.php?page=update_user_status", {
                method:"POST",
                headers:{"Content-Type":"application/x-www-form-urlencoded"},
                body: new URLSearchParams({id:userId, status:newStatus})
            }).then(r=>r.json()).then(data=>{
                if (data.success) {
                    toggleElem.setAttribute("data-status", newStatus);
                    const iconElem = toggleElem.querySelector('i.bi');
                    if (iconElem) {
                        iconElem.classList.remove('bi-toggle-on','bi-toggle-off');
                        iconElem.classList.add(newStatus==='ativo'?'bi-toggle-on':'bi-toggle-off');
                    }
                    const color = newStatus==='ativo'? '#198754':'#6c757d';
                    toggleElem.style.color = color;
                    const label = toggleElem.nextElementSibling;
                    if (label) {
                        label.textContent = newStatus.charAt(0).toUpperCase()+newStatus.slice(1);
                        label.style.color = color;
                    }
                } else {
                    alert("Erro ao atualizar status: "+(data.message||''));
                }
            }).catch(err=>{
                console.error("Erro AJAX toggle-status:", err);
                alert("Erro ao conectar ao servidor.");
            });
        }
    });
    // pageSize change
    pageSizeSelect.addEventListener('change', function() {
        const newSize = parseInt(this.value,10) || 25;
        loadData(1, newSize);
    });
    // Busca inputs
    searchNameInput.addEventListener('input', filterTable);
    searchEmailInput.addEventListener('input', filterTable);
});
</script>

</body>
</html>

<?php
if (isset($conn)) {
    sqlsrv_close($conn);
}
?>
