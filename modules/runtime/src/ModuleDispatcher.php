<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use PDO;
use Throwable;

final readonly class ModuleDispatcher
{
    public function __construct(
        private ModuleRegistry $registry,
        private ModuleStateStore $state,
        private ModuleStorageFactory $storageFactory,
        private ModuleEventRepository $events,
        private PDO $coreDatabase,
        private array $aiConfiguration = []
    ) {
    }

    /** @return array{modules: list<array<string, mixed>>, processed: int, failed: int} */
    public function consumeOnce(int $limitPerModule = 50): array
    {
        $records = [];
        $processedTotal = 0;
        $failed = 0;

        foreach ($this->registry->activeManifests() as $manifest) {
            try {
                $record = $this->consumeModule($manifest, $limitPerModule);
                $processedTotal += $record['processed'];
                $records[] = $record;
            } catch (Throwable $exception) {
                $failed++;
                $records[] = [
                    'module_id' => $manifest->id,
                    'processed' => 0,
                    'ignored' => 0,
                    'status' => 'failed',
                    'error_type' => $exception::class,
                ];
            }
        }

        return ['modules' => $records, 'processed' => $processedTotal, 'failed' => $failed];
    }

    /** @return array{module_id: string, processed: int, ignored: int, status: string} */
    private function consumeModule(ModuleManifest $manifest, int $limit): array
    {
        $module = $this->registry->load($manifest);
        $storage = $this->storageFactory->open($manifest);
        $context = new ModuleContext(
            $manifest,
            $storage,
            new CoreReadApi($this->coreDatabase, $manifest->capabilities),
            new LanguageModelApi($manifest->capabilities, $this->aiConfiguration)
        );
        $module->install($context);
        $cursor = (int) $storage->query(
            'SELECT last_event_row_id FROM runtime_event_cursor WHERE singleton_id = 1'
        )->fetchColumn();
        $earliest = $this->events->earliestRowId();

        if ($earliest > 0 && $cursor > 0 && $cursor < ($earliest - 1)) {
            $gap = $storage->prepare(
                "INSERT INTO runtime_event_gaps (previous_cursor, resumed_at_row_id, recorded_at)
                 VALUES (:previous_cursor, :resumed_at_row_id, datetime('now'))"
            );
            $gap->execute(['previous_cursor' => $cursor, 'resumed_at_row_id' => $earliest]);
            $storage->prepare(
                "UPDATE runtime_event_cursor
                    SET last_event_row_id = :row_id, updated_at = datetime('now')
                  WHERE singleton_id = 1"
            )->execute(['row_id' => $earliest - 1]);
            $cursor = $earliest - 1;
        }

        $processed = 0;
        $ignored = 0;

        foreach ($this->events->after($cursor, $limit) as $record) {
            $event = $record['event'];
            $storage->beginTransaction();

            try {
                $known = $storage->prepare('SELECT 1 FROM runtime_processed_events WHERE event_id = :event_id');
                $known->execute(['event_id' => $event->eventId]);

                if ($known->fetchColumn() === false && in_array($event->eventType, $manifest->subscribedEvents, true)) {
                    $module->handle($event, $context);
                    $insert = $storage->prepare(
                        "INSERT INTO runtime_processed_events (event_id, event_type, processed_at)
                         VALUES (:event_id, :event_type, datetime('now'))"
                    );
                    $insert->execute(['event_id' => $event->eventId, 'event_type' => $event->eventType]);
                    $processed++;
                } else {
                    $ignored++;
                }

                $cursorUpdate = $storage->prepare(
                    "UPDATE runtime_event_cursor
                        SET last_event_row_id = :row_id, updated_at = datetime('now')
                      WHERE singleton_id = 1"
                );
                $cursorUpdate->execute(['row_id' => $record['row_id']]);
                $storage->commit();
            } catch (Throwable $exception) {
                if ($storage->inTransaction()) {
                    $storage->rollBack();
                }

                throw $exception;
            }
        }

        return [
            'module_id' => $manifest->id,
            'processed' => $processed,
            'ignored' => $ignored,
            'status' => 'completed',
        ];
    }
}
