<?php

declare(strict_types=1);

namespace Eva\Application\Access;

use Eva\Http\Security\AccessException;
use PDO;
use PDOException;
use Throwable;

final readonly class AccessManagementService
{
    public function __construct(private PDO $database, private AuthService $auth)
    {
    }

    /** @return list<array<string, mixed>> */
    public function users(): array
    {
        $users = $this->database->query(
            'SELECT id, username, active, last_login_at, created_at FROM users ORDER BY username ASC'
        )->fetchAll();
        $projects = $this->database->query(
            'SELECT user_id, project_id FROM user_projects ORDER BY user_id, project_id'
        )->fetchAll();
        $documents = $this->database->query(
            'SELECT user_id, document_id FROM user_documents ORDER BY user_id, document_id'
        )->fetchAll();

        foreach ($users as &$user) {
            $userId = (int) $user['id'];
            $user['id'] = $userId;
            $user['active'] = (bool) $user['active'];
            $user['project_ids'] = array_map(
                'intval',
                array_column(array_filter($projects, static fn (array $row): bool => (int) $row['user_id'] === $userId), 'project_id')
            );
            $user['document_ids'] = array_map(
                'intval',
                array_column(array_filter($documents, static fn (array $row): bool => (int) $row['user_id'] === $userId), 'document_id')
            );
        }

        return $users;
    }

    /** @return array{user: array<string, mixed>, recovery_code: string} */
    public function createUser(string $username, string $password): array
    {
        $username = trim($username);

        if (preg_match('/^[\p{L}\p{N}._-]{3,80}$/u', $username) !== 1) {
            throw new AccessException('O username deve ter de 3 a 80 caracteres e usar apenas letras, números, ponto, hífen ou sublinhado.', 422);
        }

        $this->auth->assertPassword($password);
        $code = $this->auth->generateRecoveryCode();

        try {
            $statement = $this->database->prepare(
                'INSERT INTO users (username, password_hash, recovery_code_hash)
                 VALUES (:username, :password_hash, :recovery_hash)'
            );
            $statement->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'recovery_hash' => password_hash($code, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new AccessException('Este username já está cadastrado.', 409);
            }

            throw $exception;
        }

        return [
            'user' => ['id' => (int) $this->database->lastInsertId(), 'username' => $username, 'active' => true],
            'recovery_code' => $code,
        ];
    }

    public function setUserActive(int $userId, bool $active): void
    {
        $statement = $this->database->prepare('UPDATE users SET active = :active WHERE id = :id');
        $statement->execute(['active' => $active ? 1 : 0, 'id' => $userId]);

        if ($statement->rowCount() === 0 && !$this->userExists($userId)) {
            throw new AccessException('Usuário não localizado.', 404);
        }

        if (!$active) {
            $delete = $this->database->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);
        }
    }

    /** @return array{recovery_code: string} */
    public function resetPassword(int $userId, string $password): array
    {
        $this->auth->assertPassword($password);

        if (!$this->userExists($userId)) {
            throw new AccessException('Usuário não localizado.', 404);
        }

        $code = $this->auth->generateRecoveryCode();
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'UPDATE users SET password_hash = :password_hash, recovery_code_hash = :recovery_hash WHERE id = :id'
            );
            $statement->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'recovery_hash' => password_hash($code, PASSWORD_DEFAULT),
                'id' => $userId,
            ]);
            $delete = $this->database->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);
            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }

        return ['recovery_code' => $code];
    }

    /** @param list<int> $projectIds @param list<int> $documentIds */
    public function setPermissions(int $userId, array $projectIds, array $documentIds): void
    {
        if (!$this->userExists($userId)) {
            throw new AccessException('Usuário não localizado.', 404);
        }

        $projectIds = $this->validatedIds($projectIds, 'projects');
        $documentIds = $this->validatedIds($documentIds, 'documents');
        $this->database->beginTransaction();

        try {
            $deleteProjects = $this->database->prepare('DELETE FROM user_projects WHERE user_id = :user_id');
            $deleteProjects->execute(['user_id' => $userId]);
            $deleteDocuments = $this->database->prepare('DELETE FROM user_documents WHERE user_id = :user_id');
            $deleteDocuments->execute(['user_id' => $userId]);
            $projectInsert = $this->database->prepare(
                'INSERT INTO user_projects (user_id, project_id) VALUES (:user_id, :project_id)'
            );
            $documentInsert = $this->database->prepare(
                'INSERT INTO user_documents (user_id, document_id) VALUES (:user_id, :document_id)'
            );

            foreach ($projectIds as $projectId) {
                $projectInsert->execute(['user_id' => $userId, 'project_id' => $projectId]);
            }

            foreach ($documentIds as $documentId) {
                $documentInsert->execute(['user_id' => $userId, 'document_id' => $documentId]);
            }

            $this->database->commit();
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function projects(): array
    {
        $projects = $this->database->query(
            'SELECT id, name, active, created_at FROM projects ORDER BY name ASC'
        )->fetchAll();
        $relations = $this->database->query(
            'SELECT pd.project_id, d.id, d.public_id, d.title, d.status
               FROM project_documents pd
               JOIN documents d ON d.id = pd.document_id
              ORDER BY pd.project_id, d.title'
        )->fetchAll();

        foreach ($projects as &$project) {
            $projectId = (int) $project['id'];
            $project['id'] = $projectId;
            $project['active'] = (bool) $project['active'];
            $project['documents'] = array_values(array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'public_id' => (string) $row['public_id'],
                    'title' => (string) $row['title'],
                    'status' => (string) $row['status'],
                ],
                array_filter($relations, static fn (array $row): bool => (int) $row['project_id'] === $projectId)
            ));
            $project['document_ids'] = array_column($project['documents'], 'id');
        }

        return $projects;
    }

    /** @param list<int> $documentIds @return array<string, mixed> */
    public function saveProject(?int $projectId, string $name, array $documentIds): array
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name, 'UTF-8') > 180) {
            throw new AccessException('Informe um nome de projeto com até 180 caracteres.', 422);
        }

        $documentIds = $this->validatedIds($documentIds, 'documents');
        $this->database->beginTransaction();

        try {
            if ($projectId === null) {
                $statement = $this->database->prepare('INSERT INTO projects (name) VALUES (:name)');
                $statement->execute(['name' => $name]);
                $projectId = (int) $this->database->lastInsertId();
            } else {
                $statement = $this->database->prepare('UPDATE projects SET name = :name WHERE id = :id');
                $statement->execute(['name' => $name, 'id' => $projectId]);

                if ($statement->rowCount() === 0 && !$this->projectExists($projectId)) {
                    throw new AccessException('Projeto não localizado.', 404);
                }
            }

            $delete = $this->database->prepare('DELETE FROM project_documents WHERE project_id = :project_id');
            $delete->execute(['project_id' => $projectId]);
            $insert = $this->database->prepare(
                'INSERT INTO project_documents (project_id, document_id) VALUES (:project_id, :document_id)'
            );

            foreach ($documentIds as $documentId) {
                $insert->execute(['project_id' => $projectId, 'document_id' => $documentId]);
            }

            $this->database->commit();
        } catch (PDOException $exception) {
            $this->database->rollBack();

            if ((string) $exception->getCode() === '23000') {
                throw new AccessException('Já existe um projeto com este nome.', 409);
            }

            throw $exception;
        } catch (Throwable $exception) {
            $this->database->rollBack();
            throw $exception;
        }

        return ['id' => $projectId, 'name' => $name, 'document_ids' => $documentIds];
    }

    /** @param list<int> $ids @return list<int> */
    private function validatedIds(array $ids, string $table): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->database->prepare("SELECT id FROM {$table} WHERE id IN ({$placeholders})");
        $statement->execute($ids);
        $found = array_map('intval', array_column($statement->fetchAll(), 'id'));
        sort($found);
        $expected = $ids;
        sort($expected);

        if ($found !== $expected) {
            throw new AccessException('Uma das permissões informadas não existe.', 422);
        }

        return $ids;
    }

    private function userExists(int $userId): bool
    {
        $statement = $this->database->prepare('SELECT 1 FROM users WHERE id = :id');
        $statement->execute(['id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    private function projectExists(int $projectId): bool
    {
        $statement = $this->database->prepare('SELECT 1 FROM projects WHERE id = :id');
        $statement->execute(['id' => $projectId]);

        return $statement->fetchColumn() !== false;
    }
}
