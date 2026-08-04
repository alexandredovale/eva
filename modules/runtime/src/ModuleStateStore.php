<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use JsonException;

final readonly class ModuleStateStore
{
    public function __construct(private string $path)
    {
    }

    public function isActive(string $moduleId): bool
    {
        $state = $this->read();

        return ($state['modules'][$moduleId]['active'] ?? false) === true;
    }

    public function setActive(string $moduleId, bool $active): void
    {
        $state = $this->read();
        $state['modules'][$moduleId] ??= ['active' => false];
        $state['modules'][$moduleId]['active'] = $active;
        unset($state['modules'][$moduleId]['configuration']);
        $this->write($state);
    }

    public function remove(string $moduleId): void
    {
        $state = $this->read();
        unset($state['modules'][$moduleId]);
        $this->write($state);
    }

    /** @return array{modules: array<string, array{active: bool}>} */
    public function read(): array
    {
        $contents = is_file($this->path) ? file_get_contents($this->path) : false;

        if (!is_string($contents)) {
            return ['modules' => []];
        }

        try {
            $state = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ModuleException('O estado local dos módulos contém JSON inválido.', 0, $exception);
        }

        if (!is_array($state) || !is_array($state['modules'] ?? null)) {
            throw new ModuleException('O estado local dos módulos é inválido.');
        }

        return ['modules' => $state['modules']];
    }

    /** @param array<string, mixed> $state */
    private function write(array $state): void
    {
        try {
            $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } catch (JsonException $exception) {
            throw new ModuleException('O estado local dos módulos não pode ser serializado.', 0, $exception);
        }

        $directory = dirname($this->path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new ModuleException('O diretório de estado dos módulos não pode ser criado.');
        }

        $handle = fopen($this->path, 'c+b');

        if ($handle === false) {
            throw new ModuleException('O estado local dos módulos não pode ser aberto.');
        }

        try {
            if (!flock($handle, LOCK_EX) || !ftruncate($handle, 0) || rewind($handle) === false) {
                throw new ModuleException('O estado local dos módulos não pode ser bloqueado para gravação.');
            }

            $written = fwrite($handle, $encoded);

            if ($written !== strlen($encoded) || !fflush($handle)) {
                throw new ModuleException('O estado local dos módulos não foi gravado integralmente.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
