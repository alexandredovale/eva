<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

use Eva\Domain\Document\NormalizedDocument;
use Eva\Domain\Document\NormalizedNode;
use JsonException;
use stdClass;

final class JsonParser implements DocumentParserInterface
{
    public function format(): string
    {
        return 'json';
    }

    public function parse(string $content, string $documentTitle): NormalizedDocument
    {
        $sourceHash = hash('sha256', $content);
        $content = ParserInput::utf8Content($content, 'JSON');
        $documentTitle = ParserInput::title($documentTitle);

        try {
            $value = json_decode($content, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException $exception) {
            throw new ParserException('JSON inválido: ' . $exception->getMessage(), 0, $exception);
        }

        $children = [];
        $rootContent = '';

        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $childValue) {
                $children[] = $this->buildNode(
                    (string) $key,
                    $childValue,
                    '/' . $this->pointerSegment((string) $key),
                    1,
                    count($children)
                );
            }

            if ($children === []) {
                $rootContent = '{}';
            }
        } elseif (is_array($value)) {
            foreach ($value as $index => $childValue) {
                $children[] = $this->buildNode('[' . $index . ']', $childValue, '/' . $index, 1, count($children));
            }

            if ($children === []) {
                $rootContent = '[]';
            }
        } else {
            $rootContent = $this->scalarContent($value);
        }

        $root = new NormalizedNode(
            type: 'document',
            title: $documentTitle,
            structuralPath: '/',
            depth: 0,
            order: 0,
            content: $rootContent,
            sourceReference: 'json-pointer:',
            metadata: ['format' => 'json', 'json_type' => $this->valueType($value)],
            children: $children
        );

        return new NormalizedDocument($this->format(), $documentTitle, $sourceHash, $root);
    }

    private function buildNode(string $title, mixed $value, string $path, int $depth, int $order): NormalizedNode
    {
        $children = [];
        $content = '';

        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $childValue) {
                $childPath = $path . '/' . $this->pointerSegment((string) $key);
                $children[] = $this->buildNode((string) $key, $childValue, $childPath, $depth + 1, count($children));
            }

            if ($children === []) {
                $content = '{}';
            }
        } elseif (is_array($value)) {
            foreach ($value as $index => $childValue) {
                $childPath = $path . '/' . $index;
                $children[] = $this->buildNode('[' . $index . ']', $childValue, $childPath, $depth + 1, count($children));
            }

            if ($children === []) {
                $content = '[]';
            }
        } else {
            $content = $this->scalarContent($value);
        }

        return new NormalizedNode(
            type: $this->nodeType($value),
            title: $title,
            structuralPath: $path,
            depth: $depth,
            order: $order,
            content: $content,
            sourceReference: 'json-pointer:' . $path,
            metadata: ['json_type' => $this->valueType($value)],
            children: $children
        );
    }

    private function pointerSegment(string $value): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $value);
    }

    private function nodeType(mixed $value): string
    {
        return match (true) {
            $value instanceof stdClass => 'object',
            is_array($value) => 'array',
            default => 'value',
        };
    }

    private function valueType(mixed $value): string
    {
        return match (true) {
            $value instanceof stdClass => 'object',
            is_array($value) => 'array',
            is_string($value) => 'string',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_bool($value) => 'boolean',
            $value === null => 'null',
            default => 'unknown',
        };
    }

    private function scalarContent(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            default => (string) $value,
        };
    }
}

