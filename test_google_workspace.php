<?php
require 'vendor/autoload.php';

// 🔹 Definir a credencial corretamente para Windows
putenv("GOOGLE_APPLICATION_CREDENTIALS=C:\\xampp\\htdocs\\forecast\\chave-credencial.json");

$client = new Google\Client();
$client->useApplicationDefaultCredentials();
$client->setScopes(['https://www.googleapis.com/auth/admin.directory.user.readonly, https://www.googleapis.com/auth/admin.directory.user']);

$service = new Google\Service\Directory($client);

try {
    $results = $service->users->listUsers([
        'domain' => 'colormaq.com.br', // Substitua pelo seu domínio
        'maxResults' => 10,
        'orderBy' => 'email'
    ]);

    if (count($results->getUsers()) == 0) {
        echo "Nenhum usuário encontrado.\n";
    } else {
        foreach ($results->getUsers() as $user) {
            echo "Usuário: " . $user->getPrimaryEmail() . "\n";
        }
    }
} catch (Google\Service\Exception $e) {
    echo 'Erro ao obter usuários: ' . $e->getMessage();
    echo "\nDetalhes do erro:\n";
    print_r($e->getErrors());
}
?>