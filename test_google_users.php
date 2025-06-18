<?php
require 'vendor/autoload.php';

// Definir a variável de ambiente com a chave correta
putenv('GOOGLE_APPLICATION_CREDENTIALS=C:\xampp\htdocs\forecast\chave_workspace.json');

$client = new Google\Client();
$client->useApplicationDefaultCredentials();
$client->setScopes(['https://www.googleapis.com/auth/admin.directory.user.readonly']);

$service = new Google\Service\Directory($client);

try {
    $results = $service->users->listUsers([
        'customer' => 'C01rd4tnj', // Substitua pelo ID correto do Google Workspace
        'maxResults' => 10,
        'orderBy' => 'email'
    ]);

    if (count($results->getUsers()) == 0) {
        echo "Nenhum usuário encontrado.\n";
    } else {
        echo "Usuários encontrados:\n";
        foreach ($results->getUsers() as $user) {
            echo "- " . $user->getPrimaryEmail() . "\n";
        }
    }
} catch (Exception $e) {
    echo 'Erro ao obter usuários: ' . $e->getMessage();
}
?>
