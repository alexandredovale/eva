<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use Eva\Application\Access\ScopeAccessService;
use Eva\Application\Query\DocumentContextRetriever;
use Eva\Application\Query\DocumentQueryService;
use Eva\Application\Query\InputType;
use Eva\Application\Query\InputTypeDetector;
use Eva\Http\Security\ActorContext;
use Eva\Infrastructure\Ai\CognitiveProviderFactory;
use PDO;

final readonly class CoreQueryApi
{
    private const MAX_INPUT_BYTES = 20_000;
    private const MAX_INSTRUCTION_LENGTH = 6_000;

    /** @param list<string> $capabilities @param array<string, mixed> $aiConfiguration */
    public function __construct(
        private PDO $database,
        private array $capabilities,
        private array $aiConfiguration,
        private ActorContext $actor
    ) {
    }

    /**
     * @param list<array<string, mixed>> $scopes
     * @return array<string, mixed>
     */
    public function answer(array $scopes, string $input, string $supplementaryInstruction = ''): array
    {
        $this->requireCapability('core.query.scoped');
        $input = trim($input);
        $supplementaryInstruction = trim($supplementaryInstruction);

        if ($input === '' || strlen($input) > self::MAX_INPUT_BYTES) {
            throw new ModuleException('O input da consulta modular Ã© invÃ¡lido.');
        }

        if (mb_strlen($supplementaryInstruction, 'UTF-8') > self::MAX_INSTRUCTION_LENGTH) {
            throw new ModuleException('A instruÃ§Ã£o complementar do mÃ³dulo excede o limite permitido.');
        }

        $scopeAccess = new ScopeAccessService($this->database);
        $documentIds = $scopeAccess->resolveSelections($this->actor, $scopes);
        $responseProfiles = $scopeAccess->responseProfiles($this->actor, $scopes);
        $factory = new CognitiveProviderFactory($this->aiConfiguration);
        $detector = new InputTypeDetector();
        $understanding = $detector->detect($input);
        $needsEmbedding = $understanding->has(InputType::Conceptual)
            || $understanding->has(InputType::Relational);
        $retriever = new DocumentContextRetriever(
            $this->database,
            $needsEmbedding ? $factory->embeddings() : null,
            $detector,
            (int) ($this->aiConfiguration['query']['candidate_limit'] ?? 50)
        );
        $instructions = $supplementaryInstruction === '' ? [] : [$supplementaryInstruction];
        $result = (new DocumentQueryService($retriever, $factory->queryAnswers()))->queryDocuments(
            $documentIds,
            $input,
            (int) ($this->aiConfiguration['query']['max_evidence'] ?? 8),
            (int) ($this->aiConfiguration['query']['max_interactions'] ?? 20),
            $responseProfiles,
            $instructions
        );

        return $result->toArray();
    }

    private function requireCapability(string $capability): void
    {
        if (!in_array($capability, $this->capabilities, true)) {
            throw new ModuleException(sprintf('O mÃ³dulo nÃ£o declarou a capacidade %s.', $capability));
        }
    }
}
