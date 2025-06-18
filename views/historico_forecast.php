<?php
// Ativar exibição de erros para desenvolvimento (remover em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('apontar_forecast');

// Configuração da página
$pageTitle = 'Histórico de Apontamentos - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Criar conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Obter usuário logado
$userName = $_SESSION['user_name'] ?? null;
if (!$userName) {
    die("<div class='alert alert-danger'>Erro: Usuário não identificado. Faça login novamente.</div>");
}

// --- Parâmetros e Paginação ---
// Capturar filtros enviados via GET
$gestor  = $_GET['gestor']  ?? '';
$empresa = $_GET['empresa'] ?? '';
$linha   = $_GET['linha']   ?? '';
$modelo  = $_GET['modelo']  ?? '';

// Paginação
$currentPage = isset($_GET['p']) && is_numeric($_GET['p']) ? (int) $_GET['p'] : 1;
$rowsPerPage = isset($_GET['rows']) && in_array($_GET['rows'], ['25','50','100']) ? (int) $_GET['rows'] : 25;
$offset = ($currentPage - 1) * $rowsPerPage;

// --- Calcular os próximos 3 meses (formato "mm/YYYY") ---
$nextMonths = [];
$start = new DateTime("first day of next month");
for ($i = 0; $i < 3; $i++) {
    $nextMonths[] = $start->format("m/Y");
    $start->modify("+1 month");
}

// Função para converter mês para abreviação
function abreviaMes($mesAno) {
    $mes = substr($mesAno, 0, 2);
    $ano = substr($mesAno, 3);
    $meses = [
        '01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr',
        '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago',
        '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'
    ];
    return ($meses[$mes] ?? $mes) . '/' . $ano;
}

// --- Função para obter opções de filtros ---
// Essa função executa uma query para obter valores distintos de um campo na visão unificada
function getDistinctOptions($conn, $field, $nextMonths) {
    // A query unificada para filtros (apenas os registros dos próximos três meses)
    $sql = "
    SELECT DISTINCT $field FROM (
        SELECT f.cod_gestor AS gestor,
               f.empresa,
               f.mes_referencia,
               COALESCE(f.modelo_produto, '') AS modelo,
               i.LINHA AS linha
        FROM forecast_entries f
        LEFT JOIN (SELECT DISTINCT MODELO, LINHA FROM V_DEPARA_ITEM) i ON f.modelo_produto = i.MODELO
        WHERE f.mes_referencia IN (?, ?, ?)
        UNION ALL
        SELECT s.codigo_gestor AS gestor,
               s.empresa,
               s.mes_referencia,
               s.modelo AS modelo,
               s.linha AS linha
        FROM forecast_entries_sku s
        WHERE s.mes_referencia IN (?, ?, ?)
    ) AS options
    ORDER BY $field";
    // Usamos os mesmos três valores para cada parte (total 6 parâmetros)
    $params = array_merge($nextMonths, $nextMonths);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $options = [];
    if ($stmt !== false) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $options[] = $row[$field];
        }
    }
    return $options;
}

// Obter opções para filtros
$gestoresOptions = getDistinctOptions($conn, 'gestor', $nextMonths);
$empresasOptions = getDistinctOptions($conn, 'empresa', $nextMonths);
$linhasOptions = getDistinctOptions($conn, 'linha', $nextMonths);
$modelosOptions = getDistinctOptions($conn, 'modelo', $nextMonths);

// --- Montar a query unificada ---
// A query unificada junta os registros de forecast_entries (modelo) e forecast_entries_sku (sku)
$unionSql = "
SELECT 
    f.id,
    f.cod_gestor AS gestor,
    f.empresa,
    f.mes_referencia,
    i.LINHA AS linha,
    f.modelo_produto AS modelo,
    'N/D' AS descricao,
    f.quantidade,
    COALESCE(f.ultimo_usuario_editou, f.usuario_apontamento) AS usuario_final,
    'modelo' AS tipo
FROM forecast_entries f
LEFT JOIN (SELECT DISTINCT MODELO, LINHA FROM V_DEPARA_ITEM) i ON f.modelo_produto = i.MODELO
WHERE f.mes_referencia IN (?, ?, ?)
  AND f.cod_gestor IN (
      SELECT Regional FROM DW..DEPARA_COMERCIAL 
      WHERE GNV = ? OR NomeRegional = ? OR Analista = ?
  )
