<?php
require 'vendor/autoload.php';

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . getenv('GOOGLE_APPLICATION_CREDENTIALS'));

$client = new Google\Client();
$client->useApplicationDefaultCredentials();
$client->setScopes(['https://www.googleapis.com/auth/admin.directory.user.readonly']);

try {
    $service = new Google\Service\Directory($client);
    echo "Autenticação bem-sucedida! ✅";
} catch (Exception $e) {
    echo "Erro ao autenticar: " . $e->getMessage();
}
