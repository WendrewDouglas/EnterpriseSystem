<?php
// Inclui o arquivo de conexão com o banco de dados
require_once __DIR__ . '/../includes/db_connection.php';

// Inicia a sessão
session_start();

// Recupera a conexão com o banco de dados
$conn = $db->getConnection();

// Verifica se a conexão foi estabelecida corretamente
if (!$conn) {
    die("Erro ao conectar ao banco de dados: " . print_r(sqlsrv_errors(), true));
}

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_lojas'])) {
    // Recupera os dados do formulário
    $cod_cliente = filter_input(INPUT_POST, 'cod_cliente', FILTER_SANITIZE_NUMBER_INT);
    $num_lojas = filter_input(INPUT_POST, 'num_lojas', FILTER_SANITIZE_NUMBER_INT);
    $usuario_apontamento = $_SESSION['user_name']; // Obtém o nome do usuário da sessão
    $data_apontamento = date('Y-m-d H:i:s'); // Obtém a data e hora atual

    // Validação do número de lojas
    if ($num_lojas === null || $num_lojas < 0) {
        $_SESSION['mensagem_erro'] = "Número de lojas inválido. Deve ser um número inteiro não negativo.";
        header("Location: ../views/apontamento_lojas.php"); // Redireciona para a página do formulário
        exit;
    }

    // Inicia a transação
    sqlsrv_begin_transaction($conn);

    // Verifica se já existe um apontamento para este cliente na tabela COMERCIAL..TBQTDELOJAS
    $sql_check = "SELECT COUNT(*) FROM COMERCIAL..TBQTDELOJAS WHERE COD_CLIENTE = ?";
    $params_check = array($cod_cliente);
    $stmt_check = sqlsrv_prepare($conn, $sql_check, $params_check);

    if ($stmt_check === false) {
        $_SESSION['mensagem_erro'] = "Erro ao verificar apontamento existente: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        header("Location: ../views/apontamento_lojas.php");
        exit;
    }

    if (sqlsrv_execute($stmt_check) === false) {
        $_SESSION['mensagem_erro'] = "Erro ao executar verificação de apontamento: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        header("Location: ../views/apontamento_lojas.php");
        exit;
    }

    $row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_NUMERIC);
    $count = $row_check[0];

    if ($count > 0) {
        // Já existe um apontamento, então atualiza a tabela COMERCIAL..TBQTDELOJAS
        $sql_update = "UPDATE COMERCIAL..TBQTDELOJAS SET Lojas = ?, USERALT = ?, DATEALT = ? WHERE COD_CLIENTE = ?";
        $params_update = array($num_lojas, $usuario_apontamento, $data_apontamento, $cod_cliente);
        $stmt_update = sqlsrv_prepare($conn, $sql_update, $params_update);

        if ($stmt_update === false) {
            $_SESSION['mensagem_erro'] = "Erro ao preparar atualização: " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            header("Location: ../views/apontamento_lojas.php");
            exit;
        }

        if (sqlsrv_execute($stmt_update) === false) {
            $_SESSION['mensagem_erro'] = "Erro ao atualizar número de lojas: " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            header("Location: ../views/apontamento_lojas.php");
            exit;
        }
        $_SESSION['mensagem_sucesso'] = "Número de lojas do cliente atualizado com sucesso!";
    } else {
        // Não existe apontamento, então insere um novo registro na tabela COMERCIAL..TBQTDELOJAS
        $sql_insert = "INSERT INTO COMERCIAL..TBQTDELOJAS (COD_CLIENTE, Lojas, USERALT, DATEALT) VALUES (?, ?, ?, ?)";
        $params_insert = array($cod_cliente, $num_lojas, $usuario_apontamento, $data_apontamento);
        $stmt_insert = sqlsrv_prepare($conn, $sql_insert, $params_insert);

        if ($stmt_insert === false) {
            $_SESSION['mensagem_erro'] = "Erro ao preparar inserção: " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            header("Location: ../views/apontamento_lojas.php");
            exit;
        }

        if (sqlsrv_execute($stmt_insert) === false) {
            $_SESSION['mensagem_erro'] = "Erro ao inserir número de lojas: " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            header("Location: ../views/apontamento_lojas.php");
            exit;
        }
        $_SESSION['mensagem_sucesso'] = "Número de lojas do cliente cadastrado com sucesso!";
    }

    // Commit da transação
    sqlsrv_commit($conn);

    // Redireciona de volta para a página de apontamento
    header("Location: ../views/apontamento_lojas.php");
    exit;
} else {
    // Se o formulário não foi enviado, redireciona para a página de apontamento
    header("Location: ../views/apontamento_lojas.php");
    exit;
}
?>
