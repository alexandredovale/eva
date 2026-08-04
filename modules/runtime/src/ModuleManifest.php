<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use JsonException;

final readonly class ModuleManifest
{
    /**
     * @param list<string> $subscribedEvents
     * @param list<string> $capabilities
     * @param array{enabled: bool, entrypoint?: string, order?: int} $dashboard
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $vendor,
        public string $version,
        public string $evaContract,
        public string $entrypoint,
        public array $subscribedEvents,
        public array $capabilities,
        public array $dashboard,
        public string $storageDriver,
        public int $storageSchemaVersion,
        public string $directory
    ) {
    }

    public static function fromDirectory(string $directory): self
    {
        $resolvedDirectory = realpath($directory);

        if ($resolvedDirectory === false || !is_dir($resolvedDirectory)) {
            throw new ModuleException('O diretório do módulo não existe.');
        }

        $manifestPath = $resolvedDirectory . DIRECTORY_SEPARATOR . 'module.json';
        $contents = is_file($manifestPath) ? file_get_contents($manifestPath) : false;

        if (!is_string($contents)) {
            throw new ModuleException('O manifesto do módulo não pode ser lido.');
        }

        try {
            $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ModuleException('O manifesto do módulo não contém JSON válido.', 0, $exception);
        }

        if (!is_array($data)) {
            throw new ModuleException('O manifesto do módulo é inválido.');
        }

        $allowed = ['id', 'name', 'vendor', 'version', 'eva_contract', 'entrypoint', 'subscribed_events', 'capabilities', 'dashboard', 'storage'];

        if (array_diff(array_keys($data), $allowed) !== []) {
            throw new ModuleException('O manifesto contém campos não reconhecidos.');
        }

        $id = self::requiredString($data, 'id', 160);

        if (preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)+$/', $id) !== 1 || basename($resolvedDirectory) !== $id) {
            throw new ModuleException('O identificador do módulo deve corresponder ao nome do diretório.');
        }

        $version = self::requiredString($data, 'version', 64);

        if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new ModuleException('A versão do módulo é inválida.');
        }

        $evaContract = self::requiredString($data, 'eva_contract', 16);

        if ($evaContract !== '1') {
            throw new ModuleException('O módulo exige uma versão incompatível do EVA Module Contract.');
        }

        $name = self::requiredString($data, 'name', 180);
        $entrypoint = self::relativePhpPath(self::requiredString($data, 'entrypoint', 255));
        self::resolveInside($resolvedDirectory, $entrypoint, true);
        $events = self::stringList($data['subscribed_events'] ?? null, '/^[a-z][a-z0-9_.-]{2,79}$/', 100, 'eventos');
        $capabilities = self::stringList($data['capabilities'] ?? null, '/^[a-z][a-z0-9_.-]{2,119}$/', 100, 'capacidades');
        $dashboard = is_array($data['dashboard'] ?? null) ? $data['dashboard'] : ['enabled' => false];

        if (!is_bool($dashboard['enabled'] ?? null)
            || array_diff(array_keys($dashboard), ['enabled', 'entrypoint', 'order']) !== []) {
            throw new ModuleException('A configuração de dashboard do módulo é inválida.');
        }

        if (isset($dashboard['order']) && (!is_int($dashboard['order'])
            || $dashboard['order'] < 1 || $dashboard['order'] > 9999)) {
            throw new ModuleException('A ordem do dashboard do módulo é inválida.');
        }

        if (($dashboard['enabled'] ?? false) === true) {
            $dashboardEntrypoint = self::relativePhpPath(self::requiredString($dashboard, 'entrypoint', 255));
            self::resolveInside($resolvedDirectory, $dashboardEntrypoint, true);
            $dashboard['entrypoint'] = $dashboardEntrypoint;
            $dashboard['order'] = $dashboard['order'] ?? 1000;
        }

        $storage = $data['storage'] ?? null;

        if (!is_array($storage)
            || array_diff(array_keys($storage), ['driver', 'schema_version']) !== []
            || ($storage['driver'] ?? null) !== 'sqlite'
            || !is_int($storage['schema_version'] ?? null)
            || $storage['schema_version'] < 1) {
            throw new ModuleException('A configuração de armazenamento do módulo é inválida.');
        }

        return new self(
            $id,
            $name,
            self::requiredString($data, 'vendor', 180),
            $version,
            $evaContract,
            $entrypoint,
            $events,
            $capabilities,
            $dashboard,
            'sqlite',
            $storage['schema_version'],
            $resolvedDirectory
        );
    }

    public function entrypointPath(): string
    {
        return self::resolveInside($this->directory, $this->entrypoint, true);
    }

    /** @return array<string, mixed> */
    public function toArray(bool $active = false): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vendor' => $this->vendor,
            'version' => $this->version,
            'eva_contract' => $this->evaContract,
            'subscribed_events' => $this->subscribedEvents,
            'capabilities' => $this->capabilities,
            'dashboard' => $this->dashboard,
            'storage' => ['driver' => $this->storageDriver, 'schema_version' => $this->storageSchemaVersion],
            'active' => $active,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function requiredString(array $data, string $key, int $maxLength): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || trim($value) === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            throw new ModuleException(sprintf('O campo %s do manifesto é inválido.', $key));
        }

        return trim($value);
    }

    /** @return list<string> */
    private static function stringList(mixed $value, string $pattern, int $maxItems, string $label): array
    {
        if (!is_array($value) || count($value) > $maxItems) {
            throw new ModuleException(sprintf('A lista de %s do módulo é inválida.', $label));
        }

        $items = [];

        foreach ($value as $item) {
            if (!is_string($item) || preg_match($pattern, $item) !== 1 || isset($items[$item])) {
                throw new ModuleException(sprintf('A lista de %s do módulo é inválida.', $label));
            }

            $items[$item] = $item;
        }

        return array_values($items);
    }

    private static function relativePhpPath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || str_contains($path, '../')
            || str_contains($path, '/..')
            || !str_ends_with(strtolower($path), '.php')) {
            throw new ModuleException('O entrypoint do módulo é inválido.');
        }

        return $path;
    }

    private static function resolveInside(string $directory, string $relative, bool $mustExist): string
    {
        $candidate = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($candidate);

        if ($mustExist && ($resolved === false || !is_file($resolved))) {
            throw new ModuleException('O entrypoint declarado pelo módulo não existe.');
        }

        $resolved ??= $candidate;
        $base = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!str_starts_with($resolved, $base)) {
            throw new ModuleException('O módulo tentou acessar um caminho externo ao pacote.');
        }

        return $resolved;
    }
}
