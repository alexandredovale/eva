<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class DocumentQueryService
{
    public function __construct(
        private DocumentContextRetriever $retriever,
        private QueryAnswerProviderInterface $answerProvider
    ) {
    }

    public function query(
        int $documentId,
        string $input,
        int $maxEvidence = 8,
        int $maxInteractions = 20
    ): DocumentQueryResult {
        $context = $this->retriever->retrieve($documentId, $input, $maxEvidence, $maxInteractions);

        return $this->answerFromContext($input, $context);
    }

    /** @param list<int> $documentIds */
    public function queryDocuments(
        array $documentIds,
        string $input,
        int $maxEvidence = 8,
        int $maxInteractions = 20
    ): DocumentQueryResult {
        $documentIds = array_values(array_unique(array_filter(
            array_map('intval', $documentIds),
            static fn (int $documentId): bool => $documentId > 0
        )));

        if ($documentIds === [] || count($documentIds) > 50) {
            throw new QueryException('O conjunto de documentos da consulta é inválido.');
        }

        if (count($documentIds) === 1) {
            return $this->query($documentIds[0], $input, $maxEvidence, $maxInteractions);
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
        $routingPoints = [];
        $limitations = [];

        foreach ($contexts as $context) {
            $routingPoints = [...$routingPoints, ...$context->routingPoints];
            $limitations = [...$limitations, ...$context->limitations];
        }

        $evidenceByPublicId = [];
        $position = 0;

        while (count($evidenceByPublicId) < $maxEvidence) {
            $added = false;

            foreach ($contexts as $context) {
                if (!isset($context->evidences[$position])) {
                    continue;
                }

                $evidence = $context->evidences[$position];
                $evidenceByPublicId[$evidence->publicId] = $evidence;
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
            array_values(array_unique($limitations))
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
                $context->limitations
            );
        }

        $generated = $this->answerProvider->answer($input, $context);
        $available = [];

        foreach ($context->evidences as $evidence) {
            $available[$evidence->publicId] = $evidence;
        }

        $usedIds = array_values(array_unique($generated->usedEvidenceIds));

        foreach ($usedIds as $evidenceId) {
            if (!isset($available[$evidenceId])) {
                throw new QueryException('A resposta citou uma evidência fora do contexto recuperado.');
            }
        }

        preg_match_all('/\[(EVA-E\d{6,})\]/', $generated->answer, $citationMatches);

        foreach (array_unique($citationMatches[1] ?? []) as $citation) {
            if (!isset($available[$citation])) {
                throw new QueryException('A resposta contém uma citação documental não recuperada.');
            }
        }

        if ($usedIds === []) {
            if (($citationMatches[1] ?? []) !== []) {
                throw new QueryException('A resposta citou evidências sem declará-las como utilizadas.');
            }

            if ($generated->interactions !== []) {
                throw new QueryException('Uma resposta sem evidências utilizadas não pode declarar interações.');
            }

            if ($generated->limitations === []) {
                throw new QueryException('A análise dos candidatos não indicou evidência utilizada nem limitação documental.');
            }

            return new DocumentQueryResult(
                $context->understanding,
                $generated->answer,
                [],
                [],
                [],
                $context->routingPoints,
                array_values(array_unique([...$context->limitations, ...$generated->limitations]))
            );
        }

        $answer = $this->renderMissingCitations($generated->answer, $usedIds);

        if (count($generated->interactions) > $context->interactionLimit) {
            throw new QueryException('A resposta excedeu o limite de interações transitórias.');
        }

        if (!$context->understanding->has(InputType::Relational) && $generated->interactions !== []) {
            throw new QueryException('Uma consulta não relacional retornou interações cognitivas.');
        }

        $simetry = [];
        $assimetry = [];

        foreach ($generated->interactions as $interaction) {
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
                    throw new QueryException('Uma interação transitória deve usar evidências citadas na resposta.');
                }
            }

            if ($interaction->interactionType === 'simetry') {
                $simetry[] = $interaction;
            } else {
                $assimetry[] = $interaction;
            }
        }

        return new DocumentQueryResult(
            $context->understanding,
            $answer,
            array_map(static fn (string $id): RetrievedEvidence => $available[$id], $usedIds),
            $simetry,
            $assimetry,
            $context->routingPoints,
            array_values(array_unique([...$context->limitations, ...$generated->limitations]))
        );
    }

    /** @param list<string> $usedIds */
    private function renderMissingCitations(string $answer, array $usedIds): string
    {
        $missing = array_values(array_filter(
            $usedIds,
            static fn (string $evidenceId): bool => !str_contains($answer, '[' . $evidenceId . ']')
        ));

        if ($missing === []) {
            return $answer;
        }

        $citations = implode(' ', array_map(
            static fn (string $evidenceId): string => '[' . $evidenceId . ']',
            $missing
        ));

        return rtrim($answer) . "\n\nEvidências: " . $citations;
    }
}