UNION ALL
SELECT
    s.id,
    s.codigo_gestor AS gestor,
    s.empresa,
    s.mes_referencia,
    s.linha AS linha,
    s.modelo AS modelo,
    s.descricao AS descricao,
    s.quantidade,
    'N/D' AS usuario_final,
    'sku' AS tipo
FROM forecast_entries_sku s
WHERE s.mes_referencia IN (?, ?, ?)
  AND s.codigo_gestor IN (
      SELECT Regional FROM DW..DEPARA_COMERCIAL 
      WHERE GNV = ? OR NomeRegional = ? OR Analista = ?
  )
";

// Parâmetros para os filtros de período e permissão (total: 3 + 3 + 3 + 3 = 12)
$paramsUnion = array_merge(
    $nextMonths,                     // para forecast_entries: mes_referencia IN (?, ?, ?)
    [$userName, $userName, $userName],// para forecast_entries: permissão
    $nextMonths,                     // para forecast_entries_sku: mes_referencia IN (?, ?, ?)
    [$userName, $userName, $userName] // para forecast_entries_sku: permissão
);

// Aplicar filtros adicionais na união via subquery:
$finalSql = "SELECT * FROM ( $unionSql ) AS unified WHERE 1=1";
$filterParams = [];

// Aqui os filtros são aplicados sobre os campos padronizados:
if (!empty($gestor)) {
    $finalSql .= " AND gestor = ?";
    $filterParams[] = $gestor;
}
if (!empty($empresa)) {
    $finalSql .= " AND empresa = ?";
    $filterParams[] = $empresa;
}
if (!empty($linha)) {
    $finalSql .= " AND linha = ?";
    $filterParams[] = $linha;
}
if (!empty($modelo)) {
    $finalSql .= " AND modelo = ?";
    $filterParams[] = $modelo;
}

// Ordenação
$finalSql .= " ORDER BY mes_referencia, gestor, empresa, linha, modelo";

// Query final com paginação
$finalSqlPaginated = $finalSql . " OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$paginationParams = [$offset, $rowsPerPage];

// Parâmetros finais para a query de dados
$paramsFinal = array_merge($paramsUnion, $filterParams, $paginationParams);

// --- Query de Contagem ---
// Remover a cláusula ORDER BY para a query de contagem
$finalSqlForCount = preg_replace('/ORDER BY[\s\S]*$/i', '', $finalSql);
$countSql = "SELECT COUNT(*) as total FROM ($finalSqlForCount) AS countTable";
$paramsCount = array_merge($paramsUnion, $filterParams);

$stmtCount = sqlsrv_query($conn, $countSql, $paramsCount);
if ($stmtCount === false) {
    die("<div class='alert alert-danger'>Erro ao contar registros: " . print_r(sqlsrv_errors(), true) . "</div>");
}
$countRow = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
$totalRecords = $countRow['total'] ?? 0;
$totalPages = ceil($totalRecords / $rowsPerPage);

$stmt = sqlsrv_query($conn, $finalSqlPaginated, $paramsFinal);
if ($stmt === false) {
    die("<div class='alert alert-danger'>Erro ao carregar registros: " . print_r(sqlsrv_errors(), true) . "</div>");
}

$resultados = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $resultados[] = $row;
}
?>


