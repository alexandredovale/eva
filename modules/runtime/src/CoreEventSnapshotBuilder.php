<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use PDO;

final readonly class CoreEventSnapshotBuilder
{
    public function __construct(private PDO $database)
    {
    }

    /**
     * @param list<array<string, mixed>> $selectedScopes
     * @param list<int> $documentIds
     * @return array{projects: list<array<string, mixed>>, documents: list<array<string, mixed>>}
     */
    public function build(array $selectedScopes, array $documentIds): array
    {
        $projectIds = [];

        foreach ($selectedScopes as $scope) {
            if (($scope['type'] ?? null) === 'project' && is_int($scope['id'] ?? null) && $scope['id'] > 0) {
                $projectIds[$scope['id']] = $scope['id'];
            }
        }

        return [
            'projects' => $this->projects(array_values($projectIds)),
            'documents' => $this->documents(array_values(array_unique(array_map('intval', $documentIds)))),
        ];
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    private function projects(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $statement = $this->database->prepare(
            'SELECT id, name FROM projects WHERE id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') ORDER BY id'
        );
        $statement->execute($ids);

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']],
            $statement->fetchAll()
        );
    }

    /** @param list<int> $ids @return list<array<string, mixed>> */
    private function documents(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $statement = $this->database->prepare(
            'SELECT id, public_id, title FROM documents WHERE id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ') ORDER BY id'
        );
        $statement->execute($ids);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'public_id' => (string) $row['public_id'],
                'title' => (string) $row['title'],
            ],
            $statement->fetchAll()
        );
    }
}
