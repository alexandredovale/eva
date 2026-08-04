<?php

declare(strict_types=1);

use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleInterface;

return new class implements ModuleInterface {
    public function id(): string
    {
        return 'com.example.capture';
    }

    public function install(ModuleContext $context): void
    {
        $context->storage->exec(
            'CREATE TABLE IF NOT EXISTS captured_events (
                event_id TEXT PRIMARY KEY,
                event_type TEXT NOT NULL
            )'
        );
    }

    public function handle(ModuleEvent $event, ModuleContext $context): void
    {
        $statement = $context->storage->prepare(
            'INSERT INTO captured_events (event_id, event_type) VALUES (:event_id, :event_type)'
        );
        $statement->execute(['event_id' => $event->eventId, 'event_type' => $event->eventType]);
    }
};
