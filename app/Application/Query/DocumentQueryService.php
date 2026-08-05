<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class DocumentQueryService
{
    private const MAX_ANSWER_VALIDATION_ATTEMPTS = 3;

    private const PUBLIC_VALIDATION_FAILURE_MESSAGE =
        'Não foi possível concluir a resposta documental após três tentativas. Tente novamente em alguns instantes.';

    public function __construct(
        private DocumentContextRetriever $retriever,
        private QueryAnswerProviderInterface $answerProvider
    ) {
    }

    public function query(
        int $documentId,
        string $input,
        int $maxEvidence = 8,
        int $maxInteractions = 20,
        array $responseProfiles = [],
        array $supplementaryInstructions = []
    ): DocumentQueryResult
    {
        $context = $this->retriever->retrieve(
            $documentId,
            $input,
            $maxEvidence,
            $maxInteractions
        );

        if ($responseProfiles !== [] || $supplementaryInstructions !== []) {
            $context = new QueryContext(
                $context->understanding,
                $context->evidences,
                $context->interactionLimit,
                $context->routingPoints,
                $context->limitations,
                $responseProfiles,
                $context->contextIntelligenceAnalyses,
                $context->evidenceSelection,
                $supplementaryInstructions
            );
        }

        return $this->answerFromContext($input, $context);
    }

    /**
     * @param list<int> $documentIds
     * @param list<array{project_id: int, project_name: string, response_profile: string, documents: list<string>}> $responseProfiles
     * @param list<string> $supplementaryInstructions
     */
    public function queryDocuments(
        array $documentIds,
        string $input,
        int $maxEvidence = 8,
        int $maxInteractions = 20,
        array $responseProfiles = [],
        array $supplementaryInstructions = []
    ): DocumentQueryResult {
        $documentIds = array_values(array_unique(array_filter(
            array_map('intval', $documentIds),
            static fn (int $documentId): bool => $documentId > 0
        )));

        if ($documentIds === [] || count($documentIds) > 50) {
            throw new QueryException('O conjunto de documentos da consulta é inválido.');
        }

        if (count($documentIds) === 1) {
            return $this->query(
                $documentIds[0],
                $input,
                $maxEvidence,
                $maxInteractions,
                $responseProfiles,
                $supplementaryInstructions
            );
        }

        $contexts = array_map(
            fn (int $documentId): QueryContext => $this->retriever->retrieve(
                $documentId,
                $input,
                $maxEvidence,
                $maxInteractions
            ),
            $documentIds
        );

        $hasEvidence = array_filter(
            $contexts,
            static fn (QueryContext $context): bool => $context->evidences !== []
        ) !== [];
        $routingPoints = [];
        $limitations = [];
        $contextIntelligenceAnalyses = [];

        foreach ($contexts as $context) {
            $routingPoints = [...$routingPoints, ...$context->routingPoints];

            if (!$hasEvidence || $context->evidences !== []) {
                $limitations = [...$limitations, ...$context->limitations];
            }

            $contextIntelligenceAnalyses = [
                ...$contextIntelligenceAnalyses,
                ...$context->contextIntelligenceAnalyses,
            ];
        }

        $evidenceByPublicId = [];
        $evidenceSelection = [];
        $position = 0;

        while (count($evidenceByPublicId) < $maxEvidence) {
            $added = false;

            foreach ($contexts as $context) {
                if (!isset($context->evidences[$position])) {
                    continue;
                }

                $evidence = $context->evidences[$position];
                $evidenceByPublicId[$evidence->publicId] = $evidence;
                $region = $context->evidenceSelection[$evidence->publicId] ?? 'core';

                if (($evidenceSelection[$evidence->publicId] ?? null) !== 'core') {
                    $evidenceSelection[$evidence->publicId] = $region;
                }

                $added = true;

                if (count($evidenceByPublicId) >= $maxEvidence) {
                    break;
                }
            }

            if (!$added) {
                break;
            }

            $position++;
        }

        $context = new QueryContext(
            $contexts[0]->understanding,
            array_values($evidenceByPublicId),
            $maxInteractions,
            array_values(array_unique($routingPoints)),
            array_values(array_unique($limitations)),
            $responseProfiles,
            $contextIntelligenceAnalyses,
            $evidenceSelection,
            $supplementaryInstructions
        );

        return $this->answerFromContext($input, $context);
    }

    private function answerFromContext(string $input, QueryContext $context): DocumentQueryResult
    {
        if ($context->evidences === []) {
            return new DocumentQueryResult(
                $context->understanding,
                'Não há evidência documental suficiente para responder a este input.',
                [],
                [],
                [],
                $context->routingPoints,
                $context->limitations,
                $context->contextIntelligenceAnalyses,
                $context->evidenceSelection
            );
        }

        $available = [];

        foreach ($context->evidences as $evidence) {
            $available[$evidence->publicId] = $evidence;
        }

        $lastValidationException = null;
        $validationFeedback = [];

        for ($attempt = 1; $attempt <= self::MAX_ANSWER_VALIDATION_ATTEMPTS; $attempt++) {
            try {
                return $this->generateValidatedResult(
                    $input,
                    $context,
                    $available,
                    $validationFeedback
                );
            } catch (QueryException $exception) {
                $lastValidationException = $exception;
                $validationFeedback = $this->validationFeedback($exception);
            }
        }

        throw new QueryException(
            self::PUBLIC_VALIDATION_FAILURE_MESSAGE,
            0,
            $lastValidationException,
            is_string($validationFeedback['code'] ?? null) ? $validationFeedback['code'] : null
        );
    }

    /**
     * @param array<string, RetrievedEvidence> $available
     * @param array{code: string, evidence_id?: string} $validationFeedback
     */
    private function generateValidatedResult(
        string $input,
        QueryContext $context,
        array $available,
        array $validationFeedback
    ): DocumentQueryResult {
        $generated = $this->answerProvider->answer($input, $context, $validationFeedback);

        $electedIds = array_keys($available);

        preg_match_all('/\[(EVA-E\d{6,})\]/', $generated->answer, $citationMatches);
        $citedIds = array_values(array_unique($citationMatches[1] ?? []));

        foreach ($citedIds as $citation) {
            if (!isset($available[$citation])) {
                throw new QueryException('A resposta contém uma citação documental não recuperada.');
            }
        }

        $usedIds = array_values(array_filter(
            $electedIds,
            static fn (string $evidenceId): bool => in_array($evidenceId, $citedIds, true)
        ));

        if ($usedIds === []) {
            throw new QueryException('A resposta não citou nenhuma evidência documental recuperada.');
        }

        $this->assertAnalyticalEvidenceCoverage($generated->answer, $usedIds);
        $answer = $generated->answer;

        if (count($generated->interactions) > $context->interactionLimit) {
            throw new QueryException('A resposta excedeu o limite de interações transitórias.');
        }

        $simetry = [];
        $assimetry = [];

        foreach ($generated->interactions as $interaction) {
            $usesOnlyCitedEvidence = true;

            foreach ($interaction->evidences as $association) {
                $evidenceId = $association['evidence_id'];
                $excerpt = $association['excerpt'];

                if (!isset($available[$evidenceId])) {
                    throw new QueryException('Uma interação menciona evidência fora do contexto recuperado.');
                }

                if (!is_string($excerpt) || trim($excerpt) === ''
                    || !str_contains($available[$evidenceId]->content, $excerpt)) {
                    throw new QueryException('Uma interação não contém fragmento literal da evidência indicada.');
                }

                if (!in_array($evidenceId, $usedIds, true)) {
                    $usesOnlyCitedEvidence = false;
                }
            }

            if (!$usesOnlyCitedEvidence) {
                continue;
            }

            if ($interaction->interactionType === 'simetry') {
                $simetry[] = $interaction;
            } else {
                $assimetry[] = $interaction;
            }
        }

        $usedEvidenceSelection = array_intersect_key(
            $context->evidenceSelection,
            array_fill_keys($usedIds, true)
        );

        return new DocumentQueryResult(
            $context->understanding,
            $answer,
            array_map(static fn (string $id): RetrievedEvidence => $available[$id], $usedIds),
            $simetry,
            $assimetry,
            $context->routingPoints,
            array_values(array_unique([...$context->limitations, ...$generated->limitations])),
            $context->contextIntelligenceAnalyses,
            $usedEvidenceSelection
        );
    }

    /** @param list<string> $usedIds */
    private function assertAnalyticalEvidenceCoverage(string $answer, array $usedIds): void
    {
        $segments = preg_split('/(?<=[.!?])\s+|\R+/u', $answer);

        if (!is_array($segments)) {
            throw new QueryException('A resposta não pôde ser validada quanto ao uso analítico das evidências.');
        }

        foreach ($usedIds as $evidenceId) {
            $marker = '[' . $evidenceId . ']';
            $analyticalUseFound = false;

            foreach ($segments as $segment) {
                if (!str_contains($segment, $marker)) {
                    continue;
                }

                $withoutCitations = preg_replace('/\[EVA-E\d{6,}\]/u', '', $segment);
                $withoutLabel = is_string($withoutCitations)
                    ? preg_replace('/^\s*(?:evidências?|fontes?|citações?)\s*:?\s*/iu', '', $withoutCitations)
                    : null;

                if (!is_string($withoutLabel)) {
                    continue;
                }

                preg_match_all('/\p{L}+/u', $withoutLabel, $words);

                if (count($words[0] ?? []) >= 4) {
                    $analyticalUseFound = true;
                    break;
                }
            }

            if (!$analyticalUseFound) {
                throw new QueryException(sprintf(
                    'A resposta não incorporou analiticamente a evidência eleita %s.',
                    $evidenceId
                ));
            }
        }

        if (preg_match(
            '/(?:^|\R)\s*(?:evidências?|fontes?|citações?)\s*:\s*(?:\[EVA-E\d{6,}\]\s*)+\.?\s*(?:\R|$)/iu',
            $answer
        ) === 1) {
            throw new QueryException(
                'A resposta apenas inventariou evidências sem incorporá-las à análise.'
            );
        }
    }

    /** @return array{code: string, evidence_id?: string} */
    private function validationFeedback(QueryException $exception): array
    {
        $message = $exception->getMessage();
        $feedback = ['code' => match (true) {
            str_contains($message, 'fora do contexto recuperado') => 'evidence_outside_context',
            str_contains($message, 'citação documental não recuperada') => 'citation_outside_context',
            str_contains($message, 'não citou nenhuma evidência documental') => 'missing_documentary_citation',
            str_contains($message, 'não incorporou analiticamente') => 'missing_analytical_evidence',
            str_contains($message, 'apenas inventariou evidências') => 'citation_inventory_without_analysis',
            str_contains($message, 'limite de interações') => 'interaction_limit_exceeded',
            str_contains($message, 'fragmento literal') => 'interaction_excerpt_invalid',
            str_contains($message, 'evidências citadas na resposta') => 'interaction_uncited_evidence',
            default => 'answer_contract_invalid',
        }];

        if ($feedback['code'] === 'missing_analytical_evidence'
            && preg_match('/\bEVA-E\d{6,}\b/', $message, $matches) === 1) {
            $feedback['evidence_id'] = $matches[0];
        }

        return $feedback;
    }
}
