<?php

declare(strict_types=1);

use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use Eva\Application\Cognitive\StructuredSummaryUnit;
use Eva\Application\Query\InputType;
use Eva\Application\Query\InputUnderstanding;
use Eva\Application\Query\QueryContext;
use Eva\Application\Query\RetrievedEvidence;
use Eva\Infrastructure\Ai\AiProviderException;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
use Eva\Infrastructure\Ai\SummaryProvider;
use Eva\Infrastructure\Ai\QueryAnswerProvider;
use Eva\Infrastructure\Ai\JsonHttpClientInterface;
use Eva\Infrastructure\Ai\EmbeddingProvider;

$container = require __DIR__ . '/bootstrap.php';
$container['ai']['live_enabled'] = false;
$assertions = 0;

function assertAiAdapter(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class CapturingJsonHttpClient implements JsonHttpClientInterface
{
    /** @var list<array{url: string, headers: list<string>, payload: array<string, mixed>, timeout: int}> */
    public array $requests = [];

    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses)
    {
    }

    public function post(string $url, array $headers, array $payload, int $timeoutSeconds): array
    {
        $this->requests[] = [
            'url' => $url,
            'headers' => $headers,
            'payload' => $payload,
            'timeout' => $timeoutSeconds,
        ];

        $response = array_shift($this->responses);

        if (!is_array($response)) {
            throw new RuntimeException('Resposta simulada ausente.');
        }

        return $response;
    }
}

assertAiAdapter(($container['ai']['live_enabled'] ?? null) === false, 'Chamadas reais devem iniciar desativadas.');
foreach ($container['ai']['providers'] as $providerConfig) {
    assertAiAdapter(!isset($providerConfig['api_key']), 'Credenciais não devem integrar o array de configuração.');
    assertAiAdapter(isset($providerConfig['api_key_environment']), 'A configuração deve referenciar a credencial por ambiente.');
}

$longOrganizedContent = str_repeat('Conteúdo semanticamente organizado. ', 250);
$embeddingHttp = new CapturingJsonHttpClient([[
    'object' => 'list',
    'model' => 'embedding-model-test',
    'data' => [
        ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2, 0.3]],
        ['object' => 'embedding', 'index' => 1, 'embedding' => [-0.4, 0.5, 0.6]],
    ],
    'usage' => ['prompt_tokens' => 123, 'total_tokens' => 123],
]]);
$embeddingProvider = new EmbeddingProvider(
    $embeddingHttp,
    'test-key',
    'embedding-model-test',
    'https://embedding-provider.test/v1/embeddings',
    17
);
$embeddingUnits = [
    new StructuredEmbeddingUnit('EVA-E000001', $longOrganizedContent),
    new StructuredEmbeddingUnit('EVA-E000002', 'Segunda unidade completa.'),
];
$embeddingResult = $embeddingProvider->embed($embeddingUnits);

assertAiAdapter(count($embeddingHttp->requests) === 1, 'O lote de embeddings deve usar uma única requisição.');
assertAiAdapter($embeddingHttp->requests[0]['url'] === 'https://embedding-provider.test/v1/embeddings', 'Endpoint de embeddings inválido.');
assertAiAdapter($embeddingHttp->requests[0]['payload']['input'][0] === $longOrganizedContent, 'A unidade foi cortada antes do embedding.');
assertAiAdapter($embeddingHttp->requests[0]['payload']['input'][1] === 'Segunda unidade completa.', 'A segunda unidade foi alterada.');
assertAiAdapter($embeddingHttp->requests[0]['payload']['encoding_format'] === 'float', 'Formato vetorial inválido.');
assertAiAdapter($embeddingHttp->requests[0]['timeout'] === 17, 'Timeout do provedor não foi respeitado.');
assertAiAdapter(count($embeddingResult->vectors) === 2, 'Quantidade de vetores simulados inválida.');
assertAiAdapter($embeddingResult->vectors[0]->contentHash === hash('sha256', $longOrganizedContent), 'Hash do conteúdo vetorizado inválido.');
assertAiAdapter($embeddingResult->vectors[0]->dimensions() === 3, 'Dimensão vetorial inválida.');
assertAiAdapter($embeddingResult->inputTokens === 123, 'Uso de tokens do provedor inválido.');

