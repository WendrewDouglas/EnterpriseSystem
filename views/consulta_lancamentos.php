<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: text/html; charset=UTF-8');

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('consulta_lancamentos');

// Forçar o locale para exibir meses em português
setlocale(LC_TIME, 'ptb.UTF-8', 'ptb', 'portuguese', 'portuguese_brazil');

// Cria conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Função que renderiza a tabela de lançamentos e retorna também os dados de paginação
function renderTable($conn) {
    // Capturar filtros enviados via GET
    $mesReferencia = $_GET['mesReferencia'] ?? '';
    $gestor      = $_GET['gestor'] ?? '';
    $empresa     = $_GET['empresa'] ?? '';
    $linha       = $_GET['linha'] ?? '';
    $modelo      = $_GET['modelo'] ?? '';
    $sku         = $_GET['sku'] ?? '';
    $descitem    = $_GET['descitem'] ?? '';

    // Paginação (parâmetro "p")
    $paginaAtual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
    if ($paginaAtual < 1) {
        $paginaAtual = 1;
    }
    $limite = 100;
    $offset = ($paginaAtual - 1) * $limite;

    // Construção dinâmica da consulta SQL
    $sql = "SELECT 
                CAST(TRY_PARSE('01/' + f.mes_referencia AS date USING 'pt-BR') AS DATE) AS mes_referencia, 
                f.sku, 
                f.empresa, 
                f.quantidade,
                f.codigo_gestor AS gestor,
                f.descricao,
                f.linha,
                f.modelo
            FROM forecast_system f";

    $params = [];
    $conditions = [];

    if (!empty($mesReferencia)) {
        $conditions[] = "FORMAT(TRY_PARSE('01/' + f.mes_referencia AS date USING 'pt-BR'), 'MMMM/yyyy', 'pt-BR') = ?";
        $params[] = $mesReferencia;
    }
    if (!empty($gestor)) {
        $conditions[] = "f.codigo_gestor = ?";
        $params[] = $gestor;
    }
    if (!empty($empresa)) {
        $conditions[] = "f.empresa = ?";
        $params[] = $empresa;
    }
    if (!empty($sku)) {
        $conditions[] = "f.sku = ?";
        $params[] = $sku;
    }
    if (!empty($linha)) {
        $conditions[] = "f.linha = ?";
        $params[] = $linha;
    }
    if (!empty($modelo)) {
        $conditions[] = "f.modelo = ?";
        $params[] = $modelo;
    }
    if (!empty($descitem)) {
        $conditions[] = "f.descricao = ?";
        $params[] = $descitem;
    }
    // Somente registros com quantidade > 0
    $conditions[] = "f.quantidade > 0";

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Consulta para paginação: contar total de registros
    $sqlCount = "SELECT COUNT(*) as total FROM forecast_system f";
    if (!empty($conditions)) {
        $sqlCount .= " WHERE " . implode(" AND ", $conditions);
    }
    $stmtCount = sqlsrv_query($conn, $sqlCount, $params);
    if ($stmtCount === false) {
        die("Erro ao contar registros: " . print_r(sqlsrv_errors(), true));
    }
    $rowCount = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC);
    $totalRows = $rowCount['total'];
    $totalPaginas = ceil($totalRows / $limite);

    // Consulta principal com paginação
    $sql .= " ORDER BY TRY_PARSE('01/' + f.mes_referencia AS date USING 'pt-BR') DESC OFFSET " . $offset . " ROWS FETCH NEXT " . $limite . " ROWS ONLY";
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        die("<div class='alert alert-danger'>Erro ao carregar os lançamentos: " . print_r(sqlsrv_errors(), true) . "</div>");
    }

    // Captura o HTML da tabela
    ob_start();
    ?>
    <div class="card shadow-sm p-4">
        <table class="table table-striped">
            <thead class="table-dark">
                <tr>
                    <th>Mes Referência</th>
                    <th>Gestor</th>
                    <th>Empresa</th>
                    <th>SKU</th>
                    <th>Linha</th>
                    <th>Modelo</th>
                    <th>Descrição</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
                    <tr>
                        <td>
                            <?php 
                            if ($row['mes_referencia'] instanceof DateTime) {
                                echo mb_convert_case(strftime('%B/%Y', $row['mes_referencia']->getTimestamp()), MB_CASE_TITLE, "UTF-8");
                            } else {
                                echo "Data Inválida";
                            }
                            ?>
                        </td>
                        <td><?= htmlspecialchars($row['gestor']); ?></td>
                        <td><?= htmlspecialchars($row['empresa']); ?></td>
                        <td><?= htmlspecialchars($row['sku']); ?></td>
                        <td><?= htmlspecialchars($row['linha']); ?></td>
                        <td><?= htmlspecialchars($row['modelo']); ?></td>
                        <td><?= htmlspecialchars($row['descricao']); ?></td>
                        <td><?= number_format($row['quantidade'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php
    $html = ob_get_clean();
    return [
        'html' => $html,
        'paginaAtual' => $paginaAtual,
        'totalPaginas' => $totalPaginas
    ];
}

// Se a requisição vier com o parâmetro fetch=table, retorna somente a tabela (para atualização via AJAX)
if (isset($_GET['fetch']) && $_GET['fetch'] == 'table') {
    echo renderTable($conn)['html'];
    exit;
}

// --- Função para obter opções de filtros ---
function obterOpcoesFiltro($conn, $campo, $tabela) {
    $query = "SELECT DISTINCT $campo FROM $tabela WHERE $campo IS NOT NULL ORDER BY $campo ASC";
    $stmt = sqlsrv_query($conn, $query);
    $opcoes = [];
    if ($stmt === false) {
        die("Erro na consulta SQL: " . print_r(sqlsrv_errors(), true));
    }
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $opcoes[] = $row[$campo];
    }
    return $opcoes;
}