<style>
    .modified {
        background-color: #fff3cd !important;
    }
    .update-input {
        width: 80px;
        text-align: right;
    }
    .cell-edit-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .current-value {
        text-align: left;
    }
    .pagination {
        margin-top: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .pagination a, .pagination span {
        margin: 0 5px;
        padding: 5px 10px;
        text-decoration: none;
        border: 1px solid #ddd;
        color: #007bff;
    }
    .pagination .current-page {
        font-weight: bold;
        background-color: #007bff;
        color: #fff;
    }
    /* Estilo para controle de linhas por página no final da tabela */
    .rows-per-page {
        margin-top: 10px;
        text-align: right;
    }
</style>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-clock-history"></i> Histórico Unificado de Apontamentos</h2>
    <!-- Passo a passo para consulta e alteração -->
    <div class="alert alert-info mt-3">
      <h5>🚀 Como Consultar e Alterar Apontamentos</h5>
      <ol class="mb-0">
        <li>👉 <strong>Passo 1:</strong> Utilize os filtros abaixo para <strong>refinar a busca</strong> dos apontamentos.</li>
        <li>👉 <strong>Passo 2:</strong> A tabela exibirá os lançamentos dos <strong>próximos três meses</strong>.</li>
        <li>👉 <strong>Passo 3:</strong> Na coluna <strong>"Nova Quantidade"</strong>, digite o valor que deseja alterar.</li>
        <li>👉 <strong>Passo 4:</strong> Clique no botão "Alterar" na mesma linha para <strong>salvar a modificação</strong>.</li>
        <li>👉 <strong>Passo 5:</strong> Os registros serão atualizados em <strong>tempo real</strong>.</li>
        <li>👉 <strong>Passo 6:</strong> Alerações nos apontamentos somente são permitidos até o <strong>prazo limite</strong> de submissão para o PCP.</li>
      </ol>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm p-4 mb-4">
        <form method="GET" action="index.php" id="filterForm">
            <input type="hidden" name="page" value="historico_forecast">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="gestor" class="form-label fw-bold">Gestor:</label>
                    <select class="form-select" name="gestor" id="gestor">
                        <option value="">Todos</option>
                        <?php foreach ($gestoresOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt); ?>" <?= ($gestor == $opt) ? 'selected' : ''; ?>><?= htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="empresa" class="form-label fw-bold">Empresa:</label>
                    <select class="form-select" name="empresa" id="empresa">
                        <option value="">Todas</option>
                        <?php foreach ($empresasOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt); ?>" <?= ($empresa == $opt) ? 'selected' : ''; ?>><?= htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="linha" class="form-label fw-bold">Linha:</label>
                    <select class="form-select" name="linha" id="linha">
                        <option value="">Todas</option>
                        <?php foreach ($linhasOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt); ?>" <?= ($linha == $opt) ? 'selected' : ''; ?>><?= htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="modelo" class="form-label fw-bold">Modelo:</label>
                    <select class="form-select" name="modelo" id="modelo">
                        <option value="">Todos</option>
                        <?php foreach ($modelosOptions as $opt): ?>
                            <option value="<?= htmlspecialchars($opt); ?>" <?= ($modelo == $opt) ? 'selected' : ''; ?>><?= htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3 text-center">
                <button type="submit" class="btn btn-primary"><i class="bi bi-filter"></i> Aplicar Filtros</button>
                <a href="index.php?page=historico_forecast" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Limpar Filtros</a>
            </div>
        </form>
    </div>

    <!-- Tabela Unificada -->
    <div class="card shadow-sm p-4">
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Gestor</th>
                    <th>Empresa</th>
                    <th>Mês de Referência</th>
                    <th>Linha</th>
                    <th>Modelo</th>
                    <th>Descrição</th>
                    <th>Quantidade</th>
                    <th>Nova Quantidade</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowIndex = 0; ?>
                <?php foreach ($resultados as $row): ?>
                    <?php $rowIndex++; ?>
                    <tr data-type="<?= htmlspecialchars($row['tipo']); ?>" data-id="<?= htmlspecialchars($row['id']); ?>">
                        <td><?= htmlspecialchars($row['gestor']); ?></td>
                        <td><?= htmlspecialchars($row['empresa']); ?></td>
                        <td><?= htmlspecialchars($row['mes_referencia']); ?></td>
                        <td><?= htmlspecialchars($row['linha']); ?></td>
                        <td><?= htmlspecialchars($row['modelo']); ?></td>
                        <td><?= htmlspecialchars($row['descricao']); ?></td>
                        <td><?= number_format($row['quantidade'], 0, ',', '.'); ?></td>
                        <td>
                            <input type="number" min="0" step="1" class="form-control form-control-sm update-input" 
                                   id="update_<?= $rowIndex; ?>" placeholder="Nova quantidade">
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary alter-button" 
                                data-type="<?= htmlspecialchars($row['tipo']); ?>"
                                data-id="<?= htmlspecialchars($row['id']); ?>"
                                data-gestor="<?= htmlspecialchars($row['gestor']); ?>"
                                data-empresa="<?= htmlspecialchars($row['empresa']); ?>"
                                data-mes="<?= htmlspecialchars($row['mes_referencia']); ?>"
                                data-modelo="<?= htmlspecialchars($row['modelo']); ?>"
                                data-descricao="<?= htmlspecialchars($row['descricao']); ?>">
                                <i class="bi bi-pencil"></i> Alterar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <!-- Controle de Linhas por Página no Final -->
        <div class="rows-per-page">
            <label for="rows" class="form-label">Linhas por página:</label>
            <select class="form-select d-inline-block w-auto" id="rows" name="rows" onchange="location = this.value;">
                <?php
                // Monta os links com o parâmetro rows alterado
                foreach (['25', '50', '100'] as $rpp) {
                    $link = "?page=historico_forecast&p=1&rows=$rpp&gestor=" . urlencode($gestor) .
                            "&empresa=" . urlencode($empresa) .
                            "&linha=" . urlencode($linha) .
                            "&modelo=" . urlencode($modelo);
                    $selected = ($rowsPerPage == $rpp) ? 'selected' : '';
                    echo "<option value='$link' $selected>$rpp</option>";
                }
                ?>
            </select>
        </div>

        <!-- Controles de Paginação -->
        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?page=historico_forecast&p=<?= $currentPage - 1; ?>&rows=<?= $rowsPerPage; ?>&gestor=<?= urlencode($gestor); ?>&empresa=<?= urlencode($empresa); ?>&linha=<?= urlencode($linha); ?>&modelo=<?= urlencode($modelo); ?>">&laquo; Anterior</a>
            <?php else: ?>
                <span>&laquo; Anterior</span>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $currentPage): ?>
                    <span class="current-page"><?= $i; ?></span>
                <?php else: ?>
                    <a href="?page=historico_forecast&p=<?= $i; ?>&rows=<?= $rowsPerPage; ?>&gestor=<?= urlencode($gestor); ?>&empresa=<?= urlencode($empresa); ?>&linha=<?= urlencode($linha); ?>&modelo=<?= urlencode($modelo); ?>"><?= $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=historico_forecast&p=<?= $currentPage + 1; ?>&rows=<?= $rowsPerPage; ?>&gestor=<?= urlencode($gestor); ?>&empresa=<?= urlencode($empresa); ?>&linha=<?= urlencode($linha); ?>&modelo=<?= urlencode($modelo); ?>">Próxima &raquo;</a>
            <?php else: ?>
                <span>Próxima &raquo;</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Ao digitar no input de nova quantidade, adiciona a classe 'modified'
