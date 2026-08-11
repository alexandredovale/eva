<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Storage;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FigureStorage
{
    /** @var array<string, list<string>> */
    private const ALLOWED_TYPES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    private string $directory;

    public function __construct(string $directory, private readonly int $maxFigureBytes = 20_971_520)
    {
        $this->directory = rtrim($directory, '/\\');

        if ($this->directory === '' || $this->maxFigureBytes < 1) {
            throw new RuntimeException('O armazenamento de figuras não foi configurado corretamente.');
        }
    }

    /**
     * @return array{relative_path: string, absolute_path: string, mime_type: string, size: int}|null
     */
    public function locate(string $documentPublicId, string $declaredPath): ?array
    {
        if (preg_match('/^EVA-D\d{6,}$/', $documentPublicId) !== 1) {
            return null;
        }

        $relativePath = $this->normalizeDeclaredPath($declaredPath);

        if ($relativePath === null) {
            return null;
        }

        $documentDirectory = $this->directory . DIRECTORY_SEPARATOR . $documentPublicId;

        if (!is_dir($documentDirectory)) {
            return null;
        }

        $path = $documentDirectory . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $realDocumentDirectory = realpath($documentDirectory);
        $realPath = realpath($path);

        if ($realDocumentDirectory === false || $realPath === false || !is_file($realPath)) {
            return null;
        }

        $prefix = rtrim($realDocumentDirectory, '/\\') . DIRECTORY_SEPARATOR;

        if (!str_starts_with($realPath, $prefix)) {
            return null;
        }

        $size = filesize($realPath);

        if ($size === false || $size < 1 || $size > $this->maxFigureBytes) {
            return null;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($realPath);

        if (!is_string($mimeType) || !isset(self::ALLOWED_TYPES[$extension])
            || !in_array($mimeType, self::ALLOWED_TYPES[$extension], true)) {
            return null;
        }

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $realPath,
            'mime_type' => $mimeType,
            'size' => (int) $size,
        ];
    }

    public function normalizeDeclaredPath(string $declaredPath): ?string
    {
        $path = trim(str_replace('\\', '/', $declaredPath));
        $path = preg_replace('~^(?:figuras|figures)/~iu', '', $path, 1) ?? $path;

        if ($path === '' || str_starts_with($path, '/') || preg_match('/[\x00-\x1F:]/u', $path) === 1) {
            return null;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return isset(self::ALLOWED_TYPES[$extension]) ? implode('/', $segments) : null;
    }

    /** @return array{content: string, mime_type: string, size: int, relative_path: string}|null */
    public function read(string $documentPublicId, string $declaredPath): ?array
    {
        $asset = $this->locate($documentPublicId, $declaredPath);

        if ($asset === null) {
            return null;
        }

        $content = file_get_contents($asset['absolute_path']);

        if (!is_string($content) || strlen($content) !== $asset['size']) {
            return null;
        }

        return [
            'content' => $content,
            'mime_type' => $asset['mime_type'],
            'size' => $asset['size'],
            'relative_path' => $asset['relative_path'],
        ];
    }

    public function removeDocument(string $documentPublicId): void
    {
        if (preg_match('/^EVA-D\d{6,}$/', $documentPublicId) !== 1) {
            throw new RuntimeException('Identificador de documento inválido para limpeza de figuras.');
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $documentPublicId;

        if (!is_dir($path)) {
            return;
        }

        $realRoot = realpath($this->directory);
        $realPath = realpath($path);

        if ($realRoot === false || $realPath === false
            || dirname($realPath) !== rtrim($realRoot, '/\\')) {
            throw new RuntimeException('O diretório de figuras não pôde ser validado para exclusão.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $removed = $entry->isDir() && !$entry->isLink()
                ? rmdir($entry->getPathname())
                : unlink($entry->getPathname());

            if (!$removed) {
                throw new RuntimeException('Não foi possível remover integralmente as figuras do documento.');
            }
        }

        if (!rmdir($realPath)) {
            throw new RuntimeException('Não foi possível remover o diretório de figuras do documento.');
        }
    }
}
