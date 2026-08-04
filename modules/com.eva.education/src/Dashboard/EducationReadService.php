<?php

declare(strict_types=1);

namespace EvaModule\Education\Dashboard;

use Eva\ModuleRuntime\ModuleException;
use PDO;

final class EducationReadService
{
    /** @param array{user_id: int, role: string} $actor @param array<string, mixed> $filters @return array<string, mixed> */
    public function read(PDO $database, array $actor, array $filters): array
    {
        $userId = $actor['role'] === 'superadmin' && isset($filters['user_id'])
            ? (int) $filters['user_id']
            : $actor['user_id'];

        if ($actor['role'] === 'superadmin' && $userId < 1) {
            return ['user_id' => null, 'timeline' => []];
        }

        if ($userId < 1) {
            throw new ModuleException('O usuário do trajeto educacional é inválido.');
        }

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 30)));
        $sql = 'SELECT * FROM interactions WHERE user_id = :user_id';
        $parameters = ['user_id' => $userId];

        if (is_string($filters['date_from'] ?? null) && $filters['date_from'] !== '') {
            $sql .= ' AND occurred_at >= :date_from';
            $parameters['date_from'] = $filters['date_from'];
        }

        if (is_string($filters['date_to'] ?? null) && $filters['date_to'] !== '') {
            $sql .= ' AND occurred_at <= :date_to';
            $parameters['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY occurred_at DESC, id DESC LIMIT :limit';
        $statement = $database->prepare($sql);

        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value, $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $timeline = [];

        foreach ($statement->fetchAll() as $interaction) {
            $projects = $this->decode($interaction['projects_json']);
            $documents = $this->decode($interaction['documents_json']);

            if (!$this->matchesContextFilter($projects, $filters['project_id'] ?? null)
                || !$this->matchesContextFilter($documents, $filters['document_id'] ?? null)) {
                continue;
            }

            $interpretation = $database->prepare(
                'SELECT governance_version, interpreter_version, observations_json, limitations_json, created_at
                   FROM interpretations WHERE interaction_id = :interaction_id ORDER BY id DESC LIMIT 1'
            );
            $interpretation->execute(['interaction_id' => $interaction['id']]);
            $latest = $interpretation->fetch();
            $cognitive = $this->decode($interaction['cognitive_json']);
            $observationsPayload = is_array($latest) ? $this->decode($latest['observations_json']) : [];
            $localizedEnvelope = is_array($observationsPayload['items'] ?? null);
            $timeline[] = [
                'interaction_id' => (int) $interaction['id'],
                'event_id' => $interaction['event_id'],
                'occurred_at' => $interaction['occurred_at'],
                'current_input' => $interaction['current_input'],
                'answer' => $interaction['answer'],
                'projects' => $projects,
                'documents' => $documents,
                'evidences' => $this->decode($interaction['evidences_json']),
                'limitations' => $this->decode($interaction['limitations_json']),
                'direct_references' => $cognitive['interaction']['direct_references'] ?? [],
                'processing_status' => $interaction['processing_status'],
                'interpretation' => is_array($latest) ? [
                    'governance_version' => $latest['governance_version'],
                    'interpreter_version' => $latest['interpreter_version'],
                    'language' => $localizedEnvelope && is_string($observationsPayload['language'] ?? null)
                        ? $observationsPayload['language']
                        : null,
                    'labels' => $localizedEnvelope && is_array($observationsPayload['labels'] ?? null)
                        ? $observationsPayload['labels']
                        : [],
                    'observations' => $localizedEnvelope ? $observationsPayload['items'] : $observationsPayload,
                    'linguistic_analysis' => $localizedEnvelope
                        && is_array($observationsPayload['linguistic_analysis'] ?? null)
                        ? $observationsPayload['linguistic_analysis']
                        : ['units' => [], 'relations' => [], 'concepts' => []],
                    'limitations' => $this->decode($latest['limitations_json']),
                    'created_at' => $latest['created_at'],
                ] : null,
            ];
        }

        return ['user_id' => $userId, 'timeline' => $timeline];
    }

    /** @param list<array<string, mixed>> $items */
    private function matchesContextFilter(array $items, mixed $filter): bool
    {
        if ($filter === null || $filter === '') {
            return true;
        }

        foreach ($items as $item) {
            if (is_array($item) && (string) ($item['id'] ?? '') === (string) $filter) {
                return true;
            }
        }

        return false;
    }

    /** @return array<mixed> */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
