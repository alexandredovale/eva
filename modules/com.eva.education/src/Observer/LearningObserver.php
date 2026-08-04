<?php

declare(strict_types=1);

namespace EvaModule\Education\Observer;

use Eva\ModuleRuntime\ModuleEvent;
use JsonException;
use PDO;

final class LearningObserver
{
    public const COGNITIVE_JSON_VERSION = 1;

    public function observe(ModuleEvent $event, PDO $database): void
    {
        if ($event->eventType !== 'interaction.completed') {
            return;
        }

        $cognitive = [
            'version' => self::COGNITIVE_JSON_VERSION,
            'event_id' => $event->eventId,
            'actor' => $event->actor,
            'interaction' => $event->interaction,
            'scope' => $event->scope,
            'evidence_refs' => array_values(array_filter(array_map(
                static fn (array $evidence): ?string => is_string($evidence['id'] ?? null) ? $evidence['id'] : null,
                $event->evidences
            ))),
            'limitations' => $event->limitations,
            'interpretation_state' => 'pending',
        ];
        $statement = $database->prepare(
            "INSERT OR IGNORE INTO interactions (
                event_id, user_id, actor_role, occurred_at, current_input, contextual_input, answer,
                projects_json, documents_json, evidences_json, limitations_json, cognitive_json,
                processing_status, created_at, updated_at
             ) VALUES (
                :event_id, :user_id, :actor_role, :occurred_at, :current_input, :contextual_input, :answer,
                :projects_json, :documents_json, :evidences_json, :limitations_json, :cognitive_json,
                'pending', datetime('now'), datetime('now')
             )"
        );
        $statement->execute([
            'event_id' => $event->eventId,
            'user_id' => $event->actor['user_id'],
            'actor_role' => $event->actor['role'],
            'occurred_at' => $event->occurredAt,
            'current_input' => $event->interaction['current_input'],
            'contextual_input' => $event->interaction['contextual_input'],
            'answer' => $event->interaction['answer'],
            'projects_json' => $this->encode($event->scope['projects']),
            'documents_json' => $this->encode($event->scope['documents']),
            'evidences_json' => $this->encode($event->evidences),
            'limitations_json' => $this->encode($event->limitations),
            'cognitive_json' => $this->encode($cognitive),
        ]);
    }

    /** @param mixed $value */
    private function encode(mixed $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new \RuntimeException('O registro cognitivo não pôde ser serializado.', 0, $exception);
        }
    }
}
