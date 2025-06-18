<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

verificarPermissao('users');

$pageTitle = 'Gerenciar Usuários - Forecast System';

include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';



// Criar conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Buscar usuários do banco de dados
$sql = "SELECT id, name, email, role, status FROM users ORDER BY name";
$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("<div class='alert alert-danger'>Erro ao carregar usuários.</div>");
}
?>

<div class="content">
    <h2>👥 Gerenciar Usuários</h2>
    <p class="text-muted">Aqui você pode visualizar, editar e excluir usuários.</p>

    <?php 
    if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; 
    ?>

    <?php 
    if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; 
    ?>


    <div class="card shadow-sm p-4; d-flex flex-column">
        <table class="table table-hover mt-3">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th class="text-center">Perfil</th>
                    <th class="text-center">Permissões</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): 
                    // Verificar permissões customizadas
                    $qtd_permissoes = 0;
                    $sql_perm_count = "SELECT COUNT(*) as total FROM user_permissions WHERE user_id = ? AND has_access = 1";
                    $stmt_perm_count = sqlsrv_query($conn, $sql_perm_count, [$row['id']]);
                    if ($stmt_perm_count !== false && ($perm_row = sqlsrv_fetch_array($stmt_perm_count, SQLSRV_FETCH_ASSOC))) {
                        $qtd_permissoes = $perm_row['total'];
                    }
                ?>
                    <tr>
                        <td><?= $row['id']; ?></td>
                        <td><?= htmlspecialchars($row['name']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?php 
                                switch ($row['role']) {
                                    case 'admin': echo 'danger'; break;
                                    case 'gestor': echo 'primary'; break;
                                    case 'analista': echo 'success'; break;
                                    default: echo 'secondary'; 
                                }
                            ?>">
                                <?= ucfirst($row['role']); ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($qtd_permissoes > 0): ?>
                                <span class="badge bg-info"><?= $qtd_permissoes ?> páginas</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Padrão do perfil</span>
                            <?php endif; ?>
                        </td>
                        <!-- Botão de ativar/inativar -->
                        <td class="text-center d-flex align-items-center justify-content-center">
                            <span class="toggle-status" 
                                data-id="<?= $row['id']; ?>" 
                                data-status="<?= $row['status']; ?>" 
                                style="cursor: pointer; font-size: 1.5rem; color: <?= $row['status'] === 'ativo' ? 'green' : 'gray'; ?>;">
                                <i class="bi <?= $row['status'] === 'ativo' ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                            </span>
                            <span style="color: <?= $row['status'] === 'ativo' ? 'green' : 'gray'; ?>; font-weight: bold; margin-left: 8px;">
                                <?= ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <!-- Botões de editar e excluir -->
                        <td class="text-center">
                            <a href="index.php?page=edit_user&id=<?= $row['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil-square"></i> Editar
                            </a>
                            <a href="../delete_user.php?id=<?= $row['id']; ?>" 
                                class="btn btn-sm btn-danger" 
                                onclick="return confirm('Tem certeza que deseja excluir <?= $row['name']; ?>')">
                                <i class="bi bi-trash"></i> Excluir
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="mt-3">
            <a href="index.php?page=add_user" class="btn btn-success">
                <i class="bi bi-person-plus-fill"></i> Adicionar Novo Usuário
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".toggle-status").forEach(icon => {
        icon.addEventListener("click", function () {
            let userId = this.getAttribute("data-id");
            let currentStatus = this.getAttribute("data-status");
            let newStatus = currentStatus === "ativo" ? "inativo" : "ativo";

            fetch("index.php?page=update_user_status", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    id: userId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualiza o status visualmente sem recarregar a página
                    this.setAttribute("data-status", newStatus);
                    this.innerHTML = `<i class="bi ${newStatus === "ativo" ? "bi-toggle-on" : "bi-toggle-off"}"></i>`;
                    this.style.color = newStatus === "ativo" ? "green" : "gray";

                    // Atualiza o texto ao lado do toggle
                    this.nextElementSibling.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    this.nextElementSibling.style.color = newStatus === "ativo" ? "green" : "gray";
                } else {
                    alert("Erro ao atualizar status: " + data.message);
                }
            })
            .catch(error => {
                console.error("Erro na requisição:", error);
                alert("Erro ao conectar ao servidor.");
            });
        });
    });
});
</script>
</body>
</html>
