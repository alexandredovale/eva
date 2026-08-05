<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use Eva\Http\Security\ActorContext;
use PDO;

final readonly class RuntimeFactory
{
    private string $modulesRoot;
    private ModuleStateStore $state;
    private ModuleRegistry $registry;
    private ModuleEventRepository $events;
    private ModuleStorageFactory $storage;

    public function __construct(
        private string $applicationRoot,
        private PDO $database,
        private array $aiConfiguration = []
    ) {
        $this->modulesRoot = rtrim($this->applicationRoot, '/\\') . DIRECTORY_SEPARATOR . 'modules';
        $this->state = new ModuleStateStore($this->modulesRoot . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'state.json');
        $this->registry = new ModuleRegistry($this->modulesRoot, $this->state);
        $this->events = new ModuleEventRepository($this->database);
        $this->storage = new ModuleStorageFactory(
            $this->modulesRoot . DIRECTORY_SEPARATOR . '.runtime' . DIRECTORY_SEPARATOR . 'data'
        );
    }

    public function state(): ModuleStateStore
    {
        return $this->state;
    }

    public function registry(): ModuleRegistry
    {
        return $this->registry;
    }

    public function events(): ModuleEventRepository
    {
        return $this->events;
    }

    public function storage(): ModuleStorageFactory
    {
        return $this->storage;
    }

    public function bridge(): ModuleBridge
    {
        return new ModuleBridge($this->events);
    }

    public function manager(): ModuleManager
    {
        return new ModuleManager($this);
    }

    public function dispatcher(): ModuleDispatcher
    {
        return new ModuleDispatcher(
            $this->registry,
            $this->state,
            $this->storage,
            $this->events,
            $this->database,
            $this->aiConfiguration
        );
    }

    public function context(ModuleManifest $manifest, ?ActorContext $actor = null): ModuleContext
    {
        return new ModuleContext(
            $manifest,
            $this->storage->open($manifest),
            new CoreReadApi($this->database, $manifest->capabilities),
            new LanguageModelApi($manifest->capabilities, $this->aiConfiguration),
            $actor instanceof ActorContext
                ? new CoreQueryApi(
                    $this->database,
                    $manifest->capabilities,
                    $this->aiConfiguration,
                    $actor
                )
                : null
        );
    }
}