$summaryHttp = new CapturingJsonHttpClient([[
    'model' => 'language-model-test',
    'choices' => [[
        'message' => ['role' => 'assistant', 'content' => '{"summary":"Síntese neutra e rastreável."}'],
    ]],
    'usage' => ['prompt_tokens' => 70, 'completion_tokens' => 12],
]]);
$summaryProvider = new SummaryProvider(
    $summaryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions',
    300,
    19
);
$summaryUnit = new StructuredSummaryUnit(
    'Documento real',
    'chapter',
    'Capítulo completo',
    '/capitulo-completo',
    $longOrganizedContent,
    [[
        'title' => 'Subseção',
        'structural_path' => '/capitulo-completo/subsecao',
        'summary' => 'Resumo filho explícito.',
    ]]
);
$summaryResult = $summaryProvider->summarize($summaryUnit);
$summaryRequest = $summaryHttp->requests[0];
$userMessage = $summaryRequest['payload']['messages'][1]['content'];
$encodedUnit = substr($userMessage, strpos($userMessage, "\n") + 1);
$decodedUnit = json_decode($encodedUnit, true, 512, JSON_THROW_ON_ERROR);
$systemPrompt = $summaryRequest['payload']['messages'][0]['content'];

assertAiAdapter($summaryRequest['url'] === 'https://language-provider.test/v1/chat/completions', 'Endpoint de sínteses inválido.');
assertAiAdapter($decodedUnit['own_content'] === $longOrganizedContent, 'O conteúdo do resumo foi cortado.');
assertAiAdapter($decodedUnit['child_summaries'][0]['summary'] === 'Resumo filho explícito.', 'O resumo filho foi alterado.');
assertAiAdapter(str_contains($systemPrompt, 'não atribua pesos'), 'A neutralidade cognitiva não está explícita no prompt.');
assertAiAdapter(str_contains($systemPrompt, 'Não invente relações'), 'O prompt deve proibir relações inferidas.');
assertAiAdapter($summaryRequest['payload']['response_format']['type'] === 'json_object', 'A resposta do provedor deve ser JSON.');
assertAiAdapter($summaryRequest['payload']['thinking']['type'] === 'disabled', 'O modo econômico não foi configurado.');
assertAiAdapter($summaryRequest['payload']['max_tokens'] === 300, 'Limite de saída do provedor inválido.');
assertAiAdapter($summaryResult->summary === 'Síntese neutra e rastreável.', 'Resumo do provedor inválido.');
assertAiAdapter($summaryResult->inputTokens === 70 && $summaryResult->outputTokens === 12, 'Uso de tokens do provedor inválido.');

$controlCharacterHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => "{\"summary\":\"Linha 1\nLinha 2\"}"],
    ]],
]]);
$controlCharacterSummary = (new SummaryProvider(
    $controlCharacterHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->summarize($summaryUnit);
assertAiAdapter(
    $controlCharacterSummary->summary === "Linha 1\nLinha 2",
    'Caracteres de controle em strings JSON devem ser escapados sem alterar o resumo.'
);

$malformedEnvelopeHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => "{\"summary\":\"Linha com \"termo citado\"\nLinha final\"}"],
    ]],
]]);
$recoveredEnvelopeSummary = (new SummaryProvider(
    $malformedEnvelopeHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->summarize($summaryUnit);
assertAiAdapter(
    $recoveredEnvelopeSummary->summary === "Linha com \"termo citado\"\nLinha final",
    'O envelope de resumo deve recuperar aspas internas sem alterar seu conteúdo.'
);

$fencedEnvelopeHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => "```json\n{\"summary\":\"Síntese cercada por Markdown.\"}\n```"],
    ]],
]]);
$fencedEnvelopeSummary = (new SummaryProvider(
    $fencedEnvelopeHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->summarize($summaryUnit);
assertAiAdapter(
    $fencedEnvelopeSummary->summary === 'Síntese cercada por Markdown.',
    'O envelope JSON cercado por Markdown deve ser normalizado.'
);

$commentedEnvelopeHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => "Resposta solicitada:\n{\"summary\":\"Síntese com texto externo.\"}\nFim."],
    ]],
]]);
$commentedEnvelopeSummary = (new SummaryProvider(
    $commentedEnvelopeHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->summarize($summaryUnit);
assertAiAdapter(
    $commentedEnvelopeSummary->summary === 'Síntese com texto externo.',
    'O objeto JSON deve ser extraído sem aceitar o texto externo como síntese.'
);

$plainTextHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => 'Síntese sem qualquer envelope estruturado.'],
    ]],
]]);
$plainTextRejected = false;

