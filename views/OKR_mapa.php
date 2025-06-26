<?php
// ===== Proteções e Includes =====
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

if (session_status() === PHP_SESSION_NONE) session_start();
verificarPermissao('consultar_okr');

$pageTitle = 'Dashboard Estratégico - OKR System';
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// ===== Conexão com OKR DB =====
$connOKR = (new OKRDatabase())->getConnection();
$connForecast  = (new Database())->getConnection();
if (!$connOKR || !$connForecast) {
    die("<div class='alert alert-danger'>❌ Erro: conexão com OKR DB não estabelecida.</div>");
}

// ===== Carregar Usuários Ativos (para nome do dono) =====
$usuarios = [];
$sqlUsers = "SELECT id, name FROM users WHERE status = 'ativo'";
$stmtUsers = sqlsrv_query($connForecast, $sqlUsers);
while ($u = sqlsrv_fetch_array($stmtUsers, SQLSRV_FETCH_ASSOC)) {
    $usuarios[$u['id']] = $u['name'];
}

// ===== Funções Auxiliares =====
function removerAcentos(string $texto): string {
    $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','Ä'=>'A',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'Ó'=>'O','Ò'=>'O','Õ'=>'O','Ô'=>'O','Ö'=>'O',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'ç'=>'c','Ç'=>'C'
    ];
    return strtr($texto, $mapa);
}

function hex2rgba(string $hex, float $alpha = 1.0): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $r = hexdec(str_repeat($hex[0],2));
        $g = hexdec(str_repeat($hex[1],2));
        $b = hexdec(str_repeat($hex[2],2));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return "rgba($r, $g, $b, $alpha)";
}

function normalizeText(string $text): string {
    return ucfirst(mb_strtolower($text, 'UTF-8'));
}

// ===== Carregar Pilares =====
$pilares = [];
$sqlPilares = "SELECT id_pilar, descricao_exibicao FROM dom_pilar_bsc ORDER BY ordem_pilar";
$stmt = sqlsrv_query($connOKR, $sqlPilares);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $key = mb_strtolower(trim($row['id_pilar']));
    $pilares[$key] = [
        'titulo' => $row['descricao_exibicao'],
        'cor'    => [
            'aprendizado e crescimento'=>'#8e44ad',
            'processos internos'=>'#2980b9',
            'clientes'=>'#27ae60',
            'financeiro'=>'#f39c12'
        ][$key] ?? '#6c757d'
    ];
}

// ===== Carregar Métricas =====
$metrics = [];
$sqlMetrics = <<<'SQL'
WITH ProgressoKR AS (
  SELECT kr.id_objetivo,
    CASE WHEN (msMeta.valor_esperado-msBase.valor_esperado)<>0 THEN
      ROUND(((ISNULL(msUlt.valor_real,msBase.valor_esperado)-msBase.valor_esperado)
      /(msMeta.valor_esperado-msBase.valor_esperado))*100,1) ELSE 0 END AS progresso_kr
  FROM key_results kr
  OUTER APPLY (SELECT TOP 1 * FROM milestones_kr WHERE id_kr=kr.id_kr ORDER BY num_ordem) msBase
  OUTER APPLY (SELECT TOP 1 * FROM milestones_kr WHERE id_kr=kr.id_kr ORDER BY num_ordem DESC) msMeta
  OUTER APPLY (SELECT TOP 1 * FROM milestones_kr WHERE id_kr=kr.id_kr AND valor_real IS NOT NULL ORDER BY num_ordem DESC) msUlt
),
Summary AS (
  SELECT obj.id_objetivo,
    COUNT(kr.id_kr) AS qtd_kr,
    ISNULL(AVG(p.progresso_kr),0) AS progresso
  FROM objetivos obj
  LEFT JOIN key_results kr ON kr.id_objetivo=obj.id_objetivo
  LEFT JOIN ProgressoKR p ON p.id_objetivo=obj.id_objetivo
  GROUP BY obj.id_objetivo
)
SELECT s.id_objetivo, s.qtd_kr, s.progresso,
  COALESCE(
    (SELECT TOP 1 farol FROM key_results WHERE id_objetivo=s.id_objetivo
     ORDER BY CASE LOWER(farol)
       WHEN 'péssimo' THEN 1 WHEN 'ruim' THEN 2 WHEN 'bom' THEN 3 WHEN 'ótimo' THEN 4 ELSE 5 END),
    '-') AS farol
FROM Summary s;
SQL;
$stmt = sqlsrv_query($connOKR, $sqlMetrics);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $metrics[$row['id_objetivo']] = [
        'qtd_kr'    => (int)$row['qtd_kr'],
        'progresso' => (float)$row['progresso'],
        'farol'     => $row['farol']
    ];
}

// ===== Carregar Objetivos =====
$objetivos = [];
$sqlObj = "SELECT id_objetivo, descricao, LOWER(pilar_bsc) AS pilar, tipo, dono, status, dt_prazo, qualidade FROM objetivos";
$stmt = sqlsrv_query($connOKR, $sqlObj);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $objetivos[] = $row;
}
?>

