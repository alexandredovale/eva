<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

interface ModuleInterface
{
    public function id(): string;

    public function install(ModuleContext $context): void;

    public function handle(ModuleEvent $event, ModuleContext $context): void;
}
