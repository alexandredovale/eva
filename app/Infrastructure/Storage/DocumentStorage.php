<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Storage;

use RuntimeException;

final class DocumentStorage
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');

        if ($this->directory === '') {
            throw new RuntimeException('O diretório de documentos não foi configurado.');
        }
    }

    public function store(string $documentPublicId, string $format, string $content): string
    {
        $extension = match ($format) {
            'markdown' => 'md',
            'json' => 'json',
            'xml' => 'xml',
            default => throw new RuntimeException('Formato de armazenamento não suportado.'),
        };

        if (preg_match('/^EVA-D\d{6,}$/', $documentPublicId) !== 1) {
            throw new RuntimeException('Identificador de documento inválido para armazenamento.');
        }

        $this->ensureDirectory();
        $filename = $documentPublicId . '.' . $extension;
        $finalPath = $this->directory . DIRECTORY_SEPARATOR . $filename;

        if (is_file($finalPath)) {
            throw new RuntimeException('A fonte do documento já existe no armazenamento.');
        }

        $temporaryPath = $finalPath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        $written = file_put_contents($temporaryPath, $content, LOCK_EX);

        if ($written === false || $written !== strlen($content)) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            throw new RuntimeException('Não foi possível gravar integralmente a fonte do documento.');
        }

        if (!rename($temporaryPath, $finalPath)) {
            unlink($temporaryPath);
            throw new RuntimeException('Não foi possível finalizar o armazenamento da fonte.');
        }

        return 'documents/' . $filename;
    }

    public function absolutePath(string $relativePath): string
    {
        if (!str_starts_with($relativePath, 'documents/')) {
            throw new RuntimeException('Caminho de documento inválido.');
        }

        $filename = substr($relativePath, strlen('documents/'));

        if ($filename === '' || basename($filename) !== $filename) {
            throw new RuntimeException('Nome de documento inválido.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $filename;
    }

    public function remove(string $relativePath): void
    {
        $path = $this->absolutePath($relativePath);

        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Não foi possível remover a fonte solicitada.');
        }
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->directory)) {
            return;
        }

        if (!mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Não foi possível criar o diretório de documentos.');
        }
    }
}

