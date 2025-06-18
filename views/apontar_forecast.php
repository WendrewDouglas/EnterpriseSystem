<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('apontar_forecast');

// Configuração da página
$pageTitle = 'Apontar Forecast - Forecast System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Criar conexão com o banco
$db = new Database();
$conn = $db->getConnection();

// Forçar o locale para exibir meses em português
setlocale(LC_TIME, 'ptb.UTF-8', 'ptb', 'portuguese', 'portuguese_brazil');

// Obtém o usuário logado
$userName = $_SESSION['user_name'] ?? null;
if (!$userName) {
    die("<div class='alert alert-danger'>Erro: Usuário não identificado. Faça login novamente.</div>");
}

// Verificar se o usuário está cadastrado na tabela DEPARA_COMERCIAL como GNV, NomeRegional ou Analista
$sql = "SELECT Regional, GNV, NomeRegional, Analista FROM DW..DEPARA_COMERCIAL 
        WHERE GNV = ? OR NomeRegional = ? OR Analista = ?";
$params = [$userName, $userName, $userName];
$stmt = sqlsrv_query($conn, $sql, $params);
$gestorInfo = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Verifica se o usuário está habilitado
$usuarioHabilitado = ($gestorInfo !== null && $gestorInfo !== false);
$regionaisPermitidas = [];
if ($usuarioHabilitado) {
    do {
        if (!empty($gestorInfo['Regional'])) {
            $regionaisPermitidas[] = $gestorInfo['Regional'];
        }
    } while ($gestorInfo = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC));
}
$regionaisPermitidas = array_unique($regionaisPermitidas);
if (empty($regionaisPermitidas)) {
    $usuarioHabilitado = false;
}

// Mapeamento de Empresas para CD
$mapaCD = [
    '1001' => 'Matriz',
    '1002' => 'Feira de Santana'
];

// Capturar filtros selecionados pelo usuário
$cdSelecionado = $_POST['cd'] ?? '';
$regionalSelecionado = $_POST['regional'] ?? '';
$modeloSelecionado = $_POST['modelo'] ?? ''; // Novo: captura o modelo selecionado
$empresaSelecionada = isset($mapaCD[$cdSelecionado]) ? $cdSelecionado : null;

// Função para determinar os próximos 3 meses (o próximo mês é o definitivo)
function obterProximosMeses($quantidade = 3) {
    $meses = [];
    $data = new DateTime('first day of next month');
    for ($i = 0; $i < $quantidade; $i++) {
        $meses[] = [
            'label' => ucfirst(strftime('%B de %Y', $data->getTimestamp())),
            'value' => $data->format('m/Y')
        ];
        $data->modify("+1 month");
    }
    return $meses;
}
$mesesForecast = obterProximosMeses();

// Verificar se já existe forecast definitivo para o próximo mês
$forecastExiste = false;
if (!empty($cdSelecionado) && !empty($regionalSelecionado)) {
    $mesReferencia = $mesesForecast[0]['value']; // próximo mês
    $sqlForecast = "SELECT 1 FROM forecast_entries 
                    WHERE empresa = ? AND cod_gestor = ? AND mes_referencia = ? AND finalizado = 1";
    $paramsForecast = [$cdSelecionado, $regionalSelecionado, $mesReferencia];
    $stmtForecast = sqlsrv_query($conn, $sqlForecast, $paramsForecast);
    if ($stmtForecast !== false && sqlsrv_fetch_array($stmtForecast, SQLSRV_FETCH_ASSOC)) {
        $forecastExiste = true;
    }
}

