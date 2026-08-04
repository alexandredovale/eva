<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use Throwable;

final readonly class ModuleRegistry
{
    public function __construct(
        private string $modulesRoot,
        private ModuleStateStore $state
    ) {
    }

    /** @return list<ModuleManifest> */
    public function manifests(): array
    {
        $manifests = [];

        foreach ($this->inspect() as $record) {
            if ($record['manifest'] instanceof ModuleManifest) {
                $manifests[] = $record['manifest'];
            }
        }

        usort($manifests, static fn (ModuleManifest $left, ModuleManifest $right): int => strcasecmp($left->name, $right->name));

        return $manifests;
    }

    /** @return list<ModuleManifest> */
    public function activeManifests(): array
    {
        return array_values(array_filter(
            $this->manifests(),
            fn (ModuleManifest $manifest): bool => $this->state->isActive($manifest->id)
        ));
    }

    public function hasActiveSubscriber(string $eventType): bool
    {
        foreach ($this->activeManifests() as $manifest) {
            if (in_array($eventType, $manifest->subscribedEvents, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{directory: string, manifest: ModuleManifest|null, error: string|null}> */
    public function inspect(): array
    {
        $records = [];
        $entries = is_dir($this->modulesRoot) ? scandir($this->modulesRoot) : false;

        if ($entries === false) {
            throw new ModuleException('O diretório de módulos não pode ser lido.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'runtime' || str_starts_with($entry, '.')) {
                continue;
            }

            $directory = $this->modulesRoot . DIRECTORY_SEPARATOR . $entry;

            if (!is_dir($directory)) {
                continue;
            }

            try {
                $records[] = ['directory' => $entry, 'manifest' => ModuleManifest::fromDirectory($directory), 'error' => null];
            } catch (Throwable $exception) {
                $records[] = ['directory' => $entry, 'manifest' => null, 'error' => $exception->getMessage()];
            }
        }

        return $records;
    }

    public function find(string $moduleId): ModuleManifest
    {
        foreach ($this->manifests() as $manifest) {
            if ($manifest->id === $moduleId) {
                return $manifest;
            }
        }

        throw new ModuleException('O módulo não está instalado ou possui manifesto inválido.');
    }

    public function load(ModuleManifest $manifest): ModuleInterface
    {
        $module = require $manifest->entrypointPath();

        if (!$module instanceof ModuleInterface || $module->id() !== $manifest->id) {
            throw new ModuleException('O entrypoint não retornou um módulo compatível com o manifesto.');
        }

        return $module;
    }

    public function deletePackage(ModuleManifest $manifest): int
    {
        return SafeDirectoryRemover::removeExactChild($this->modulesRoot, $manifest->directory, $manifest->id);
    }

    /** @return list<array<string, mixed>> */
    public function listForAdministration(): array
    {
        $records = [];

        foreach ($this->inspect() as $record) {
            $manifest = $record['manifest'];

            if (!$manifest instanceof ModuleManifest) {
                $records[] = [
                    'directory' => $record['directory'],
                    'valid' => false,
                    'active' => false,
                    'error' => $record['error'],
                ];
                continue;
            }

            $records[] = [
                ...$manifest->toArray($this->state->isActive($manifest->id)),
                'valid' => true,
                'error' => null,
            ];
        }

        return $records;
    }
}
