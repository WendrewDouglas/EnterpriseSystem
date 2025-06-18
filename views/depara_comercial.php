<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Permitir apenas ADMIN acessar
verificarPermissao('depara_comercial');

// Configuração da página
$pageTitle = 'Gestores Comerciais - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Criar conexão com o banco
$db = new Database();
$conn = $db->getConnection();
if (!$conn) {
    die("<div class='alert alert-danger'>Erro de conexão com o banco: " . print_r(sqlsrv_errors(), true) . "</div>");
}

$sqlTest = "SELECT TOP 1 * FROM DW..DEPARA_COMERCIAL";
$stmtTest = sqlsrv_query($conn, $sqlTest);

if ($stmtTest === false) {
    die("<div class='alert alert-danger'>Erro ao acessar a tabela: " . print_r(sqlsrv_errors(), true) . "</div>");
}

// Consulta para obter os usuários cadastrados no sistema
$sqlUsuarios = "SELECT DISTINCT name FROM users ORDER BY name";
$stmtUsuarios = sqlsrv_query($conn, $sqlUsuarios);

$usuarios = [];
while ($rowUsuario = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC)) {
    $usuarios[] = $rowUsuario['name'];
}

// Consulta para obter os registros da tabela
$sql = "SELECT 
            Regional, 
            GNV, 
            NomeRegional, 
            Analista,
            status_regional
        FROM DW..DEPARA_COMERCIAL
        WHERE Regional IS NOT NULL AND LTRIM(RTRIM(Regional)) <> ''
        ORDER BY Regional";

$stmt = sqlsrv_query($conn, $sql);

if ($stmt === false) {
    die("<div class='alert alert-danger'>Erro ao carregar os gestores comerciais.</div>");
}
?>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-person-badge"></i> Gestores Comerciais</h2>

    <div class="card shadow-sm p-4, d-flex flex-column">
        <p class="text-muted">A tabela abaixo lista os gestores comerciais cadastrados no sistema.</p>

        <table class="table table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>COD</th>
                    <th>GNV</th>
                    <th>REGIONAL</th>
                    <th>ANALISTA</th>
                    <th>STATUS</th>
                    <th>AÇÃO</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Regional']); ?></td>
                        
                        <!-- GNV -->
                        <td>
                            <select class="form-select form-select-sm update-field" 
                                data-id="<?= $row['Regional']; ?>" 
                                data-column="GNV"
                                data-current-value="<?= htmlspecialchars($row['GNV']); ?>">
                                <option value="<?= htmlspecialchars($row['GNV']); ?>"><?= htmlspecialchars($row['GNV']); ?> (Atual)</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= htmlspecialchars($usuario); ?>"><?= htmlspecialchars($usuario); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <!-- NomeRegional -->
                        <td>
                            <select class="form-select form-select-sm update-field" 
                                data-id="<?= $row['Regional']; ?>" 
                                data-column="NomeRegional"
                                data-current-value="<?= htmlspecialchars($row['NomeRegional']); ?>">
                                <option value="<?= htmlspecialchars($row['NomeRegional']); ?>"><?= htmlspecialchars($row['NomeRegional']); ?> (Atual)</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= htmlspecialchars($usuario); ?>"><?= htmlspecialchars($usuario); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <!-- Analista -->
                        <td>
                            <select class="form-select form-select-sm update-field" 
                                data-id="<?= $row['Regional']; ?>" 
                                data-column="Analista"
                                data-current-value="<?= htmlspecialchars($row['Analista']); ?>">
                                <option value="<?= htmlspecialchars($row['Analista']); ?>"><?= htmlspecialchars($row['Analista']); ?> (Atual)</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= htmlspecialchars($usuario); ?>"><?= htmlspecialchars($usuario); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <!-- Status -->
                        <td class="text-center d-flex align-items-center justify-content-center">
                            <span class="toggle-status" 
                                data-id="<?= $row['Regional']; ?>" 
                                data-status="<?= $row['status_regional']; ?>" 
                                style="cursor: pointer; font-size: 1.5rem; color: <?= $row['status_regional'] === 'ativo' ? 'green' : 'gray'; ?>;">
                                <i class="bi <?= $row['status_regional'] === 'ativo' ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                            </span>
                            <span style="color: <?= $row['status_regional'] === 'ativo' ? 'green' : 'gray'; ?>; font-weight: bold; margin-left: 8px;">
                                <?= ucfirst($row['status_regional']); ?>
                            </span>
                        </td>

                        <!-- Botão de Salvar -->
                        <td>
                            <button class="btn btn-sm btn-primary save-button" data-id="<?= $row['Regional']; ?>">
                                <i class="bi bi-check-circle"></i> Salvar
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Atualização do Toggle Status (Ativo/Inativo)
    document.querySelectorAll(".toggle-status").forEach(icon => {
        icon.addEventListener("click", function () {
            let regionalId = this.getAttribute("data-id");
            let currentStatus = this.getAttribute("data-status");
            let newStatus = currentStatus === "ativo" ? "inativo" : "ativo";

            fetch("/forecast/views/update_depara_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    regional: regionalId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualiza visualmente o toggle
                    this.setAttribute("data-status", newStatus);
                    this.innerHTML = `<i class="bi ${newStatus === "ativo" ? "bi-toggle-on" : "bi-toggle-off"}"></i>`;
                    this.style.color = newStatus === "ativo" ? "green" : "gray";

                    // Atualiza o texto ao lado do toggle
                    let statusText = this.nextElementSibling;
                    statusText.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    statusText.style.color = newStatus === "ativo" ? "green" : "gray";
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

    // Alteração Visual ao Mudar um Campo (Destaque em Rosa)
    document.querySelectorAll(".update-field").forEach(select => {
        verificarMudanca(select);
        select.addEventListener("change", function () {
            verificarMudanca(this);
        });
    });

    function verificarMudanca(select) {
        let currentValue = select.getAttribute("data-current-value");
        select.style.backgroundColor = (select.value !== currentValue) ? "#f8d7da" : "";
    }

    // Atualização dos Dados ao Clicar em "Salvar"
    document.querySelectorAll(".save-button").forEach(button => {
        button.addEventListener("click", function () {
            let id = this.getAttribute("data-id");
            let gnv = document.querySelector(`select[data-id="${id}"][data-column="GNV"]`).value;
            let nomeRegional = document.querySelector(`select[data-id="${id}"][data-column="NomeRegional"]`).value;
            let analista = document.querySelector(`select[data-id="${id}"][data-column="Analista"]`).value;

            fetch("/forecast/views/process_update_comercial.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({
                    regional: id,
                    gnv: gnv,
                    nomeRegional: nomeRegional,
                    analista: analista
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Registro atualizado com sucesso!");
                    location.reload();
                } else {
                    // Exibe mensagem com detalhes do erro
                    let errorMsg = "Erro ao atualizar: " + (data.message || "Erro desconhecido");
                    if (data.sql_error) {
                        errorMsg += "\nSQL Error: " + JSON.stringify(data.sql_error);
                    }
                    if (data.db_error) {
                        errorMsg += "\nDB Error: " + JSON.stringify(data.db_error);
                    }
                    alert(errorMsg);
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

            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
