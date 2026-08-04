<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use DateTimeImmutable;
use JsonException;
use PDO;
use PDOException;

final readonly class ModuleEventRepository
{
    public function __construct(private PDO $database)
    {
    }

    public function append(ModuleEvent $event): int
    {
        try {
            $payload = json_encode($event->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new ModuleException('O evento modular não pode ser persistido.', 0, $exception);
        }

        try {
            $statement = $this->database->prepare(
                'INSERT INTO module_events (event_id, event_type, contract_version, payload_json, created_at)
                 VALUES (:event_id, :event_type, :contract_version, :payload_json, :created_at)'
            );
            $statement->execute([
                'event_id' => $event->eventId,
                'event_type' => $event->eventType,
                'contract_version' => $event->contractVersion,
                'payload_json' => $payload,
                'created_at' => (new DateTimeImmutable($event->occurredAt))->format('Y-m-d H:i:s'),
            ]);

            return (int) $this->database->lastInsertId();
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $statement = $this->database->prepare('SELECT id FROM module_events WHERE event_id = :event_id');
            $statement->execute(['event_id' => $event->eventId]);
            $id = $statement->fetchColumn();

            if ($id === false) {
                throw $exception;
            }

            return (int) $id;
        }
    }

    /** @return list<array{row_id: int, event: ModuleEvent}> */
    public function after(int $rowId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));
        $statement = $this->database->prepare(
            "SELECT id, payload_json
               FROM module_events
              WHERE id > :row_id
              ORDER BY id ASC
              LIMIT {$limit}"
        );
        $statement->execute(['row_id' => max(0, $rowId)]);
        $events = [];

        foreach ($statement->fetchAll() as $row) {
            try {
                $payload = json_decode((string) $row['payload_json'], true, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new ModuleException('A caixa postal contém um evento inválido.', 0, $exception);
            }

            if (!is_array($payload)) {
                throw new ModuleException('A caixa postal contém um payload incompatível.');
            }

            $events[] = ['row_id' => (int) $row['id'], 'event' => ModuleEvent::fromArray($payload)];
        }

        return $events;
    }

    public function earliestRowId(): int
    {
        $value = $this->database->query('SELECT MIN(id) FROM module_events')->fetchColumn();

        return $value === false || $value === null ? 0 : (int) $value;
    }

    public function latestRowId(): int
    {
        $value = $this->database->query('SELECT MAX(id) FROM module_events')->fetchColumn();

        return $value === false || $value === null ? 0 : (int) $value;
    }

    public function pruneBefore(DateTimeImmutable $threshold): int
    {
        $statement = $this->database->prepare('DELETE FROM module_events WHERE created_at < :threshold');
        $statement->execute(['threshold' => $threshold->format('Y-m-d H:i:s')]);

        return $statement->rowCount();
    }
}