<div class="content p-4">
  <h1 class="mb-4 fw-bold text-primary">
      <i class="bi bi-diagram-3 me-2"></i>Mapa Estratégico
  </h1>
  <div id="mapa-canvas">
    <?php foreach($pilares as $pillar => $info): ?>
      <section class="pilar-layer" data-pilar="<?= $pillar ?>">
        <div class="pilar-header" style="background:<?= $info['cor'] ?>;border-radius:8px;width:70%;margin:0 auto 1rem;padding:.5rem;text-align:center;">
          <h3 style="color:#fff;margin:0;text-transform:capitalize;"><?= htmlspecialchars(mb_strtolower($info['titulo'],'UTF-8')) ?></h3>
        </div>
        <div class="cards-container">
          <?php foreach($objetivos as $obj): if($obj['pilar'] === $pillar):
            $m = $metrics[$obj['id_objetivo']] ?? ['qtd_kr'=>0,'progresso'=>0,'farol'=>'-'];
            $bg = hex2rgba($info['cor'],0.25);
            $f = strtolower(removerAcentos($m['farol']));
            switch($f){ case 'otimo': case 'ótimo': $badge='bg-purple';break; case 'bom': $badge='bg-success';break; case 'moderado': $badge='bg-warning';break; case 'ruim': $badge='bg-orange';break; case 'pessimo': case 'péssimo': $badge='bg-dark';break; default: $badge='bg-secondary'; }
            if($obj['dt_prazo'] instanceof DateTime){ $prazo = $obj['dt_prazo']->format('d/m/Y'); }
            elseif(!empty($obj['dt_prazo'])){ $prazo = (new DateTime($obj['dt_prazo']))->format('d/m/Y'); }
            else { $prazo = 'Sem prazo'; }
          ?>
              <div class="card-objetivo position-relative" style="border:2px solid <?= $info['cor'] ?>;background:<?= $bg ?>;flex:0 0 18%;max-width:18%;height:120px;display:flex;flex-direction:column;padding:.5rem;position:relative;transition:transform .2s,box-shadow .2s,height .2s;">                <div class="card-top" style="flex:0 0 auto; text-align:center;">
                  <small class="descricao-limite"><?= htmlspecialchars(normalizeText($obj['descricao'])) ?></small>
                </div>
                <div class="card-footer">
                  <div class="progress" style="height:8px; background:#fff; border:1px solid #ccc; border-radius:4px; margin-bottom:4px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:<?= $m['progresso'] ?>%; background-color:<?= $info['cor'] ?>;" role="progressbar"></div>
                  </div>
                  <div class="info-row d-flex justify-content-around small" style="margin-bottom:4px;">
                    <div>KR:<strong><?= $m['qtd_kr'] ?></strong></div>
                    <div>Prog:<strong><?= $m['progresso'] ?>%</strong></div>
                  </div>
                  <div class="farol-row mb-2">
                    <span class="badge <?= $badge ?>"><?= htmlspecialchars(normalizeText($m['farol'])) ?></span>
                  </div>
                  <div class="detail-info small">
                    <div><span class="badge <?= $obj['tipo']==='Estratégico'?'bg-primary text-white':'bg-secondary text-white' ?>"><?= htmlspecialchars($obj['tipo']) ?></span></div>
                    <div>Dono: <?= htmlspecialchars($usuarios[$obj['dono']] ?? $obj['dono']) ?></div>
                    <div>Status: <span class="badge <?= $obj['status']==='cancelado'?'bg-danger text-white':'bg-info text-white' ?>"><?= ucfirst(str_replace('-',' ',$obj['status'])) ?></span></div>
                    <div>Prazo: <?= $prazo ?></div>
                    <div>Qualidade: <?= htmlspecialchars($obj['qualidade']) ?></div>
                    <a href="index.php?page=OKR_detalhe_objetivo&id=<?= urlencode($obj['id_objetivo']) ?>" class="stretched-link"></a>
                  </div>
                </div>
              </div>
          <?php endif; endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
</div>

<style>
#mapa-canvas{width:100%;}
.pilar-layer{margin-bottom:2rem;}
.cards-container{display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;}
.card-objetivo:hover{transform:scale(1.05);box-shadow:0 10px 20px rgba(0,0,0,0.15);z-index:10;height:200px!important;}
.card-footer{margin-top:auto;flex:0 0 auto;}
.detail-info{opacity:0;max-height:0;overflow:hidden;transition:opacity .2s,max-height .2s;}
.card-objetivo:hover .detail-info{opacity:1!important;max-height:200px!important;}
.farol-row{text-align:left!important;}
.card-objetivo:hover .card-footer{opacity:1!important;max-height:500px!important;}
.descricao-limite{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;}
.info-row{border-top:1px solid rgba(0,0,0,0.1);padding-top:4px;}
.farol-row .badge{padding:.25em .5em;}
/* cores */
.bg-purple{background-color:#6f42c1!important;color:#fff!important;}
.bg-success{background-color:#198754!important;color:#fff!important;}
.bg-warning{background-color:#ffc107!important;color:#000!important;}
.bg-orange{background-color:#fd7e14!important;color:#fff!important;}
.bg-dark{background-color:#212529!important;color:#fff!important;}
.bg-secondary{background-color:#6c757d!important;color:#fff!important;}
a.stretched-link {
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    text-decoration:none !important;
    color:inherit !important;
}
</style>

<script>
// sem JS adicional necessário; CSS hover controla expansão
</script>

<?php include __DIR__ . '/../templates/footer.php'; ?>