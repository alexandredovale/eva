<?php

declare(strict_types=1);

use Eva\ModuleRuntime\ModuleContext;
use Eva\ModuleRuntime\ModuleEvent;
use Eva\ModuleRuntime\ModuleInterface;
return new class implements ModuleInterface {
    public function id(): string
    {
        return 'com.example.fail';
    }

    public function install(ModuleContext $context): void
    {
    }

    public function handle(ModuleEvent $event, ModuleContext $context): void
    {
        throw new \RuntimeException('fixture failure');
    }
};
