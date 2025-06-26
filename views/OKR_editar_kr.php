<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status()===PHP_SESSION_NONE) session_start();
verificarPermissao('novo_kr');

// Conexões
$db     = new Database();
$conn   = $db->getConnection();
$dbOKR  = new OKRDatabase();
$connOKR= $dbOKR->getConnection();
if (!$conn || !$connOKR) die("<div class='alert alert-danger'>Erro de conexão.</div>");

// IDs vindos de GET
$idKr      = $_GET['id_kr']      ?? '';
$idObjetivo= $_GET['id_objetivo']?? '';
if (!$idKr || !$idObjetivo) {
    die("<div class='alert alert-danger'>Parâmetros inválidos.</div>");
}

// buscar dados do KR
$sql = "SELECT * FROM key_results WHERE id_kr = ?";
$stmt= sqlsrv_query($connOKR, $sql, [$idKr]);
$kr  = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
if (!$kr) die("<div class='alert alert-danger'>KR não encontrado.</div>");

// helpers dropdown
function fetchDD($conn,$q,$idF,$txtF){
    $s=sqlsrv_query($conn,$q);
    $r=[];
    while($row=sqlsrv_fetch_array($s,SQLSRV_FETCH_ASSOC)){
        $r[]=['id'=>$row[$idF],'text'=>$row[$txtF]];
    }
    return $r;
}

$objetivos   = fetchDD($connOKR,"SELECT id_objetivo,descricao FROM objetivos",'id_objetivo','descricao');
$tiposKR     = fetchDD($connOKR,"SELECT id_tipo,descricao_exibicao FROM dom_tipo_kr",'id_tipo','descricao_exibicao');
$naturezas   = fetchDD($connOKR,"SELECT id_natureza,descricao_exibicao FROM dom_natureza_kr",'id_natureza','descricao_exibicao');
$frequencias = fetchDD($connOKR,"SELECT id_frequencia,descricao_exibicao FROM dom_tipo_frequencia_milestone",'id_frequencia','descricao_exibicao');
$users       = fetchDD($conn,"SELECT id,name FROM users WHERE status='ativo'",'id','name');

$mensagemSistema = '';
if (!empty($_SESSION['erro_kr'])) {
    $mensagemSistema = "<div class='alert alert-danger mt-3'>{$_SESSION['erro_kr']}</div>";
    unset($_SESSION['erro_kr']);
}
if (!empty($_SESSION['sucesso_kr'])) {
    $mensagemSistema = "<div class='alert alert-success mt-3'>{$_SESSION['sucesso_kr']}</div>";
    unset($_SESSION['sucesso_kr']);
}

// processamento POST
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $f = fn($k)=>($_POST[$k]??'');
    // coletar campos
    $descricao        = trim($f('descricao'));
    $tipo_kr          = $f('tipo_kr');
    $natureza_kr      = $f('natureza_kr');
    $baseline         = (float)$f('baseline');
    $meta             = (float)$f('meta');
    $unidade          = $f('unidade_medida');
    $direcao          = $f('direcao_metrica');
    $frequencia       = $f('tipo_frequencia_milestone');
    $data_inicio      = $f('data_inicio');
    $data_fim         = $f('data_fim');
    $responsavel      = $f('responsavel');
    $margem_confianca = is_numeric($f('margem_confianca')) ? ((float)$f('margem_confianca')/100) : 0;
    $status           = $f('status');
    $obs              = trim($f('observacao'));
    $obs_json         = json_encode($obs?[["origin"=>"editor","observation"=>$obs,"date"=>date('c')]]:[]);

    // valida essenciais
    if (!$descricao||!$tipo_kr||!$natureza_kr||!$data_fim||!$responsavel) {
        $_SESSION['erro_kr']='Preencha todos os campos obrigatórios.';
        header("Location: ".$_SERVER['REQUEST_URI']);
        exit;
    }

    // atualizar
    $sqlU = "UPDATE key_results SET
                descricao=?, tipo_kr=?, natureza_kr=?, baseline=?, meta=?,
                unidade_medida=?, direcao_metrica=?, tipo_frequencia_milestone=?,
                data_inicio=?, data_fim=?, responsavel=?, margem_confianca=?,
                status=?, observacoes=?
             WHERE id_kr=?";
    $p = [
        $descricao, $tipo_kr, $natureza_kr, $baseline, $meta,
        $unidade, $direcao, $frequencia,
        $data_inicio, $data_fim, $responsavel, $margem_confianca,
        $status, $obs_json,
        $idKr
    ];
    $st = sqlsrv_prepare($connOKR,$sqlU,$p);
    if ($st && sqlsrv_execute($st)) {
        $_SESSION['sucesso_kr']='Key Result alterado com sucesso.';
        header("Location: OKR_detalhe_objetivo.php?id={$idObjetivo}&open_kr={$idKr}");
        exit;
    } else {
        $_SESSION['erro_kr']='Erro ao salvar: '.print_r(sqlsrv_errors(),true);
        header("Location: ".$_SERVER['REQUEST_URI']);
        exit;
    }
}

