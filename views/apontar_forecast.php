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
function obterProximosMeses($quantidade = 3)
{
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
function obterQuantidadePorModelo($conn, $cdSelecionado, $regionalSelecionado, $mesesForecast, $modeloSelecionado)
{
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
<style>
  /* Container flexível, alinhamento horizontal e wrapping */
  #linhaCheckboxContainer,
  #modeloCheckboxContainer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 0.5rem 0;
  }

  /* Esconde o input real (Bootstrap btn-check é opcional) */
  .model-checkbox,
  .line-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
  }

  /* Base dos botões estilizados */
  .model-label,
  .line-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border: 2px solid var(--bs-primary);
    border-radius: 1.5rem;
    background-color: var(--bs-white);
    color: var(--bs-primary);
    font-weight: 500;
    cursor: pointer;
    user-select: none;
    transition:
      background-color 0.2s ease,
      color 0.2s ease,
      box-shadow 0.2s ease;
  }

  /* Sombra suave */
  .model-label,
  .line-label {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  }

  /* Hover */
  .model-label:hover,
  .line-label:hover {
    background-color: rgba(13, 110, 253, 0.1);
  }

  /* Estado ativo */
  .model-checkbox:checked+.model-label,
  .line-checkbox:checked+.line-label {
    background-color: var(--bs-primary);
    color: var(--bs-white);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  /* Foco via teclado */
  .model-checkbox:focus+.model-label,
  .line-checkbox:focus+.line-label {
    outline: 3px solid rgba(13, 110, 253, 0.4);
    outline-offset: 2px;
  }
</style>

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
        <?php if ($forecastExiste): ?>
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
                <label class="form-label fw-bold">Linha:</label>
                <div id="linhaCheckboxContainer"></div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold">Modelo:</label>
                <div id="modeloCheckboxContainer"></div>
              </div>
            </div>
            <div class="col-md-6">
              <!-- Espaço reservado para alinhamento -->
            </div>
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
<script>
  document.addEventListener('DOMContentLoaded', () => {
    let prevCd = "";
    let prevRegional = "";
    const cdSelect = document.getElementById("cd");
    const regionalSelect = document.getElementById("regional");
    const filterForm = document.getElementById("filterForm");
    const updateMessage = document.getElementById("updateMessage");
    const successMessage = document.getElementById("successMessage");

    cdSelect.addEventListener("focus", () => prevCd = cdSelect.value);
    regionalSelect.addEventListener("focus", () => prevRegional = regionalSelect.value);

    function confirmAndSubmit(changed, prevValue) {
      let modeloHidden = document.getElementById("modeloHiddenInputs").innerHTML.trim();
      let skuHidden = document.getElementById("skuHiddenInputs")?.innerHTML.trim() || "";
      if ((modeloHidden || skuHidden) && !confirm("Você possui apontamentos não enviados. Ao alterar, estes dados serão perdidos. Deseja continuar?")) {
        changed.value = prevValue;
        return false;
      }
      updateMessage.style.display = "block";
      successMessage.style.display = "none";
      filterForm.submit();
      return true;
    }

    cdSelect.addEventListener("change", () => confirmAndSubmit(cdSelect, prevCd));
    regionalSelect.addEventListener("change", () => confirmAndSubmit(regionalSelect, prevRegional));
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const linhaContainer = document.getElementById('linhaCheckboxContainer');
    const modeloContainer = document.getElementById('modeloCheckboxContainer');
    const addBtn = document.getElementById('btnAdicionarModelo');
    const sendBtn = document.getElementById('btnEnviarModelo');
    const tbody = document.querySelector('#modeloForecastList tbody');
    const hiddenInputs = document.getElementById('modeloHiddenInputs');
    const form = document.getElementById('modeloForecastForm');

    // agora: modelo → linha
    const selectedModels = new Map();
    let currentLine = null;

    function populateLinhaCheckboxes() {
      linhaContainer.innerHTML = '';
      [...new Set(skuData.map(i => i.LINHA))].forEach(linha => {
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.id = `linha_${linha}`;
        cb.value = linha;
        const label = document.createElement('label');
        label.htmlFor = cb.id;
        label.textContent = linha;
        const wrapper = document.createElement('div');
        wrapper.className = 'line-option';
        wrapper.append(cb, label);
        linhaContainer.append(wrapper);

        cb.addEventListener('change', () => {
          if (!cb.checked) return;
          currentLine = linha;
          // desmarca as outras linhas
          linhaContainer.querySelectorAll('input').forEach(i => {
            if (i !== cb) i.checked = false;
          });
          renderModeloCheckboxes(linha);
        });
      });
    }

    function renderModeloCheckboxes(linha) {
      modeloContainer.innerHTML = '';
      [...new Set(
        skuData.filter(item => item.LINHA === linha).map(i => i.MODELO)
      )].forEach(modelo => {
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.id = `modelo_${modelo}`;
        cb.value = modelo;
        // se já estava selecionado, mostrar como marcado
        if (selectedModels.has(modelo)) cb.checked = true;

        cb.addEventListener('change', e => {
          if (e.target.checked) {
            // associa esse modelo à linha atual
            selectedModels.set(modelo, linha);
          } else {
            selectedModels.delete(modelo);
          }
        });

        const label = document.createElement('label');
        label.htmlFor = cb.id;
        label.textContent = modelo;
        const wrapper = document.createElement('div');
        wrapper.className = 'model-option';
        wrapper.append(cb, label);
        modeloContainer.append(wrapper);
      });
    }

    addBtn.addEventListener('click', () => {
      if (!selectedModels.size) {
        return alert('Selecione ao menos um modelo.');
      }
      // para cada par [modelo, linha], insere a linha certa
      selectedModels.forEach((linha, modelo) => {
        const tr = tbody.insertRow();
        tr.insertCell(0).innerText = linha;
        tr.insertCell(1).innerText = modelo;
        <?php foreach ($mesesForecast as $mes): ?> {
            const td = tr.insertCell(-1);
            const inp = document.createElement('input');
            inp.type = 'number';
            inp.min = '0';
            inp.value = '0';
            inp.dataset.mes = '<?= $mes['value']; ?>';
            inp.classList.add('form-control', 'form-control-sm');
            td.appendChild(inp);
          }
        <?php endforeach; ?>
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-sm btnExcluirModelo';
        btn.textContent = 'Excluir';
        tr.insertCell(-1).appendChild(btn);
      });

      // re-renderiza apenas os modelos da última linha clicada
      if (currentLine) renderModeloCheckboxes(currentLine);
    });

    tbody.addEventListener('click', e => {
      if (e.target.classList.contains('btnExcluirModelo')) {
        e.target.closest('tr').remove();
      }
    });

    sendBtn.addEventListener('click', () => {
      if (!confirm('Deseja enviar os apontamentos?')) return;
      hiddenInputs.innerHTML = '';
      tbody.querySelectorAll('tr').forEach(tr => {
        const modelo = tr.cells[1].innerText;
        tr.querySelectorAll('input[type=number]').forEach(inp => {
          const h = document.createElement('input');
          h.type = 'hidden';
          h.name = `forecast[${modelo}][${inp.dataset.mes}]`;
          h.value = inp.value;
          hiddenInputs.appendChild(h);
        });
      });
      form.submit();
    });

    populateLinhaCheckboxes();
  });
</script>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const linhaSelect = document.getElementById('skuLinha');
    const modeloSelect = document.getElementById('skuModelo');
    const skuSelect = document.getElementById('skuSelect');
    const addSkuBtn = document.getElementById('btnAdicionarSku');
    const sendSkuBtn = document.getElementById('btnEnviarSku');
    const tbodySku = document.querySelector('#skuForecastList tbody');
    const hiddenSkuDiv = document.getElementById('skuHiddenInputs');

    function populateLinhaModeloSku() {
      linhaSelect.innerHTML = '<option value="">Selecione a Linha</option>';
      [...new Set(skuData.map(i => i.LINHA))].forEach(linha => {
        let opt = document.createElement('option');
        opt.value = linha;
        opt.text = linha;
        linhaSelect.append(opt);
      });
    }

    function updateModeloDropdownSku() {
      modeloSelect.innerHTML = '<option value="">Selecione o Modelo</option>';
      let modelos = skuData.filter(i => i.LINHA === linhaSelect.value)
        .map(i => i.MODELO);
      [...new Set(modelos)].forEach(modelo => {
        let opt = document.createElement('option');
        opt.value = modelo;
        opt.text = modelo;
        modeloSelect.append(opt);
      });
    }

    function filterSkuDropdown() {
      skuSelect.innerHTML = '<option value="" disabled selected>Selecione um SKU</option>';
      skuData.filter(i =>
        i.LINHA === linhaSelect.value &&
        i.MODELO === modeloSelect.value
      ).forEach(item => {
        let opt = document.createElement('option');
        opt.value = item.CODITEM;
        opt.text = `${item.CODITEM} – ${item.DESCITEM} (${item.STATUS})`;
        skuSelect.append(opt);
      });
    }

    populateLinhaModeloSku();
    linhaSelect.addEventListener('change', () => {
      updateModeloDropdownSku();
      filterSkuDropdown();
    });
    modeloSelect.addEventListener('change', filterSkuDropdown);

    addSkuBtn.addEventListener('click', () => {
      const sku = skuSelect.value;
      if (!sku) return alert('Selecione um SKU.');
      const meses = Array.from(document.querySelectorAll('#skuForecastForm input[data-mes]'));
      const vals = meses.map(inp => inp.value);
      if (!vals.some(v => +v > 0)) return alert('Informe pelo menos uma quantidade.');

      const tr = tbodySku.insertRow();
      tr.insertCell(0).innerText = sku;
      vals.forEach(v => tr.insertCell(-1).innerText = v);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-danger btn-sm btnExcluirSku';
      btn.textContent = 'Excluir';
      tr.insertCell(-1).append(btn);

      // atualizar hidden
      hiddenSkuDiv.innerHTML = '';
      Array.from(tbodySku.rows).forEach(r => {
        const s = r.cells[0].innerText;
        meses.forEach((inp, i) => {
          let h = document.createElement('input');
          h.type = 'hidden';
          h.name = `skuForecast[${s}][${inp.dataset.mes}]`;
          h.value = r.cells[i + 1].innerText;
          hiddenSkuDiv.append(h);
        });
      });

      skuSelect.selectedIndex = 0;
      meses.forEach(inp => inp.value = 0);
    });

    tbodySku.addEventListener('click', e => {
      if (e.target.classList.contains('btnExcluirSku')) {
        e.target.closest('tr').remove();
        addSkuBtn.click(); // reaplica os hidden inputs
      }
    });

    sendSkuBtn.addEventListener('click', () => {
      if (confirm('Deseja enviar os apontamentos SKU?')) {
        document.getElementById('skuForecastForm').submit();
      }
    });
  });
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>

<!-- Injetar a lista de SKUs no JavaScript -->
<script>
  var skuData = <?php echo json_encode($skuList); ?>;
</script>

<?php if (isset($_SESSION['success_message'])): ?>
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
    document.addEventListener("DOMContentLoaded", function() {
      var successModal = new bootstrap.Modal(document.getElementById('successModal'));
      successModal.show();
    });
  </script>
  <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>