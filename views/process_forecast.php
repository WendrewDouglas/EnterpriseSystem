<?php
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';
require_once __DIR__ . '/../includes/permissions.php';

// Permitir apenas ADMIN e GESTOR acessar
verificarPermissao('apontar_forecast');

// Função para capturar o IP do usuário
function obterIPUsuario() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
$ipUsuario = obterIPUsuario();

// Verifica se os dados foram enviados via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Capturar filtros e usuário
    $cdSelecionado = $_POST['cd'] ?? null;
    $regionalSelecionado = $_POST['regional'] ?? null;
    $usuarioApontamento = $_POST['usuario_apontamento'] ?? null;
    if (!$cdSelecionado || !$regionalSelecionado || !$usuarioApontamento) {
        $_SESSION['error_message'] = "Erro: Centro de Distribuição, Código Regional ou Usuário não identificados.";
        header("Location: index.php?page=apontar_forecast");
        exit();
    }

    // Criar conexão com o banco
    $db = new Database();
    $conn = $db->getConnection();

    // Calcular o mês de referência definitivo: sempre o próximo mês
    $data = new DateTime('first day of next month');
    $mesReferenciaDefinitivo = $data->format('m/Y');

    // Verificar se já existe forecast definitivo para o próximo mês para o CD e Regional
    $sqlCheck = "SELECT 1 FROM forecast_entries 
                 WHERE empresa = ? AND cod_gestor = ? AND mes_referencia = ? AND finalizado = 1";
    $paramsCheck = [$cdSelecionado, $regionalSelecionado, $mesReferenciaDefinitivo];
    $stmtCheck = sqlsrv_query($conn, $sqlCheck, $paramsCheck);
    if ($stmtCheck !== false && sqlsrv_fetch_array($stmtCheck, SQLSRV_FETCH_ASSOC)) {
        $_SESSION['error_message'] = "<i class='bi bi-emoji-smile'></i> Já existe apontamento de forecast para o regional selecionado. Caso queira editar, acesse o histórico de apontamentos, <a href='index.php?page=consulta_lancamentos'>clique aqui</a>.";
        header("Location: index.php?page=apontar_forecast");
        exit();
    }

    // Capturar os dados enviados pelo formulário
    $forecastData = $_POST['forecast'] ?? [];
    $dataHoraAtual = date('Y-m-d H:i:s');
    $errosSQL = [];

    // Processar os dados enviados para cada modelo e mês
    foreach ($forecastData as $modelo => $meses) {
        foreach ($meses as $mesReferenciaInput => $quantidade) {
            // Converter valores inválidos para zero
            $quantidade = is_numeric($quantidade) ? intval($quantidade) : 0;
            // Definir o status: se for o próximo mês, será definitivo (1); caso contrário, prévia (0)
            $finalizado = ($mesReferenciaInput == $mesReferenciaDefinitivo) ? 1 : 0;

            // Verificar se já existe um registro para este modelo, CD, Regional e mês
            $sqlExist = "SELECT 1 FROM forecast_entries 
                         WHERE empresa = ? AND cod_gestor = ? AND mes_referencia = ? AND modelo_produto = ?";
            $paramsExist = [$cdSelecionado, $regionalSelecionado, $mesReferenciaInput, $modelo];
            $stmtExist = sqlsrv_query($conn, $sqlExist, $paramsExist);
            if ($stmtExist !== false && sqlsrv_fetch_array($stmtExist, SQLSRV_FETCH_ASSOC)) {
                // Se existir, atualiza o registro (a atualização também define a flag finalizado)
                $sqlUpdate = "UPDATE forecast_entries 
                              SET quantidade = ?, data_lancamento = GETDATE(), finalizado = ? 
                              WHERE empresa = ? AND cod_gestor = ? AND mes_referencia = ? AND modelo_produto = ?";
                $paramsUpdate = [$quantidade, $finalizado, $cdSelecionado, $regionalSelecionado, $mesReferenciaInput, $modelo];
                $stmtUpdate = sqlsrv_query($conn, $sqlUpdate, $paramsUpdate);
                if ($stmtUpdate === false) {
                    $errosSQL[] = sqlsrv_errors();
                    error_log("Erro ao atualizar: " . print_r(sqlsrv_errors(), true));
                }
            } else {
                // Se não existir, insere o registro
                $sqlInsert = "INSERT INTO forecast_entries 
                              (data_lancamento, modelo_produto, mes_referencia, empresa, cod_gestor, usuario_apontamento, ip_usuario, quantidade, finalizado)
                              VALUES (GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?)";
                $paramsInsert = [$modelo, $mesReferenciaInput, $cdSelecionado, $regionalSelecionado, $usuarioApontamento, $ipUsuario, $quantidade, $finalizado];
                $stmtInsert = sqlsrv_query($conn, $sqlInsert, $paramsInsert);
                if ($stmtInsert === false) {
                    $errosSQL[] = sqlsrv_errors();
                    error_log("Erro ao inserir: " . print_r(sqlsrv_errors(), true));
                }
            }
        }
    }

    if (!empty($errosSQL)) {
        $_SESSION['error_message'] = "Houve erros ao salvar os dados. " . implode(" ", $errosSQL);
    } else {
        $_SESSION['success_message'] = "Forecast enviado com sucesso!";
    }
    header("Location: index.php?page=apontar_forecast");
    exit();
} else {
    header("Location: index.php?page=apontar_forecast");
    exit();
}
?>
