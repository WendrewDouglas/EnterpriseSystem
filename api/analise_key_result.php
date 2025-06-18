<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$descricao = trim($input['descricao'] ?? '');

if (!$descricao) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Descrição do Key Result não enviada.'
    ]);
    exit;
}

// Sua chave da OpenAI (mantenha segura)
$apiKey = getenv('API_OKR_NAME');

$payload = [
    "model" => "gpt-4",
    "messages" => [
        [
            "role" => "system",
            "content" => "Você é um especialista em OKRs. Analise exclusivamente o seguinte KR levando em consideração as características de um bom Key Result dentro da metodologia de OKRs.
            Ao criar um Key Result (KR), é fundamental garantir que ele atenda às boas práticas do framework de OKR. Um KR de qualidade precisa ser claro, mensurável e diretamente conectado ao objetivo. Como OKR Master, sua análise deve considerar os seguintes pontos essenciais:
            O primeiro aspecto a ser observado é a clareza e objetividade. Um bom KR precisa ser específico, direto e sem ambiguidades. Toda a equipe deve entender exatamente o que está sendo medido e qual é o resultado esperado. Evite termos subjetivos como “melhorar” ou “aumentar” sem especificação. Um exemplo correto seria: “Reduzir o prazo médio de entrega de 12 para 8 dias”, enquanto um exemplo inadequado seria: “Melhorar os prazos”.
            Em seguida, é indispensável validar se o KR representa de fato um resultado e não uma tarefa. KRs não são atividades a serem executadas, mas sim resultados que evidenciam progresso. A pergunta-chave aqui é: “Se eu concluir esse KR, significa que atingi um resultado real ou apenas executei uma ação?”. Por exemplo, “Aumentar a satisfação dos clientes de 85% para 92%” é um KR válido. Já “Implantar uma nova ferramenta de atendimento” não é um KR, e sim uma ação.
            O KR também precisa ser, obrigatoriamente, mensurável e quantitativo. Isso significa que deve conter uma métrica, seja ela percentual, numérica, monetária ou baseada em algum critério objetivo. Sem um número, não é um Key Result, é apenas uma intenção. Por exemplo, é válido escrever “Reduzir os custos logísticos em R$ 300 mil até dezembro”, mas inadequado simplesmente escrever “Reduzir custos”.
            Outro ponto essencial é garantir que o KR esteja apontando na direção correta, que pode ser:
            Aumentar (ex.: receita, produtividade, satisfação do cliente);
            Reduzir (ex.: custos, erros, desperdícios, tempo);
            Ou Manter algum patamar (ex.: manter SLA acima de 95%, churn abaixo de 2%).
            Além disso, o KR precisa ter uma temporalidade clara, ou seja, ele deve ser mensurado dentro do ciclo vigente, seja trimestral, semestral ou anual. Pergunte-se: “Esse resultado é possível de ser acompanhado e atingido dentro do período do ciclo?”. Caso contrário, ele precisa ser reformulado.
            Na avaliação de qualidade, é necessário também observar se o KR é desafiador, mas atingível. Um KR eficaz deve gerar um nível saudável de tensão criativa: não pode ser tão fácil que não gere transformação, nem tão difícil que se torne desmotivador. O equilíbrio entre desafio e viabilidade é parte da essência dos OKRs bem construídos.
            Outro critério essencial é que o KR seja acompanhável e atualizável periodicamente. Se não houver uma fonte de dados clara, seja ela um sistema, planilha ou indicador manual, o KR perde valor. Se não for possível monitorá-lo, então ele não é efetivo e precisa ser revisado.
            Por fim, o ponto mais crítico de todos: garantir que o KR esteja diretamente alinhado ao objetivo. A pergunta final que o OKR Master sempre deve fazer é: “Se eu atingir este Key Result, meu objetivo estará mais próximo de ser alcançado?”. Se a resposta for não, o KR precisa ser ajustado.
            🚦 Checklist Final do OKR Master para Validação de um Key Result:
            Está claro, específico e objetivo?
            Mede um resultado e não uma tarefa?
            Possui indicador numérico ou critério mensurável?
            Está na direção correta (aumentar, reduzir ou manter)?
            Tem horizonte de tempo compatível com o ciclo?
            É desafiador, porém possível?
            Pode ser acompanhado com dados reais e atualizações periódicas?
            Contribui diretamente para o atingimento do objetivo?
            Se a resposta for “SIM” para todos os itens, o Key Result está bem formulado e pronto para ser aprovado e acompanhado. Caso contrário, ele deve ser revisado, pois um KR mal construído leva o time na direção errada, gera confusão e prejudica o sucesso dos OKRs.

        Responda formatando a resposta em HTML com quebras de linha (<br>) e uso opcional de negrito (<strong>) e emojis. A estrutura deve ser:

        <strong>🎯 Key Result analisado:</strong> ...<br>
        <strong>📊 Qualidade:</strong> ...<br><br> (deve ser obrigatóriamente Ruim, Moderado, Bom ou Ótimo)

        <strong>📌 Pontos de melhoria:</strong><br>
        1. Clareza: ...<br>
        2. Inspiração e desafio: ...<br>
        3. Alinhamento com missão/visão: ...<br><br>

        <strong>💡 Exemplos de melhoria:</strong><br>
        - Exemplo 1: ...<br>
        - Exemplo 2: ...<br><br>

        <strong>✨ Mensagem final curta e motivadora:</strong> ...<br>

        Use frases curtas, emojis e uma linguagem inspiradora."        ],
        [
            "role" => "user",
            "content" => $descricao
        ]
    ],
    "temperature" => 0.7
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
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro na comunicação com a API: ' . curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

$result = json_decode($response, true);

if (!isset($result['choices'][0]['message']['content'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Resposta inesperada da IA.'
    ]);
    exit;
}

$conteudoHtml = $result['choices'][0]['message']['content'];

// Tenta extrair a Qualidade
$qualidadeExtraida = null;
if (preg_match('/<strong>📊 Qualidade:<\/strong>\s*(.*?)<br>/', $conteudoHtml, $matches)) {
    $qualidadeExtraida = trim(strip_tags($matches[1]));
}

// Se a qualidade não foi encontrada, retorna erro
if (!$qualidadeExtraida) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível identificar a qualidade na resposta da IA.',
        'resposta_html' => $conteudoHtml
    ]);
    exit;
}

// Tudo certo
echo json_encode([
    'sucesso' => true,
    'qualidade' => $qualidadeExtraida,
    'resposta_html' => $conteudoHtml
]);
