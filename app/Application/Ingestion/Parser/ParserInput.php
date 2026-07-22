<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

final class ParserInput
{
    public static function utf8Content(string $content, string $format): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (trim($content) === '') {
            throw new ParserException(sprintf('O documento %s está vazio.', $format));
        }

        if (preg_match('//u', $content) !== 1) {
            throw new ParserException(sprintf('O documento %s não possui codificação UTF-8 válida.', $format));
        }

        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    public static function title(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            throw new ParserException('O título do documento é obrigatório.');
        }

        if (preg_match('//u', $title) !== 1) {
            throw new ParserException('O título do documento não possui codificação UTF-8 válida.');
        }

        return $title;
    }
}

