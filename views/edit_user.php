<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

$pageTitle = 'Editar Usuário - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de usuário inválido.</div>";
    exit();
}

$user_id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT id, name, email, role FROM users WHERE id = ?";
$params = array($user_id);
$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false || !($user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    echo "<div class='alert alert-danger'>Usuário não encontrado.</div>";
    exit();
}

// Obter lista de páginas disponíveis no sistema 
$available_pages = [
    'dashboard' => 'Dashboard',
    'users' => 'Gerenciar Usuários',
    'add_user' => 'Adicionar Usuário',
    'edit_user' => 'Editar Usuário',
    'configuracoes' => 'Configurações',
    'apontar_forecast' => 'Apontar Forecast',
    'consulta_lancamentos' => 'Relatório PCP',
    'historico_forecast' => 'Histórico de Forecast',
    'depara_comercial' => 'Gestores Comerciais',
    'enviar_sellout' => 'Enviar Sell-Out',
    'export_sellout' => 'Exportar Sell-Out',
    'financeiro' => 'Financeiro',
    'cursos' => 'Cursos',
    'forecast_geral' => 'Forecast Geral',
    'novo_objetivo' => 'Criar Objetivo (OKR)',
    'aprovacao_OKR' => 'Aprovações (OKR)',
    'novo_kr' => 'Novo KR'
];

// Buscar permissões atuais do usuário
$user_permissions = [];
$sql_permissions = "SELECT page_name, permission_type FROM user_permissions WHERE user_id = ?";
$stmt_permissions = sqlsrv_query($conn, $sql_permissions, [$user_id]);

if ($stmt_permissions !== false) {
    while ($permission = sqlsrv_fetch_array($stmt_permissions, SQLSRV_FETCH_ASSOC)) {
        $user_permissions[$permission['page_name']][$permission['permission_type']] = true;
    }
}

// Garantir que as permissões de admin estejam configuradas corretamente
if ($user['role'] == 'admin') {
    foreach ($available_pages as $page => $page_title) {
        $user_permissions[$page]['view'] = true;
        $user_permissions[$page]['modify'] = true;
    }
}

// DEBUG: Log de permissões para verificação
error_log("Permissões do usuário $user_id: " . json_encode($user_permissions));

// Definir permissões padrão para administradores
$admin_permissions = [];
foreach (array_keys($available_pages) as $page) {
    $admin_permissions[$page] = ['view' => true, 'modify' => true];
}

// Quando o formulário for submetido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['id'];
    $permissions = $_POST['permissions'] ?? [];
    
    // Primeiro, deletar todas as permissões existentes do usuário
    $sqlDelete = "DELETE FROM user_permissions WHERE user_id = ?";
    $stmtDelete = sqlsrv_query($conn, $sqlDelete, [$userId]);
    
    // Inserir as novas permissões
    foreach ($permissions as $page => $types) {
        foreach ($types as $type => $value) {
            if ($value == 1) {
                $sqlInsert = "INSERT INTO user_permissions (user_id, page_name, permission_type, has_access) 
                             VALUES (?, ?, ?, ?)";
                $params = [$userId, $page, $type, 1];
                $stmtInsert = sqlsrv_query($conn, $sqlInsert, $params);
                
                if ($stmtInsert === false) {
                    error_log("Erro ao inserir permissão: " . print_r(sqlsrv_errors(), true));
                }
            }
        }
    }
    
    $_SESSION['success_message'] = "Permissões atualizadas com sucesso!";
    header("Location: index.php?page=users");
    exit();
}
?>

<div class="content">
    <h2>✏️ Editar Usuário</h2>
    
    <form action="index.php?page=update_user" method="POST">
        <input type="hidden" name="id" value="<?= htmlspecialchars($user['id']); ?>">

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informações Básicas</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Perfil</label>
                    <select name="role" id="role" class="form-control" required onchange="updatePermissionsDisplay()">
                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="gestor" <?= $user['role'] == 'gestor' ? 'selected' : ''; ?>>Gestor</option>
                        <option value="custom" <?= $user['role'] != 'admin' && $user['role'] != 'gestor' ? 'selected' : ''; ?>>Personalizado</option>
                    </select>
                    <small class="text-muted">Administradores têm acesso completo a todas as funcionalidades. Gestores têm acesso a páginas específicas de gestão.</small>
                </div>
            </div>
        </div>

        <div id="permissionsCard" class="card mb-4" <?= $user['role'] == 'admin' ? 'style="display:none;"' : ''; ?>>
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Permissões de Acesso</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Página</th>
                                <th class="text-center">Visualizar</th>
                                <th class="text-center">Modificar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($available_pages as $page => $page_title): ?>
                                <tr>
                                    <td><?= $page_title ?></td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input permission-view" type="checkbox" 
                                                name="permissions[<?= $page ?>][view]" 
                                                id="perm_view_<?= $page ?>" 
                                                value="1"
                                                <?= (isset($user_permissions[$page]['view']) && $user_permissions[$page]['view']) ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check d-flex justify-content-center">
                                            <input class="form-check-input permission-modify" type="checkbox" 
                                                name="permissions[<?= $page ?>][modify]" 
                                                id="perm_modify_<?= $page ?>" 
                                                value="1"
                                                onchange="ensureViewPermission(this, '<?= $page ?>')"
                                                <?= (isset($user_permissions[$page]['modify']) && $user_permissions[$page]['modify']) ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Salvar Alterações</button>
        <a href="index.php?page=users" class="btn btn-secondary">Cancelar</a>
    </form>
