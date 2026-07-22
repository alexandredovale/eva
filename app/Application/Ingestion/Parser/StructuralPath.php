<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

final class StructuralPath
{
    public static function segment(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value === '' ? 'node' : $value;
    }

    public static function child(string $parentPath, string $segment): string
    {
        return $parentPath === '/' ? '/' . $segment : $parentPath . '/' . $segment;
    }
}