try {
    (new SummaryProvider(
        $plainTextHttp,
        'test-key',
        'language-model-test',
        'https://language-provider.test/v1/chat/completions'
    ))->summarize($summaryUnit);
} catch (AiProviderException $exception) {
    $plainTextRejected = str_contains($exception->getMessage(), 'fora do JSON exigido');
}

assertAiAdapter($plainTextRejected, 'Texto livre sem envelope JSON deve continuar rejeitado.');

$truncatedEnvelopeHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'finish_reason' => 'length',
        'message' => ['content' => '{"summary":"Síntese interrompida'],
    ]],
]]);
$truncatedEnvelopeRejected = false;

try {
    (new SummaryProvider(
        $truncatedEnvelopeHttp,
        'test-key',
        'language-model-test',
        'https://language-provider.test/v1/chat/completions'
    ))->summarize($summaryUnit);
} catch (AiProviderException $exception) {
    $truncatedEnvelopeRejected = str_contains($exception->getMessage(), 'truncou o resumo');
}

assertAiAdapter($truncatedEnvelopeRejected, 'Resumos truncados pelo limite de saída devem ser identificados.');

$leftContent = 'O conceito A interage explicitamente com o conceito B.';
$rightContent = 'O conceito B interage explicitamente com o conceito A.';
$queryEvidence = new RetrievedEvidence(
    1,
    'EVA-E000001',
    'Documento real',
    'Unidade completa',
    '/unidade-completa',
    'linha 1',
    $leftContent
);
$secondQueryEvidence = new RetrievedEvidence(
    2,
    'EVA-E000002',
    'Documento real',
    'Segunda unidade completa',
    '/segunda-unidade',
    'linha 2',
    $rightContent
);
$queryContext = new QueryContext(
    new InputUnderstanding([InputType::Relational, InputType::Conceptual], []),
    [$queryEvidence, $secondQueryEvidence],
    3,
    ['evidence:EVA-E000001:primary:node_content'],
    []
);
$queryHttp = new CapturingJsonHttpClient([[
    'model' => 'language-model-test',
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'Resposta sustentada [EVA-E000001] [EVA-E000002].',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
            'interactions' => [[
                'interaction_type' => 'simetry',
                'summary' => 'Interação recíproca explicitamente descrita.',
                'left_evidence_id' => 'EVA-E000001',
                'right_evidence_id' => 'EVA-E000002',
                'origin_evidence_id' => null,
                'left_excerpt' => $leftContent,
                'right_excerpt' => $rightContent,
            ]],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
$queryProvider = new QueryAnswerProvider(
    $queryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
);
$queryAnswer = $queryProvider->answer('Explique a unidade.', $queryContext);
$queryMessage = $queryHttp->requests[0]['payload']['messages'][1]['content'];
$queryMessageLines = explode("\n", $queryMessage, 3);
$queryJson = $queryMessageLines[2] ?? '';
$queryPayload = json_decode($queryJson, true, 512, JSON_THROW_ON_ERROR);

assertAiAdapter(
    $queryPayload['primary_evidences'][0]['content'] === $leftContent,
    'A evidencia primaria foi cortada antes da resposta.'
);
assertAiAdapter(
    $queryHttp->requests[0]['payload']['response_format']['type'] === 'json_object',
    'A resposta de consulta deve usar JSON estruturado.'
);
assertAiAdapter(
    $queryHttp->requests[0]['payload']['max_tokens'] === 1800,
    'O teto padrão da resposta de consulta deve comportar o contrato relacional completo.'
);
assertAiAdapter(
    str_contains($queryMessage, 'interaction_limit é um teto de segurança, não uma meta'),
    'O comando de saída deve impedir o preenchimento artificial do limite de interações.'
);
assertAiAdapter($queryPayload['analyze_interactions'] === true, 'A consulta relacional deve ativar interações transitórias.');
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'operadores cognitivos internos e essenciais'),
    'O prompt deve preservar simetry e assimetry na compreensão cognitiva.'
);
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'conjunto de candidatos'),
    'O prompt deve exigir análise dos candidatos lexicais sem descarte por intrusos.'
);
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'avalie por si mesmo se a solicitação atual é continuidade')
        && str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'não são evidências documentais'),
    'O prompt deve avaliar a continuidade sem transformar o histórico em evidência.'
);
assertAiAdapter($queryAnswer->usedEvidenceIds === ['EVA-E000001', 'EVA-E000002'], 'A resposta perdeu evidências utilizadas.');
assertAiAdapter(count($queryAnswer->interactions) === 1, 'A interação transitória válida foi perdida.');
assertAiAdapter($queryAnswer->interactions[0]->interactionType === 'simetry', 'A classificação simetry foi perdida.');
assertAiAdapter(!array_key_exists('id', $queryAnswer->interactions[0]->toArray()), 'A interação transitória não deve possuir identidade persistente.');
assertAiAdapter(str_contains($queryAnswer->answer, '[EVA-E000001]'), 'A resposta perdeu a citacao documental.');