// Função para buscar os dados da carteira, garantindo que o primeiro mês inclua valores anteriores
function obterQuantidadePorModelo($conn, $cdSelecionado, $regionalSelecionado, $mesesForecast, $modeloSelecionado) {
  $params = [];
  $carteiraColumns = "";

  foreach ($mesesForecast as $index => $mes) {
      list($mesNum, $ano) = explode('/', $mes['value']);

      if ($index == 0) {
          // Primeiro mês (exemplo: abril) -> Somar TODOS os valores anteriores + abril
          $carteiraColumns .= "ISNULL(SUM(CASE 
              WHEN CONVERT(DATE, Data_agendada) <= EOMONTH(DATEFROMPARTS(?, ?, 1)) 
              THEN CAST(Quantidade AS INT) ELSE 0 END), 0) as Carteira_Mes" . ($index + 1) . ", ";
          $params[] = (int)$ano;
          $params[] = (int)$mesNum;
      } else {
          // Para os meses seguintes (exemplo: maio, junho), considerar apenas os valores daquele mês específico
          $carteiraColumns .= "ISNULL(SUM(CASE 
              WHEN YEAR(CONVERT(DATE, Data_agendada)) = ? 
              AND MONTH(CONVERT(DATE, Data_agendada)) = ? 
              THEN CAST(Quantidade AS INT) ELSE 0 END), 0) as Carteira_Mes" . ($index + 1) . ", ";
          $params[] = (int)$ano;
          $params[] = (int)$mesNum;
      }
  }

  $carteiraColumns = rtrim($carteiraColumns, ", ");

  $sql = "SELECT 
              Cod_produto AS Modelo_Produto,
              Empresa,
              $carteiraColumns
          FROM V_CARTEIRA_PEDIDOS
          WHERE Empresa = ?
            AND Cod_regional = ?
            AND Cod_produto = ?
          GROUP BY Cod_produto, Empresa
          ORDER BY Cod_produto";

  $params[] = $cdSelecionado;
  $params[] = $regionalSelecionado;
  $params[] = $modeloSelecionado;

  $stmt = sqlsrv_query($conn, $sql, $params);
  if ($stmt === false) {
      die("<div class='alert alert-danger'>Erro ao carregar dados da carteira.</div>");
  }

  $resultados = [];
  while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
      $resultados[] = $row;
  }
  return $resultados;
}




// Obter os dados da carteira com a lógica correta
$resultados = obterQuantidadePorModelo($conn, $cdSelecionado, $regionalSelecionado, $mesesForecast, $modeloSelecionado);

// Buscar a lista de SKUs para o modo "por SKU"
$sqlSku = "SELECT CODITEM, DESCITEM, LINHA, MODELO, STATUS FROM V_DEPARA_ITEM ORDER BY CODITEM";
$stmtSku = sqlsrv_query($conn, $sqlSku);
$skuList = [];
while ($rowSku = sqlsrv_fetch_array($stmtSku, SQLSRV_FETCH_ASSOC)) {
    $skuList[] = $rowSku;
}
?>

