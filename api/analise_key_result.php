<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

// DEBUG: Salva o input recebido para análise posterior
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0777, true);

$log_file = $log_dir . '/debug_okr_input.log';
$log_payload = [
    'timestamp' => date('Y-m-d H:i:s'),
    'raw_input' => file_get_contents('php://input'),
    'parsed_input' => $input
];
file_put_contents($log_file, json_encode($log_payload, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);

// Recebe todos os campos esperados do payload
$nome_objetivo   = $input['nome_objetivo'] ?? '';
$nome_kr         = $input['nome_kr'] ?? '';
$tipo_kr         = $input['tipo_kr'] ?? '';
$natureza_kr     = $input['natureza_kr'] ?? '';
$baseline        = $input['baseline'] ?? '';
$meta            = $input['meta'] ?? '';
$unidade         = $input['unidade'] ?? '';
$direcao_metrica = $input['direcao_metrica'] ?? '';
$frequencia      = $input['frequencia'] ?? '';
$data_inicio     = $input['data_inicio'] ?? '';
$data_fim        = $input['data_fim'] ?? '';
$margem_confianca= $input['margem_confianca'] ?? '';
$status_inicial  = $input['status_inicial'] ?? '';
$observacoes_kr  = $input['observacoes_kr'] ?? '';

// Mensagens específicas de debug para cada campo obrigatório
$errors = [];
if (!$nome_objetivo)   $errors[] = "nome_objetivo não enviado";
if (!$nome_kr)         $errors[] = "nome_kr não enviado";
// (pode adicionar mais campos se quiser checagem de outros)

if ($errors) {
    echo json_encode(['erro' => implode(' / ', $errors)]);
    exit;
}

// ... [restante do seu código: geração dos prompts, chamada da API, etc.]

/* --- MANTENHA O RESTANTE DO SEU SCRIPT ABAIXO, EXATAMENTE COMO JÁ ESTAVA --- */
$userPrompt = "
Objetivo estratégico: $nome_objetivo

Key Result:
- Nome: $nome_kr
- Tipo: $tipo_kr
- Natureza: $natureza_kr
- Baseline: $baseline
- Meta: $meta
- Unidade: $unidade
- Direção da métrica: $direcao_metrica
- Frequência de acompanhamento: $frequencia
- Data início: $data_inicio
- Data fim: $data_fim
- Margem de confiança: $margem_confianca
- Status inicial: $status_inicial" .
($observacoes_kr && trim($observacoes_kr) !== '' ? "\n- Observações: $observacoes_kr" : "") . "

Analise todos os campos acima, sinalizando discrepâncias, inconsistências e oportunidades de melhoria. Considere: tipo, baseline/meta (distância, realismo), unidade, direção, frequência (se faz sentido com o que está sendo medido), datas (coerência com status), margem, clareza, etc. Sugira melhorias e traga recomendações práticas e sintéticas para o contexto.
";

// [ ... sistemaPrompt e o restante ... ]

$systemPrompt = "Você é um especialista em OKRs. Analise exclusivamente o seguinte KR levando em consideração as características de um bom Key Result dentro da metodologia de OKRs.
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
            Esta diretamente ligado ao objetivo?
            Está claro, específico e objetivo?
            Mede um resultado e não uma tarefa?
            Possui indicador numérico ou critério mensurável?
            Está na direção correta (aumentar, reduzir ou manter)?
            Tem horizonte de tempo compatível com o ciclo?
            É desafiador, porém possível?
            Pode ser acompanhado com dados reais e atualizações periódicas?
            Contribui diretamente para o atingimento do objetivo?
            Se a resposta for “SIM” para todos os itens, o Key Result está bem formulado e pronto para ser aprovado e acompanhado. Caso contrário, ele deve ser revisado, pois um KR mal construído leva o time na direção errada, gera confusão e prejudica o sucesso dos OKRs.

Visão: Ser referência em custo-benefício no segmento em que atua, estando presente na maior parte dos lares brasileiros, buscando a expansão no mercado internacional. 
Missão: Facilitar a vida das pessoas, oferecendo produtos acessíveis, com qualidade e sustentabilidade.

Sua resposta **deve ser formatada como um painel visual em HTML**, simulando um card ou dashboard moderno e amigável. Use **divs, cores de fundo suaves, bordas arredondadas, espaçamento, badges coloridas para a qualidade (péssimo, ruim, moderado, bom, ótimo)**, listas e emojis para tornar a resposta fácil de entender e visualmente atraente.

**Siga o seguinte modelo de estrutura HTML:**

<div style='max-width:600px; margin:auto; background:#fff; border-radius:16px; box-shadow:0 6px 24px #0001; padding:28px 24px 22px 24px; font-family:Segoe UI, Arial, sans-serif; font-size:1.1em;'>
  <div style='display:flex; align-items:center; gap:10px; margin-bottom:12px;'>
    <span style='font-size:1.5em;'>🎯</span>
    <span style='font-size:1.2em; font-weight:bold;'>Key Result analisado:</span>
  </div>
  <div style='margin-bottom:16px; color:#003d69;'><strong>[NOME_DO_KR_AQUI]</strong></div>
  <div style='margin-bottom:18px;'>
    <span style='background:[COR_DA_QUALIDADE]; color:#fff; border-radius:12px; padding:6px 18px; font-weight:bold;'>
      📊 Qualidade: [QUALIDADE_AQUI]
    </span>
  </div>
  <div style='margin-bottom:16px;'>
    <strong>📌 Pontos de melhoria:</strong>
    <ol style='margin:8px 0 0 20px;'>
      <li><strong>Nome do Key Result:</strong> [ANÁLISE]</li>
      <li><strong>Análise do tipo de KR:</strong> [ANÁLISE]</li>
      <li><strong>Análise da natureza do KR:</strong> [ANÁLISE]</li>
      <li><strong>Análise de baseline e meta:</strong> [ANÁLISE]</li>
      <li><strong>Análise da unidade de medida:</strong> [ANÁLISE]</li>
      <li><strong>Análise da direção:</strong> [ANÁLISE]</li>
      <li><strong>Análise da frequência:</strong> [ANÁLISE]</li>
      <li><strong>Análise das datas:</strong> [ANÁLISE]</li>
      <li><strong>Análise da margem de confiança:</strong> [ANÁLISE]</li>
      <li><strong>Análise das observações:</strong> [ANÁLISE]</li>
    </ol>
  </div>
  <div style='margin-bottom:16px;'>
    <strong>💡 Recomendações para o Key Result:</strong>
    <ul style='margin:8px 0 0 20px;'>
      <li>[DICA_1]</li>
      <li>[DICA_2]</li>
    </ul>
  </div>
  <div style='background:#f4f8fb; border-radius:12px; padding:12px; text-align:center; font-size:1.12em; font-weight:500; color:#006738;'>
    ✨ [MENSAGEM FINAL CURTA E INSPIRADORA]
  </div>
</div>


Importante:

Use cores de fundo diferentes para cada qualidade de KR (exemplo: verde para 'Ótimo', amarelo para 'Moderado', vermelho para 'Ruim', etc.).

Utilize negrito, emojis e listas para facilitar a leitura.

Nunca retorne apenas texto simples — sempre siga a estrutura HTML acima, preenchendo cada campo de acordo com a análise feita.

O painel deve ser amigável, visualmente atraente e fácil de entender, mesmo para quem não é especialista em OKRs.

Depois da análise, entregue sempre a resposta completa já formatada, pronta para ser exibida como painel.

"; // Mantenha igual ao anterior!

$payload = [
    "model" => "gpt-4",
    "messages" => [
        [ "role" => "system", "content" => $systemPrompt ],
        [ "role" => "user", "content" => $userPrompt ]
    ],
    "temperature" => 0.5
];

$apiKey = getenv('API_OKR_NAME');
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

// Extrair qualidade com regex (cobre variações possíveis)
preg_match('/Qualidade:\s*([^\s<]+)/i', $resposta, $matches);
$qualidadeExtraida = isset($matches[1]) ? trim(strip_tags($matches[1])) : null;

echo json_encode([
    'resposta' => $resposta,
    'qualidade' => $qualidadeExtraida
]);