$recoveredQueryPayload = [
    'answer' => 'Resposta regenerada e sustentada [EVA-E000001] [EVA-E000002].',
    'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
    'interactions' => [],
    'limitations' => [],
];
$truncatedQueryHttp = new CapturingJsonHttpClient([
    [
        'choices' => [[
            'finish_reason' => 'length',
            'message' => ['content' => '{"answer":"Resposta interrompida'],
        ]],
    ],
    [
        'choices' => [[
            'finish_reason' => 'stop',
            'message' => ['content' => json_encode(
                $recoveredQueryPayload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            )],
        ]],
    ],
]);
$recoveredQueryAnswer = (new QueryAnswerProvider(
    $truncatedQueryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Como as unidades se relacionam?', $queryContext);

assertAiAdapter(
    count($truncatedQueryHttp->requests) === 2,
    'Uma consulta truncada deve realizar uma única regeneração integral.'
);
assertAiAdapter(
    !str_contains($truncatedQueryHttp->requests[0]['payload']['messages'][1]['content'], 'Modo de recuperação compacta')
        && str_contains($truncatedQueryHttp->requests[1]['payload']['messages'][1]['content'], 'Modo de recuperação compacta'),
    'Somente a regeneração deve receber o comando compacto adicional.'
);
assertAiAdapter(
    $recoveredQueryAnswer->answer === $recoveredQueryPayload['answer'],
    'A saída parcial não pode ser reparada nem aproveitada após a regeneração.'
);

$repeatedTruncationHttp = new CapturingJsonHttpClient([
    [
        'choices' => [[
            'finish_reason' => 'length',
            'message' => ['content' => '{"answer":"Primeira saída parcial'],
        ]],
    ],
    [
        'choices' => [[
            'finish_reason' => 'length',
            'message' => ['content' => '{"answer":"Segunda saída parcial'],
        ]],
    ],
]);
$repeatedTruncationRejected = false;

try {
    (new QueryAnswerProvider(
        $repeatedTruncationHttp,
        'test-key',
        'language-model-test',
        'https://language-provider.test/v1/chat/completions'
    ))->answer('Como as unidades se relacionam?', $queryContext);
} catch (AiProviderException $exception) {
    $repeatedTruncationRejected = str_contains($exception->getMessage(), 'após a regeneração compacta');
}

assertAiAdapter(
    $repeatedTruncationRejected && count($repeatedTruncationHttp->requests) === 2,
    'Duas saídas truncadas devem encerrar com erro explícito sem repetição ilimitada.'
);

$invalidOptionalInteractionHttp = new CapturingJsonHttpClient([[
    'model' => 'language-model-test',
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'A relação documental permanece sustentada [EVA-E000001] [EVA-E000002]. A interação é assimétrica: esta classificação pertence ao sistema.',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
            'interactions' => [[
                'interaction_type' => 'simetry',
                'summary' => 'Interação candidata sem transcrição literal.',
                'left_evidence_id' => 'EVA-E000001',
                'right_evidence_id' => 'EVA-E000002',
                'origin_evidence_id' => null,
                'left_excerpt' => 'Paráfrase ausente da primeira evidência.',
                'right_excerpt' => 'Paráfrase ausente da segunda evidência.',
            ]],
            'limitations' => ['Não foi localizada evidência suficiente no contexto recuperado para: conceito Z.'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
$answerWithDiscardedInteraction = (new QueryAnswerProvider(
    $invalidOptionalInteractionHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Como as unidades se relacionam em simetry ou assimetry?', $queryContext);

assertAiAdapter(
    $answerWithDiscardedInteraction->usedEvidenceIds === ['EVA-E000001', 'EVA-E000002'],
    'Uma interação opcional inválida não deve apagar evidências válidas da resposta.'
);
assertAiAdapter(
    $answerWithDiscardedInteraction->interactions === [],
    'Uma interação sem fragmentos literais não deve integrar o resultado.'
);
assertAiAdapter(
    str_contains($answerWithDiscardedInteraction->answer, '[EVA-E000001]'),
    'A resposta documental válida deve sobreviver ao descarte da interação.'
);
assertAiAdapter(
    array_filter(
        $answerWithDiscardedInteraction->limitations,
        static fn (string $limitation): bool => str_contains($limitation, 'ambiente relacional')
    ) !== [],
    'O descarte de todas as interações deve produzir limitação relacional explícita.'
);
assertAiAdapter(
    in_array(
        'Não foi localizada evidência suficiente no contexto recuperado para: conceito Z.',
        $answerWithDiscardedInteraction->limitations,
        true
    ),
    'A ausência de Z não deve apagar a resposta sustentada pela relação entre X e Y.'
);

$forbiddenHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'Resposta sustentada [EVA-E000001].',
            'used_evidence_ids' => ['EVA-E000001'],
            'interactions' => [['confidence' => 0.9]],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR)],
    ]],
]]);
$forbiddenRejected = false;

try {
    (new QueryAnswerProvider(
        $forbiddenHttp,
        'test-key',
        'language-model-test',
        'https://language-provider.test/v1/chat/completions'
    ))->answer('Como as unidades interagem?', $queryContext);
} catch (AiProviderException $exception) {
    $forbiddenRejected = str_contains($exception->getMessage(), 'proibido');
}

assertAiAdapter($forbiddenRejected, 'Campos de confiança ou peso devem invalidar a interação transitória.');

$disabledFactory = new CognitiveProviderFactory([
    'live_enabled' => false,
    'request_timeout_seconds' => 30,
    'providers' => $container['ai']['providers'],
]);
$blocked = false;

try {
    $disabledFactory->embeddings($embeddingHttp);
} catch (AiProviderException $exception) {
    $blocked = str_contains($exception->getMessage(), 'desativadas');
}

assertAiAdapter($blocked, 'A fábrica deve bloquear consumo real sem opt-in.');

echo sprintf("Adaptadores de IA validados com %d asserções e zero chamadas pagas.\n", $assertions);
