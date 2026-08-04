<?php

declare(strict_types=1);

namespace EvaModule\Education\Storage;

use PDO;

final class EducationSchema
{
    public const VERSION = 2;

    private const RETIRED_OBSERVATION_DIMENSIONS = ['question_refinement'];

    public function install(PDO $database): void
    {
        $database->exec(
            'CREATE TABLE IF NOT EXISTS interactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_id TEXT NOT NULL UNIQUE,
                user_id INTEGER,
                actor_role TEXT NOT NULL,
                occurred_at TEXT NOT NULL,
                current_input TEXT NOT NULL,
                contextual_input TEXT NOT NULL,
                answer TEXT NOT NULL,
                projects_json TEXT NOT NULL,
                documents_json TEXT NOT NULL,
                evidences_json TEXT NOT NULL,
                limitations_json TEXT NOT NULL,
                cognitive_json TEXT NOT NULL,
                processing_status TEXT NOT NULL DEFAULT \'pending\'
                    CHECK (processing_status IN (\'pending\', \'processing\', \'completed\', \'failed\')),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $database->exec('CREATE INDEX IF NOT EXISTS idx_education_interactions_user_date ON interactions (user_id, occurred_at)');
        $database->exec('CREATE INDEX IF NOT EXISTS idx_education_interactions_status ON interactions (processing_status, id)');
        $database->exec(
            'CREATE TABLE IF NOT EXISTS interpretations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                interaction_id INTEGER NOT NULL,
                governance_version TEXT NOT NULL,
                interpreter_version TEXT NOT NULL,
                observations_json TEXT NOT NULL,
                limitations_json TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (interaction_id) REFERENCES interactions(id) ON DELETE CASCADE
            )'
        );
        $database->exec('CREATE INDEX IF NOT EXISTS idx_education_interpretations_interaction ON interpretations (interaction_id, id)');
        $database->exec(
            'CREATE TABLE IF NOT EXISTS module_settings (
                setting_key TEXT PRIMARY KEY,
                value_json TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
        $database->exec(
            'CREATE TABLE IF NOT EXISTS processing_failures (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                interaction_id INTEGER NOT NULL,
                error_type TEXT NOT NULL,
                attempted_at TEXT NOT NULL,
                FOREIGN KEY (interaction_id) REFERENCES interactions(id) ON DELETE CASCADE
            )'
        );
        $database->exec(
            'CREATE VIEW IF NOT EXISTS event_cursor AS
             SELECT last_event_row_id, updated_at FROM runtime_event_cursor WHERE singleton_id = 1'
        );
        $this->migrate($database, $this->currentVersion($database));
        $statement = $database->prepare(
            "INSERT OR REPLACE INTO module_settings (setting_key, value_json, updated_at)
             VALUES ('schema_version', :value_json, datetime('now'))"
        );
        $statement->execute(['value_json' => json_encode(self::VERSION, JSON_THROW_ON_ERROR)]);
    }

    private function currentVersion(PDO $database): int
    {
        $statement = $database->prepare(
            "SELECT value_json FROM module_settings WHERE setting_key = 'schema_version' LIMIT 1"
        );
        $statement->execute();
        $value = $statement->fetchColumn();

        if (!is_string($value)) {
            return 0;
        }

        $decoded = json_decode($value, true);

        return is_int($decoded) ? $decoded : 0;
    }

    private function migrate(PDO $database, int $currentVersion): void
    {
        if ($currentVersion >= 2) {
            return;
        }

        $rows = $database->query('SELECT id, observations_json FROM interpretations')->fetchAll();
        $update = $database->prepare(
            'UPDATE interpretations SET observations_json = :observations_json WHERE id = :id'
        );

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['observations_json'], true);

            if (!is_array($payload)) {
                continue;
            }

            if (is_array($payload['items'] ?? null)) {
                $items = $payload['items'];
                $envelope = true;
            } elseif (array_is_list($payload)) {
                $items = $payload;
                $envelope = false;
            } else {
                continue;
            }

            $filtered = array_values(array_filter(
                $items,
                static fn (mixed $item): bool => !is_array($item)
                    || !in_array(
                        $item['dimension'] ?? null,
                        self::RETIRED_OBSERVATION_DIMENSIONS,
                        true
                    )
            ));

            if (count($filtered) === count($items)) {
                continue;
            }

            if ($envelope) {
                $payload['items'] = $filtered;
            } else {
                $payload = $filtered;
            }

            $update->execute([
                'id' => $row['id'],
                'observations_json' => json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]);
        }
    }
}
