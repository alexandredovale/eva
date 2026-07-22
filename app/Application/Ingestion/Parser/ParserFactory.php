<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

final class ParserFactory
{
    public static function forFormat(string $format): DocumentParserInterface
    {
        return match (strtolower(ltrim(trim($format), '.'))) {
            'md', 'markdown' => new MarkdownParser(),
            'json' => new JsonParser(),
            'xml' => new XmlParser(),
            default => throw new ParserException('Formato não suportado. Use Markdown, JSON ou XML.'),
        };
    }

    public static function forFilename(string $filename): DocumentParserInterface
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new ParserException('O arquivo não possui uma extensão reconhecível.');
        }

        return self::forFormat($extension);
    }
}

