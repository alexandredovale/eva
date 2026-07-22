<?php

declare(strict_types=1);

namespace Eva\Application\Access;

use Eva\Http\Security\AccessException;
use Eva\Http\Security\ActorContext;
use PDO;

final readonly class ScopeAccessService
{
    public function __construct(private PDO $database)
    {
    }

    /** @return array{projects: list<array<string, mixed>>, documents: list<array<string, mixed>>} */
    public function scopes(ActorContext $actor): array
    {
        if ($actor->isSuperadmin()) {
            $documents = $this->database->query(
                "SELECT d.id, d.public_id, d.title
                   FROM documents d
                  WHERE d.status = 'ready'
                    AND NOT EXISTS (
                        SELECT 1
                          FROM project_documents pd
                          JOIN projects p ON p.id = pd.project_id AND p.active = 1
                         WHERE pd.document_id = d.id
                    )
                  ORDER BY d.title"
            )->fetchAll();
            $projects = $this->database->query(
                "SELECT p.id, p.name, 1 AS full_access, d.id AS document_id, d.public_id, d.title
                   FROM projects p
                   JOIN project_documents pd ON pd.project_id = p.id
                   JOIN documents d ON d.id = pd.document_id AND d.status = 'ready'
                  WHERE p.active = 1
                  ORDER BY p.name, d.title"
            )->fetchAll();

            return ['projects' => $this->groupProjects($projects), 'documents' => $this->castDocuments($documents)];
        }

        $projectsStatement = $this->database->prepare(
            "SELECT p.id, p.name, 1 AS full_access, d.id AS document_id, d.public_id, d.title
               FROM user_projects up
               JOIN projects p ON p.id = up.project_id AND p.active = 1
               JOIN project_documents pd ON pd.project_id = p.id
               JOIN documents d ON d.id = pd.document_id AND d.status = 'ready'
              WHERE up.user_id = :project_user_id
              UNION ALL
             SELECT p.id, p.name, 0 AS full_access, d.id AS document_id, d.public_id, d.title
               FROM user_documents ud
               JOIN documents d ON d.id = ud.document_id AND d.status = 'ready'
               JOIN project_documents pd ON pd.document_id = d.id
               JOIN projects p ON p.id = pd.project_id AND p.active = 1
              WHERE ud.user_id = :document_user_id
                AND NOT EXISTS (
                    SELECT 1
                      FROM user_projects covered_up
                      JOIN projects covered_p ON covered_p.id = covered_up.project_id AND covered_p.active = 1
                      JOIN project_documents covered_pd ON covered_pd.project_id = covered_p.id
                     WHERE covered_up.user_id = :covered_user_id
                       AND covered_pd.document_id = d.id
                )
              ORDER BY name, title"
        );
        $projectsStatement->execute([
            'project_user_id' => $actor->userId,
            'document_user_id' => $actor->userId,
            'covered_user_id' => $actor->userId,
        ]);
        $documentsStatement = $this->database->prepare(
            "SELECT d.id, d.public_id, d.title
               FROM user_documents ud
               JOIN documents d ON d.id = ud.document_id AND d.status = 'ready'
              WHERE ud.user_id = :user_id
                AND NOT EXISTS (
                    SELECT 1
                      FROM project_documents pd
                      JOIN projects p ON p.id = pd.project_id AND p.active = 1
                     WHERE pd.document_id = d.id
                )
              ORDER BY d.title"
        );
        $documentsStatement->execute(['user_id' => $actor->userId]);

        return [
            'projects' => $this->groupProjects($projectsStatement->fetchAll()),
            'documents' => $this->castDocuments($documentsStatement->fetchAll()),
        ];
    }

    /** @return list<int> */
    public function resolveDocumentIds(ActorContext $actor, string $scopeType, int $scopeId): array
    {
        if ($scopeId < 1 || !in_array($scopeType, ['document', 'project'], true)) {
            throw new AccessException('O escopo da consulta é inválido.', 422);
        }

        if ($scopeType === 'document') {
            $sql = "SELECT d.id FROM documents d WHERE d.id = :scope_id AND d.status = 'ready'";
            $parameters = ['scope_id' => $scopeId];

            if (!$actor->isSuperadmin()) {
                $sql .= ' AND (EXISTS (SELECT 1 FROM user_documents ud WHERE ud.user_id = :user_id AND ud.document_id = d.id)
                    OR EXISTS (
                        SELECT 1 FROM user_projects up
                        JOIN projects p ON p.id = up.project_id AND p.active = 1
                        JOIN project_documents pd ON pd.project_id = p.id
                        WHERE up.user_id = :project_user_id AND pd.document_id = d.id
                    ))';
                $parameters['user_id'] = $actor->userId;
                $parameters['project_user_id'] = $actor->userId;
            }

            $statement = $this->database->prepare($sql);
            $statement->execute($parameters);
            $documentId = $statement->fetchColumn();

            if ($documentId === false) {
                throw new AccessException('Esta obra não está disponível para o usuário.', 403);
            }

            return [(int) $documentId];
        }

        $sql = "SELECT d.id
                  FROM projects p
                  JOIN project_documents pd ON pd.project_id = p.id
                  JOIN documents d ON d.id = pd.document_id AND d.status = 'ready'
                 WHERE p.id = :scope_id AND p.active = 1";
        $parameters = ['scope_id' => $scopeId];

        if (!$actor->isSuperadmin()) {
            $sql .= ' AND EXISTS (
                SELECT 1 FROM user_projects up WHERE up.user_id = :user_id AND up.project_id = p.id
            )';
            $parameters['user_id'] = $actor->userId;
        }

        $sql .= ' ORDER BY d.id';
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $documentIds = array_map('intval', array_column($statement->fetchAll(), 'id'));

        if ($documentIds === []) {
            throw new AccessException('Este projeto não está disponível para o usuário ou não possui obras prontas.', 403);
        }

        return $documentIds;
    }

    /** @param list<array<string, mixed>> $scopes @return list<int> */
    public function resolveSelections(ActorContext $actor, array $scopes): array
    {
        if ($scopes === [] || count($scopes) > 100) {
            throw new AccessException('Selecione ao menos um projeto ou obra para a consulta.', 422);
        }

        $documentIds = [];

        foreach ($scopes as $scope) {
            $scopeType = is_array($scope) && is_string($scope['type'] ?? null) ? $scope['type'] : '';
            $scopeId = is_array($scope) ? filter_var($scope['id'] ?? null, FILTER_VALIDATE_INT) : false;

            if ($scopeId === false || $scopeId < 1) {
                throw new AccessException('Um dos escopos selecionados é inválido.', 422);
            }

            $documentIds = array_merge(
                $documentIds,
                $this->resolveDocumentIds($actor, $scopeType, (int) $scopeId)
            );
        }

        $documentIds = array_values(array_unique($documentIds));
        sort($documentIds);

        return $documentIds;
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function groupProjects(array $rows): array
    {
        $projects = [];

        foreach ($rows as $row) {
            $projectId = (int) $row['id'];
            $projects[$projectId] ??= [
                'id' => $projectId,
                'name' => (string) $row['name'],
                'full_access' => (bool) ($row['full_access'] ?? false),
                'documents' => [],
            ];
            $projects[$projectId]['full_access'] = $projects[$projectId]['full_access']
                || (bool) ($row['full_access'] ?? false);
            $projects[$projectId]['documents'][] = [
                'id' => (int) $row['document_id'],
                'public_id' => (string) $row['public_id'],
                'title' => (string) $row['title'],
            ];
        }

        return array_values($projects);
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function castDocuments(array $rows): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'public_id' => (string) $row['public_id'],
            'title' => (string) $row['title'],
        ], $rows);
    }
}
