<?php

declare(strict_types=1);

use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleInterface;

return new class implements ModuleInterface {
    public function id(): string
    {
        return 'com.example.unrelated';
    }

    public function install(ModuleContext $context): void
    {
        $context->storage->exec('CREATE TABLE IF NOT EXISTS unrelated_events (event_id TEXT PRIMARY KEY)');
    }

    public function handle(ModuleEvent $event, ModuleContext $context): void
    {
        $statement = $context->storage->prepare('INSERT INTO unrelated_events (event_id) VALUES (:event_id)');
        $statement->execute(['event_id' => $event->eventId]);
    }
};
