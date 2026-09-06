<?php

declare(strict_types=1);

use Eva\Application\Cognitive\StructuredEmbeddingUnit;
use Eva\Application\Query\InputType;
use Eva\Application\Query\InputUnderstanding;
use Eva\Application\Query\QueryContext;
use Eva\Application\Query\QueryException;
use Eva\Application\Query\RetrievedEvidence;
use Eva\Infrastructure\Ai\AiProviderException;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
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
    new InputUnderstanding([InputType::Conceptual], []),
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
$queryAnswer = $queryProvider->answer('Explique a unidade.', $queryContext, []);
$queryMessage = $queryHttp->requests[0]['payload']['messages'][1]['content'];
$queryJsonStart = strpos($queryMessage, '{"input":');
$queryJson = $queryJsonStart === false ? '' : substr($queryMessage, $queryJsonStart);
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
assertAiAdapter($queryPayload['analyze_interactions'] === true, 'Duas evidências eleitas devem ativar a análise transitória de interações.');
assertAiAdapter(
    $queryPayload['evidence_selection_contract']['available_evidence_ids'] === ['EVA-E000001', 'EVA-E000002']
        && $queryPayload['primary_evidences'][0]['selection_region'] === 'core',
    'O payload deve preservar o conjunto determinístico disponível e o papel das evidências.'
);
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'operadores cognitivos internos e essenciais'),
    'O prompt deve preservar simetry e assimetry na compreensão cognitiva.'
);
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'foram recuperadas deterministicamente'),
    'O prompt deve preservar a recuperação local sem obrigar o uso artificial de todas as evidências.'
);
assertAiAdapter(
    preg_match(
        '/\[EVA-E\d{6,}\]/',
        $queryHttp->requests[0]['payload']['messages'][0]['content']
    ) !== 1
        && str_contains(
            $queryHttp->requests[0]['payload']['messages'][0]['content'],
            'presente em available_evidence_ids'
        ),
    'O prompt-base não deve oferecer um identificador ilustrativo que possa ser emitido como citação real.'
);
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'A aceitação formal de um identificador não equivale ao uso da evidência')
        && str_contains($queryMessage, 'uma lista isolada de citações é inválida'),
    'O prompt deve exigir incorporação analítica de núcleo e convergência, não apenas a devolução de IDs.'
);
assertAiAdapter(
    str_contains($queryHttp->requests[0]['payload']['messages'][0]['content'], 'colchetes literais'),
    'O prompt deve fixar o delimitador canônico das citações.'
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

$selectiveQueryHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'Somente a primeira evidência contribui para esta resposta [EVA-E000001].',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
            'interactions' => [],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
$selectiveQueryAnswer = (new QueryAnswerProvider(
    $selectiveQueryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Explique a primeira unidade.', $queryContext, []);

assertAiAdapter(
    $selectiveQueryAnswer->usedEvidenceIds === ['EVA-E000001'],
    'Uma evidência declarada pelo provedor, mas ausente das citações visíveis, deve ser descartada.'
);

$parentheticalCitationHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'A primeira evidência contribui diretamente (EVA-E000001).',
            'used_evidence_ids' => ['EVA-E000001'],
            'interactions' => [],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
$parentheticalCitationAnswer = (new QueryAnswerProvider(
    $parentheticalCitationHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Explique a primeira unidade.', $queryContext, []);

assertAiAdapter(
    $parentheticalCitationAnswer->answer === 'A primeira evidência contribui diretamente [EVA-E000001].'
        && $parentheticalCitationAnswer->usedEvidenceIds === ['EVA-E000001'],
    'Uma citação parentética inequívoca deve ser normalizada para o formato canônico antes da validação.'
);

$correctiveQueryHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'A evidência omitida agora contribui para a análise [EVA-E000001]. A segunda evidência também permanece incorporada [EVA-E000002].',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
            'interactions' => [],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
(new QueryAnswerProvider(
    $correctiveQueryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Explique a unidade.', $queryContext, [
    'code' => 'missing_analytical_evidence',
    'evidence_id' => 'EVA-E000001',
]);
$correctiveMessage = $correctiveQueryHttp->requests[0]['payload']['messages'][1]['content'];

assertAiAdapter(
    str_contains($correctiveMessage, 'validation_failure_code: missing_analytical_evidence')
        && str_contains($correctiveMessage, 'A evidência EVA-E000001 deve contribuir efetivamente'),
    'A regeneração deve receber a regra segura e o identificador da evidência omitida.'
);

$invalidCorrectiveFeedbackRejected = false;
$invalidCorrectiveHttp = new CapturingJsonHttpClient([]);

try {
    (new QueryAnswerProvider(
        $invalidCorrectiveHttp,
        'test-key',
        'language-model-test',
        'https://language-provider.test/v1/chat/completions'
    ))->answer('Explique a unidade.', $queryContext, [
        'code' => 'missing_analytical_evidence',
        'evidence_id' => 'EVA-E999999',
    ]);
} catch (AiProviderException $exception) {
    $invalidCorrectiveFeedbackRejected = str_contains($exception->getMessage(), 'feedback de evidência ausente');
}

assertAiAdapter(
    $invalidCorrectiveFeedbackRejected && $invalidCorrectiveHttp->requests === [],
    'O feedback corretivo não deve aceitar nem enviar uma evidência fora do contexto eleito.'
);

$profiledContext = new QueryContext(
    $queryContext->understanding,
    $queryContext->evidences,
    $queryContext->interactionLimit,
    $queryContext->routingPoints,
    $queryContext->limitations,
    [[
        'project_id' => 7,
        'project_name' => 'Projeto Educacional',
        'response_profile' => 'Auxilie professores na elaboração de atividades.',
        'documents' => ['Documento real'],
    ]]
);
$profiledQueryHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'Resposta governada [EVA-E000001] [EVA-E000002].',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
            'interactions' => [],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
(new QueryAnswerProvider(
    $profiledQueryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Explique a unidade para um professor.', $profiledContext, []);
$baseQuerySystemPrompt = $queryHttp->requests[0]['payload']['messages'][0]['content'];
$profiledSystemPrompt = $profiledQueryHttp->requests[0]['payload']['messages'][0]['content'];

assertAiAdapter(
    !str_contains($baseQuerySystemPrompt, 'active_project_response_profiles'),
    'Uma consulta sem projeto governado não deve receber perfis de respostas.'
);
assertAiAdapter(
    str_starts_with($profiledSystemPrompt, $baseQuerySystemPrompt)
    && str_contains($profiledSystemPrompt, 'active_project_response_profiles')
    && str_contains($profiledSystemPrompt, 'Projeto Educacional')
    && str_contains($profiledSystemPrompt, 'Auxilie professores na elaboração de atividades.')
    && str_contains($profiledSystemPrompt, 'Documento real'),
    'O perfil ativo deve complementar integralmente o prompt base com projeto, comportamento e obras.'
);
assertAiAdapter(
    str_contains($profiledSystemPrompt, 'nunca substituem nem flexibilizam as regras-base'),
    'A governança por projeto deve permanecer subordinada às regras documentais do EVA.'
);

$supplementaryContext = new QueryContext(
    $queryContext->understanding,
    $queryContext->evidences,
    $queryContext->interactionLimit,
    $queryContext->routingPoints,
    $queryContext->limitations,
    [],
    $queryContext->contextIntelligenceAnalyses,
    $queryContext->evidenceSelection,
    ['Apresente a resposta em uma estrutura operacional definida pelo módulo.']
);
$supplementaryHttp = new CapturingJsonHttpClient([[
    'choices' => [[
        'message' => ['content' => json_encode([
            'answer' => 'Resposta modular [EVA-E000001] [EVA-E000002].',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
            'interactions' => [],
            'limitations' => [],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
    ]],
]]);
(new QueryAnswerProvider(
    $supplementaryHttp,
    'test-key',
    'language-model-test',
    'https://language-provider.test/v1/chat/completions'
))->answer('Execute a tarefa autorizada.', $supplementaryContext, []);
$supplementarySystemPrompt = $supplementaryHttp->requests[0]['payload']['messages'][0]['content'];

assertAiAdapter(
    str_contains($supplementarySystemPrompt, 'active_module_instructions')
    && str_contains($supplementarySystemPrompt, 'estrutura operacional definida pelo módulo'),
    'A instrução modular autorizada não foi transportada integralmente para a consulta.'
);
assertAiAdapter(
    str_contains($supplementarySystemPrompt, 'nunca substituem nem flexibilizam as regras-base')
    && str_starts_with($supplementarySystemPrompt, $baseQuerySystemPrompt),
    'A governança modular deve permanecer subordinada ao prompt documental do Core.'
);

$maximumSupplementaryContext = new QueryContext(
    $queryContext->understanding,
    $queryContext->evidences,
    $queryContext->interactionLimit,
    $queryContext->routingPoints,
    $queryContext->limitations,
    [],
    $queryContext->contextIntelligenceAnalyses,
    $queryContext->evidenceSelection,
    [str_repeat('a', QueryContext::MAX_SUPPLEMENTARY_INSTRUCTION_LENGTH)]
);
$oversizedSupplementaryRejected = false;

try {
    new QueryContext(
        $queryContext->understanding,
        $queryContext->evidences,
        $queryContext->interactionLimit,
        $queryContext->routingPoints,
        $queryContext->limitations,
        [],
        $queryContext->contextIntelligenceAnalyses,
        $queryContext->evidenceSelection,
        [str_repeat('a', QueryContext::MAX_SUPPLEMENTARY_INSTRUCTION_LENGTH + 1)]
    );
} catch (QueryException) {
    $oversizedSupplementaryRejected = true;
}

assertAiAdapter(
    mb_strlen($maximumSupplementaryContext->supplementaryInstructions[0], 'UTF-8') === 10_000
        && $oversizedSupplementaryRejected,
    'O contexto de consulta não respeitou o limite compartilhado de 10 mil caracteres.'
);

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
))->answer('Como as unidades se relacionam?', $queryContext, []);

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
    ))->answer('Como as unidades se relacionam?', $queryContext, []);
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
))->answer('Como as unidades se relacionam em simetry ou assimetry?', $queryContext, []);

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
        static fn (string $limitation): bool => str_contains($limitation, 'simetry ou assimetry')
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
            'answer' => 'Resposta sustentada [EVA-E000001] [EVA-E000002].',
            'used_evidence_ids' => ['EVA-E000001', 'EVA-E000002'],
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
    ))->answer('Como as unidades interagem?', $queryContext, []);
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
