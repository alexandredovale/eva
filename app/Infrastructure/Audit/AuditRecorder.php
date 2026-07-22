<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Audit;

use JsonException;
use PDO;

final readonly class AuditRecorder
{
    public function __construct(private PDO $database)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function record(
        string $eventType,
        ?string $entityType,
        ?string $entityId,
        ?string $actorFingerprint,
        ?string $networkAddress,
        array $metadata = []
    ): void {
        try {
            $encoded = json_encode(
                $this->sanitize($metadata),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException) {
            $encoded = '{}';
        }

        $statement = $this->database->prepare(
            'INSERT INTO audit_events
                (event_type, entity_type, entity_id, actor_fingerprint, network_fingerprint, metadata)
             VALUES
                (:event_type, :entity_type, :entity_id, :actor_fingerprint, :network_fingerprint, :metadata)'
        );
        $statement->execute([
            'event_type' => mb_substr($eventType, 0, 80, 'UTF-8'),
            'entity_type' => $entityType === null ? null : mb_substr($entityType, 0, 40, 'UTF-8'),
            'entity_id' => $entityId === null ? null : mb_substr($entityId, 0, 64, 'UTF-8'),
            'actor_fingerprint' => $actorFingerprint,
            'network_fingerprint' => $networkAddress === null || $networkAddress === ''
                ? null
                : hash('sha256', $networkAddress),
            'metadata' => $encoded,
        ]);
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && preg_match('/password|secret|token|api[_-]?key|authorization|content|input/i', $key) === 1) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitize($childValue, (string) $childKey);
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return mb_substr($value, 0, 300, 'UTF-8');
        }

        return is_scalar($value) || $value === null ? $value : get_debug_type($value);
    }
}
