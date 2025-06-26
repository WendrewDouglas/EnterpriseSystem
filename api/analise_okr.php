<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$nome = $input['nome'] ?? '';

if (!$nome) {
    echo json_encode(['erro' => 'Nome do objetivo não enviado']);
    exit;
}

// Substitua com sua chave da OpenAI
$apiKey = getenv('API_OKR_NAME');

$payload = [
    "model" => "gpt-4",
    "messages" => [
        [
            "role" => "system",
            "content" => "Você é um especialista em OKRs. Analise exclusivamente o seguinte Objetivo estratégico levando em consideração as características de um bom objetivo estratégico dentro da metodologia de OKRs.
(diferente de key results e iniciativas, o objetivo tem suas características e propósitos específicos bem definidos na metodologia de OKRs). A orientação, não regra, é que tenha ligação com missão e visão da empresa que são: 

Visão: Ser referência em custo-benefício no segmento em que atua, estando presente na maior parte dos lares brasileiros, buscando a expansão no mercado internacional. 
Missão: Facilitar a vida das pessoas, oferecendo produtos acessíveis, com qualidade e sustentabilidade.

Responda formatando a resposta em HTML com quebras de linha (<br>) e uso opcional de negrito (<strong>) e emojis. A estrutura deve ser:

<strong>🎯 Objetivo analisado:</strong> ...<br>
<strong>📊 Qualidade:</strong> ...<br><br> (deve ser obrigatóriamente Péssimo, Ruim, Moderado, Bom ou Ótimo, levando em consideração uma escala de 0 a 100% onde 0 a 20 é péssimo e 80 a 100 é ótimo)

<strong>📌 Pontos de melhoria:</strong><br>
1. Clareza: ...<br>
2. Inspiração e desafio: ...<br>
3. Alinhamento com missão/visão: ...<br><br>

<strong>💡 Exemplos de melhoria:</strong><br>
- Exemplo 1: ...<br>
- Exemplo 2: ...<br><br>

<strong>✨ Mensagem final curta e motivadora:</strong> ...<br>

Use frases curtas, emojis e uma linguagem inspiradora."
        ],
        [
            "role" => "user",
            "content" => $nome
        ]
    ],
    "temperature" => 0.5
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(['erro' => curl_error($ch)]);
    exit;
}

$result = json_decode($response, true);
$resposta = $result['choices'][0]['message']['content'] ?? 'Erro ao gerar resposta';

// Tentar extrair a Qualidade com regex
$qualidade = null;
if (preg_match('/📊 Qualidade:\s*(\w+)/u', $resposta, $matches)) {
    $qualidade = trim($matches[1]); // Ex: "Bom", "Ótimo", "Ruim"
}

$conteudoHtml = $result['choices'][0]['message']['content'] ?? 'Erro ao gerar resposta';

// Extrair qualidade da resposta
preg_match('/<strong>📊 Qualidade:<\/strong>\s*(.*?)<br>/', $conteudoHtml, $matches);
$qualidadeExtraida = isset($matches[1]) ? trim(strip_tags($matches[1])) : null;

echo json_encode([
    'resposta' => $conteudoHtml,
    'qualidade' => $qualidadeExtraida
]);