<!DOCTYPE html>
<div class="content">
  <h2 class="mb-4"><i class="bi bi-graph-up"></i> Apontar Forecast</h2>

  <?php if ($usuarioHabilitado): ?>

    <div class="alert alert-info mt-3">
      <h5>🚀 Passo a Passo para Apontar o Forecast</h5>
      <ol class="mb-0">
        <li>👉 <strong>Passo 1:</strong> Selecione o <em>Centro de Distribuição</em> e o <em>Código Regional</em> para o qual deseja fazer o apontamento.</li>
        <li>👉 <strong>Passo 2:</strong> Por padrão, o sistema utiliza o modo <strong>por Modelo</strong>. Se desejar apontar por SKU, clique em <strong>Apontar por SKU</strong>.</li>
        <li>👉 <strong>Passo 3:</strong> No modo por Modelo, selecione a <strong>Linha</strong> e o <strong>Modelo</strong> do produto.</li>
        <li>👉 <strong>Passo 4:</strong> Uma visão de sua <strong>Carteira</strong> (vendas agendadas) será exibida abaixo, mostrando os pedidos já programados para os próximos meses.</li>
        <li>👉 <strong>Passo 5:</strong> Preencha os valores de forecast para os 3 próximos meses e clique em <strong>Adicionar</strong> para acumular os apontamentos.</li>
        <li>👉 <strong>Passo 6:</strong> Após revisar a lista, clique em <strong>Enviar Forecast</strong> para confirmar o apontamento.</li>
      </ol>
    </div>

    <!-- Filtro para selecionar o CD e Regional -->
    <form action="index.php?page=apontar_forecast" method="POST" id="filterForm">
      <div class="row g-3">
        <div class="col-md-4">
          <label for="cd" class="form-label fw-bold">Centro de Distribuição:</label>
          <select class="form-select" id="cd" name="cd" required>
            <?php if (!$cdSelecionado): ?> 
              <option value="" selected disabled>Selecione o CD</option> 
            <?php endif; ?>
            <?php foreach ($mapaCD as $key => $value): ?>
              <option value="<?= $key; ?>" <?= ($cdSelecionado == $key) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($value); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span id="mensagemCD" style="color: red; font-weight: bold; display: <?= $cdSelecionado ? 'none' : 'block'; ?>;">
            Informe um CD para apontar o forecast.
          </span>
        </div>
        <div class="col-md-4">
          <label for="regional" class="form-label fw-bold">Código Regional:</label>
          <select class="form-select" id="regional" name="regional" required>
            <?php if (!$regionalSelecionado): ?>
              <option value="" selected disabled>Selecione o Código Regional</option>
            <?php endif; ?>
            <?php foreach ($regionaisPermitidas as $regional): ?>
              <option value="<?= htmlspecialchars($regional); ?>" <?= ($regionalSelecionado == $regional) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($regional); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span id="mensagemRegional" style="color: red; font-weight: bold; display: <?= $regionalSelecionado ? 'none' : 'block'; ?>;">
            Informe um Código Regional para apontar o forecast.
          </span>
        </div>
        <div class="col-md-4">
          <!-- Botão para alternar para o modo "Apontar por SKU" -->
          <?php if (!empty($cdSelecionado) && !empty($regionalSelecionado) && !$forecastExiste): ?>
            <div id="toggleSkuContainer" class="mt-3 text-center">
              <button type="button" id="btnToggleSku" class="btn btn-warning">Apontar por SKU</button>
            </div>
          <?php endif; ?>
          <!-- Container para o botão Voltar, iniciado oculto -->
          <div id="voltarSkuContainer" class="mt-3 text-center" style="display: none;">
            <button type="button" id="btnVoltar" class="btn btn-warning">Voltar para o apontamento por modelo</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Mensagens de atualização -->
    <div id="updateMessage" class="alert alert-warning text-center mt-3" style="display: none;">
      Atualizando dados...
    </div>
    <div id="successMessage" class="alert alert-success text-center mt-3" style="display: none;">
      Dados atualizados com sucesso!
    </div>

    <?php if (empty($cdSelecionado) || empty($regionalSelecionado) || $forecastExiste): ?>
      <div class="text-center mt-3">
        <?php if($forecastExiste): ?>
          <div class="alert alert-warning">
            <i class="bi bi-emoji-smile"></i> Já existe apontamento de forecast para o regional selecionado. Caso queira editar, acesse o histórico de apontamentos, <a href="index.php?page=historico_forecast">clique aqui</a>.
          </div>
        <?php endif; ?>
        <img src="../public/assets/img/apontar forecast.jpg" alt="Apontar Forecast" class="img-fluid" />
      </div>
    <?php else: ?>
      <!-- Container do formulário de apontamento por Modelo -->
      <div id="modeloForecastContainer">
        <div class="card shadow-sm p-3 mt-4">
        <h4><i class="bi bi-box-seam"></i> Apontamento por Modelo</h4>
          <form action="index.php?page=process_forecast" method="POST" id="modeloForecastForm">
            <input type="hidden" name="cd" value="<?= htmlspecialchars($cdSelecionado); ?>">
            <input type="hidden" name="regional" value="<?= htmlspecialchars($regionalSelecionado); ?>">
            <input type="hidden" name="usuario_apontamento" value="<?= htmlspecialchars($userName); ?>">
            <div class="row g-3 align-items-end">
              <div class="col-md-3">
                <label for="modeloLinha" class="form-label fw-bold">Linha:</label>
                <select class="form-select" id="modeloLinha">
                  <option value="" selected>Selecione a Linha</option>
                  <!-- Opções serão populadas dinamicamente -->
                </select>
              </div>
              <div class="col-md-3">
                <label for="modeloModelo" class="form-label fw-bold">Modelo:</label>
                <select class="form-select" id="modeloModelo" name="modelo">
                  <option value="" selected>Selecione o Modelo</option>
                  <!-- Opções serão populadas dinamicamente -->
                </select>
              </div>
              <div class="col-md-6">
                <!-- Espaço reservado para alinhamento -->
              </div>
            </div>
            <!-- Container para a visão de carteira (carteira de pedidos) -->
            <div id="carteiraContainer" class="row g-3 mt-3 align-items-end">
              <!-- A tabela de carteira será carregada via AJAX quando o usuário selecionar Linha e Modelo -->
            </div>
            <div class="row g-3 mt-3 align-items-end">
              <?php foreach ($mesesForecast as $index => $mes): ?>
                <div class="col-md-4">
                  <label for="modeloQuantidade<?= $index; ?>" class="form-label fw-bold"> Forecast para <?= htmlspecialchars($mes['label']); ?>:</label>
                  <input type="number" class="form-control" id="modeloQuantidade<?= $index; ?>" data-mes="<?= htmlspecialchars($mes['value']); ?>" value="0" min="0">
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mt-3">
              <button type="button" id="btnAdicionarModelo" class="btn btn-secondary"><i class="bi bi-upload"></i> Adicionar</button>
            </div>
            <!-- Container para inputs ocultos que acumularão os dados para envio -->
            <div id="modeloHiddenInputs"></div>
          </form>
          <hr>
          <h5>Lista de Apontamentos por Modelo</h5>
          <table class="table table-striped" id="modeloForecastList">
            <thead class="table-dark">
              <tr>
                <th>Linha</th>
                <th>Modelo</th>
                <?php foreach ($mesesForecast as $mes): ?>
                  <th><?= htmlspecialchars($mes['label']); ?></th>
                <?php endforeach; ?>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <!-- Linhas adicionadas dinamicamente -->
            </tbody>
          </table>
          <div class="mt-3 text-center">
            <button type="button" id="btnEnviarModelo" class="btn btn-primary"><i class="bi bi-send"></i> Enviar Forecast</button>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Container do modo "Apontamento por SKU" (inicialmente oculto) -->
    <div id="skuForecastContainer" style="display: none;">
      <div class="card shadow-sm p-3 mt-4">
      <h4><i class="bi bi-upc-scan"></i> Apontamento por SKU</h4>
        <form id="skuForecastForm" action="index.php?page=process_forecast_sku" method="POST">
          <input type="hidden" name="cd" value="<?= htmlspecialchars($cdSelecionado); ?>">
          <input type="hidden" name="regional" value="<?= htmlspecialchars($regionalSelecionado); ?>">
          <input type="hidden" name="usuario_apontamento" value="<?= htmlspecialchars($userName); ?>">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label for="skuLinha" class="form-label fw-bold">Linha:</label>
              <select class="form-select" id="skuLinha">
                <option value="" selected>Selecione a Linha</option>
                <!-- Opções serão populadas dinamicamente -->
              </select>
            </div>
            <div class="col-md-3">
              <label for="skuModelo" class="form-label fw-bold">Modelo:</label>
              <select class="form-select" id="skuModelo">
                <option value="" selected>Selecione o Modelo</option>
                <!-- Opções serão populadas dinamicamente -->
              </select>
            </div>
            <div class="col-md-6">
              <label for="skuSelect" class="form-label fw-bold">SKU:</label>
              <select class="form-select" id="skuSelect" name="sku">
                <option value="" selected disabled>Selecione um SKU</option>
                <!-- Será filtrado conforme Linha e Modelo -->
              </select>
            </div>
          </div>
          <!-- Linha de Quantidades para os 3 meses -->
          <div class="row g-3 mt-3 align-items-end">
            <?php foreach ($mesesForecast as $index => $mes): ?>
              <div class="col-md-4">
                <label for="skuQuantidade<?= $index; ?>" class="form-label fw-bold"> Forecast para <?= htmlspecialchars($mes['label']); ?>:</label>
                <input type="number" class="form-control" id="skuQuantidade<?= $index; ?>" data-mes="<?= htmlspecialchars($mes['value']); ?>" value="0" min="0">
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-3">
            <button type="button" id="btnAdicionarSku" class="btn btn-secondary"><i class="bi bi-upload"></i> Adicionar</button>
          </div>
          <!-- Container para inputs ocultos que acumularão os dados para envio -->
          <div id="skuHiddenInputs"></div>
        </form>
        <hr>
        <h5>Lista de Apontamentos por SKU</h5>
        <table class="table table-striped" id="skuForecastList">
          <thead class="table-dark">
            <tr>
              <th>SKU</th>
              <?php foreach ($mesesForecast as $mes): ?>
                <th><?= htmlspecialchars($mes['label']); ?></th>
              <?php endforeach; ?>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <!-- Linhas serão adicionadas dinamicamente -->
          </tbody>
        </table>
        <div class="mt-3 text-center">
          <button type="button" id="btnEnviarSku" class="btn btn-primary"><i class="bi bi-send"></i> Enviar Forecast</button>
        </div>
      </div>
    </div>

  <?php else: ?>
    <div class="alert alert-info text-center">
      <h4><i class="bi bi-exclamation-triangle-fill"></i> Acesso Restrito!</h4>
      <p>Poxa, esta área é exclusiva para <strong>gestores e analistas comerciais</strong>.</p>
      <p>Se você é um desses e está vendo essa mensagem, abra um chamado na TI para resolvermos rapidinho!</p>
      <p>
        <i class="bi bi-person-check" style="font-size: 1.5rem;"></i>
        <i class="bi bi-headset" style="font-size: 1.5rem;"></i>
        <i class="bi bi-tools" style="font-size: 1.5rem;"></i>
      </p>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<!-- Injetar a lista de SKUs no JavaScript -->
