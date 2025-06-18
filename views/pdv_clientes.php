<?php
// *** CORREÇÃO: Iniciar o buffer de saída no início do script ***
ob_start();

// Proteção de acesso não autorizado
require_once __DIR__ . '/../includes/auto_check.php';

// Inclusão da conexão com o banco de dados
require_once __DIR__ . '/../includes/db_connection.php';

// Recupera a conexão a partir da instância $db definida em db_connection.php
$conn = $db->getConnection();

// Verifica se a conexão foi estabelecida corretamente
if (!isset($conn) || $conn === null) {
    error_log("Erro: Variável \$conn não definida. Verifique o arquivo db_connection.php.");
    ob_end_clean();
    die("Erro interno: conexão com banco de dados não estabelecida.");
}

// Definição do título da página
$pageTitle = 'Número de Lojas por Cliente';

// Inclusão do cabeçalho e barra lateral (Mantidos como no original)
include __DIR__ . '/../templates/header.php';
include __DIR__ . '/../templates/sidebar.php';

// Função para formatar números (Mantida como no original)
function formatarNumero($numero) {
    return number_format($numero, 0, ',', '.');
}

// Captura dos filtros via GET e montagem da query string para preservá-los
$f_cliente      = isset($_GET['f_cliente']) ? trim($_GET['f_cliente']) : '';
$f_regional     = isset($_GET['f_regional']) ? trim($_GET['f_regional']) : '';
$f_representante= isset($_GET['f_representante']) ? trim($_GET['f_representante']) : '';
$f_grupo        = isset($_GET['f_grupo']) ? trim($_GET['f_grupo']) : '';
$filtrosQuery = "";
if ($f_cliente != '')       { $filtrosQuery .= "&f_cliente=" . urlencode($f_cliente); }
if ($f_regional != '')      { $filtrosQuery .= "&f_regional=" . urlencode($f_regional); }
if ($f_representante != '') { $filtrosQuery .= "&f_representante=" . urlencode($f_representante); }
if ($f_grupo != '')         { $filtrosQuery .= "&f_grupo=" . urlencode($f_grupo); }