</div>

<script>
// Armazenar permissões padrão para administradores e gestores
const adminPermissions = <?= json_encode($admin_permissions) ?>;
const gestorPermissions = {
    'dashboard': { view: true, modify: true },
    'apontar_forecast': { view: true, modify: true },
    'historico_forecast': { view: true, modify: true },
    'enviar_sellout': { view: true, modify: true }
};
const availablePages = <?= json_encode(array_keys($available_pages)) ?>;
// Armazena as permissões atuais do usuário para uso no JavaScript
const currentUserPermissions = <?= json_encode($user_permissions) ?>;

// Atualiza a exibição das permissões com base no perfil selecionado
function updatePermissionsDisplay() {
    const role = document.getElementById('role').value;
    const permissionsCard = document.getElementById('permissionsCard');
    
    if (role === 'admin') {
        permissionsCard.style.display = 'none';
        
        // Para admins, adicionar input hidden para cada permissão
        availablePages.forEach(page => {
            // Adicionar campos ocultos para garantir que as permissões sejam enviadas para admins
            if (!document.getElementById(`hidden_view_${page}`)) {
                const hiddenView = document.createElement('input');
                hiddenView.type = 'hidden';
                hiddenView.name = `permissions[${page}][view]`;
                hiddenView.id = `hidden_view_${page}`;
                hiddenView.value = '1';
                document.querySelector('form').appendChild(hiddenView);
                
                const hiddenModify = document.createElement('input');
                hiddenModify.type = 'hidden';
                hiddenModify.name = `permissions[${page}][modify]`;
                hiddenModify.id = `hidden_modify_${page}`;
                hiddenModify.value = '1';
                document.querySelector('form').appendChild(hiddenModify);
            }
        });
    } else if (role === 'gestor') {
        permissionsCard.style.display = 'none';
        
        // Para gestores, adicionar input hidden apenas para as páginas permitidas
        Object.keys(gestorPermissions).forEach(page => {
            if (!document.getElementById(`hidden_view_${page}`)) {
                const hiddenView = document.createElement('input');
                hiddenView.type = 'hidden';
                hiddenView.name = `permissions[${page}][view]`;
                hiddenView.id = `hidden_view_${page}`;
                hiddenView.value = '1';
                document.querySelector('form').appendChild(hiddenView);
                
                const hiddenModify = document.createElement('input');
                hiddenModify.type = 'hidden';
                hiddenModify.name = `permissions[${page}][modify]`;
                hiddenModify.id = `hidden_modify_${page}`;
                hiddenModify.value = '1';
                document.querySelector('form').appendChild(hiddenModify);
            }
        });
    } else {
        permissionsCard.style.display = 'block';
        
        // Remover campos ocultos se existirem
        availablePages.forEach(page => {
            const hiddenView = document.getElementById(`hidden_view_${page}`);
            const hiddenModify = document.getElementById(`hidden_modify_${page}`);
            
            if (hiddenView) hiddenView.remove();
            if (hiddenModify) hiddenModify.remove();
        });
        
        // Restaurar as permissões do usuário
        restoreUserPermissions();
    }
}

// Restaura as permissões do usuário a partir do objeto recuperado do PHP
function restoreUserPermissions() {
    // Reset all checkboxes first
    document.querySelectorAll('.permission-view, .permission-modify').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Set checkboxes based on saved permissions
    availablePages.forEach(page => {
        const viewCheckbox = document.getElementById(`perm_view_${page}`);
        const modifyCheckbox = document.getElementById(`perm_modify_${page}`);
        
        if (viewCheckbox && currentUserPermissions[page] && currentUserPermissions[page]['view']) {
            viewCheckbox.checked = true;
        }
        
        if (modifyCheckbox && currentUserPermissions[page] && currentUserPermissions[page]['modify']) {
            modifyCheckbox.checked = true;
        }
    });
}

// Função para garantir que, se tiver permissão de modificação, também tenha permissão de visualização
function ensureViewPermission(modifyCheckbox, page) {
    if (modifyCheckbox.checked) {
        document.getElementById(`perm_view_${page}`).checked = true;
    }
}

// Inicializar as permissões com base no perfil selecionado ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    console.log("Carregando permissões do usuário:", currentUserPermissions);
    
    // Atualiza a exibição baseada no perfil
    updatePermissionsDisplay();
    
    // Adicionar CSS para garantir que os checkboxes são exibidos corretamente
    const styleElement = document.createElement('style');
    styleElement.textContent = `
        .form-check-input:checked {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
        }
        .form-check-input {
            transition: background-color 0.15s ease-in-out, border-color 0.15s ease-in-out !important;
        }
    `;
    document.head.appendChild(styleElement);
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
