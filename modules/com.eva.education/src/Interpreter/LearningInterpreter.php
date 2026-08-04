<?php

declare(strict_types=1);

namespace EvaModule\Education\Interpreter;

use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleException;
use EvaModule\Education\Governance\GovernancePolicy;
use JsonException;
use Throwable;

final class LearningInterpreter
{
    public const VERSION = '1.2.4';
    private const STATES = ['observed', 'not_observed', 'insufficient_evidence', 'conflicting_evidence'];
    private const LINGUISTIC_ROLES = [
        'subject',
        'predicate',
        'object',
        'complement',
        'predicative',
        'adjective',
        'adverbial_modifier',
    ];
    private const LINGUISTIC_SOURCES = ['question', 'answer'];
    private const LABEL_KEYS = [
        'pending',
        'completed',
        'failed',
        'evidences',
        'scope',
        'document',
        'projects',
        'documents',
        'direct_references',
        'concepts',
        'none',
        'no_direct_references',
        'interpretation_pending',
    ];

    public function processPending(ModuleContext $context, int $limit = 10): array
    {
        $statement = $context->storage->prepare(
            "SELECT * FROM interactions WHERE processing_status = 'pending' ORDER BY id LIMIT :limit"
        );
        $statement->bindValue('limit', max(1, min(100, $limit)), \PDO::PARAM_INT);
        $statement->execute();

        return $this->processInteractions($context, $statement->fetchAll());
    }

    /** @return array{processed: int, failed: int} */
    public function processEvent(ModuleContext $context, string $eventId): array
    {
        $statement = $context->storage->prepare(
            "SELECT * FROM interactions WHERE event_id = :event_id AND processing_status = 'pending' LIMIT 1"
        );
        $statement->execute(['event_id' => $eventId]);
        $interaction = $statement->fetch();

        return $this->processInteractions($context, is_array($interaction) ? [$interaction] : []);
    }

    /** @param list<array<string, mixed>> $interactions @return array{processed: int, failed: int} */
    private function processInteractions(ModuleContext $context, array $interactions): array
    {
        $processed = 0;
        $failed = 0;

        foreach ($interactions as $interaction) {
            try {
                $this->interpret($context, $interaction);
                $processed++;
            } catch (Throwable $exception) {
                $failed++;
                $failure = $context->storage->prepare(
                    "INSERT INTO processing_failures (interaction_id, error_type, attempted_at)
                     VALUES (:interaction_id, :error_type, datetime('now'))"
                );
                $failure->execute(['interaction_id' => $interaction['id'], 'error_type' => $exception::class]);
                $update = $context->storage->prepare(
                    "UPDATE interactions SET processing_status = 'failed', updated_at = datetime('now') WHERE id = :id"
                );
                $update->execute(['id' => $interaction['id']]);
            }
        }

        return ['processed' => $processed, 'failed' => $failed];
    }

    public function queueForReprocessing(ModuleContext $context, int $interactionId): void
    {
        $statement = $context->storage->prepare(
            "UPDATE interactions SET processing_status = 'pending', updated_at = datetime('now') WHERE id = :id"
        );
        $statement->execute(['id' => $interactionId]);
    }

