<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use PDO;

final readonly class CoreReadApi
{
    /** @param list<string> $capabilities */
    public function __construct(
        private PDO $database,
        private array $capabilities
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(int $id): ?array
    {
        $this->requireCapability('core.read.users');

        return $this->one('SELECT id, username, active, created_at FROM users WHERE id = :id', $id);
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        $this->requireCapability('core.read.users');
        $rows = $this->database->query(
            'SELECT id, username, active, created_at FROM users ORDER BY username ASC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function project(int $id): ?array
    {
        $this->requireCapability('core.read.projects');

        return $this->one('SELECT id, name, active, created_at FROM projects WHERE id = :id', $id);
    }

    /** @return array<string, mixed>|null */
    public function document(int $id): ?array
    {
        $this->requireCapability('core.read.documents');

        return $this->one('SELECT id, public_id, title, format, status, created_at FROM documents WHERE id = :id', $id);
    }

    /** @return array<string, mixed>|null */
    public function evidenceByPublicId(string $publicId): ?array
    {
        $this->requireCapability('core.read.evidences');
        $statement = $this->database->prepare(
            'SELECT id, public_id, document_id, evidence_class, evidence_type, content, summary, status, created_at
               FROM evidences
              WHERE public_id = :public_id'
        );
        $statement->execute(['public_id' => $publicId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private function one(string $sql, int $id): ?array
    {
        $statement = $this->database->prepare($sql);
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function requireCapability(string $capability): void
    {
        if (!in_array($capability, $this->capabilities, true)) {
            throw new ModuleException(sprintf('O módulo não declarou a capacidade %s.', $capability));
        }
    }
}
