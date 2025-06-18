<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir o arquivo de conexão
require_once __DIR__ . '/includes/db_connection.php';

try {
    // Criar uma instância da conexão com o banco de dados
    $db = new Database();
    $conn = $db->getConnection();

    if ($conn) {
        echo "Conexão com SQL Server bem-sucedida!";
    } else {
        echo "Erro na conexão!";
    }
} catch (Exception $e) {
    echo "Erro na conexão: " . $e->getMessage();
}
?>
