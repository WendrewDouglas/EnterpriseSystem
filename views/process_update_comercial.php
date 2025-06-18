<?php
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/auto_check.php'; // Para capturar o usuário logado

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $regional = $_POST['regional'] ?? null;
    $gnv = $_POST['gnv'] ?? null;
    $nomeRegional = $_POST['nomeRegional'] ?? null;
    $analista = $_POST['analista'] ?? null;

    // Capturar usuário logado
    $usuarioLogado = $_SESSION['user_name'] ?? 'Desconhecido';

    // Capturar IP do usuário
    $ipUsuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Capturar Data/Hora Atual
    $dataEdicao = date('Y-m-d H:i:s');

    // Verificação de campos obrigatórios
    if (!$regional || !$gnv || !$nomeRegional || !$analista) {
        echo json_encode(["success" => false, "message" => "Todos os campos são obrigatórios."]);
        exit();
    }

    // Criar conexão com o banco
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        echo json_encode(["success" => false, "message" => "Erro na conexão com o banco."]);
        exit();
    }

    // Montar a query de atualização
    $sql = "UPDATE DW..DEPARA_COMERCIAL 
            SET GNV = ?, NomeRegional = ?, Analista = ?, 
                ultimo_usuario_editou = ?, 
                data_ultima_edicao = ?, 
                ip_ultima_edicao = ? 
            WHERE Regional = ?";

    // Especificar o tipo do parâmetro de data/hora para evitar erro de conversão
    $paramDataEdicao = [
        $dataEdicao,
        SQLSRV_PARAM_IN,
        null,
        SQLSRV_SQLTYPE_DATETIME
    ];

    // Array de parâmetros
    $params = [
        $gnv,
        $nomeRegional,
        $analista,
        $usuarioLogado,
        $paramDataEdicao,
        $ipUsuario,
        $regional
    ];

    // Executar a query
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        echo json_encode(["success" => false, "message" => "Erro ao atualizar no banco."]);
    } else {
        echo json_encode(["success" => true]);
    }

    exit();
}
?>
