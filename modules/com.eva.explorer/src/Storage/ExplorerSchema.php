<?php

declare(strict_types=1);

namespace EvaModule\Explorer\Storage;

use PDO;

final class ExplorerSchema
{
    public const VERSION = 2;

    public function install(PDO $database): void
    {
        $database->exec('PRAGMA foreign_keys = ON');
        $database->exec(
            'CREATE TABLE IF NOT EXISTS module_settings (
                setting_key TEXT PRIMARY KEY,
                value_json TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL COLLATE NOCASE UNIQUE,
                description TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS user_profiles (
                user_id INTEGER PRIMARY KEY,
                profile_id INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE
            )'
        );
        $database->exec('CREATE INDEX IF NOT EXISTS idx_explorer_user_profiles_profile ON user_profiles (profile_id, user_id)');
        $database->exec(
            "CREATE TABLE IF NOT EXISTS action_requests (
                request_id TEXT PRIMARY KEY,
                action_id TEXT NOT NULL,
                actor_user_id INTEGER NOT NULL,
                input_hash TEXT NOT NULL,
                status TEXT NOT NULL CHECK (status IN ('processing', 'completed', 'failed')),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS learning_themes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id TEXT NOT NULL UNIQUE,
                professor_user_id INTEGER NOT NULL,
                professor_name TEXT NOT NULL,
                project_id INTEGER NOT NULL,
                project_name TEXT NOT NULL,
                document_id INTEGER NOT NULL,
                document_public_id TEXT NOT NULL,
                document_title TEXT NOT NULL,
                document_source_hash TEXT NOT NULL,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (request_id) REFERENCES action_requests(request_id) ON DELETE CASCADE
            )'
        );
        $database->exec('CREATE INDEX IF NOT EXISTS idx_explorer_themes_professor ON learning_themes (professor_user_id, created_at DESC)');
        $database->exec(
            "CREATE TABLE IF NOT EXISTS interaction_prompts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                theme_id INTEGER NOT NULL,
                student_user_id INTEGER NOT NULL,
                interaction_type TEXT NOT NULL CHECK (interaction_type IN ('quiz', 'nodes', 'prova')),
                prompt_text TEXT NOT NULL,
                options_json TEXT NOT NULL,
                correct_option TEXT,
                answer_contract_json TEXT NOT NULL DEFAULT '{}',
                evidences_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (theme_id, student_user_id, interaction_type),
                FOREIGN KEY (theme_id) REFERENCES learning_themes(id) ON DELETE CASCADE
            )"
        );
        $this->addColumnWhenMissing(
            $database,
            'interaction_prompts',
            'answer_contract_json',
            "TEXT NOT NULL DEFAULT '{}'"
        );
        $database->exec(
            "CREATE TABLE IF NOT EXISTS student_interactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                request_id TEXT NOT NULL UNIQUE,
                prompt_id INTEGER NOT NULL UNIQUE,
                theme_id INTEGER NOT NULL,
                student_user_id INTEGER NOT NULL,
                interaction_type TEXT NOT NULL CHECK (interaction_type IN ('quiz', 'nodes', 'prova')),
                response_text TEXT NOT NULL,
                outcome TEXT NOT NULL CHECK (outcome IN ('correct', 'deepen')),
                analysis_text TEXT NOT NULL,
                evidences_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                UNIQUE (theme_id, student_user_id, interaction_type),
                FOREIGN KEY (request_id) REFERENCES action_requests(request_id) ON DELETE CASCADE,
                FOREIGN KEY (prompt_id) REFERENCES interaction_prompts(id) ON DELETE CASCADE,
                FOREIGN KEY (theme_id) REFERENCES learning_themes(id) ON DELETE CASCADE
            )"
        );
        $database->exec('CREATE INDEX IF NOT EXISTS idx_explorer_interactions_student ON student_interactions (student_user_id, created_at DESC)');
        $database->exec('CREATE INDEX IF NOT EXISTS idx_explorer_interactions_theme ON student_interactions (theme_id, created_at DESC)');
        $this->synchronizeProfiles($database);

        $statement = $database->prepare(
            "INSERT OR REPLACE INTO module_settings (setting_key, value_json, updated_at)
             VALUES ('schema_version', :value_json, datetime('now'))"
        );
        $statement->execute(['value_json' => json_encode(self::VERSION, JSON_THROW_ON_ERROR)]);
    }

    private function addColumnWhenMissing(PDO $database, string $table, string $column, string $definition): void
    {
        foreach ($database->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $field) {
            if (($field['name'] ?? null) === $column) {
                return;
            }
        }

        $database->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private function synchronizeProfiles(PDO $database): void
    {
        $profiles = [
            'Professor' => 'Publicação de Temas de Aprendizado e acompanhamento do percurso dos alunos.',
            'Secretaria' => 'Acompanhamento institucional da evolução dos alunos e dos temas publicados.',
            'Aluno' => 'Realização das relações cognitivas Quizz, Nodes e Prova.',
        ];
        $seed = $database->prepare(
            "INSERT INTO profiles (name, description, active, created_at, updated_at)
             VALUES (:name, :description, 1, datetime('now'), datetime('now'))
             ON CONFLICT(name) DO UPDATE SET description = excluded.description, active = 1, updated_at = datetime('now')"
        );

        foreach ($profiles as $name => $description) {
            $seed->execute(['name' => $name, 'description' => $description]);
        }
    }
}
