<?php
// ----------------------------------------------
// OKR_salvar_apontamento.php
// ----------------------------------------------

// 1) Erros em log, não no output
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', __DIR__ . '/../logs/okr_apontamento_errors.log');

// 2) Includes e sessão
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';      
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';
if (session_status()===PHP_SESSION_NONE) session_start();
verificarPermissao('apontar_progresso');

// 3) Cabeçalho JSON
header('Content-Type: application/json; charset=utf-8');

// 4) Captura e validação
$idKr      = $_POST['id_kr'] ?? null;
$registros = isset($_POST['registros']) 
             ? json_decode($_POST['registros'], true) 
             : null;
if (!$idKr || !is_array($registros)) {
    echo json_encode(['status'=>'erro','mensagem'=>'Dados de entrada inválidos.']);
    exit;
}

// 5) Conexão OKR e início de transação
$dbOKR = new OKRDatabase();
$conn  = $dbOKR->getConnection();
if (!$conn) {
    echo json_encode(['status'=>'erro','mensagem'=>'Falha na conexão OKR.']);
    exit;
}
sqlsrv_begin_transaction($conn);

// 6) ID do usuário
$userId = $_SESSION['user_id'] ?? null;

// 7) Atualiza milestones_kr
foreach ($registros as $r) {
    $msId     = intval($r['id_milestone']);
    $valor    = floatval($r['novo_valor']);
    $dtEvid   = $r['data_evidencia'];
    $obs      = $r['observacao'] ?? '';
    $diffPerc = isset($r['diferenca_perc'])
                ? floatval($r['diferenca_perc'])
                : null;

    $sqlUpd = "
      UPDATE milestones_kr
      SET
        valor_real          = ?,
        dt_evidencia        = ?,
        dt_apontamento      = GETDATE(),
        id_user_solicitante = ?,
        comentario_analise  = ?,
        diferenca_perc      = ?
      WHERE id_milestone = ?
    ";
    $paramsUpd = [$valor, $dtEvid, $userId, $obs, $diffPerc, $msId];
    if (! sqlsrv_query($conn, $sqlUpd, $paramsUpd)) {
        sqlsrv_rollback($conn);
        $err = sqlsrv_errors()[0]['message'] ?? 'Erro ao atualizar milestone';
        echo json_encode(['status'=>'erro','mensagem'=>"Falha ao atualizar milestone #{$msId}: {$err}"]);
        exit;
    }
}

// 8) Recalcula e atualiza o FAROL no key_results
//    8.1) Busca direção e margem do KR
$sqlKR = "SELECT direcao_metrica, margem_confianca 
          FROM key_results WHERE id_kr = ?";
$stmtKR = sqlsrv_query($conn, $sqlKR, [$idKr]);
$krRow  = sqlsrv_fetch_array($stmtKR, SQLSRV_FETCH_ASSOC);
$direcao = $krRow['direcao_metrica'];
$margem   = floatval($krRow['margem_confianca']) * 100;  // conforme seu cálculo

//    8.2) Busca último milestone preenchido
$sqlLast = "SELECT TOP 1 valor_real, valor_esperado 
            FROM milestones_kr
            WHERE id_kr = ? AND valor_real IS NOT NULL
            ORDER BY num_ordem DESC";
$stmtLast = sqlsrv_query($conn, $sqlLast, [$idKr]);
$last = sqlsrv_fetch_array($stmtLast, SQLSRV_FETCH_ASSOC);

$farol = '-';
if ($last) {
    $real     = floatval($last['valor_real']);
    $esperado = floatval($last['valor_esperado']);
    $diffPerc = $esperado != 0
               ? (($real - $esperado) / $esperado) * 100
               : 0;

    if ($direcao === 'maior') {
        if ($diffPerc <= -$margem)      $farol = 'Péssimo';
        elseif ($diffPerc < 0)          $farol = 'Ruim';
        elseif ($diffPerc <= $margem)   $farol = 'Bom';
        else                            $farol = 'Ótimo';
    } elseif ($direcao === 'menor') {
        if ($diffPerc >= $margem)       $farol = 'Péssimo';
        elseif ($diffPerc > 0)          $farol = 'Ruim';
        elseif ($diffPerc >= -$margem)  $farol = 'Bom';
        else                            $farol = 'Ótimo';
    } else { // intervalo
        // ... sua lógica de intervalo aqui ...
    }
}

//    8.3) Grava no key_results
$sqlUpdKR = "UPDATE key_results SET farol = ? WHERE id_kr = ?";
if (! sqlsrv_query($conn, $sqlUpdKR, [$farol, $idKr])) {
    sqlsrv_rollback($conn);
    $err = sqlsrv_errors()[0]['message'] ?? 'Erro ao atualizar farol';
    echo json_encode(['status'=>'erro','mensagem'=>"Falha ao atualizar farol do KR {$idKr}: {$err}"]);
    exit;
}

// 9) Processa anexos (sem alterações)

// ... (seu código de move_uploaded_file e INSERT em anexos_apontamento_check) ...

// 10) Commit e sucesso
sqlsrv_commit($conn);
echo json_encode(['status'=>'sucesso','mensagem'=>'Apontamento, anexos e farol atualizados com êxito.']);
exit;
