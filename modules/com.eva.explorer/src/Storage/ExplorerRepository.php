<?php

declare(strict_types=1);

namespace EvaModule\Explorer\Storage;

use Eva\ModuleRuntime\ModuleException;
use JsonException;
use PDO;

final readonly class ExplorerRepository
{
    public function __construct(private PDO $database)
    {
    }

    /** @return list<array<string, mixed>> */
    public function profiles(): array
    {
        $rows = $this->database->query(
            "SELECT id, name, description, active FROM profiles
             WHERE name IN ('Professor', 'Secretaria', 'Aluno')
             ORDER BY CASE name WHEN 'Professor' THEN 1 WHEN 'Secretaria' THEN 2 ELSE 3 END"
        )->fetchAll();

        return is_array($rows) ? array_map([$this, 'castProfile'], $rows) : [];
    }

    /** @return array<string, mixed>|null */
    public function profile(int $profileId): ?array
    {
        $statement = $this->database->prepare(
            "SELECT id, name, description, active FROM profiles
             WHERE id = :id AND name IN ('Professor', 'Secretaria', 'Aluno') LIMIT 1"
        );
        $statement->execute(['id' => $profileId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->castProfile($row) : null;
    }

    public function assignProfile(int $userId, ?int $profileId): void
    {
        if ($profileId === null) {
            $statement = $this->database->prepare('DELETE FROM user_profiles WHERE user_id = :user_id');
            $statement->execute(['user_id' => $userId]);
            return;
        }

        if ($this->profile($profileId) === null) {
            throw new ModuleException('O perfil selecionado não existe no módulo EXPLORER.');
        }

        $statement = $this->database->prepare(
            "INSERT INTO user_profiles (user_id, profile_id, created_at, updated_at)
             VALUES (:user_id, :profile_id, datetime('now'), datetime('now'))
             ON CONFLICT(user_id) DO UPDATE SET profile_id = excluded.profile_id, updated_at = datetime('now')"
        );
        $statement->execute(['user_id' => $userId, 'profile_id' => $profileId]);
    }

    /** @return array<int, int> */
    public function assignments(): array
    {
        $assignments = [];

        foreach ($this->database->query('SELECT user_id, profile_id FROM user_profiles')->fetchAll() as $row) {
            $assignments[(int) $row['user_id']] = (int) $row['profile_id'];
        }

        return $assignments;
    }

    /** @return array<string, mixed>|null */
    public function activeProfileForUser(int $userId): ?array
    {
        $statement = $this->database->prepare(
            "SELECT p.id, p.name, p.description, p.active
             FROM user_profiles up JOIN profiles p ON p.id = up.profile_id
             WHERE up.user_id = :user_id AND p.active = 1
               AND p.name IN ('Professor', 'Secretaria', 'Aluno') LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->castProfile($row) : null;
    }

    /** @return list<int> */
    public function userIdsForProfile(string $name): array
    {
        $statement = $this->database->prepare(
            'SELECT up.user_id FROM user_profiles up JOIN profiles p ON p.id = up.profile_id
             WHERE p.name = :name AND p.active = 1 ORDER BY up.user_id'
        );
        $statement->execute(['name' => $name]);

        return array_map('intval', array_column($statement->fetchAll(), 'user_id'));
    }

    /** @return array<string, mixed>|null */
    public function actionRequest(string $requestId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM action_requests WHERE request_id = :request_id');
        $statement->execute(['request_id' => $requestId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function beginAction(string $requestId, string $action, int $userId, string $inputHash): void
    {
        $statement = $this->database->prepare(
            "INSERT INTO action_requests (request_id, action_id, actor_user_id, input_hash, status, created_at, updated_at)
             VALUES (:request_id, :action_id, :actor_user_id, :input_hash, 'processing', datetime('now'), datetime('now'))"
        );
        $statement->execute([
            'request_id' => $requestId,
            'action_id' => $action,
            'actor_user_id' => $userId,
            'input_hash' => $inputHash,
        ]);
    }

    public function completeRequest(string $requestId): void
    {
        $statement = $this->database->prepare(
            "UPDATE action_requests SET status = 'completed', updated_at = datetime('now') WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
    }

    public function failRequest(string $requestId): void
    {
        $statement = $this->database->prepare(
            "UPDATE action_requests SET status = 'failed', updated_at = datetime('now') WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);
    }

    /** @param array<string, mixed> $scope */
    public function createTheme(
        string $requestId,
        int $professorId,
        string $professorName,
        array $scope,
        string $title,
        string $description
    ): int {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                "INSERT INTO learning_themes (
                    request_id, professor_user_id, professor_name, project_id, project_name,
                    document_id, document_public_id, document_title, document_source_hash,
                    title, description, active, created_at, updated_at
                 ) VALUES (
                    :request_id, :professor_user_id, :professor_name, :project_id, :project_name,
                    :document_id, :document_public_id, :document_title, :document_source_hash,
                    :title, :description, 1, datetime('now'), datetime('now')
                 )"
            );
            $statement->execute([
                'request_id' => $requestId,
                'professor_user_id' => $professorId,
                'professor_name' => $professorName,
                'project_id' => (int) $scope['project_id'],
                'project_name' => (string) $scope['project_name'],
                'document_id' => (int) $scope['document_id'],
                'document_public_id' => (string) $scope['document_public_id'],
                'document_title' => (string) $scope['document_title'],
                'document_source_hash' => (string) $scope['document_source_hash'],
                'title' => $title,
                'description' => $description,
            ]);
            $themeId = (int) $this->database->lastInsertId();
            $this->markRequestCompleted($requestId);
            $this->database->commit();

            return $themeId;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function setThemeActive(int $themeId, int $professorId, bool $active): void
    {
        $statement = $this->database->prepare(
            "UPDATE learning_themes SET active = :active, updated_at = datetime('now')
             WHERE id = :id AND professor_user_id = :professor_user_id"
        );
        $statement->execute([
            'active' => $active ? 1 : 0,
            'id' => $themeId,
            'professor_user_id' => $professorId,
        ]);

        if ($statement->rowCount() !== 1) {
            throw new ModuleException('O Tema de Aprendizado informado não pertence ao professor autenticado.');
        }
    }

    /** @return array<string, mixed>|null */
    public function theme(int $themeId, bool $onlyActive = false): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM learning_themes WHERE id = :id' . ($onlyActive ? ' AND active = 1' : '') . ' LIMIT 1'
        );
        $statement->execute(['id' => $themeId]);
        $row = $statement->fetch();

        return is_array($row) ? $this->castTheme($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function activeThemes(): array
    {
        return $this->themesFromStatement(
            $this->database->query('SELECT * FROM learning_themes WHERE active = 1 ORDER BY created_at DESC, id DESC')
        );
    }

    /** @return list<array<string, mixed>> */
    public function themesByProfessor(int $professorId): array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM learning_themes WHERE professor_user_id = :professor_user_id ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['professor_user_id' => $professorId]);

        return $this->themesFromStatement($statement);
    }

    /**
     * @param list<array<string, mixed>> $evidences
     * @param array<string, string> $options
     * @param array<string, mixed> $answerContract
     */
    public function savePrompt(
        int $themeId,
        int $studentId,
        string $type,
        string $prompt,
        array $options,
        ?string $correctOption,
        array $answerContract,
        array $evidences
    ): int {
        $statement = $this->database->prepare(
            'INSERT OR IGNORE INTO interaction_prompts (
                theme_id, student_user_id, interaction_type, prompt_text, options_json,
                correct_option, answer_contract_json, evidences_json, created_at
             ) VALUES (
                :theme_id, :student_user_id, :interaction_type, :prompt_text, :options_json,
                :correct_option, :answer_contract_json, :evidences_json, datetime(\'now\')
             )'
        );
        $statement->execute([
            'theme_id' => $themeId,
            'student_user_id' => $studentId,
            'interaction_type' => $type,
            'prompt_text' => $prompt,
            'options_json' => $this->encode($options),
            'correct_option' => $correctOption,
            'answer_contract_json' => $this->encode($answerContract),
            'evidences_json' => $this->encode($this->compactEvidences($evidences)),
        ]);
        $stored = $this->prompt($themeId, $studentId, $type);

        if ($stored === null) {
            throw new ModuleException('Não foi possível registrar a etapa de aprendizado.');
        }

        return (int) $stored['id'];
    }

    /** @return array<string, mixed>|null */
    public function prompt(int $themeId, int $studentId, string $type): ?array
    {
        $statement = $this->database->prepare(
            'SELECT * FROM interaction_prompts
             WHERE theme_id = :theme_id AND student_user_id = :student_user_id AND interaction_type = :interaction_type LIMIT 1'
        );
        $statement->execute([
            'theme_id' => $themeId,
            'student_user_id' => $studentId,
            'interaction_type' => $type,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->castPrompt($row) : null;
    }

    /** @param list<array<string, mixed>> $evidences */
    public function saveInteraction(
        string $requestId,
        array $prompt,
        int $studentId,
        string $response,
        string $outcome,
        string $analysis,
        array $evidences
    ): int {
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'INSERT INTO student_interactions (
                    request_id, prompt_id, theme_id, student_user_id, interaction_type,
                    response_text, outcome, analysis_text, evidences_json, created_at
                 ) VALUES (
                    :request_id, :prompt_id, :theme_id, :student_user_id, :interaction_type,
                    :response_text, :outcome, :analysis_text, :evidences_json, datetime(\'now\')
                 )'
            );
            $statement->execute([
                'request_id' => $requestId,
                'prompt_id' => (int) $prompt['id'],
                'theme_id' => (int) $prompt['theme_id'],
                'student_user_id' => $studentId,
                'interaction_type' => (string) $prompt['interaction_type'],
                'response_text' => $response,
                'outcome' => $outcome,
                'analysis_text' => $analysis,
                'evidences_json' => $this->encode($this->compactEvidences($evidences)),
            ]);
            $interactionId = (int) $this->database->lastInsertId();
            $this->markRequestCompleted($requestId);
            $this->database->commit();

            return $interactionId;
        } catch (\Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function interaction(int $themeId, int $studentId, string $type): ?array
    {
        $statement = $this->database->prepare(
            'SELECT si.*, ip.prompt_text, ip.options_json, ip.correct_option, ip.answer_contract_json
             FROM student_interactions si JOIN interaction_prompts ip ON ip.id = si.prompt_id
             WHERE si.theme_id = :theme_id AND si.student_user_id = :student_user_id
               AND si.interaction_type = :interaction_type LIMIT 1'
        );
        $statement->execute([
            'theme_id' => $themeId,
            'student_user_id' => $studentId,
            'interaction_type' => $type,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->castInteraction($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function interactionsForStudent(int $studentId): array
    {
        $statement = $this->database->prepare(
            'SELECT si.*, ip.prompt_text, ip.options_json, ip.correct_option, ip.answer_contract_json
             FROM student_interactions si JOIN interaction_prompts ip ON ip.id = si.prompt_id
             WHERE si.student_user_id = :student_user_id ORDER BY si.created_at DESC, si.id DESC'
        );
        $statement->execute(['student_user_id' => $studentId]);

        return $this->interactionsFromStatement($statement);
    }

    /** @return list<array<string, mixed>> */
    public function interactionsForThemes(array $themeIds, ?int $days = null): array
    {
        $themeIds = array_values(array_unique(array_filter(array_map('intval', $themeIds), static fn (int $id): bool => $id > 0)));

        if ($themeIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($themeIds), '?'));
        $sql = 'SELECT si.*, ip.prompt_text, ip.options_json, ip.correct_option, ip.answer_contract_json
                FROM student_interactions si JOIN interaction_prompts ip ON ip.id = si.prompt_id
                WHERE si.theme_id IN (' . $placeholders . ')';

        if ($days !== null) {
            $sql .= " AND si.created_at >= datetime('now', ?)";
            $themeIds[] = '-' . max(1, $days) . ' days';
        }

        $sql .= ' ORDER BY si.created_at DESC, si.id DESC';
        $statement = $this->database->prepare($sql);
        $statement->execute($themeIds);

        return $this->interactionsFromStatement($statement);
    }

    /** @return list<array<string, mixed>> */
    public function allThemes(?int $professorId = null, ?int $days = null): array
    {
        $conditions = [];
        $parameters = [];

        if ($professorId !== null) {
            $conditions[] = 'professor_user_id = :professor_user_id';
            $parameters['professor_user_id'] = $professorId;
        }

        if ($days !== null) {
            $conditions[] = "created_at >= datetime('now', :period)";
            $parameters['period'] = '-' . max(1, $days) . ' days';
        }

        $sql = 'SELECT * FROM learning_themes' . ($conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions))
            . ' ORDER BY created_at DESC, id DESC';
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return $this->themesFromStatement($statement);
    }

    /** @return list<array<string, mixed>> */
    private function themesFromStatement(\PDOStatement $statement): array
    {
        $themes = [];

        foreach ($statement->fetchAll() as $row) {
            $themes[] = $this->castTheme($row);
        }

        return $themes;
    }

    /** @return list<array<string, mixed>> */
    private function interactionsFromStatement(\PDOStatement $statement): array
    {
        $interactions = [];

        foreach ($statement->fetchAll() as $row) {
            $interactions[] = $this->castInteraction($row);
        }

        return $interactions;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function castProfile(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['active'] = (bool) $row['active'];

        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function castTheme(array $row): array
    {
        foreach (['id', 'professor_user_id', 'project_id', 'document_id'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['active'] = (bool) $row['active'];

        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function castPrompt(array $row): array
    {
        foreach (['id', 'theme_id', 'student_user_id'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['options'] = $this->decodeArray((string) $row['options_json']);
        $row['answer_contract'] = $this->decodeArray((string) ($row['answer_contract_json'] ?? '{}'));
        $row['evidences'] = $this->decodeArray((string) $row['evidences_json']);
        unset($row['options_json'], $row['answer_contract_json'], $row['evidences_json']);

        return $row;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function castInteraction(array $row): array
    {
        foreach (['id', 'prompt_id', 'theme_id', 'student_user_id'] as $key) {
            $row[$key] = (int) $row[$key];
        }
        $row['options'] = $this->decodeArray((string) $row['options_json']);
        $row['answer_contract'] = $this->decodeArray((string) ($row['answer_contract_json'] ?? '{}'));
        $row['evidences'] = $this->decodeArray((string) $row['evidences_json']);
        unset($row['options_json'], $row['answer_contract_json'], $row['evidences_json']);

        return $row;
    }

    /** @param list<array<string, mixed>> $evidences @return list<array<string, mixed>> */
    private function compactEvidences(array $evidences): array
    {
        $result = [];

        foreach ($evidences as $evidence) {
            if (!is_array($evidence) || !is_string($evidence['id'] ?? null)) {
                continue;
            }

            $result[$evidence['id']] = array_filter([
                'id' => $evidence['id'],
                'document' => is_string($evidence['document'] ?? null) ? $evidence['document'] : null,
                'node' => is_string($evidence['node'] ?? null) ? $evidence['node'] : null,
                'structural_path' => is_string($evidence['structural_path'] ?? null) ? $evidence['structural_path'] : null,
                'source_reference' => is_string($evidence['source_reference'] ?? null) ? $evidence['source_reference'] : null,
                'content' => is_string($evidence['content'] ?? null) ? $evidence['content'] : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        return array_values($result);
    }

    /** @param mixed $value @return list<mixed>|array<string, mixed> */
    private function decodeArray(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function markRequestCompleted(string $requestId): void
    {
        $statement = $this->database->prepare(
            "UPDATE action_requests SET status = 'completed', updated_at = datetime('now') WHERE request_id = :request_id"
        );
        $statement->execute(['request_id' => $requestId]);

        if ($statement->rowCount() !== 1) {
            throw new ModuleException('A requisição modular não está disponível para conclusão.');
        }
    }
}