// Processamento do formulário de atualização (se enviado)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['atualizar_lojas'])) {
    // Recebe o código enviado, mas para unificação usaremos o nome do cliente
    $cod_cliente = filter_input(INPUT_POST, 'atualizar_lojas', FILTER_SANITIZE_NUMBER_INT);
    $num_lojas_key = 'num_lojas_' . $cod_cliente;
    $num_lojas = filter_input(INPUT_POST, $num_lojas_key, FILTER_SANITIZE_NUMBER_INT);

    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    $usuario_apontamento = $_SESSION['user_name'] ?? 'system';
    $data_apontamento = date('Y-m-d H:i:s');

    if ($cod_cliente === null || $cod_cliente === false || $cod_cliente <= 0) {
        $_SESSION['mensagem_erro'] = "Código do cliente inválido recebido.";
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    if ($num_lojas === null || $num_lojas < 0) {
        $_SESSION['mensagem_erro'] = "Número de lojas inválido para o cliente $cod_cliente. Deve ser um número inteiro não negativo.";
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    if (sqlsrv_begin_transaction($conn) === false) {
        $_SESSION['mensagem_erro'] = "Erro ao iniciar transação: " . print_r(sqlsrv_errors(), true);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }

    // Obtenha o nome do cliente para o código recebido
    $sql_getName = "SELECT NAME1 FROM COMERCIAL..TB_QTDELOJAS WHERE COD = ?";
    $stmt_getName = sqlsrv_prepare($conn, $sql_getName, [$cod_cliente]);
    if ($stmt_getName === false || sqlsrv_execute($stmt_getName) === false) {
        $_SESSION['mensagem_erro'] = "Erro ao obter o nome do cliente: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    $nome_cliente = null;
    if ($row = sqlsrv_fetch_array($stmt_getName, SQLSRV_FETCH_ASSOC)) {
        $nome_cliente = $row['NAME1'];
    }
    sqlsrv_free_stmt($stmt_getName);
    if (!$nome_cliente) {
        $_SESSION['mensagem_erro'] = "Nome do cliente não encontrado para o código informado.";
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    // Obtenha o código unificado para este cliente
    $sql_unificacao = "SELECT TOP 1 COD FROM COMERCIAL..TB_QTDELOJAS WHERE NAME1 = ? ORDER BY Lojas DESC, COD ASC";
    $stmt_unificacao = sqlsrv_prepare($conn, $sql_unificacao, [$nome_cliente]);
    if ($stmt_unificacao === false || sqlsrv_execute($stmt_unificacao) === false) {
        $_SESSION['mensagem_erro'] = "Erro ao obter código unificado: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    $unified_cod = null;
    if ($row = sqlsrv_fetch_array($stmt_unificacao, SQLSRV_FETCH_ASSOC)) {
        $unified_cod = $row['COD'];
    }
    sqlsrv_free_stmt($stmt_unificacao);
    if (!$unified_cod) {
        $_SESSION['mensagem_erro'] = "Não foi possível determinar o código unificado para o cliente.";
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    // Verifica se o registro unificado já existe
    $sql_check = "SELECT COUNT(*) FROM COMERCIAL..TB_QTDELOJAS WHERE COD = ?";
    $params_check = array($unified_cod);
    $stmt_check = sqlsrv_prepare($conn, $sql_check, $params_check);
    $count = 0;
    if ($stmt_check === false) {
        $_SESSION['mensagem_erro'] = "Erro ao preparar verificação: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    if (sqlsrv_execute($stmt_check) === false) {
        $_SESSION['mensagem_erro'] = "Erro ao executar verificação: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    if ($row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_NUMERIC)) {
         $count = $row_check[0];
    }
    sqlsrv_free_stmt($stmt_check);
    if ($count > 0) {
        $sql_update = "UPDATE COMERCIAL..TB_QTDELOJAS SET Lojas = ?, USERALT = ?, DATEALT = ? WHERE COD = ?";
        $params_update = array($num_lojas, $usuario_apontamento, $data_apontamento, $unified_cod);
        $stmt_update = sqlsrv_prepare($conn, $sql_update, $params_update);
        if ($stmt_update === false) {
            $_SESSION['mensagem_erro'] = "Erro ao preparar atualização: " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
            header("Location: " . $redirect_url);
            ob_end_flush();
            exit;
        }
        if (sqlsrv_execute($stmt_update) === false) {
            $_SESSION['mensagem_erro'] = "Erro ao atualizar (Cliente: $unified_cod): " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
            header("Location: " . $redirect_url);
            ob_end_flush();
            exit;
        }
        $_SESSION['mensagem_sucesso'] = "Número de lojas do cliente unificado $unified_cod atualizado com sucesso!";
        sqlsrv_free_stmt($stmt_update);
    } else {
        $sql_insert = "INSERT INTO COMERCIAL..TB_QTDELOJAS (COD, Lojas, USERALT, DATEALT) VALUES (?, ?, ?, ?)";
        $params_insert = array($unified_cod, $num_lojas, $usuario_apontamento, $data_apontamento);
        $stmt_insert = sqlsrv_prepare($conn, $sql_insert, $params_insert);
        if ($stmt_insert === false) {
            $_SESSION['mensagem_erro'] = "Erro ao preparar inserção: " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
            header("Location: " . $redirect_url);
            ob_end_flush();
            exit;
        }
        if (sqlsrv_execute($stmt_insert) === false) {
            $_SESSION['mensagem_erro'] = "Erro ao inserir (Cliente: $unified_cod): " . print_r(sqlsrv_errors(), true);
            sqlsrv_rollback($conn);
            $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
            header("Location: " . $redirect_url);
            ob_end_flush();
            exit;
        }
        $_SESSION['mensagem_sucesso'] = "Número de lojas do cliente unificado $unified_cod cadastrado com sucesso!";
        sqlsrv_free_stmt($stmt_insert);
    }
    if (sqlsrv_commit($conn) === false) {
        $_SESSION['mensagem_erro'] = "Erro ao commitar: " . print_r(sqlsrv_errors(), true);
        sqlsrv_rollback($conn);
        $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
        header("Location: " . $redirect_url);
        ob_end_flush();
        exit;
    }
    $redirect_url = "index.php?page=pdv_clientes" . (isset($_GET['pagina']) ? '&pagina=' . intval($_GET['pagina']) : '') . $filtrosQuery;
    header("Location: " . $redirect_url);
    ob_end_flush();
    exit;
}

// --- Lógica de busca de dados, paginação, cards (Mantida o restante) ---
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Filtro: Nome do Cliente
$sql_filtro_cliente = "SELECT DISTINCT NAME1 AS nome_cliente FROM COMERCIAL..TB_QTDELOJAS ORDER BY nome_cliente";
$stmt_filtro_cliente = sqlsrv_query($conn, $sql_filtro_cliente);
$filtro_clientes = [];
if ($stmt_filtro_cliente !== false) {
    while ($row = sqlsrv_fetch_array($stmt_filtro_cliente, SQLSRV_FETCH_ASSOC)) {
        $filtro_clientes[] = $row['nome_cliente'];
    }
    sqlsrv_free_stmt($stmt_filtro_cliente);
}

// Captura os filtros via GET
$f_cliente = isset($_GET['f_cliente']) ? trim($_GET['f_cliente']) : '';
$f_regional = isset($_GET['f_regional']) ? trim($_GET['f_regional']) : '';
$f_representante = isset($_GET['f_representante']) ? trim($_GET['f_representante']) : '';
$f_grupo = isset($_GET['f_grupo']) ? trim($_GET['f_grupo']) : '';

// Filtro: Regional (Utilizando a tabela de agentes)
$params_regionais = [];
$sql_filtro_regional = "
SELECT 
    r.nome_regional,
    STUFF((SELECT ',' + t_sub.VKGRP
           FROM (
                SELECT DISTINCT t_inner.VKGRP
                FROM ZSAP..SAP_CADAGENTE t_inner
                INNER JOIN DW..DEPARA_COMERCIAL t4_inner 
                    ON t_inner.VKGRP = CAST(t4_inner.Regional AS NVARCHAR(50))
                WHERE t_inner.VKGRP NOT IN ('SHP', 'MKP', '', ' ')
                  AND UPPER(t4_inner.NomeRegional) = r.nome_regional
           ) t_sub
           FOR XML PATH(''), TYPE
          ).value('.', 'NVARCHAR(MAX)'), 1, 1, '') AS regionais
FROM (
    SELECT DISTINCT UPPER(t4.NomeRegional) AS nome_regional
    FROM ZSAP..SAP_CADAGENTE t
    INNER JOIN DW..DEPARA_COMERCIAL t4 
         ON t.VKGRP = CAST(t4.Regional AS NVARCHAR(50))
    WHERE t.VKGRP NOT IN ('SHP', 'MKP', '', ' ')
) r
ORDER BY r.nome_regional;
";
$stmt_filtro_regional = sqlsrv_query($conn, $sql_filtro_regional, $params_regionais);
$filtro_regionais = [];
if ($stmt_filtro_regional !== false) {
    while ($row = sqlsrv_fetch_array($stmt_filtro_regional, SQLSRV_FETCH_ASSOC)) {
        $filtro_regionais[] = [
            'nome_regional' => $row['nome_regional'],
            'regionais'     => $row['regionais']
        ];
    }
    sqlsrv_free_stmt($stmt_filtro_regional);
}

// Filtro: Representante (Mantido)
$sql_filtro_representante = "SELECT DISTINCT 
    CASE 
        WHEN CHARINDEX(' ', NAME1) = 0 THEN NAME1
        ELSE 
            CASE 
                WHEN CHARINDEX(' ', NAME1, CHARINDEX(' ', NAME1) + 1) = 0 THEN NAME1
                ELSE SUBSTRING(NAME1, 1, CHARINDEX(' ', NAME1, CHARINDEX(' ', NAME1) + 1) - 1)
            END
    END AS representante
FROM (
    SELECT NAME1 FROM OPENQUERY(SAP, '
        SELECT DISTINCT B.NAME1
        FROM SAPHANADB.LFA1 B
        LEFT JOIN SAPHANADB.KNVP C ON C.LIFNR = B.LIFNR AND C.MANDT = B.MANDT
        WHERE C.PARVW = ''ZR'' AND B.MANDT = ''500''
    ') AS Representantes
) AS r
ORDER BY representante";
$stmt_filtro_representante = sqlsrv_query($conn, $sql_filtro_representante);
$filtro_representantes = [];
if ($stmt_filtro_representante !== false) {
    while ($row = sqlsrv_fetch_array($stmt_filtro_representante, SQLSRV_FETCH_ASSOC)) {
        $filtro_representantes[] = $row['representante'];
    }
    sqlsrv_free_stmt($stmt_filtro_representante);
}

// Filtro: Grupo (Mantido)
$sql_filtro_grupo = "SELECT DISTINCT STATUS AS GRP_CLIENTE FROM ZSAP..SAP_CADAGENTE WHERE STATUS IS NOT NULL AND KDGRP IN ('Z1', 'Z2', 'Z3', 'Z4') ORDER BY STATUS";
$stmt_filtro_grupo = sqlsrv_query($conn, $sql_filtro_grupo);
$filtro_grupos = [];
if ($stmt_filtro_grupo !== false) {
    while ($row = sqlsrv_fetch_array($stmt_filtro_grupo, SQLSRV_FETCH_ASSOC)) {
        $filtro_grupos[] = $row['GRP_CLIENTE'];
    }
    sqlsrv_free_stmt($stmt_filtro_grupo);
}

// Monta a cláusula WHERE dinâmica
$where = "WHERE 1=1";
$params_filter = [];
if ($f_cliente !== '') {
    $where .= " AND u.NAME1 LIKE ?";
    $params_filter[] = "%" . $f_cliente . "%";
}
if ($f_regional !== '') {
    $regionCodes = explode(',', $f_regional);
    $placeholders = implode(',', array_fill(0, count($regionCodes), '?'));
    $where .= " AND t2.REGIONAL IN ($placeholders)";
    foreach($regionCodes as $code) {
        $params_filter[] = trim($code);
    }
}
if ($f_representante !== '') {
    $where .= " AND t3.NOMEREPRE LIKE ?";
    $params_filter[] = "%" . $f_representante . "%";
}
if ($f_grupo !== '') {
    $where .= " AND t2.GRP_CLIENTE LIKE ?";
    $params_filter[] = "%" . $f_grupo . "%";
}

// Define o número de registros por página a partir do GET, com padrão 25
$itens_por_pagina = isset($_GET['itens_por_pagina']) ? intval($_GET['itens_por_pagina']) : 25;
$pagina_atual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Consulta de clientes unificados usando CTE para obter apenas um registro por NAME1
$sql_clientes = "WITH Unificados AS (
    SELECT COD, NAME1, Lojas, DATEALT, USERALT,
           ROW_NUMBER() OVER (PARTITION BY NAME1 ORDER BY Lojas DESC, COD ASC) AS rn
    FROM COMERCIAL..TB_QTDELOJAS
)
SELECT
    u.COD AS cod_cliente,
    u.NAME1 AS nome_cliente,
    t2.REGIONAL,
    UPPER(t4.NomeRegional) AS nome_regional,
    CASE 
        WHEN CHARINDEX(' ', t3.NOMEREPRE) = 0 THEN t3.NOMEREPRE
        ELSE 
            CASE 
                WHEN CHARINDEX(' ', t3.NOMEREPRE, CHARINDEX(' ', t3.NOMEREPRE) + 1) = 0 THEN t3.NOMEREPRE
                ELSE SUBSTRING(t3.NOMEREPRE, 1, CHARINDEX(' ', t3.NOMEREPRE, CHARINDEX(' ', t3.NOMEREPRE) + 1) - 1)
            END
    END AS representante,
    t2.GRP_CLIENTE,
    u.Lojas AS num_lojas,
    u.DATEALT AS data_apontamento,
    u.USERALT AS usuario_apontamento
FROM Unificados u
INNER JOIN (
    SELECT
        COD_CLIENTE,
        REGIONAL,
        REPRESENTANTE,
        GRP_CLIENTE
    FROM (
        SELECT
            KUNNR AS COD_CLIENTE,
            VKGRP AS REGIONAL,
            LIFNR AS REPRESENTANTE,
            STATUS AS GRP_CLIENTE,
            ROW_NUMBER() OVER (PARTITION BY KUNNR ORDER BY KDGRP ASC) AS linha
        FROM ZSAP..SAP_CADAGENTE
        WHERE KDGRP IN ('Z1', 'Z2', 'Z3', 'Z4')
          AND VKGRP NOT IN ('SHP', 'MKP', '', ' ')
    ) AS subquery
    WHERE linha = 1
) t2 ON u.COD = t2.COD_CLIENTE
LEFT JOIN
(
    SELECT *
    FROM OPENQUERY(SAP, '
        SELECT DISTINCT
            B.LIFNR AS CODREPRE,
            B.NAME1 AS NOMEREPRE
        FROM SAPHANADB.LFA1 B
        LEFT JOIN SAPHANADB.KNVP C ON C.LIFNR = B.LIFNR AND C.MANDT = B.MANDT
        WHERE C.PARVW = ''ZR'' AND B.MANDT = ''500''
    ')
) t3 ON t2.REPRESENTANTE = t3.CODREPRE
LEFT JOIN DW..DEPARA_COMERCIAL t4 ON t2.REGIONAL = t4.Regional
$where
AND u.rn = 1
ORDER BY u.Lojas DESC, u.COD
OFFSET ? ROWS
FETCH NEXT ? ROWS ONLY";

$params_clientes = array_merge($params_filter, array($offset, $itens_por_pagina));
$stmt_clientes = sqlsrv_query($conn, $sql_clientes, $params_clientes);
$clientes = [];
if ($stmt_clientes === false) {
    error_log("Erro ao consultar clientes: " . print_r(sqlsrv_errors(), true));
    $_SESSION['mensagem_erro'] = "Erro ao acessar dados dos clientes.";
} else {
    while ($row = sqlsrv_fetch_array($stmt_clientes, SQLSRV_FETCH_ASSOC)) {
        $clientes[] = $row;
    }
    sqlsrv_free_stmt($stmt_clientes);
}

// Total de clientes unificados (conta por NAME1)
$sql_total_clientes = "WITH Unificados AS (
    SELECT COD, NAME1, Lojas, DATEALT, USERALT,
           ROW_NUMBER() OVER (PARTITION BY NAME1 ORDER BY Lojas DESC, COD ASC) AS rn
    FROM COMERCIAL..TB_QTDELOJAS
    $where
)
SELECT COUNT(*) AS total_clientes FROM Unificados WHERE rn = 1";
$stmt_total_clientes = sqlsrv_query($conn, $sql_total_clientes);
$total_clientes = 0;
if ($stmt_total_clientes && $row_total_clientes = sqlsrv_fetch_array($stmt_total_clientes, SQLSRV_FETCH_ASSOC)) {
    $total_clientes = $row_total_clientes['total_clientes'];
    sqlsrv_free_stmt($stmt_total_clientes);
}
$total_paginas = ($itens_por_pagina > 0) ? ceil($total_clientes / $itens_por_pagina) : 0;

// Consulta de resumo (cards) usando CTE para unificar registros
$sql_resumo = "WITH Unificados AS (
    SELECT COD, NAME1, Lojas,
           ROW_NUMBER() OVER (PARTITION BY NAME1 ORDER BY Lojas DESC, COD ASC) AS rn
    FROM COMERCIAL..TB_QTDELOJAS
)
SELECT COUNT(*) AS total_clientes,
       SUM(CASE WHEN Lojas IS NOT NULL THEN 1 ELSE 0 END) AS clientes_com_lojas,
       SUM(CASE WHEN Lojas IS NULL THEN 1 ELSE 0 END) AS clientes_sem_lojas
FROM Unificados
WHERE rn = 1";
$stmt_resumo = sqlsrv_query($conn, $sql_resumo);
$total_clientes_resumo = 0;
$clientes_com_lojas = 0;
$clientes_sem_lojas = 0;
if ($stmt_resumo !== false) {
    if ($row_resumo = sqlsrv_fetch_array($stmt_resumo, SQLSRV_FETCH_ASSOC)) {
        $total_clientes_resumo = $row_resumo['total_clientes'];
        $clientes_com_lojas = $row_resumo['clientes_com_lojas'];
        $clientes_sem_lojas = $row_resumo['clientes_sem_lojas'];
    }
    sqlsrv_free_stmt($stmt_resumo);
}
$percentual_com_lojas = ($total_clientes_resumo > 0) ? ($clientes_com_lojas / $total_clientes_resumo) * 100 : 0;
$percentual_sem_lojas = 100 - $percentual_com_lojas;
$percentual_com_lojas_formatado = number_format($percentual_com_lojas, 2, ',', '.');
$percentual_sem_lojas_formatado = number_format($percentual_sem_lojas, 2, ',', '.');

// --- NOVA CONSULTA PARA DADOS DO GRÁFICO (Gráfico de Grupo) ---
$sql_grafico = "WITH Unificados AS (
    SELECT COD, NAME1, Lojas,
           ROW_NUMBER() OVER (PARTITION BY NAME1 ORDER BY Lojas DESC, COD ASC) AS rn
    FROM COMERCIAL..TB_QTDELOJAS
)
SELECT t2.GRP_CLIENTE, 
       COUNT(DISTINCT u.COD) AS total, 
       SUM(CASE WHEN u.Lojas > 0 THEN 1 ELSE 0 END) AS com_lojas,
       SUM(CASE WHEN (u.Lojas IS NULL OR u.Lojas = 0) THEN 1 ELSE 0 END) AS sem_lojas
FROM Unificados u
LEFT JOIN (
    SELECT KUNNR AS COD_CLIENTE,
           STATUS AS GRP_CLIENTE,
           ROW_NUMBER() OVER (PARTITION BY KUNNR ORDER BY KDGRP ASC) AS linha
    FROM ZSAP..SAP_CADAGENTE
    WHERE KDGRP IN ('Z1', 'Z2', 'Z3', 'Z4')
      AND VKGRP NOT IN ('SHP', 'MKP', '', ' ')
) t2 ON u.COD = t2.COD_CLIENTE AND t2.linha = 1
WHERE u.rn = 1
GROUP BY t2.GRP_CLIENTE
ORDER BY t2.GRP_CLIENTE";
$stmt_grafico = sqlsrv_query($conn, $sql_grafico);
$grafico_dados = [];
if ($stmt_grafico !== false) {
    while ($row = sqlsrv_fetch_array($stmt_grafico, SQLSRV_FETCH_ASSOC)) {
        $grafico_dados[] = $row;
    }
    sqlsrv_free_stmt($stmt_grafico);
}

// --- NOVA CONSULTAS PARA RANKING DE REPRESENTANTES E REGIONAIS ---
// Ranking de Representantes (por percentual de clientes com lojas)
// Calcula percentual_com e percentual_sem para cada representante
$sql_ranking_reps = "WITH Unificados AS (
    SELECT COD, NAME1, Lojas,
           ROW_NUMBER() OVER (PARTITION BY NAME1 ORDER BY Lojas DESC, COD ASC) AS rn
    FROM COMERCIAL..TB_QTDELOJAS
),
DadosReps AS (
    SELECT 
         t3.NOMEREPRE,
         u.NAME1,
         u.Lojas
    FROM Unificados u
    LEFT JOIN (
       SELECT 
            KUNNR AS COD_CLIENTE,
            LIFNR AS COD_REPRE,
            STATUS AS GRP_CLIENTE,
            ROW_NUMBER() OVER (PARTITION BY KUNNR ORDER BY KDGRP ASC) AS linha
       FROM ZSAP..SAP_CADAGENTE
       WHERE KDGRP IN ('Z1','Z2','Z3','Z4')
         AND VKGRP NOT IN ('SHP','MKP','',' ')
    ) t2 ON u.COD = t2.COD_CLIENTE AND t2.linha = 1
    LEFT JOIN (
       SELECT *
       FROM OPENQUERY(SAP, '
            SELECT DISTINCT B.LIFNR AS CODREPRE, B.NAME1 AS NOMEREPRE
            FROM SAPHANADB.LFA1 B
            LEFT JOIN SAPHANADB.KNVP C ON C.LIFNR = B.LIFNR AND C.MANDT = B.MANDT
            WHERE C.PARVW = ''ZR'' AND B.MANDT = ''500''
       ')
    ) t3 ON t2.COD_REPRE = t3.CODREPRE
    WHERE u.rn = 1
)
SELECT 
    NOMEREPRE AS representante,
    COUNT(DISTINCT NAME1) AS total_clientes,
    SUM(CASE WHEN Lojas > 0 THEN 1 ELSE 0 END) AS clientes_com_lojas,
    SUM(CASE WHEN Lojas IS NULL OR Lojas = 0 THEN 1 ELSE 0 END) AS clientes_sem_lojas,
    ROUND((SUM(CASE WHEN Lojas > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT NAME1)),1) AS percentual_com,
    ROUND(100 - (SUM(CASE WHEN Lojas > 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(DISTINCT NAME1)),1) AS percentual_sem
FROM DadosReps
WHERE NOMEREPRE IS NOT NULL
GROUP BY NOMEREPRE
ORDER BY percentual_com DESC";
$stmt_ranking_reps = sqlsrv_query($conn, $sql_ranking_reps);
$ranking_reps = [];
if ($stmt_ranking_reps !== false) {
    while ($row = sqlsrv_fetch_array($stmt_ranking_reps, SQLSRV_FETCH_ASSOC)) {
        $ranking_reps[] = $row;
    }
    sqlsrv_free_stmt($stmt_ranking_reps);
}

// Ranking de Regionais (por percentual de clientes com lojas)
$sql_ranking_regionais = "WITH Unificados AS (
    SELECT COD, NAME1, Lojas,
           ROW_NUMBER() OVER (PARTITION BY NAME1 ORDER BY Lojas DESC, COD ASC) AS rn
    FROM COMERCIAL..TB_QTDELOJAS
),
DadosRegionais AS (
    SELECT 
           UPPER(t4.NomeRegional) AS nome_regional,
           u.NAME1,
           u.Lojas
    FROM Unificados u
    LEFT JOIN (
       SELECT 
            KUNNR AS COD_CLIENTE,
            VKGRP,
            LIFNR,
            STATUS AS GRP_CLIENTE,
            ROW_NUMBER() OVER (PARTITION BY KUNNR ORDER BY KDGRP ASC) AS linha
       FROM ZSAP..SAP_CADAGENTE
       WHERE KDGRP IN ('Z1','Z2','Z3','Z4')
         AND VKGRP NOT IN ('SHP','MKP','',' ')
    ) t2 ON u.COD = t2.COD_CLIENTE AND t2.linha = 1
    LEFT JOIN DW..DEPARA_COMERCIAL t4 ON t2.VKGRP = t4.Regional
    WHERE u.rn = 1
)
SELECT 
    nome_regional,
    COUNT(DISTINCT NAME1) AS total_clientes,
    SUM(CASE WHEN Lojas > 0 THEN 1 ELSE 0 END) AS clientes_com_lojas,
    SUM(CASE WHEN Lojas IS NULL OR Lojas = 0 THEN 1 ELSE 0 END) AS clientes_sem_lojas,
    ROUND((SUM(CASE WHEN Lojas > 0 THEN 1 ELSE 0 END)*100.0/COUNT(DISTINCT NAME1)),1) AS percentual_com,
    ROUND(100 - (SUM(CASE WHEN Lojas > 0 THEN 1 ELSE 0 END)*100.0/COUNT(DISTINCT NAME1)),1) AS percentual_sem
FROM DadosRegionais
WHERE nome_regional IS NOT NULL
GROUP BY nome_regional
ORDER BY percentual_com DESC";
$stmt_ranking_regionais = sqlsrv_query($conn, $sql_ranking_regionais);
$ranking_regionais = [];
if ($stmt_ranking_regionais !== false) {
    while ($row = sqlsrv_fetch_array($stmt_ranking_regionais, SQLSRV_FETCH_ASSOC)) {
        $ranking_regionais[] = $row;
    }
    sqlsrv_free_stmt($stmt_ranking_regionais);
}

// Função para definir emoji com base no percentual de clientes sem lojas (percentual_sem)
function getRankingEmoji($percentual_com) {
    if ($percentual_com == 100) {
        return '🏆';
    } elseif ($percentual_com >= 90 && $percentual_com < 100) {
        return '🥇';
    } elseif ($percentual_com >= 80 && $percentual_com < 90) {
        return '🥈';
    } elseif ($percentual_com >= 70 && $percentual_com < 80) {
        return '🥉';
    } elseif ($percentual_com >= 50 && $percentual_com < 70) {
        return '⚠️';
    } elseif ($percentual_com >= 25 && $percentual_com < 50) {
        return '⛔';
    } elseif ($percentual_com > 0 && $percentual_com < 25) {
        return '❌';
    } elseif ($percentual_com == 0) {
        return '☠️';
    } else {
        return 'ℹ️';
    }
}

// Monta a query string com os filtros para a paginação
$filtrosQuery = "";
if ($f_cliente != '')       { $filtrosQuery .= "&f_cliente=" . urlencode($f_cliente); }
if ($f_regional != '')      { $filtrosQuery .= "&f_regional=" . urlencode($f_regional); }
if ($f_representante != '') { $filtrosQuery .= "&f_representante=" . urlencode($f_representante); }
if ($f_grupo != '')         { $filtrosQuery .= "&f_grupo=" . urlencode($f_grupo); }
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Número de Lojas por Cliente'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inclusão do Chart.js e Plugin de DataLabels -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
    <style>
        /* Estilos CSS mantidos como no original */
        .sidebar { height: 100vh; background-color: #133fa2; color: white; position: fixed; width: 200px; padding-top: 15px; display: flex; flex-direction: column; z-index: 1030; }
        .sidebar a { padding: 8px 12px; text-decoration: none; font-size: 0.9rem; color: white; display: block; }
        .sidebar a:hover { background-color: #ee5b2f; color: #ffffff; }
        .sidebar img { max-width: 100px; display: block; margin: 0 auto 15px auto; }
        .content { margin-left: 210px; padding: 15px; font-size: 0.8rem; }
        #loadingOverlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 10000; display: none; justify-content: center; align-items: center; transition: opacity 0.5s ease, visibility 0.5s ease; }
        #loadingContainer { width: 300px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        #loadingContainer img { max-width: 100%; height: auto; }
        .loading-text { font-size: 24px; margin-top: 20px; }
        .loading-phrase { font-size: 18px; margin-top: 10px; min-height: 2.5em; }
        .pagination-container { margin-top: 20px; display: flex; justify-content: center; }
        .pagination { display: flex; padding-left: 0; list-style: none; border-radius: 0.25rem; }
        .page-item { margin: 0 2px; }
        .page-link { position: relative; display: block; padding: 0.375rem 0.75rem; line-height: 1.25; color: #0d6efd; background-color: #fff; border: 1px solid #dee2e6; border-radius: 0.25rem; font-size: 0.8rem; }
        .page-link:hover { z-index: 2; color: #0a58ca; background-color: #e9ecef; border-color: #dee2e6; text-decoration: none; }
        .page-item.active .page-link { z-index: 3; color: #fff; background-color: #0d6efd; border-color: #0d6efd; }
        .page-item.disabled .page-link { color: #6c757d; pointer-events: none; background-color: #fff; border-color: #dee2e6; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
        .card-summary { border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); transition: transform 0.3s ease, box-shadow 0.3s ease; margin-bottom: 1rem; }
        .card-summary:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2); }
        .card-body-icon { font-size: 2.5rem; margin-right: 15px; }
        .card-title-number { font-size: 1.5rem; font-weight: bold; margin-bottom: 5px; }
        .card-text { margin-bottom: 0.25rem; }
        .card-text-small { font-size: 0.8rem; color: #6c757d; margin-bottom: 0; }
        .table { font-size: 0.8rem; }
        .table th, .table td { padding: 0.5rem; vertical-align: middle; }
        .form-control { font-size: 0.8rem; height: calc(1.5em + 0.75rem + 2px); padding: 0.375rem 0.75rem; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.7rem; border-radius: 0.2rem; }
        .alert-dismissible .btn-close { padding: 0.75rem 1rem; }
        /* Ajuste para o gráfico de grupo: margem superior para o título */
        #chartClientesGrupo {
            margin-top: 40px;
        }

        /* Ajustes botão limpar filtros*/
        #applied-filters {
            flex-grow: 1;
        }

        /* Se o container estiver vazio, não expande */
        #applied-filters:empty {
            flex-grow: 0;
        }

        /* Estilo para os containers de ranking (mini-cards) */
        .ranking-container {
            height: 240px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .mini-card {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 8px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .mini-card span {
            display: block;
        }
        .ranking-info {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div id="loadingOverlay">
        <div id="loadingContainer">
            <img src="/forecast/public/assets/img/logo_color1.png" alt="Logo da Empresa">
            <div class="loading-text">CARREGANDO</div>
            <div class="loading-phrase">Preparando os dados...</div>
        </div>
    </div>

    <div class="content">
        <h2 class="mb-4"><?php echo htmlspecialchars($pageTitle ?? 'Número de Lojas por Cliente'); ?></h2>

        <?php if (isset($_SESSION['mensagem_sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['mensagem_sucesso']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['mensagem_sucesso']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['mensagem_erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['mensagem_erro']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['mensagem_erro']); ?>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card card-summary border-primary h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-users card-body-icon text-primary"></i>
                        <div>
                            <h5 class="card-title-number"><?php echo formatarNumero($total_clientes_resumo); ?></h5>
                            <h6 class="card-text">Total de Clientes</h6>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-summary border-success h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-store card-body-icon text-success"></i>
                        <div>
                            <h5 class="card-title-number"><?php echo formatarNumero($clientes_com_lojas); ?></h5>
                            <h6 class="card-text">Clientes com Lojas Apontadas</h6>
                            <p class="card-text-small">(<?php echo $percentual_com_lojas_formatado; ?>%)</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-summary border-warning h-100">
                    <div class="card-body d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle card-body-icon text-warning"></i>
                        <div>
                            <h5 class="card-title-number"><?php echo formatarNumero($clientes_sem_lojas); ?></h5>
                            <h6 class="card-text">Clientes sem Lojas Apontadas</h6>
                            <p class="card-text-small">(<?php echo $percentual_sem_lojas_formatado; ?>%)</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NOVA SEÇÃO: Gráficos e Rankings -->
        <div class="row mb-4">
            <!-- Coluna Esquerda: Gráfico de Quantidade de lojas por grupo -->
            <div class="col-md-6" style="height: 550px;">
                <div class="chart-header" style="text-align: left; margin-bottom: 5px;">
                    <h3 style="margin: 0; font-size: 1.5em;">Quantidade de lojas por grupo</h3>
                    <h5 style="margin: 0; font-style: italic; font-size: 0.9em; color: rgba(0,0,0,0.46);">
                        Destaque ao apontamento de lojas
                    </h5>
                </div>
                <canvas id="chartClientesGrupo" style="width: 100%; height: 100%;"></canvas>
            </div>
            <!-- Coluna Direita: Rankings (Mini-cards) -->
            <div class="col-md-6">
                <!-- Ranking de Representantes -->
                <div class="mb-4" style="height: 280px;">
                    <div class="chart-header" style="text-align: left; margin-bottom: 5px;">
                        <h3 style="margin: 0; font-size: 1.3em;">Ranking de Representantes com PDV apontados 🚨</h3>
                        <h5 style="margin: 0; font-style: italic; font-size: 0.8em; color: rgba(0,0,0,0.46);">
                            Percentual de clientes com lojas: menor é pior
                        </h5>
                    </div>
                    <div class="ranking-container" id="rankingRepsContainer">
                    <?php
                    if (!empty($ranking_reps)) {
                        foreach ($ranking_reps as $i => $rep) {
                            // Aqui usamos o valor percentual_com para definir o emoji
                            $emoji = getRankingEmoji(floatval($rep['percentual_com']));
                            ?>
                            <div class="mini-card">
                                <div>
                                    <strong><?php echo $emoji . " " . htmlspecialchars($rep['representante']); ?></strong>
                                    <div class="ranking-info">
                                        Total: <?php echo $rep['total_clientes']; ?> |
                                        Com Lojas: <?php echo $rep['clientes_com_lojas']; ?> |
                                        Sem Lojas: <?php echo $rep['clientes_sem_lojas']; ?> |
                                        Percentual: <?php echo number_format($rep['percentual_com'], 1, ',', ''); ?>%
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='ranking-info'>Nenhum registro encontrado.</div>";
                    }
                    ?>
                    </div>
                </div>
                <!-- Ranking de Regionais -->
                <div style="height: 260px;">
                    <div class="chart-header" style="text-align: left; margin-bottom: 5px;">
                        <h3 style="margin: 0; font-size: 1.3em;">Ranking de Regionais com PDV apontados 🚨</h3>
                        <h5 style="margin: 0; font-style: italic; font-size: 0.8em; color: rgba(0,0,0,0.46);">
                            Percentual de clientes com lojas: menor é pior
                        </h5>
                    </div>
                    <div class="ranking-container" id="rankingRegionaisContainer">
                    <?php
                    if (!empty($ranking_regionais)) {
                        foreach ($ranking_regionais as $i => $reg) {
                            $emoji = getRankingEmoji(floatval($reg['percentual_com']));
                            ?>
                            <div class="mini-card">
                                <div>
                                    <strong><?php echo $emoji . " " . htmlspecialchars($reg['nome_regional']); ?></strong>
                                    <div class="ranking-info">
                                        Total: <?php echo $reg['total_clientes']; ?> |
                                        Com Lojas: <?php echo $reg['clientes_com_lojas']; ?> |
                                        Sem Lojas: <?php echo $reg['clientes_sem_lojas']; ?> |
                                        Percentual: <?php echo number_format($reg['percentual_com'], 1, ',', ''); ?>%
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='ranking-info'>Nenhum registro encontrado.</div>";
                    }
                    ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- BLOCO: Controles de Filtros Acumulados -->
        <div id="filter-controls" class="card mb-4">
            <div class="card-header">
                <h5>Filtros Aplicados</h5>
            </div>
                <div class="card-body">
                <!-- Container flexível que agrupa os filtros aplicados e o botão -->
                <div class="d-flex align-items-center mb-2">
                    <div id="applied-filters"></div>
                    <button id="btn-clear-filtros" type="button" class="btn btn-secondary">Limpar Filtros</button>
                </div>
                
                <!-- Continuação dos controles de filtros -->
                <div class="row">
                    <div class="col-md-4">
                        <label for="filter-type" class="form-label">Tipo de Filtro</label>
                        <select id="filter-type" class="form-control">
                            <option value="f_cliente">Nome do Cliente</option>
                            <option value="f_regional">Regional</option>
                            <option value="f_representante">Representante</option>
                            <option value="f_grupo">Grupo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="filter-value" class="form-label">Valor</label>
                        <div id="filter-value-container"></div>
                    </div>
                </div>
            </div>

        <!-- Formulário para seleção de registros por página -->
        <div style="margin-bottom: 15px; text-align: right; padding-right: 10px;">
            <form id="registrosPorPaginaForm" method="get" action="index.php">
                <input type="hidden" name="page" value="pdv_clientes">
                <?php 
                if($f_cliente != '') echo '<input type="hidden" name="f_cliente" value="'.htmlspecialchars($f_cliente).'">';
                if($f_regional != '') echo '<input type="hidden" name="f_regional" value="'.htmlspecialchars($f_regional).'">';
                if($f_representante != '') echo '<input type="hidden" name="f_representante" value="'.htmlspecialchars($f_representante).'">';
                if($f_grupo != '') echo '<input type="hidden" name="f_grupo" value="'.htmlspecialchars($f_grupo).'">';
                ?>
                <label for="itens_por_pagina">Registros por página:</label>
                <select name="itens_por_pagina" id="itens_por_pagina" onchange="document.getElementById('registrosPorPaginaForm').submit();">
                    <option value="25" <?php if($itens_por_pagina == 25) echo 'selected'; ?>>25</option>
                    <option value="50" <?php if($itens_por_pagina == 50) echo 'selected'; ?>>50</option>
                    <option value="75" <?php if($itens_por_pagina == 75) echo 'selected'; ?>>75</option>
                    <option value="100" <?php if($itens_por_pagina == 100) echo 'selected'; ?>>100</option>
                </select>
            </form>
        </div>

        <!-- Formulário oculto para envio dos filtros acumulados -->
        <form id="filter-form" method="get" action="index.php">
          <input type="hidden" name="page" value="pdv_clientes">
          <div id="hidden-filters"></div>
        </form>

        <!-- Formulário principal (atualização) -->
        <form method="post" action="index.php?page=pdv_clientes<?php echo ($pagina_atual > 1 ? '&pagina=' . $pagina_atual : '') . $filtrosQuery; ?>">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>Cód.</th>
                            <th>Cliente</th>
                            <th>Regional</th>
                            <th>Representante</th>
                            <th>Grupo</th>
                            <th>Nº Lojas</th>
                            <th>Últ. Apont.</th>
                            <th>Utilizador</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Nenhum cliente encontrado para esta página.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente):
                                $codClienteHtml = htmlspecialchars((string)($cliente['cod_cliente'] ?? ''), ENT_QUOTES);
                            ?>
                                <tr>
                                    <td><?php echo $codClienteHtml; ?></td>
                                    <td><?php echo htmlspecialchars($cliente['nome_cliente'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['nome_regional'] ?? ($cliente['REGIONAL'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['representante'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($cliente['GRP_CLIENTE'] ?? '-'); ?></td>
                                    <td>
                                        <input type="number"
                                               name="num_lojas_<?php echo $codClienteHtml; ?>"
                                               value="<?php echo htmlspecialchars((string)($cliente['num_lojas'] ?? 0), ENT_QUOTES); ?>"
                                               class="form-control" style="width: 90px;" min="0" required>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($cliente['data_apontamento'])) {
                                            $dataObj = $cliente['data_apontamento'];
                                            if ($dataObj instanceof DateTimeInterface) {
                                                echo htmlspecialchars($dataObj->format('d/m/Y H:i'));
                                            } else {
                                                $dataConvertida = date_create_from_format('Y-m-d H:i:s.u', is_string($dataObj) ? $dataObj : '');
                                                if ($dataConvertida) {
                                                   echo htmlspecialchars($dataConvertida->format('d/m/Y H:i'));
                                                } else {
                                                   echo '<span class="text-muted">Data inválida</span>';
                                                }
                                            }
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cliente['usuario_apontamento'] ?? 'N/A'); ?></td>
                                    <td>
                                        <button type="submit" name="atualizar_lojas" value="<?php echo $codClienteHtml; ?>" class="btn btn-warning btn-sm" title="Salvar alterações para este cliente">
                                            <i class="fas fa-save"></i> Alterar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <?php if ($total_paginas > 1): ?>
        <div class="pagination-container">
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo ($pagina_atual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=pdv_clientes&pagina=<?php echo $pagina_atual - 1 . $filtrosQuery; ?>" aria-label="Anterior">
                            <span aria-hidden="true">&laquo;</span>
                            <span class="sr-only">Anterior</span>
                        </a>
                    </li>
                    <?php
                    $max_links = 5;
                    $lado = floor($max_links / 2);
                    $inicio = max(1, $pagina_atual - $lado);
                    $fim = min($total_paginas, $pagina_atual + $lado);
                    if ($fim - $inicio + 1 < $max_links) {
                        if ($inicio == 1) $fim = min($total_paginas, $max_links);
                        elseif ($fim == $total_paginas) $inicio = max(1, $total_paginas - $max_links + 1);
                    }
                    if ($inicio > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=pdv_clientes&pagina=1' . $filtrosQuery . '">1</a></li>';
                        if ($inicio > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    for ($i = $inicio; $i <= $fim; $i++) {
                        $active = $pagina_atual == $i ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=pdv_clientes&pagina=' . $i . $filtrosQuery . '">' . $i . '</a></li>';
                    }
                    if ($fim < $total_paginas) {
                        if ($fim < $total_paginas - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page=pdv_clientes&pagina=' . $total_paginas . $filtrosQuery . '">' . $total_paginas . '</a></li>';
                    }
                    ?>
                    <li class="page-item <?php echo ($pagina_atual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=pdv_clientes&pagina=<?php echo $pagina_atual + 1 . $filtrosQuery; ?>" aria-label="Próximo">
                            <span aria-hidden="true">&raquo;</span>
                            <span class="sr-only">Próximo</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Gráfico de "Quantidade de lojas por grupo"
        document.addEventListener('DOMContentLoaded', function(){
            const ctx = document.getElementById('chartClientesGrupo').getContext('2d');
            const graficoDados = <?php echo json_encode($grafico_dados); ?>;
            graficoDados.sort((a, b) => {
                const grupoA = a.GRP_CLIENTE ? a.GRP_CLIENTE : 'SEM GRUPO';
                const grupoB = b.GRP_CLIENTE ? b.GRP_CLIENTE : 'SEM GRUPO';
                if (grupoA === 'SEM GRUPO') return 1;
                if (grupoB === 'SEM GRUPO') return -1;
                return grupoA.localeCompare(grupoB);
            });
            const grupos = graficoDados.map(item => item.GRP_CLIENTE ? item.GRP_CLIENTE : 'SEM GRUPO');
            const totalPorGrupo = graficoDados.map(item => parseInt(item.total));
            const comLojas = graficoDados.map(item => parseInt(item.com_lojas));
            const semLojas = graficoDados.map(item => parseInt(item.sem_lojas));
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: grupos,
                    datasets: [
                        {
                            label: 'Com Lojas',
                            data: comLojas,
                            backgroundColor: 'rgba(0,0,139,0.8)',
                            borderColor: 'white',
                            borderWidth: 1,
                            datalabels: {
                                color: 'black',
                                formatter: function(value, context) {
                                    const total = totalPorGrupo[context.dataIndex];
                                    const percentual = total ? ((value/total)*100).toFixed(1) : 0;
                                    return percentual + '%';
                                },
                                anchor: 'end',
                                align: 'start'
                            }
                        },
                        {
                            label: 'Sem Lojas',
                            data: semLojas,
                            backgroundColor: 'rgba(84, 130, 216, 0.8)',
                            borderColor: 'white',
                            borderWidth: 1,
                            datalabels: {
                                color: 'white',
                                formatter: function(value, context) {
                                    const total = totalPorGrupo[context.dataIndex];
                                    const percentual = total ? ((value/total)*100).toFixed(1) : 0;
                                    return percentual + '%';
                                },
                                anchor: 'end',
                                align: 'start'
                            }
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 1500, easing: 'easeOutBounce' },
                    layout: { padding: { bottom: 20 } },
                    plugins: {
                        legend: { position: 'bottom', align: 'center' },
                        datalabels: { display: true, font: { weight: 'bold' } },
                        tooltip: {
                            callbacks: {
                                title: function(context) {
                                    const index = context[0].dataIndex;
                                    return grupos[index] + ' - Total: ' + totalPorGrupo[index];
                                }
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, categoryPercentage: 1.0, barPercentage: 1.0, ticks: { padding: 15 }, title: { display: false } },
                        y: { stacked: true, beginAtZero: true, grid: { display: false }, ticks: { display: false }, title: { display: false } }
                    },
                    totalData: totalPorGrupo
                },
                plugins: [ChartDataLabels]
            });
        });

        // Loading Overlay (mantido inalterado)
        document.addEventListener('DOMContentLoaded', function(){
            const frases = [
                "TI recarregando, segura essa! ⚡️", "Café na veia, página em produção! ☕️🚀",
                "Carregando mais devagar que relatório! 🐢📊", "Já pediu café? Tá demorando! ☕️⏳",
                "Humor corporativo em atualização! 😂💼", "Paciência é KPI, obrigado! ⏳📈",
                "Relatório? Só piada de TI! 📑🤣", "Torça pra internet não cair 🤞📶",
                "Processando: pausa pro cafezinho! ⏸☕️", "Alô, chefe! Página quase pronta! 👨‍💼🚀",
                "Aguarde: humor e dados chegando! 📊😄", "Reiniciando: TI não dorme, só carrega! 🔄💻",
                "Se a impressora esperasse, já era! 🖨️😂", "Entre reuniões, página se ajeita! 🕒💼",
                "Conteúdo a caminho, com café extra! 🚚☕️"
            ];
            const fraseElemento = document.querySelector(".loading-phrase");
            let intervalId = null;
            function atualizarFrase() {
                if (fraseElemento) {
                    const randomIndex = Math.floor(Math.random() * frases.length);
                    fraseElemento.textContent = frases[randomIndex];
                }
            }
            function mostrarLoading() {
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) {
                    overlay.style.display = 'flex';
                    atualizarFrase();
                    if (intervalId) clearInterval(intervalId);
                    intervalId = setInterval(atualizarFrase, 2000);
                }
            }
            function esconderLoading() {
                 const overlay = document.getElementById('loadingOverlay');
                 if (overlay) {
                     overlay.style.display = 'none';
                     if (intervalId) clearInterval(intervalId);
                     intervalId = null;
                 }
            }
            esconderLoading();
            window.addEventListener('load', esconderLoading);
            document.addEventListener('click', function(e){
                let linkTarget = e.target.closest('a');
                if (linkTarget) {
                    const href = linkTarget.getAttribute('href');
                    const noLoading = linkTarget.hasAttribute('data-no-loading');
                    const isExport = href && href.includes("export_forecast.php");
                    const isAnchor = href && href.startsWith('#');
                    const isToggle = linkTarget.hasAttribute('data-bs-toggle');
                    if (!noLoading && !isExport && href && !isAnchor && !isToggle && !href.startsWith('javascript:')) {
                        mostrarLoading();
                    }
                }
                let buttonTarget = e.target.closest('button[type="submit"][name="atualizar_lojas"]');
                if (buttonTarget && buttonTarget.closest('form')) {
                     const form = buttonTarget.closest('form');
                     const codCliente = buttonTarget.value;
                     const numLojasInput = form.querySelector(`input[name="num_lojas_${codCliente}"]`);
                     if (numLojasInput && numLojasInput.checkValidity()) {
                         mostrarLoading();
                     } else if (!numLojasInput) {
                         console.warn("Input num_lojas não encontrado para o botão clicado:", codCliente);
                         mostrarLoading();
                     }
                }
            });
            function iniciarDownloadEReload(url) {
                mostrarLoading();
                window.open(url, '_blank');
                console.log("Download iniciado para:", url);
                setTimeout(function(){
                    esconderLoading();
                }, 1000);
            }
        });

        /* BLOCO: Controle de Filtros Acumulados */
        const filtroClientes = <?php echo json_encode($filtro_clientes); ?>;
        const filtroRegionais = <?php echo json_encode($filtro_regionais); ?>;
        const filtroRepresentantes = <?php echo json_encode($filtro_representantes); ?>;
        const filtroGrupos = <?php echo json_encode($filtro_grupos); ?>;
        const opcoesFiltro = {
          'f_cliente': filtroClientes,
          'f_regional': filtroRegionais,
          'f_representante': filtroRepresentantes,
          'f_grupo': filtroGrupos
        };

        let filtrosAtivos = {
            f_cliente: [],
            f_regional: [],
            f_representante: [],
            f_grupo: []
        };

        <?php if($f_cliente !== ''): ?>
        filtrosAtivos.f_cliente = '<?php echo addslashes($f_cliente); ?>'.split(',');
        <?php endif; ?>
        <?php if($f_regional !== ''): ?>
        filtrosAtivos.f_regional = '<?php echo addslashes($f_regional); ?>'.split(',');
        <?php endif; ?>
        <?php if($f_representante !== ''): ?>
        filtrosAtivos.f_representante = '<?php echo addslashes($f_representante); ?>'.split(',');
        <?php endif; ?>
        <?php if($f_grupo !== ''): ?>
        filtrosAtivos.f_grupo = '<?php echo addslashes($f_grupo); ?>'.split(',');
        <?php endif; ?>

        function atualizarOpcoesValor() {
            const tipo = document.getElementById('filter-type').value;
            const container = document.getElementById('filter-value-container');
            container.innerHTML = '';
            if (tipo === 'f_cliente') {
                const input = document.createElement('input');
                input.type = 'text';
                input.id = 'filter-value';
                input.className = 'form-control';
                input.placeholder = 'Digite para pesquisar...';
                container.appendChild(input);
                const dataList = document.createElement('datalist');
                dataList.id = 'clientesList';
                filtroClientes.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item;
                    dataList.appendChild(option);
                });
                container.appendChild(dataList);
                input.setAttribute('list', 'clientesList');
                input.value = '';
                input.addEventListener('change', function() {
                    const valor = input.value.trim();
                    if (valor !== '' && !filtrosAtivos[tipo].includes(valor)) {
                        filtrosAtivos[tipo].push(valor);
                        atualizarFiltrosAtivos();
                        atualizarHiddenInputs();
                        input.value = '';
                    }
                });
            } else {
                const select = document.createElement('select');
                select.id = 'filter-value';
                select.className = 'form-control';
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Selecione';
                select.appendChild(defaultOption);
                let opcoes = opcoesFiltro[tipo] || [];
                opcoes.forEach(item => {
                    let valor, texto;
                    if (tipo === 'f_regional' && typeof item === 'object') {
                        valor = item['regionais'];
                        texto = item['nome_regional'];
                    } else {
                        valor = item;
                        texto = item;
                    }
                    const opt = document.createElement('option');
                    opt.value = valor;
                    opt.textContent = texto;
                    select.appendChild(opt);
                });
                container.appendChild(select);
                select.value = '';
                select.addEventListener('change', function(){
                    const valor = select.value;
                    if (valor !== '' && !filtrosAtivos[tipo].includes(valor)) {
                        filtrosAtivos[tipo].push(valor);
                        atualizarFiltrosAtivos();
                        atualizarHiddenInputs();
                        select.value = '';
                    }
                });
            }
        }

        function atualizarFiltrosAtivos() {
            const container = document.getElementById('applied-filters');
            container.innerHTML = '';
            for (let tipo in filtrosAtivos) {
                filtrosAtivos[tipo].forEach((valor, index) => {
                    const chip = document.createElement('span');
                    chip.className = 'badge bg-primary me-2';
                    let texto = '';
                    if (tipo === 'f_regional') {
                        const opcao = filtroRegionais.find(item => item.regionais === valor);
                        texto = opcao ? `Regional: ${opcao.nome_regional}` : 'Regional: ' + valor;
                    } else if (tipo === 'f_cliente') {
                        texto = `Cliente: ${valor}`;
                    } else if (tipo === 'f_representante') {
                        texto = `Representante: ${valor}`;
                    } else if (tipo === 'f_grupo') {
                        texto = `Grupo: ${valor}`;
                    }
                    chip.textContent = texto;
                    const btnRemover = document.createElement('button');
                    btnRemover.textContent = ' x';
                    btnRemover.className = 'btn btn-sm btn-danger ms-1';
                    btnRemover.addEventListener('click', function(){
                        filtrosAtivos[tipo] = filtrosAtivos[tipo].filter(v => v !== valor);
                        atualizarFiltrosAtivos();
                        atualizarHiddenInputs();
                    });
                    chip.appendChild(btnRemover);
                    container.appendChild(chip);
                });
            }
            // Verifica se há algum chip; se não houver, esconde o botão
            const btnClear = document.getElementById('btn-clear-filtros');
            if (container.childElementCount === 0) {
                btnClear.style.display = 'none';
            } else {
                btnClear.style.display = 'block';
            }
        }

        function atualizarHiddenInputs() {
            const container = document.getElementById('hidden-filters');
            container.innerHTML = '';
            for (let tipo in filtrosAtivos) {
                if (filtrosAtivos[tipo].length > 0) {
                    const inputTipo = document.createElement('input');
                    inputTipo.type = 'hidden';
                    inputTipo.name = tipo;
                    inputTipo.value = filtrosAtivos[tipo].join(',');
                    container.appendChild(inputTipo);
                }
            }
            document.getElementById('filter-form').submit();
        }

        document.addEventListener('DOMContentLoaded', function(){
            atualizarOpcoesValor();
            document.getElementById('filter-type').addEventListener('change', function(){
                atualizarOpcoesValor();
            });
            document.getElementById('btn-clear-filtros').addEventListener('click', function(){
                filtrosAtivos = { f_cliente: [], f_regional: [], f_representante: [], f_grupo: [] };
                atualizarFiltrosAtivos();
                atualizarHiddenInputs();
            });
            atualizarFiltrosAtivos();
        });
        /* FIM BLOCO */
    </script>
</body>
</html>
<?php
if (isset($conn) && $conn !== null) {
    sqlsrv_close($conn);
}
ob_end_flush();
?>
