<?php

function buscarApontamentos($conn) {
    $sql = "SELECT * FROM apontamento_comercial";
    $stmt = sqlsrv_query($conn, $sql);
    $resultados = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $resultados[] = $row;
    }
    return $resultados;
}

?>