<script>
  var skuData = <?php echo json_encode($skuList); ?>;
</script>

<!-- JavaScript principal -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  /* ================== Variáveis Globais para Filtros ================== */
  let prevCd = "";
  let prevRegional = "";

  /* ================== Eventos do Formulário de Filtro ================== */
  const cdSelect = document.getElementById("cd");
  const regionalSelect = document.getElementById("regional");
  const mensagemCD = document.getElementById("mensagemCD");
  const mensagemRegional = document.getElementById("mensagemRegional");
  const forecastInputs = document.querySelectorAll(".forecast-input");
  const enviarForecastButton = document.getElementById("enviarForecast");
  const updateMessage = document.getElementById("updateMessage");
  const successMessage = document.getElementById("successMessage");
  const filterForm = document.getElementById("filterForm");

  // Armazenar os valores anteriores para detectar mudanças com dados não enviados
  cdSelect.addEventListener("focus", function () {
    prevCd = cdSelect.value;
  });
  regionalSelect.addEventListener("focus", function () {
    prevRegional = regionalSelect.value;
  });

  function atualizarEstadoCampos() {
    const cdSelecionado = cdSelect.value !== "";
    const regionalSelecionado = regionalSelect.value !== "";
    const habilitarForm = cdSelecionado && regionalSelecionado;
    forecastInputs.forEach(input => input.disabled = !habilitarForm);
    if (enviarForecastButton) {
      enviarForecastButton.disabled = !habilitarForm;
    }
    mensagemCD.style.display = cdSelecionado ? "none" : "block";
    mensagemRegional.style.display = regionalSelecionado ? "none" : "block";
  }

  if (cdSelect && filterForm) {
    cdSelect.addEventListener("change", function () {
      let unsentData = false;
      const modeloHidden = document.getElementById("modeloHiddenInputs");
      const skuHidden = document.getElementById("skuHiddenInputs");
      if ((modeloHidden && modeloHidden.innerHTML.trim() !== "") ||
          (skuHidden && skuHidden.innerHTML.trim() !== "")) {
        unsentData = true;
      }
      if (unsentData) {
        if (!confirm("Você possui apontamentos não enviados. Ao alterar o CD, estes dados serão perdidos. Deseja continuar?")) {
          cdSelect.value = prevCd;
          return;
        }
      }
      updateMessage.style.display = "block";
      successMessage.style.display = "none";
      filterForm.submit();
    });
  }

  if (regionalSelect && filterForm) {
    regionalSelect.addEventListener("change", function () {
      let unsentData = false;
      const modeloHidden = document.getElementById("modeloHiddenInputs");
      const skuHidden = document.getElementById("skuHiddenInputs");
      if ((modeloHidden && modeloHidden.innerHTML.trim() !== "") ||
          (skuHidden && skuHidden.innerHTML.trim() !== "")) {
        unsentData = true;
      }
      if (unsentData) {
        if (!confirm("Você possui apontamentos não enviados. Ao alterar o Código Regional, estes dados serão perdidos. Deseja continuar?")) {
          regionalSelect.value = prevRegional;
          return;
        }
      }
      updateMessage.style.display = "block";
      successMessage.style.display = "none";
      filterForm.submit();
    });
  }
  atualizarEstadoCampos();

  /* ================== Modo Apontamento por SKU ================== */
  function populateLinhaModeloSku() {
    const linhaSelect = document.getElementById("skuLinha");
    const modeloSelect = document.getElementById("skuModelo");
    linhaSelect.innerHTML = '<option value="" selected>Selecione a Linha</option>';
    modeloSelect.innerHTML = '<option value="" selected>Selecione o Modelo</option>';
    if (skuData && skuData.length > 0) {
      let linhas = [...new Set(skuData.map(item => item.LINHA).filter(Boolean))];
      linhas.forEach(linha => {
        let opt = document.createElement("option");
        opt.value = linha;
        opt.text = linha;
        linhaSelect.appendChild(opt);
      });
    }
  }
  function updateModeloDropdownSku() {
    const linhaSelected = document.getElementById("skuLinha").value;
    const modeloSelect = document.getElementById("skuModelo");
    modeloSelect.innerHTML = '<option value="" selected>Selecione o Modelo</option>';
    if (linhaSelected && skuData) {
      let modelos = skuData.filter(item => item.LINHA === linhaSelected)
                           .map(item => item.MODELO)
                           .filter(Boolean);
      modelos = [...new Set(modelos)];
      modelos.forEach(modelo => {
        let opt = document.createElement("option");
        opt.value = modelo;
        opt.text = modelo;
        modeloSelect.appendChild(opt);
      });
    }
  }
  function filterSkuDropdown() {
    const linhaSelected = document.getElementById("skuLinha").value;
    const modeloSelected = document.getElementById("skuModelo").value;
    const skuSelect = document.getElementById("skuSelect");
    skuSelect.innerHTML = '<option value="" selected disabled>Selecione um SKU</option>';
    if (skuData) {
      let filteredData = skuData;
      if (linhaSelected) {
        filteredData = filteredData.filter(item => item.LINHA === linhaSelected);
      }
      if (modeloSelected) {
        filteredData = filteredData.filter(item => item.MODELO === modeloSelected);
      }
      filteredData.forEach(item => {
        let opt = document.createElement("option");
        opt.value = item.CODITEM;
        opt.text = item.CODITEM + " - " + item.DESCITEM + " (" + item.STATUS + ")";
        skuSelect.appendChild(opt);
      });
    }
  }
  populateLinhaModeloSku();
  document.getElementById("skuLinha").addEventListener("change", function () {
    updateModeloDropdownSku();
    filterSkuDropdown();
  });
  document.getElementById("skuModelo").addEventListener("change", filterSkuDropdown);

  // Adicionar apontamentos no modo SKU
  document.getElementById("btnAdicionarSku").addEventListener("click", function () {
    const skuSelect = document.getElementById("skuSelect");
    const sku = skuSelect.value;
    const mesInputs = [
      document.getElementById("skuQuantidade0"),
      document.getElementById("skuQuantidade1"),
      document.getElementById("skuQuantidade2")
    ];
    let quantities = mesInputs.map(input => input.value);
    let mesValues = mesInputs.map(input => input.getAttribute("data-mes"));
    let valid = sku !== "" && quantities.some(q => parseInt(q) > 0);
    if (!valid) {
      alert("Por favor, selecione um SKU e informe ao menos uma quantidade válida para os três meses.");
      return;
    }
    const tableBody = document.getElementById("skuForecastList").getElementsByTagName("tbody")[0];
    const newRow = tableBody.insertRow();
    newRow.insertCell(0).innerHTML = sku;
    for (let i = 0; i < mesValues.length; i++) {
      let cell = newRow.insertCell(-1);
      cell.innerHTML = quantities[i];
    }
    newRow.insertCell(-1).innerHTML = '<button type="button" class="btn btn-danger btn-sm btnExcluirSku">Excluir</button>';
    const hiddenContainer = document.getElementById("skuHiddenInputs");
    let hiddenHtml = "";
    for (let i = 0; i < mesValues.length; i++) {
      hiddenHtml += '<input type="hidden" name="skuForecast[' + sku + '][' + mesValues[i] + ']" value="' + quantities[i] + '">';
    }
    hiddenContainer.innerHTML += hiddenHtml;
    skuSelect.selectedIndex = 0;
    mesInputs.forEach(input => input.value = "0");
  });
  document.getElementById("skuForecastList").addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("btnExcluirSku")) {
      const row = e.target.parentNode.parentNode;
      row.parentNode.removeChild(row);
      const tableBody = document.getElementById("skuForecastList").getElementsByTagName("tbody")[0];
      let newHiddenHtml = "";
      for (let i = 0; i < tableBody.rows.length; i++) {
        const cells = tableBody.rows[i].cells;
        const skuVal = cells[0].innerText;
        for (let j = 0; j < skuMeses.length; j++) {
          let quantidadeVal = cells[j + 1].innerText;
          newHiddenHtml += '<input type="hidden" name="skuForecast[' + skuVal + '][' + skuMeses[j] + ']" value="' + quantidadeVal + '">';
        }
      }
      document.getElementById("skuHiddenInputs").innerHTML = newHiddenHtml;
    }
  });
  document.getElementById("btnEnviarSku").addEventListener("click", function () {
    if (confirm("Deseja enviar os apontamentos acumulados?")) {
      document.getElementById("skuForecastForm").submit();
    }
  });

  /* ================== Alternância entre Modos ================== */
  const btnToggleSku = document.getElementById("btnToggleSku");
  if (btnToggleSku) {
    btnToggleSku.addEventListener("click", function () {
      if (confirm("Atenção: Ao alternar para o modo 'Apontar por SKU', quaisquer dados não enviados no formulário de apontamento por modelo serão perdidos. Deseja continuar?")) {
        document.getElementById("modeloForecastContainer").style.display = "none";
        document.getElementById("skuForecastContainer").style.display = "block";
        document.getElementById("toggleSkuContainer").style.display = "none";
        document.getElementById("voltarSkuContainer").style.display = "block";
      }
    });
  }
  const btnVoltar = document.getElementById("btnVoltar");
  btnVoltar.addEventListener("click", function () {
    if (confirm("Ao voltar para o apontamento por modelo, os dados acumulados no apontamento por SKU serão perdidos. Deseja continuar?")) {
      document.getElementById("skuForecastContainer").style.display = "none";
      document.getElementById("modeloForecastContainer").style.display = "block";
      document.getElementById("toggleSkuContainer").style.display = "block";
      document.getElementById("voltarSkuContainer").style.display = "none";
    }
  });

  /* ================== Modo Apontamento por Modelo ================== */
  function populateModeloLinha() {
    const linhaSelect = document.getElementById("modeloLinha");
    linhaSelect.innerHTML = '<option value="" selected>Selecione a Linha</option>';
    if (skuData && skuData.length > 0) {
      let linhas = [...new Set(skuData.map(item => item.LINHA).filter(Boolean))];
      linhas.forEach(linha => {
        let opt = document.createElement("option");
        opt.value = linha;
        opt.text = linha;
        linhaSelect.appendChild(opt);
      });
    }
  }
  function updateModeloDropdown() {
    const linhaSelected = document.getElementById("modeloLinha").value;
    const modeloSelect = document.getElementById("modeloModelo");
    modeloSelect.innerHTML = '<option value="" selected>Selecione o Modelo</option>';
    if (linhaSelected && skuData) {
      let modelos = skuData.filter(item => item.LINHA === linhaSelected)
                           .map(item => item.MODELO)
                           .filter(Boolean);
      modelos = [...new Set(modelos)];
      modelos.forEach(modelo => {
        let opt = document.createElement("option");
        opt.value = modelo;
        opt.text = modelo;
        modeloSelect.appendChild(opt);
      });
    }
  }
  populateModeloLinha();
  document.getElementById("modeloLinha").addEventListener("change", function () {
    updateModeloDropdown();
    // Carrega a carteira via AJAX para o modo Modelo
    loadCarteira();
  });
  document.getElementById("modeloModelo").addEventListener("change", loadCarteira);

  // Função para carregar a visão de carteira via AJAX (para o modo Modelo)
  function loadCarteira() {
    const linha = document.getElementById("modeloLinha").value;
    const modelo = document.getElementById("modeloModelo").value;
    if (!linha || !modelo) {
      document.getElementById("carteiraContainer").innerHTML = "";
      return;
    }
    const cd = document.getElementById("cd").value;
    const regional = document.getElementById("regional").value;
    const dados = { cd: cd, regional: regional, linha: linha, modelo: modelo };
    // Atualize o caminho para refletir a nova localização do arquivo AJAX:
    fetch("../ajax/ajax_carteira.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(dados)
    })
    .then(response => response.text())
    .then(html => {
      document.getElementById("carteiraContainer").innerHTML = html;
    })
    .catch(error => {
      console.error("Erro ao carregar carteira:", error);
      document.getElementById("carteiraContainer").innerHTML = "<div class='alert alert-danger'>Erro ao carregar carteira.</div>";
    });
  }

  document.getElementById("btnAdicionarModelo").addEventListener("click", function () {
    const linhaSelect = document.getElementById("modeloLinha");
    const modeloSelect = document.getElementById("modeloModelo");
    const linha = linhaSelect.value;
    const modelo = modeloSelect.value;
    if (!linha || !modelo) {
      alert("Por favor, selecione a Linha e o Modelo.");
      return;
    }
    const quantidadeInputs = document.querySelectorAll("#modeloForecastForm input[type='number'][id^='modeloQuantidade']");
    let quantidades = [];
    quantidadeInputs.forEach(input => {
      quantidades.push({
        mes: input.getAttribute("data-mes"),
        value: input.value
      });
    });
    let valid = quantidades.some(q => parseInt(q.value) > 0);
    if (!valid) {
      alert("Informe ao menos uma quantidade maior que zero para os meses.");
      return;
    }
    const tableBody = document.getElementById("modeloForecastList").getElementsByTagName("tbody")[0];
    const newRow = tableBody.insertRow();
    newRow.insertCell(0).innerHTML = linha;
    newRow.insertCell(1).innerHTML = modelo;
    quantidades.forEach(q => {
      let cell = newRow.insertCell(-1);
      cell.innerHTML = q.value;
    });
    let actionCell = newRow.insertCell(-1);
    actionCell.innerHTML = '<button type="button" class="btn btn-danger btn-sm btnExcluirModelo">Excluir</button>';
    const hiddenContainer = document.getElementById("modeloHiddenInputs");
    quantidades.forEach(q => {
      hiddenContainer.innerHTML += '<input type="hidden" name="forecast[' + modelo + '][' + q.mes + ']" value="' + q.value + '">';
    });
    linhaSelect.selectedIndex = 0;
    modeloSelect.innerHTML = '<option value="" selected>Selecione o Modelo</option>';
    quantidadeInputs.forEach(input => {
      input.value = "0";
    });
  });
  document.getElementById("modeloForecastList").addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("btnExcluirModelo")) {
      const row = e.target.parentNode.parentNode;
      row.parentNode.removeChild(row);
      const tableBody = document.getElementById("modeloForecastList").getElementsByTagName("tbody")[0];
      let newHiddenHtml = "";
      var modeloMeses = <?php echo json_encode(array_map(function($mes) { return $mes['value']; }, $mesesForecast)); ?>;
      for (let i = 0; i < tableBody.rows.length; i++) {
        let cells = tableBody.rows[i].cells;
        let modeloVal = cells[1].innerText;
        for (let j = 0; j < modeloMeses.length; j++) {
          let quantidadeVal = cells[j + 2].innerText;
          newHiddenHtml += '<input type="hidden" name="forecast[' + modeloVal + '][' + modeloMeses[j] + ']" value="' + quantidadeVal + '">';
        }
      }
      document.getElementById("modeloHiddenInputs").innerHTML = newHiddenHtml;
    }
  });
  document.getElementById("btnEnviarModelo").addEventListener("click", function () {
    if (confirm("Deseja enviar os apontamentos acumulados?")) {
      document.getElementById("modeloForecastForm").submit();
    }
  });
});
</script>

<?php if(isset($_SESSION['success_message'])): ?>
  <!-- Modal de Sucesso -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title" id="successModalLabel">
            <i class="bi bi-check-circle-fill text-success"></i> Forecast Enviado com Sucesso!
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body text-center">
          <img src="../public/assets/img/forecast_success.jpg" alt="Sucesso" class="img-fluid mb-3" style="max-width: 150px;">
          <p class="fs-5">
            Seu forecast foi registrado com sucesso! Você está dominando o futuro.
          </p>
          <p class="text-muted">
            Deseja realizar outro apontamento ou já concluiu seus registros?
          </p>
        </div>
        <div class="modal-footer border-0">
          <a href="index.php?page=apontar_forecast" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Novo Apontamento
          </a>
          <a href="dashboard.php" class="btn btn-secondary">
            <i class="bi bi-house-door"></i> Ir para Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener("DOMContentLoaded", function(){
      var successModal = new bootstrap.Modal(document.getElementById('successModal'));
      successModal.show();
    });
  </script>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>
