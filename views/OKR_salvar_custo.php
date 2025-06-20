<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/db_connectionOKR.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
verificarPermissao('nova_iniciativa');

// 🔗 Conexão
$dbOKR = new OKRDatabase();
$conn = $dbOKR->getConnection();
if (!$conn) {
    $error = sqlsrv_errors();
    error_log(print_r($error, true));
    echo json_encode([
        'status' => 'erro',
        'mensagem' => '❌ Falha na conexão com o banco de dados.',
        'erro_sql' => $error
    ]);
    exit;
}

// 🧠 Sessão
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['status' => 'erro', 'mensagem' => 'Usuário não autenticado.']);
    exit;
}

// 🔍 Dados recebidos
$idIniciativa    = $_POST['id_iniciativa'] ?? '';
$dataDesembolso  = $_POST['data_desembolso'] ?? '';
$valor           = $_POST['valor'] ?? '';
$descricao       = $_POST['descricao'] ?? '';

// 🔒 Validação
if (empty($idIniciativa) || empty($dataDesembolso) || empty($valor)) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Preencha todos os campos obrigatórios.'
    ]);
    exit;
}

// 🔧 Data SQL
try {
    $dataSQL = (new DateTime($dataDesembolso))->format('Y-m-d');
} catch (Exception $e) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Data inválida.'
    ]);
    exit;
}

// 🔍 Buscar id_orcamento da iniciativa
$sqlBusca = "SELECT TOP 1 id_orcamento FROM orcamentos WHERE id_iniciativa = ?";
$stmtBusca = sqlsrv_query($conn, $sqlBusca, [$idIniciativa]);
$orcamento = sqlsrv_fetch_array($stmtBusca, SQLSRV_FETCH_ASSOC);

if (!$orcamento || empty($orcamento['id_orcamento'])) {
    echo json_encode([
        'status' => 'erro',
        'mensagem' => '❌ Não foi possível localizar o orçamento vinculado a esta iniciativa.'
    ]);
    exit;
}

$idOrcamento = $orcamento['id_orcamento'];

// 🔗 Upload de anexo
$caminhoAnexo = null;
if (!empty($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
    $pasta = __DIR__ . '/../documents/OKR_compr_pgto/';
    if (!is_dir($pasta)) {
        mkdir($pasta, 0777, true);
    }

    $ext = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
    $nomeArquivo = 'custo_' . uniqid() . '.' . $ext;
    $destino = $pasta . $nomeArquivo;

    if (move_uploaded_file($_FILES['anexo']['tmp_name'], $destino)) {
        $caminhoAnexo = 'documents/OKR_compr_pgto/' . $nomeArquivo;
    } else {
        echo json_encode([
            'status' => 'erro',
            'mensagem' => '❌ Falha ao salvar o anexo.'
        ]);
        exit;
    }
}

// 🔄 Insert do custo
$sql = "INSERT INTO orcamento_custos_detalhados 
        (id_orcamento, id_iniciativa, data_desembolso, valor, descricao, caminho_evidencia, id_user_criador, dt_criacao)
        VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";

$params = [
    $idOrcamento,
    $idIniciativa,
    $dataSQL,
    floatval($valor),
    $descricao,
    $caminhoAnexo,
    $userId
];

$stmt = sqlsrv_query($conn, $sql, $params);

if (!$stmt) {
    $error = sqlsrv_errors();
    error_log(print_r($error, true));
    echo json_encode([
        'status' => 'erro',
        'mensagem' => '❌ Erro ao registrar custo no banco.',
        'erro_sql' => $error,
        'sql' => $sql,
        'params' => $params
    ]);
    exit;
}

// 🔁 Atualizar valor_realizado do orçamento
$sqlUpdate = "
    UPDATE orcamentos
    SET valor_realizado = (
        SELECT ISNULL(SUM(valor), 0)
        FROM orcamento_custos_detalhados
        WHERE id_orcamento = ?
    )
    WHERE id_orcamento = ?
";

$stmtUpdate = sqlsrv_query($conn, $sqlUpdate, [$idOrcamento, $idOrcamento]);

if (!$stmtUpdate) {
    $error = sqlsrv_errors();
    error_log(print_r($error, true));
    echo json_encode([
        'status' => 'erro',
        'mensagem' => '❌ Custo lançado, mas erro ao atualizar o orçamento.',
        'erro_sql' => $error
    ]);
    exit;
}

// 🎉 Sucesso total
echo json_encode(['status' => 'sucesso', 'mensagem' => '✔️ Custo registrado e orçamento atualizado com sucesso!']);
?>
