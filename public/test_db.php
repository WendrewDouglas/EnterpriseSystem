<?php
require_once __DIR__ . '/../config.php';

$db = new Database();
if ($db->getConnection()) {
    echo "Conexão com o banco de dados bem-sucedida!";
} else {
    echo "Erro ao conectar ao banco de dados.";
}
?>
