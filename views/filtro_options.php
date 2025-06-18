<?php
// Inicia o buffer de saída
ob_start();

// Inclui o controle de acesso e a conexão com o banco de dados
require_once __DIR__ . '/../includes/auto_check.php';
require_once __DIR__ . '/../includes/db_connection.php';

// Recupera a conexão
$conn = $db->getConnection();
if (!isset($conn) || $conn === null) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Conexão com o banco não estabelecida."]);
    exit;
}

// Recebe os filtros atuais via GET
$f_cliente       = isset($_GET['f_cliente']) ? trim($_GET['f_cliente']) : '';
$f_regional      = isset($_GET['f_regional']) ? trim($_GET['f_regional']) : '';
$f_representante = isset($_GET['f_representante']) ? trim($_GET['f_representante']) : '';
$f_grupo         = isset($_GET['f_grupo']) ? trim($_GET['f_grupo']) : '';

// Função auxiliar para construir a cláusula WHERE, EXCLUINDO o filtro atual
function buildWhereClause($excludeField = null) {
    global $f_cliente, $f_regional, $f_representante, $f_grupo;
    $where = "WHERE 1=1";
    $params = [];
    if ($excludeField !== 'cliente' && $f_cliente !== '') {
        $where .= " AND t1.NAME1 LIKE ?";
        $params[] = "%" . $f_cliente . "%";
    }
    if ($excludeField !== 'regional' && $f_regional !== '') {
        $where .= " AND t2.REGIONAL LIKE ?";
        $params[] = "%" . $f_regional . "%";
    }
    if ($excludeField !== 'representante' && $f_representante !== '') {
        $where .= " AND t3.NOMEREPRE LIKE ?";
        $params[] = "%" . $f_representante . "%";
    }
    if ($excludeField !== 'grupo' && $f_grupo !== '') {
        $where .= " AND t2.GRP_CLIENTE LIKE ?";
        $params[] = "%" . $f_grupo . "%";
    }
    return [$where, $params];
}

// Observação: utilizamos os mesmos joins da query principal para garantir consistência
$baseJoin = "
    FROM COMERCIAL..TB_QTDELOJAS t1
    LEFT JOIN (
        SELECT KUNNR AS COD_CLIENTE, VKGRP AS REGIONAL, LIFNR AS REPRESENTANTE, STATUS AS GRP_CLIENTE
        FROM ZSAP..SAP_CADAGENTE
        WHERE KDGRP IN ('Z1','Z2','Z3','Z4') AND VKGRP NOT IN ('SHP', 'MKP', '', ' ')
    ) t2 ON t1.COD = t2.COD_CLIENTE
    LEFT JOIN (
        SELECT *
        FROM OPENQUERY(SAP, '
            SELECT DISTINCT B.LIFNR AS CODREPRE, B.NAME1 AS NOMEREPRE
            FROM SAPHANADB.LFA1 B
            LEFT JOIN SAPHANADB.KNVP C ON C.LIFNR = B.LIFNR AND C.MANDT = B.MANDT
            WHERE C.PARVW = ''ZR'' AND B.MANDT = ''500''
        ')
    ) t3 ON t2.REPRESENTANTE = t3.CODREPRE
    LEFT JOIN DW..DEPARA_COMERCIAL t4 ON t2.REGIONAL = t4.Regional
";

// 1. Opções para filtro de clientes (campo NAME1) – NÃO aplica o filtro de cliente
list($whereClientes, $paramsClientes) = buildWhereClause('cliente');
$sqlClientes = "SELECT DISTINCT t1.NAME1 AS nome_cliente $baseJoin $whereClientes ORDER BY nome_cliente";
$stmtClientes = sqlsrv_prepare($conn, $sqlClientes, $paramsClientes);
$filtro_clientes = [];
if ($stmtClientes && sqlsrv_execute($stmtClientes)) {
    while ($row = sqlsrv_fetch_array($stmtClientes, SQLSRV_FETCH_ASSOC)) {
        $filtro_clientes[] = $row['nome_cliente'];
    }
}
sqlsrv_free_stmt($stmtClientes);

// 2. Opções para filtro de regionais – NÃO aplica o filtro de regional
list($whereRegionais, $paramsRegionais) = buildWhereClause('regional');
$sqlRegionais = "SELECT DISTINCT t2.REGIONAL, UPPER(t4.NomeRegional) AS nome_regional $baseJoin $whereRegionais ORDER BY nome_regional";
$stmtRegionais = sqlsrv_prepare($conn, $sqlRegionais, $paramsRegionais);
$filtro_regionais = [];
if ($stmtRegionais && sqlsrv_execute($stmtRegionais)) {
    while ($row = sqlsrv_fetch_array($stmtRegionais, SQLSRV_FETCH_ASSOC)) {
        $filtro_regionais[] = [
            "regional" => $row["REGIONAL"],
            "nome_regional" => $row["nome_regional"]
        ];
    }
}
sqlsrv_free_stmt($stmtRegionais);

// 3. Opções para filtro de representantes – NÃO aplica o filtro de representante
list($whereRepresentantes, $paramsRepresentantes) = buildWhereClause('representante');
$sqlRepresentantes = "
    SELECT DISTINCT 
    CASE 
        WHEN CHARINDEX(' ', t3.NOMEREPRE) = 0 THEN t3.NOMEREPRE
        ELSE 
            CASE 
                WHEN CHARINDEX(' ', t3.NOMEREPRE, CHARINDEX(' ', t3.NOMEREPRE)+1) = 0 THEN t3.NOMEREPRE
                ELSE SUBSTRING(t3.NOMEREPRE, 1, CHARINDEX(' ', t3.NOMEREPRE, CHARINDEX(' ', t3.NOMEREPRE)+1) - 1)
            END
    END AS representante
    $baseJoin
    $whereRepresentantes
    ORDER BY representante
";
$stmtRepresentantes = sqlsrv_prepare($conn, $sqlRepresentantes, $paramsRepresentantes);
$filtro_representantes = [];
if ($stmtRepresentantes && sqlsrv_execute($stmtRepresentantes)) {
    while ($row = sqlsrv_fetch_array($stmtRepresentantes, SQLSRV_FETCH_ASSOC)) {
        $filtro_representantes[] = $row["representante"];
    }
}
sqlsrv_free_stmt($stmtRepresentantes);

// 4. Opções para filtro de grupos – NÃO aplica o filtro de grupo
list($whereGrupos, $paramsGrupos) = buildWhereClause('grupo');
$sqlGrupos = "SELECT DISTINCT t2.GRP_CLIENTE AS grupo $baseJoin $whereGrupos ORDER BY grupo";
$stmtGrupos = sqlsrv_prepare($conn, $sqlGrupos, $paramsGrupos);
$filtro_grupos = [];
if ($stmtGrupos && sqlsrv_execute($stmtGrupos)) {
    while ($row = sqlsrv_fetch_array($stmtGrupos, SQLSRV_FETCH_ASSOC)) {
        $filtro_grupos[] = $row["grupo"];
    }
}
sqlsrv_free_stmt($stmtGrupos);

// Monta o array de retorno
$result = [
    "filtro_clientes"       => $filtro_clientes,
    "filtro_regionais"      => $filtro_regionais,
    "filtro_representantes" => $filtro_representantes,
    "filtro_grupos"         => $filtro_grupos
];

// Define o cabeçalho como JSON e retorna a resposta
header('Content-Type: application/json');
echo json_encode($result);

// Finaliza o buffer
ob_end_flush();
?>