    /** @param array<string, mixed> $interaction */
    private function interpret(ModuleContext $context, array $interaction): void
    {
        $governance = (new GovernancePolicy())->validate([]);
        $input = [
            'current_input' => $interaction['current_input'],
            'contextual_input' => $interaction['contextual_input'],
            'answer' => $interaction['answer'],
            'evidences' => $this->decode($interaction['evidences_json']),
            'limitations' => $this->decode($interaction['limitations_json']),
            'governance' => $governance,
        ];
        $output = $context->language->generateJson($this->instruction(), $input);
        $validated = $this->validateOutput(
            $output,
            $governance,
            $input['evidences'],
            $input['current_input'],
            $input['answer']
        );

        $ownsTransaction = !$context->storage->inTransaction();

        if ($ownsTransaction) {
            $context->storage->beginTransaction();
        }

        try {
            $insert = $context->storage->prepare(
                "INSERT INTO interpretations (
                    interaction_id, governance_version, interpreter_version,
                    observations_json, limitations_json, created_at
                 ) VALUES (
                    :interaction_id, :governance_version, :interpreter_version,
                    :observations_json, :limitations_json, datetime('now')
                 )"
            );
            $insert->execute([
                'interaction_id' => $interaction['id'],
                'governance_version' => $governance['protocol_version'],
                'interpreter_version' => self::VERSION,
                'observations_json' => $this->encode([
                    'language' => $validated['language'],
                    'labels' => $validated['labels'],
                    'items' => $validated['observations'],
                    'linguistic_analysis' => $validated['linguistic_analysis'],
                ]),
                'limitations_json' => $this->encode($validated['limitations']),
            ]);
            $update = $context->storage->prepare(
                "UPDATE interactions SET processing_status = 'completed', updated_at = datetime('now') WHERE id = :id"
            );
            $update->execute(['id' => $interaction['id']]);
            if ($ownsTransaction) {
                $context->storage->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $context->storage->inTransaction()) {
                $context->storage->rollBack();
            }

            throw $exception;
        }
    }

    private function instruction(): string
    {
        return 'Identify the language used in current_input and write every human-facing value exclusively in that same language. '
            . 'Return JSON with exactly language, labels, observations, linguistic_analysis and limitations. Language must be an IETF language tag such as pt-BR or en. '
            . 'Labels must contain exactly pending, completed, failed, evidences, scope, document, projects, documents, direct_references, concepts, none, no_direct_references and interpretation_pending, translated to that language. '
            . 'Every label must be natural human-readable text with spaces; never return snake_case, kebab-case or technical identifiers as label values. '
            . 'Observe pedagogically without assigning scores, weights, grades, percentages, confidence, rankings, mastery or levels. '
            . 'Each observation must contain exactly dimension, dimension_label, state, state_label, description and evidence_refs. '
            . 'Keep dimension and state as canonical identifiers; translate dimension_label, state_label, description and limitations to the current_input language. '
            . 'State must be observed, not_observed, insufficient_evidence or conflicting_evidence. Use only supplied dimensions and evidence IDs. '
            . 'Linguistic_analysis must contain exactly units, relations and concepts. Extract at most 16 concise units from exact spans present in current_input or answer. '
            . 'Each unit must contain exactly id, surface, canonical, role, source and evidence_refs. IDs must be u1, u2 and so on. '
            . 'Role must be subject, predicate, object, complement, predicative, adjective or adverbial_modifier; source must be question or answer. '
            . 'Each relation must contain exactly subject_unit_ids, predicate_unit_ids, complement_unit_ids, qualifier_unit_ids and evidence_refs. '
            . 'Each concept must contain exactly term, canonical, unit_ids and evidence_refs. A concept must be semantically derived from its referenced linguistic units, never from an isolated keyword list. '
            . 'Treat current_input and answer as the two inseparable parts of one interaction. Extract units from both sources and ensure the concepts collectively reference at least one question unit and at least one answer unit. '
            . 'Do not invent implicit words or units absent from their declared source. Keep at most 8 relations and 10 concepts.';
    }

    /** @param array<string, mixed> $output @param array<string, mixed> $governance @param list<array<string, mixed>> $evidences @return array<string, mixed> */
    private function validateOutput(
        array $output,
        array $governance,
        array $evidences,
        string $currentInput,
        string $answer
    ): array
    {
        $this->rejectValuativeKeys($output);

        $rootKeys = ['language', 'labels', 'observations', 'linguistic_analysis', 'limitations'];

        if (array_diff(array_keys($output), $rootKeys) !== []
            || array_diff($rootKeys, array_keys($output)) !== []
            || !is_string($output['language'] ?? null)
            || preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $output['language']) !== 1
            || !is_array($output['labels'] ?? null)
            || !is_array($output['observations'] ?? null)
            || !is_array($output['linguistic_analysis'] ?? null)
            || !is_array($output['limitations'] ?? null)) {
            throw new ModuleException('A interpretação pedagógica retornou estrutura inválida.');
        }

        if (array_diff(array_keys($output['labels']), self::LABEL_KEYS) !== []
            || array_diff(self::LABEL_KEYS, array_keys($output['labels'])) !== []) {
            throw new ModuleException('Os rótulos localizados da interpretação são inválidos.');
        }

        $localizedLabels = [];

        foreach ($output['labels'] as $key => $label) {
            if (!is_string($label) || trim($label) === '' || mb_strlen($label, 'UTF-8') > 100) {
                throw new ModuleException('Um rótulo localizado da interpretação é inválido.');
            }

            $localizedLabels[$key] = $this->normalizeDisplayLabel($label);
        }

        $allowedDimensions = $governance['dimensions'] ?? [];
        $allowedEvidence = [];

        foreach ($evidences as $evidence) {
            if (is_array($evidence) && is_string($evidence['id'] ?? null)) {
                $allowedEvidence[$evidence['id']] = true;
            }
        }

        foreach ($output['observations'] as $observation) {
            if (!is_array($observation)
                || array_diff(array_keys($observation), ['dimension', 'dimension_label', 'state', 'state_label', 'description', 'evidence_refs']) !== []
                || array_diff(['dimension', 'dimension_label', 'state', 'state_label', 'description', 'evidence_refs'], array_keys($observation)) !== []
                || !in_array($observation['dimension'] ?? null, $allowedDimensions, true)
                || !in_array($observation['state'] ?? null, self::STATES, true)
                || !is_string($observation['dimension_label'] ?? null)
                || trim($observation['dimension_label']) === ''
                || mb_strlen($observation['dimension_label'], 'UTF-8') > 200
                || !is_string($observation['state_label'] ?? null)
                || trim($observation['state_label']) === ''
                || mb_strlen($observation['state_label'], 'UTF-8') > 100
                || !is_string($observation['description'] ?? null)
                || trim($observation['description']) === ''
                || !is_array($observation['evidence_refs'] ?? null)) {
                throw new ModuleException('Uma observação pedagógica retornada é inválida.');
            }

            foreach ($observation['evidence_refs'] as $reference) {
                if (!is_string($reference) || !isset($allowedEvidence[$reference])) {
                    throw new ModuleException('Uma observação referencia evidência inexistente.');
                }
            }
        }

        foreach ($output['limitations'] as $limitation) {
            if (!is_string($limitation) || mb_strlen($limitation, 'UTF-8') > 2000) {
                throw new ModuleException('Uma limitação pedagógica retornada é inválida.');
            }
        }

        $linguisticAnalysis = $this->validateLinguisticAnalysis(
            $output['linguistic_analysis'],
            $allowedEvidence,
            $currentInput,
            $answer
        );

        return [
            'language' => $output['language'],
            'labels' => $localizedLabels,
            'observations' => array_values($output['observations']),
            'linguistic_analysis' => $linguisticAnalysis,
            'limitations' => array_values($output['limitations']),
        ];
    }

    /** @param array<string, mixed> $analysis @param array<string, true> $allowedEvidence @return array<string, mixed> */
    private function validateLinguisticAnalysis(
        array $analysis,
        array $allowedEvidence,
        string $currentInput,
        string $answer
    ): array {
        $analysisKeys = ['units', 'relations', 'concepts'];

        if (array_diff(array_keys($analysis), $analysisKeys) !== []
            || array_diff($analysisKeys, array_keys($analysis)) !== []
            || !is_array($analysis['units'] ?? null)
            || !is_array($analysis['relations'] ?? null)
            || !is_array($analysis['concepts'] ?? null)
            || count($analysis['units']) > 16
            || count($analysis['relations']) > 8
            || count($analysis['concepts']) > 10) {
            throw new ModuleException('A análise linguística retornou estrutura inválida.');
        }

        $units = [];
        $rolesById = [];
        $sourcesById = [];

        foreach ($analysis['units'] as $unit) {
            $keys = ['id', 'surface', 'canonical', 'role', 'source', 'evidence_refs'];

            if (!is_array($unit)
                || array_diff(array_keys($unit), $keys) !== []
                || array_diff($keys, array_keys($unit)) !== []
                || !is_string($unit['id'] ?? null)
                || preg_match('/^u[1-9][0-9]*$/', $unit['id']) !== 1
                || isset($rolesById[$unit['id']])
                || !$this->isLinguisticText($unit['surface'] ?? null, 500)
                || !$this->isLinguisticText($unit['canonical'] ?? null, 500)
                || !in_array($unit['role'] ?? null, self::LINGUISTIC_ROLES, true)
                || !in_array($unit['source'] ?? null, self::LINGUISTIC_SOURCES, true)
                || !is_array($unit['evidence_refs'] ?? null)) {
                throw new ModuleException('Uma unidade linguística retornada é inválida.');
            }

            $sourceText = $unit['source'] === 'question' ? $currentInput : $answer;

            if (mb_stripos($sourceText, $unit['surface'], 0, 'UTF-8') === false) {
                continue;
            }

            $this->validateEvidenceReferences($unit['evidence_refs'], $allowedEvidence);
            $rolesById[$unit['id']] = $unit['role'];
            $sourcesById[$unit['id']] = $unit['source'];
            $units[] = $unit;
        }

        if (!in_array('question', $sourcesById, true) || !in_array('answer', $sourcesById, true)) {
            throw new ModuleException('A análise linguística deve incluir pergunta e resposta.');
        }

        $relations = [];

        foreach ($analysis['relations'] as $relation) {
            $keys = ['subject_unit_ids', 'predicate_unit_ids', 'complement_unit_ids', 'qualifier_unit_ids', 'evidence_refs'];

            if (!is_array($relation)
                || array_diff(array_keys($relation), $keys) !== []
                || array_diff($keys, array_keys($relation)) !== []
                || !is_array($relation['subject_unit_ids'] ?? null)
                || !is_array($relation['predicate_unit_ids'] ?? null)
                || !is_array($relation['complement_unit_ids'] ?? null)
                || !is_array($relation['qualifier_unit_ids'] ?? null)
                || !is_array($relation['evidence_refs'] ?? null)) {
                throw new ModuleException('Uma relação linguística retornada é inválida.');
            }

            $normalizedRelation = [
                'subject_unit_ids' => $this->filterUnitReferences(
                    $relation['subject_unit_ids'],
                    $rolesById,
                    ['subject']
                ),
                'predicate_unit_ids' => $this->filterUnitReferences(
                    $relation['predicate_unit_ids'],
                    $rolesById,
                    ['predicate', 'predicative']
                ),
                'complement_unit_ids' => $this->filterUnitReferences(
                    $relation['complement_unit_ids'],
                    $rolesById,
                    ['object', 'complement', 'predicative']
                ),
                'qualifier_unit_ids' => $this->filterUnitReferences(
                    $relation['qualifier_unit_ids'],
                    $rolesById,
                    ['adjective', 'adverbial_modifier']
                ),
                'evidence_refs' => $relation['evidence_refs'],
            ];

            if ($normalizedRelation['predicate_unit_ids'] === []) {
                continue;
            }

            $this->validateEvidenceReferences($normalizedRelation['evidence_refs'], $allowedEvidence);
            $relations[] = $normalizedRelation;
        }

        $concepts = [];
        $canonicalConcepts = [];
        $conceptSources = [];

        foreach ($analysis['concepts'] as $concept) {
            $keys = ['term', 'canonical', 'unit_ids', 'evidence_refs'];

            if (!is_array($concept)
                || array_diff(array_keys($concept), $keys) !== []
                || array_diff($keys, array_keys($concept)) !== []
                || !$this->isLinguisticText($concept['term'] ?? null, 500)
                || !$this->isLinguisticText($concept['canonical'] ?? null, 500)
                || !is_array($concept['unit_ids'] ?? null)
                || $concept['unit_ids'] === []
                || !is_array($concept['evidence_refs'] ?? null)) {
                throw new ModuleException('Um conceito linguístico retornado é inválido.');
            }

            $normalizedUnitIds = $this->filterUnitReferences(
                $concept['unit_ids'],
                $rolesById,
                self::LINGUISTIC_ROLES
            );

            if ($normalizedUnitIds === [] || count($normalizedUnitIds) !== count($concept['unit_ids'])) {
                continue;
            }

            $this->validateEvidenceReferences($concept['evidence_refs'], $allowedEvidence);
            $canonical = mb_strtolower(trim($concept['canonical']), 'UTF-8');

            if (isset($canonicalConcepts[$canonical])) {
                throw new ModuleException('A análise linguística contém conceitos duplicados.');
            }

            $canonicalConcepts[$canonical] = count($concepts);
            $concept['unit_ids'] = $normalizedUnitIds;

            foreach ($normalizedUnitIds as $unitId) {
                $conceptSources[$sourcesById[$unitId]] = true;
            }

            $concepts[] = $concept;
        }

        foreach (self::LINGUISTIC_SOURCES as $requiredSource) {
            if (isset($conceptSources[$requiredSource])) {
                continue;
            }

            $candidateUnits = array_values(array_filter(
                $units,
                static fn (array $unit): bool => $unit['source'] === $requiredSource
                    && in_array($unit['role'], ['subject', 'predicate', 'object', 'complement', 'predicative'], true)
            ));
            $rolePriority = ['object' => 0, 'complement' => 1, 'subject' => 2, 'predicative' => 3, 'predicate' => 4];
            usort(
                $candidateUnits,
                static fn (array $left, array $right): int => $rolePriority[$left['role']] <=> $rolePriority[$right['role']]
            );
            $addedForSource = 0;

            foreach ($candidateUnits as $unit) {
                if ($unit['source'] !== $requiredSource
                    || $addedForSource >= 3) {
                    continue;
                }

                $canonical = mb_strtolower(trim($unit['canonical']), 'UTF-8');

                if (isset($canonicalConcepts[$canonical])) {
                    $conceptIndex = $canonicalConcepts[$canonical];

                    if (!in_array($unit['id'], $concepts[$conceptIndex]['unit_ids'], true)) {
                        $concepts[$conceptIndex]['unit_ids'][] = $unit['id'];
                    }

                    $concepts[$conceptIndex]['evidence_refs'] = array_values(array_unique([
                        ...$concepts[$conceptIndex]['evidence_refs'],
                        ...$unit['evidence_refs'],
                    ]));
                    $conceptSources[$requiredSource] = true;
                    $addedForSource++;
                    continue;
                }

                if (count($concepts) >= 10) {
                    break;
                }

                $concepts[] = [
                    'term' => $unit['surface'],
                    'canonical' => $unit['canonical'],
                    'unit_ids' => [$unit['id']],
                    'evidence_refs' => $unit['evidence_refs'],
                ];
                $canonicalConcepts[$canonical] = count($concepts) - 1;
                $conceptSources[$requiredSource] = true;
                $addedForSource++;
            }
        }

        if (!isset($conceptSources['question'], $conceptSources['answer'])) {
            throw new ModuleException('Os conceitos devem representar conjuntamente pergunta e resposta.');
        }

        return ['units' => $units, 'relations' => $relations, 'concepts' => $concepts];
    }

    /** @param list<mixed> $references @param array<string, string> $rolesById @param list<string> $allowedRoles @return list<string> */
    private function filterUnitReferences(array $references, array $rolesById, array $allowedRoles): array
    {
        $filtered = [];

        foreach ($references as $reference) {
            if (is_string($reference)
                && isset($rolesById[$reference])
                && in_array($rolesById[$reference], $allowedRoles, true)
                && !isset($filtered[$reference])) {
                $filtered[$reference] = $reference;
            }
        }

        return array_values($filtered);
    }

    /** @param list<mixed> $references @param array<string, true> $allowedEvidence */
    private function validateEvidenceReferences(array $references, array $allowedEvidence): void
    {
        $seen = [];

        foreach ($references as $reference) {
            if (!is_string($reference) || !isset($allowedEvidence[$reference]) || isset($seen[$reference])) {
                throw new ModuleException('A análise linguística referencia evidência inexistente.');
            }

            $seen[$reference] = true;
        }
    }

    private function isLinguisticText(mixed $value, int $maximumLength): bool
    {
        return is_string($value)
            && trim($value) !== ''
            && mb_strlen($value, 'UTF-8') <= $maximumLength;
    }

    private function normalizeDisplayLabel(string $label): string
    {
        $normalized = preg_replace('/_+/u', ' ', trim($label));
        $normalized = is_string($normalized) ? preg_replace('/\s+/u', ' ', $normalized) : null;

        if (!is_string($normalized) || $normalized === '') {
            throw new ModuleException('Um rótulo localizado da interpretação é inválido.');
        }

        return $normalized;
    }

    /** @param array<mixed> $value */
    private function rejectValuativeKeys(array $value): void
    {
        $forbidden = ['score', 'peso', 'weight', 'nota', 'grade', 'rank', 'confidence', 'confiança', 'percent', 'level', 'nível', 'mastery', 'domínio'];

        foreach ($value as $key => $child) {
            foreach ($forbidden as $term) {
                if (str_contains(strtolower((string) $key), $term)) {
                    throw new ModuleException('A interpretação contém campo valorativo proibido.');
                }
            }

            if (is_array($child)) {
                $this->rejectValuativeKeys($child);
            } elseif (is_int($child) || is_float($child)) {
                throw new ModuleException('A interpretação contém valor numérico proibido.');
            } elseif (is_string($child)) {
                $normalized = mb_strtolower($child, 'UTF-8');

                if ($this->containsValuativeLanguage($normalized)) {
                    throw new ModuleException('A interpretação contém linguagem valorativa proibida.');
                }
            }
        }
    }

    private function containsValuativeLanguage(string $value): bool
    {
        if (str_contains($value, '%')) {
            return true;
        }

        $terms = [
            'score', 'scores', 'scoring', 'peso', 'pesos', 'weight', 'weights', 'nota', 'notas',
            'grade', 'grades', 'ranking', 'rankings', 'rank', 'ranks', 'confidence', 'confiança',
            'percent', 'percentage', 'percentual', 'percentuais', 'level', 'levels', 'nível', 'níveis',
            'mastery', 'domínio',
        ];

        foreach ($terms as $term) {
            if (preg_match('/(?<![\p{L}\p{N}_])' . preg_quote($term, '/') . '(?![\p{L}\p{N}_])/iu', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return mixed */
    private function decode(string $json): mixed
    {
        return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }

    /** @param mixed $value */
    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ModuleException('A interpretação não pôde ser serializada.', 0, $exception);
        }
    }
}
