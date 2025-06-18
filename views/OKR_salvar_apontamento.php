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
if (session_status() === PHP_SESSION_NONE) session_start();
verificarPermissao('apontar_progresso');

// 3) Cabeçalho de JSON
header('Content-Type: application/json; charset=utf-8');

// 4) Captura e validação dos dados
$idKr      = $_POST['id_kr'] ?? null;
$registros = isset($_POST['registros'])
             ? json_decode($_POST['registros'], true)
             : null;
if (!$idKr || !is_array($registros)) {
    echo json_encode(['status'=>'erro','mensagem'=>'Dados de entrada inválidos.']);
    exit;
}

// 5) Conexão OKR
$dbOKR = new OKRDatabase();
$conn  = $dbOKR->getConnection();
if (!$conn) {
    echo json_encode(['status'=>'erro','mensagem'=>'Falha na conexão ao banco OKR.']);
    exit;
}

// 6) ID do usuário autenticado
$userId = $_SESSION['user_id'] ?? null;

// 7) Atualiza cada milestone (já registra o apontamento via trigger ou lógica existente)
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
    $paramsUpd = [
      $valor,
      $dtEvid,
      $userId,
      $obs,
      $diffPerc,
      $msId
    ];
    if (! sqlsrv_query($conn, $sqlUpd, $paramsUpd)) {
        $err = sqlsrv_errors()[0]['message'] ?? 'Erro ao atualizar milestone';
        echo json_encode([
          'status'=>'erro',
          'mensagem'=>"Falha ao atualizar milestone #{$msId}: {$err}"
        ]);
        exit;
    }
}

// 8) Prepara pasta de uploads
$uploadDir = __DIR__ . '/../documents/OKR_evidencias/';
if (! is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 9) Processa uploads e insere metadados em anexos_apontamento_check
foreach ($_FILES as $field => $file) {
    // esperamos nome de campo "anexo_file_{id_milestone}"
    if (strpos($field, 'anexo_file_') !== 0) {
        continue;
    }

    $msId = intval(substr($field, strlen('anexo_file_')));
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error [{$field}]: {$file['error']}");
        continue;
    }

    // gera nome único e move o arquivo
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uniqName = uniqid("okr_{$msId}_") . ".$ext";
    $dest     = $uploadDir . $uniqName;
    if (! move_uploaded_file($file['tmp_name'], $dest)) {
        error_log("Falha ao mover {$file['tmp_name']} para {$dest}");
        continue;
    }

    // lê descrição opcional
    $descKey   = "anexo_desc_{$msId}";
    $descricao = $_POST[$descKey] ?? '';
    // tipo de arquivo
    $mimeType  = $file['type'];

    // insere na tabela de anexos
    $sqlIns = "
      INSERT INTO anexos_apontamento_check
        (id_apontamento, nome_anexo, descricao_anexo,
         tipo_arquivo, caminho_arquivo, data_envio, id_user_envio)
      VALUES (?, ?, ?, ?, ?, GETDATE(), ?)
    ";
    $paramsIns = [
      $msId,                                        // FK para o milestone
      $uniqName,
      $descricao,
      $mimeType,
      'documents/OKR_evidencias/' . $uniqName,
      $userId
    ];

    if (! sqlsrv_query($conn, $sqlIns, $paramsIns)) {
        $e = sqlsrv_errors()[0]['message'] ?? 'Erro no INSERT de anexo';
        error_log("Falha ao inserir anexo para milestone #{$msId}: {$e}");
    }
}

// 10) Retorno de sucesso
echo json_encode(['status'=>'sucesso','mensagem'=>'Apontamento e anexos salvos com êxito.']);
exit;
