<?php

declare(strict_types=1);

namespace EvaModule\Education\Governance;

use Eva\ModuleRuntime\ModuleException;

final class GovernancePolicy
{
    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'protocol_version' => '1.1.0',
            'taxonomy' => 'descriptive_observation',
            'dimensions' => [
                'conceptual_articulation',
                'evidence_use',
                'contextual_connection',
            ],
            'evidence_policy' => 'Every observation must reference evidence present in the interaction.',
        ];
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    public function validate(array $configuration): array
    {
        $configuration = [...$this->defaults(), ...$configuration];
        $allowed = ['protocol_version', 'taxonomy', 'dimensions', 'evidence_policy'];

        if (array_diff(array_keys($configuration), $allowed) !== []) {
            throw new ModuleException('A governança pedagógica contém campos não reconhecidos.');
        }

        $this->rejectValuativeKeys($configuration);

        foreach (['protocol_version', 'taxonomy', 'evidence_policy'] as $field) {
            if (!is_string($configuration[$field]) || trim($configuration[$field]) === ''
                || mb_strlen($configuration[$field], 'UTF-8') > 1000) {
                throw new ModuleException('A governança pedagógica contém texto inválido.');
            }
        }

        if (!is_array($configuration['dimensions'])
            || $configuration['dimensions'] === []
            || count($configuration['dimensions']) > 30) {
            throw new ModuleException('As dimensões observáveis são inválidas.');
        }

        $dimensions = [];

        foreach ($configuration['dimensions'] as $dimension) {
            if (!is_string($dimension)
                || preg_match('/^[a-z][a-z0-9_]{2,79}$/', $dimension) !== 1
                || isset($dimensions[$dimension])) {
                throw new ModuleException('Uma dimensão observável é inválida.');
            }

            $dimensions[$dimension] = $dimension;
        }

        $configuration['dimensions'] = array_values($dimensions);

        return $configuration;
    }

    /** @param array<mixed> $configuration */
    private function rejectValuativeKeys(array $configuration): void
    {
        $forbidden = ['score', 'scoring', 'peso', 'weight', 'nota', 'grade', 'ranking', 'rank', 'confidence', 'confiança', 'percent', 'level', 'nível', 'mastery', 'domínio'];

        foreach ($configuration as $key => $value) {
            $normalized = strtolower((string) $key);

            foreach ($forbidden as $term) {
                if (str_contains($normalized, $term)) {
                    throw new ModuleException('A governança pedagógica não aceita pontuações, pesos ou rankings.');
                }
            }

            if (is_array($value)) {
                $this->rejectValuativeKeys($value);
            } elseif (is_string($value)) {
                $normalizedValue = mb_strtolower($value, 'UTF-8');

                if ($this->containsValuativeLanguage($normalizedValue)) {
                    throw new ModuleException('A governança pedagógica não aceita linguagem valorativa.');
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
}