// Buscar opções para os filtros (meses, gestores, etc.)
$opcoesMeses = [];
$stmtMeses = sqlsrv_query($conn, "
    SELECT FORMAT(TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR'), 'MMMM/yyyy', 'pt-BR') AS mes_referencia
    FROM forecast_system
    WHERE TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR') IS NOT NULL
    GROUP BY FORMAT(TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR'), 'MMMM/yyyy', 'pt-BR'), TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR')
    ORDER BY TRY_PARSE('01/' + mes_referencia AS date USING 'pt-BR') DESC
");
if ($stmtMeses === false) {
    die("Erro ao carregar os meses de referência: " . print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($stmtMeses, SQLSRV_FETCH_ASSOC)) {
    if (!in_array($row['mes_referencia'], $opcoesMeses)) {
        $opcoesMeses[] = mb_convert_case($row['mes_referencia'], MB_CASE_TITLE, "UTF-8");
    }
}

$opcoesGestores = obterOpcoesFiltro($conn, 'codigo_gestor', 'forecast_system');
$opcoesEmpresas = obterOpcoesFiltro($conn, 'empresa', 'forecast_system');
$opcoesSKU      = obterOpcoesFiltro($conn, 'sku', 'forecast_system');
$opcoesDesc     = obterOpcoesFiltro($conn, 'descricao', 'forecast_system');
$opcoesLinhas   = obterOpcoesFiltro($conn, 'linha', 'forecast_system');
$opcoesModelos  = obterOpcoesFiltro($conn, 'modelo', 'forecast_system');

// Gerar os próximos 3 meses para atualização
$nextThree = [];
$current = new DateTime();
for ($i = 0; $i <= 3; $i++) {
    $nextThree[] = (clone $current)->modify("+{$i} month")->format('m/Y');
}

// Configuração da página (inclui header e sidebar novamente para esta página)
$pageTitle = 'Consulta de Lançamentos - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

$resultTable = renderTable($conn);
?>

<div class="content">
    <h2 class="mb-4"><i class="bi bi-search"></i> Consulta de Lançamentos</h2>
    
    <!-- Área para atualização do Forecast System -->
    <div class="mb-4 text-center">
        <!-- Lista suspensa para selecionar o mês para atualização -->
        <label for="mesAtualizacao" class="form-label">Mês para Atualização</label>
        <select id="mesAtualizacao" class="form-select" style="max-width: 200px; display: inline-block; margin-right: 10px;">
            <option value="">Selecione</option>
            <?php foreach ($nextThree as $mes): ?>
                <option value="<?= htmlspecialchars($mes) ?>"><?= htmlspecialchars($mes) ?></option>
            <?php endforeach; ?>
        </select>
        <button id="btnAtualizaForecast" class="btn btn-warning">
            <i class="bi bi-arrow-repeat"></i> Atualizar Forecast System
        </button>
        <!-- Área para exibir o resultado da atualização -->
        <div id="resultadoAtualizacao" style="margin-top: 15px;"></div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm p-4 mb-4">
        <form method="GET" action="index.php" id="filterForm">
            <input type="hidden" name="page" value="consulta_lancamentos">
            <div class="row g-3">
                <!-- Filtro Mês de Referência -->
                <div class="col-md-3">
                    <label for="mesReferencia" class="form-label">Mês de Referência</label>
                    <select name="mesReferencia" id="mesReferencia" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesMeses as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['mesReferencia'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filtro Gestor -->
                <div class="col-md-3">
                    <label for="gestor" class="form-label">Gestor</label>
                    <select name="gestor" id="gestor" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesGestores as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['gestor'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filtro Empresa -->
                <div class="col-md-3">
                    <label for="empresa" class="form-label">Empresa</label>
                    <select name="empresa" id="empresa" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($opcoesEmpresas as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['empresa'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filtro SKU -->
                <div class="col-md-3">
                    <label for="sku" class="form-label">SKU</label>
                    <select name="sku" id="sku" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesSKU as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['sku'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filtro Linha -->
                <div class="col-md-3">
                    <label for="linha" class="form-label">Linha</label>
                    <select name="linha" id="linha" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($opcoesLinhas as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['linha'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filtro Modelo -->
                <div class="col-md-3">
                    <label for="modelo" class="form-label">Modelo</label>
                    <select name="modelo" id="modelo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($opcoesModelos as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['modelo'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Filtro Descrição -->
                <div class="col-md-3">
                    <label for="descitem" class="form-label">Descrição</label>
                    <select name="descitem" id="descitem" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($opcoesDesc as $opcao): ?>
                            <option value="<?= htmlspecialchars($opcao) ?>" <?= (($_GET['descitem'] ?? '') == $opcao) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcao) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-3 text-center">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Aplicar Filtros
                </button>
                <a href="index.php?page=consulta_lancamentos" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> Limpar Filtros
                </a>
                <a href="javascript:void(0);" class="btn btn-success" 
                   onclick="iniciarDownloadEReload('/forecast/views/export_forecast.php?mesReferencia=<?= urlencode($_GET['mesReferencia'] ?? '') ?>&gestor=<?= urlencode($_GET['gestor'] ?? '') ?>&empresa=<?= urlencode($_GET['empresa'] ?? '') ?>&linha=<?= urlencode($_GET['linha'] ?? '') ?>&modelo=<?= urlencode($_GET['modelo'] ?? '') ?>&sku=<?= urlencode($_GET['sku'] ?? '') ?>&descitem=<?= urlencode($_GET['descitem'] ?? '') ?>')">
                    <i class="bi bi-file-earmark-excel"></i> Exportar para Excel
                </a>
            </div>
        </form>
    </div>

    <!-- Tabela de Lançamentos -->
    <div id="tableContainer">
        <?= $resultTable['html'] ?>
    </div>

    <!-- Navegação entre páginas -->
    <nav aria-label="Navegação de páginas">
        <ul class="pagination justify-content-center">
            <?php 
            // Botão "Anterior"
            if ($resultTable['paginaAtual'] > 1) {
                $params = $_GET;
                $params['p'] = $resultTable['paginaAtual'] - 1;
                echo '<li class="page-item"><a class="page-link" href="index.php?' . http_build_query($params) . '">&laquo;</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
            }

            // Janela de páginas
            $max_links = 5; // Número máximo de links exibidos
            $paginaAtual = $resultTable['paginaAtual'];
            $totalPaginas = $resultTable['totalPaginas'];
            $start = max(1, $paginaAtual - floor($max_links / 2));
            $end = min($totalPaginas, $start + $max_links - 1);
            if ($start > 1) {
                $params = $_GET;
                $params['p'] = 1;
                echo '<li class="page-item"><a class="page-link" href="index.php?' . http_build_query($params) . '">1</a></li>';
                if ($start > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            for ($i = $start; $i <= $end; $i++) {
                $params = $_GET;
                $params['p'] = $i;
                $active = ($i == $paginaAtual) ? 'active' : '';
                echo '<li class="page-item ' . $active . '"><a class="page-link" href="index.php?' . http_build_query($params) . '">' . $i . '</a></li>';
            }
            if ($end < $totalPaginas) {
                if ($end < $totalPaginas - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                $params = $_GET;
                $params['p'] = $totalPaginas;
                echo '<li class="page-item"><a class="page-link" href="index.php?' . http_build_query($params) . '">' . $totalPaginas . '</a></li>';
            }

            // Botão "Próxima"
            if ($paginaAtual < $totalPaginas) {
                $params = $_GET;
                $params['p'] = $paginaAtual + 1;
                echo '<li class="page-item"><a class="page-link" href="index.php?' . http_build_query($params) . '">&raquo;</a></li>';
            } else {
                echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
            }
            ?>
        </ul>
    </nav>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btnAtualiza = document.getElementById("btnAtualizaForecast");
    const divResultado = document.getElementById("resultadoAtualizacao");
    
    btnAtualiza.addEventListener("click", function () {
        const mesAtualizacao = document.getElementById("mesAtualizacao").value;
        if (!mesAtualizacao) {
            alert("Selecione um Mês para Atualização!");
            return;
        }
        
        divResultado.innerHTML = "Atualizando, aguarde...";
        // Chama o endpoint para atualização passando o mês selecionado como parâmetro
        fetch(`../views/run_forecast_update.php?mesReferencia=${encodeURIComponent(mesAtualizacao)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    divResultado.innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            😞 Ocorreu um erro: ${data.error}
                        </div>`;
                } else {
                    let html = `
                        <div class="card text-white bg-success mb-3" style="max-width: 30rem; margin: auto;">
                            <div class="card-header text-center" style="font-size: 2rem; font-weight: bold;">
                                🎉 Atualização Concluída
                            </div>
                            <div class="card-body">
                                <p class="card-text">Mês atualizado: <strong>${data["Mês atualizado"]}</strong></p>
                                <p class="card-text">Total de itens gerados: <strong>${data["Quantidade de itens"]}</strong></p>
                                <p class="card-text">Total de novos itens inseridos: <strong>${data["Quantidade atualizada"]}</strong></p>
                                <p class="card-text">Desvio por arredondamento: <strong>${data["Quantidade desviada por arredondamento"]}</strong></p>
                            </div>
                        </div>`;
                    divResultado.innerHTML = html;
                    atualizarTabela();
                }
            })
            .catch(error => {
                divResultado.innerHTML = "Erro: " + error;
            });
    });

    function atualizarTabela() {
        fetch("index.php?page=consulta_lancamentos&fetch=table")
            .then(response => response.text())
            .then(html => {
                document.getElementById('tableContainer').innerHTML = html;
            })
            .catch(error => {
                console.error("Erro ao atualizar a tabela: ", error);
            });
    }

    // Funções para atualizar selects de modelo e linha
    const linhaSelect = document.getElementById("linha");
    const modeloSelect = document.getElementById("modelo");

    linhaSelect.addEventListener("change", function () {
        const linhaSelecionada = this.value;
        modeloSelect.innerHTML = '<option value="">Todos</option>';
        if (linhaSelecionada) {
            fetch(`index.php?page=consulta_lancamentos&fetch=modelos&linha=${encodeURIComponent(linhaSelecionada)}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(modelo => {
                        const option = document.createElement("option");
                        option.value = modelo;
                        option.textContent = modelo;
                        modeloSelect.appendChild(option);
                    });
                });
        }
    });

    modeloSelect.addEventListener("change", function () {
        const modeloSelecionado = this.value;
        linhaSelect.innerHTML = '<option value="">Todas</option>';
        if (modeloSelecionado) {
            fetch(`index.php?page=consulta_lancamentos&fetch=linhas&modelo=${encodeURIComponent(modeloSelecionado)}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(linha => {
                        const option = document.createElement("option");
                        option.value = linha;
                        option.textContent = linha;
                        linhaSelect.appendChild(option);
                    });
                });
        }
    });
});
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
