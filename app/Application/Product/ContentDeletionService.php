<?php

declare(strict_types=1);

namespace Eva\Application\Product;

use Eva\Http\Security\AccessException;
use Eva\Infrastructure\Storage\DocumentStorage;
use PDO;
use Throwable;

final readonly class ContentDeletionService
{
    public function __construct(private PDO $database, private DocumentStorage $storage)
    {
    }

    /** @return array<string, mixed> */
    public function deleteDocument(int $documentId): array
    {
        if ($documentId < 1) {
            throw new AccessException('Documento inválido.', 422);
        }

        $this->database->beginTransaction();

        try {
            $document = $this->lockDocument($documentId);
            $counts = $this->documentDependencyCounts([$documentId]);
            $delete = $this->database->prepare('DELETE FROM documents WHERE id = :id');
            $delete->execute(['id' => $documentId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        $storageFailures = $this->removeStoredSources([$document]);

        return [
            'entity' => 'document',
            'id' => $documentId,
            'public_id' => $document['public_id'],
            'title' => $document['title'],
            'documents_deleted' => 1,
            ...$counts,
            'storage_cleanup_failures' => $storageFailures,
        ];
    }

    /** @return array<string, mixed> */
    public function deleteProject(int $projectId): array
    {
        if ($projectId < 1) {
            throw new AccessException('Projeto inválido.', 422);
        }

        $this->database->beginTransaction();

        try {
            $projectStatement = $this->database->prepare(
                'SELECT id, name FROM projects WHERE id = :id FOR UPDATE'
            );
            $projectStatement->execute(['id' => $projectId]);
            $project = $projectStatement->fetch();

            if (!is_array($project)) {
                throw new AccessException('Projeto não localizado.', 404);
            }

            $documentStatement = $this->database->prepare(
                'SELECT d.id, d.public_id, d.title, d.storage_path
                   FROM project_documents pd
                   JOIN documents d ON d.id = pd.document_id
                  WHERE pd.project_id = :project_id
                  ORDER BY d.id
                  FOR UPDATE'
            );
            $documentStatement->execute(['project_id' => $projectId]);
            $documents = $documentStatement->fetchAll();
            $documentIds = array_map('intval', array_column($documents, 'id'));
            $counts = $this->documentDependencyCounts($documentIds);

            if ($documentIds !== []) {
                $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
                $deleteDocuments = $this->database->prepare("DELETE FROM documents WHERE id IN ({$placeholders})");
                $deleteDocuments->execute($documentIds);
            }

            $deleteProject = $this->database->prepare('DELETE FROM projects WHERE id = :id');
            $deleteProject->execute(['id' => $projectId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }

        $storageFailures = $this->removeStoredSources($documents);

        return [
            'entity' => 'project',
            'id' => $projectId,
            'name' => (string) $project['name'],
            'documents_deleted' => count($documents),
            ...$counts,
            'storage_cleanup_failures' => $storageFailures,
        ];
    }

    /** @return array{id: int, public_id: string, title: string, storage_path: ?string} */
    private function lockDocument(int $documentId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, public_id, title, storage_path FROM documents WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $documentId]);
        $document = $statement->fetch();

        if (!is_array($document)) {
            throw new AccessException('Documento não localizado.', 404);
        }

        return [
            'id' => (int) $document['id'],
            'public_id' => (string) $document['public_id'],
            'title' => (string) $document['title'],
            'storage_path' => is_string($document['storage_path']) ? $document['storage_path'] : null,
        ];
    }

    /** @param list<int> $documentIds @return array<string, int> */
    private function documentDependencyCounts(array $documentIds): array
    {
        $counts = [
            'nodes_deleted' => 0,
            'evidences_deleted' => 0,
            'embeddings_deleted' => 0,
            'derivations_deleted' => 0,
            'jobs_deleted' => 0,
        ];

        if ($documentIds === []) {
            return $counts;
        }

        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $queries = [
            'nodes_deleted' => "SELECT COUNT(*) FROM document_nodes WHERE document_id IN ({$placeholders})",
            'evidences_deleted' => "SELECT COUNT(*) FROM evidences WHERE document_id IN ({$placeholders})",
            'embeddings_deleted' => "SELECT COUNT(*) FROM evidence_embeddings ee JOIN evidences e ON e.id = ee.evidence_id WHERE e.document_id IN ({$placeholders})",
            'derivations_deleted' => "SELECT COUNT(*) FROM evidence_derivations ed JOIN evidences e ON e.id = ed.evidence_id WHERE e.document_id IN ({$placeholders})",
            'jobs_deleted' => "SELECT COUNT(*) FROM processing_jobs WHERE document_id IN ({$placeholders})",
        ];

        foreach ($queries as $key => $sql) {
            $statement = $this->database->prepare($sql);
            $statement->execute($documentIds);
            $counts[$key] = (int) $statement->fetchColumn();
        }

        return $counts;
    }

    /** @param list<array<string, mixed>> $documents */
    private function removeStoredSources(array $documents): int
    {
        $failures = 0;

        foreach ($documents as $document) {
            $path = $document['storage_path'] ?? null;

            if (!is_string($path) || $path === '') {
                continue;
            }

            try {
                $this->storage->remove($path);
            } catch (Throwable) {
                $failures++;
            }
        }

        return $failures;
    }
}