// front-end
$pageTitle='Editar Key Result';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet"/>

<div class="content">
  <h2 class="mb-4"><i class="bi bi-pencil-square me-2 fs-4"></i> Editar Key Result</h2>
  <?= $mensagemSistema ?>
  <?php $v = fn($k)=>htmlspecialchars($kr[$k] ?? ''); ?>

  <form action="" method="post" class="bg-light p-4 border rounded">
    <div class="row g-3">
      <!-- Objetivo (readonly) -->
      <div class="col-md-6">
        <label class="form-label">Objetivo Associado</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($kr['id_objetivo']) ?>" readonly>
      </div>
      <!-- Nome do KR -->
      <div class="col-md-6">
        <label class="form-label">Nome do Key Result*</label>
        <input type="text" name="descricao" class="form-control" value="<?= $v('descricao') ?>" required>
      </div>
      <!-- Tipo, Natureza -->
      <div class="col-md-6">
        <label class="form-label">Tipo*</label>
        <select name="tipo_kr" class="form-select select2" required>
          <?php foreach($tiposKR as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $o['id']==$kr['tipo_kr']?'selected':'' ?>>
              <?= htmlspecialchars($o['text']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Natureza*</label>
        <select name="natureza_kr" class="form-select select2" required>
          <?php foreach($naturezas as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $o['id']==$kr['natureza_kr']?'selected':'' ?>>
              <?= htmlspecialchars($o['text']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Baseline, Meta, Unidade -->
      <div class="col-md-3">
        <label class="form-label">Baseline*</label>
        <input type="number" step="any" name="baseline" class="form-control" value="<?= $v('baseline') ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Meta*</label>
        <input type="number" step="any" name="meta" class="form-control" value="<?= $v('meta') ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Unidade*</label>
        <input type="text" name="unidade_medida" class="form-control" value="<?= $v('unidade_medida') ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Direção*</label>
        <select name="direcao_metrica" class="form-select" required>
          <?php foreach(['maior'=>'Maior é melhor','menor'=>'Menor é melhor','intervalo'=>'Intervalo'] as $k=>$txt): ?>
            <option value="<?= $k ?>" <?= $kr['direcao_metrica']==$k?'selected':'' ?>>
              <?= $txt ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Frequência, Datas, Responsável -->
      <div class="col-md-6">
        <label class="form-label">Frequência*</label>
        <select name="tipo_frequencia_milestone" class="form-select select2" required>
          <?php foreach($frequencias as $o): ?>
            <option value="<?= $o['id'] ?>" <?= $o['id']==$kr['tipo_frequencia_milestone']?'selected':'' ?>>
              <?= htmlspecialchars($o['text']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Data Início</label>
        <input type="date" name="data_inicio" class="form-control" value="<?= date('Y-m-d', strtotime($kr['data_inicio'])) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Data Fim*</label>
        <input type="date" name="data_fim" class="form-control" value="<?= date('Y-m-d', strtotime($kr['data_fim'])) ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Responsável*</label>
        <select name="responsavel" class="form-select select2" required>
          <?php foreach($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $u['id']==$kr['responsavel']?'selected':'' ?>>
              <?= htmlspecialchars($u['text']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Margem, Status, Observações -->
      <div class="col-md-3">
        <label class="form-label">Margem de Confiança (%)</label>
        <input type="number" step="0.01" name="margem_confianca" class="form-control" value="<?= $v('margem_confianca')*100 ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status*</label>
        <select name="status" class="form-select" required>
          <?php foreach(['nao iniciado','em andamento','concluido','cancelado'] as $s): ?>
            <option value="<?= $s ?>" <?= $kr['status']==$s?'selected':'' ?>>
              <?= ucfirst($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-12">
        <label class="form-label">Observações</label>
        <textarea name="observacao" class="form-control" rows="3"><?= htmlspecialchars($obs ?? '') ?></textarea>
      </div>

      <div class="col-12 text-end">
        <button type="submit" class="btn btn-success px-4 py-2 mt-3">
          <i class="bi bi-save me-1"></i> Salvar Alterações
        </button>
      </div>
    </div>
  </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
$(document).ready(()=>$('.select2').select2({width:'100%'}));
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>
