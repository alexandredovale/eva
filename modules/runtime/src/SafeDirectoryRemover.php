<?php

declare(strict_types=1);

namespace Eva\ModuleRuntime;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SafeDirectoryRemover
{
    public static function removeExactChild(string $parent, string $child, string $expectedName): int
    {
        $resolvedParent = realpath($parent);
        $resolvedChild = realpath($child);

        if ($resolvedParent === false || $resolvedChild === false
            || !is_dir($resolvedParent) || !is_dir($resolvedChild)
            || basename($resolvedChild) !== $expectedName
            || $resolvedChild !== $resolvedParent . DIRECTORY_SEPARATOR . $expectedName) {
            throw new ModuleException('A exclusão recusou um diretório modular inesperado.');
        }

        $removed = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($resolvedChild, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $deleted = $item->isLink() || !$item->isDir() ? unlink($path) : rmdir($path);

            if (!$deleted) {
                throw new ModuleException('Um arquivo do módulo não pôde ser excluído.');
            }

            $removed++;
        }

        if (!rmdir($resolvedChild)) {
            throw new ModuleException('O diretório do módulo não pôde ser excluído.');
        }

        return $removed + 1;
    }
}