document.querySelectorAll('.update-input').forEach(input => {
    input.addEventListener('input', function() {
        if (this.value.trim() !== "") {
            this.classList.add('modified');
        } else {
            this.classList.remove('modified');
        }
    });
});

// Evento para o botão Alterar
document.querySelectorAll('.alter-button').forEach(button => {
    button.addEventListener('click', function() {
        let recordId = this.getAttribute('data-id');
        let recordType = this.getAttribute('data-type'); // 'modelo' ou 'sku'
        let gestor = this.getAttribute('data-gestor');
        let empresa = this.getAttribute('data-empresa');
        let mes = this.getAttribute('data-mes');
        let modelo = this.getAttribute('data-modelo');
        let descricao = this.getAttribute('data-descricao');
        let inputField = this.parentNode.parentNode.querySelector('.update-input');
        let novaQuantidade = inputField.value.trim();

        if (novaQuantidade === "") {
            alert("Nenhuma nova quantidade informada.");
            return;
        }

        if (!/^\d+$/.test(novaQuantidade) || parseInt(novaQuantidade) < 0) {
            alert("Valor inválido. Informe um número inteiro positivo.");
            return;
        }

        console.log("Dados enviados:", { recordId, recordType, gestor, empresa, mes, modelo, descricao, novaQuantidade });

        if (!confirm("Confirma a alteração para a nova quantidade?")) {
            return;
        }

        // Envio via AJAX
        fetch("index.php?page=update_forecast", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "id=" + encodeURIComponent(recordId) +
                  "&tipo=" + encodeURIComponent(recordType) +
                  "&nova_quantidade=" + encodeURIComponent(novaQuantidade)
        })
        .then(response => response.json())
        .then(data => {
            console.log("Resposta do servidor:", data);
            if (data.success) {
                alert("Registro atualizado com sucesso!");
                location.reload();
            } else {
                alert("Erro ao atualizar: " + (data.error_log || data.message));
            }
        })
        .catch(error => {
            console.error("Erro ao conectar ao servidor:", error);
            alert("Erro ao conectar ao servidor.");
        });
    });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
