<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

use Eva\Domain\Document\NormalizedDocument;

final class MarkdownParser implements DocumentParserInterface
{
    public function format(): string
    {
        return 'markdown';
    }

    public function parse(string $content, string $documentTitle): NormalizedDocument
    {
        $sourceHash = hash('sha256', $content);
        $content = ParserInput::utf8Content($content, 'Markdown');
        $documentTitle = ParserInput::title($documentTitle);
        $lines = explode("\n", $content);
        $lineCount = count($lines);

        $root = new MutableNode(
            type: 'document',
            title: $documentTitle,
            structuralPath: '/',
            depth: 0,
            order: 0,
            startLine: 1,
            endLine: $lineCount,
            metadata: ['format' => 'markdown']
        );

        /** @var list<array{level: int, node: MutableNode}> $stack */
        $stack = [['level' => 0, 'node' => $root]];
        $numberedNode = null;
        $fenceCharacter = null;
        $fenceLength = 0;

        for ($index = 0; $index < $lineCount; $index++) {
            $line = $lines[$index];
            $lineNumber = $index + 1;
            $currentNode = $numberedNode ?? $stack[array_key_last($stack)]['node'];

            if ($fenceCharacter !== null) {
                $currentNode->addContent($line, $lineNumber);
                $closingPattern = '/^ {0,3}' . preg_quote($fenceCharacter, '/')
                    . '{' . $fenceLength . ',}[ \t]*$/';

                if (preg_match($closingPattern, $line) === 1) {
                    $fenceCharacter = null;
                    $fenceLength = 0;
                }

                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $fenceMatch) === 1) {
                $fenceCharacter = $fenceMatch[1][0];
                $fenceLength = strlen($fenceMatch[1]);
                $currentNode->addContent($line, $lineNumber);
                continue;
            }

            $heading = $this->atxHeading($line);
            $consumedLines = 1;

            if ($heading === null && $index + 1 < $lineCount) {
                $heading = $this->setextHeading($line, $lines[$index + 1]);
                $consumedLines = $heading === null ? 1 : 2;
            }

            if ($heading !== null) {
                [$level, $title] = $heading;
                $numberedNode = null;

                while (count($stack) > 1 && $stack[array_key_last($stack)]['level'] >= $level) {
                    array_pop($stack);
                }

                $parent = $stack[array_key_last($stack)]['node'];
                $node = new MutableNode(
                    type: 'section',
                    title: $title,
                    structuralPath: $parent->nextChildPath($title),
                    depth: $parent->depth + 1,
                    order: count($parent->children),
                    startLine: $lineNumber,
                    endLine: $lineNumber + $consumedLines - 1,
                    metadata: ['heading_level' => $level]
                );
                $parent->addChild($node);
                $stack[] = ['level' => $level, 'node' => $node];
                $index += $consumedLines - 1;
                continue;
            }

            $numberedBlock = $this->numberedBlock($line);

            if ($numberedBlock !== null) {
                [$ordinal, $marker] = $numberedBlock;
                $parent = $stack[array_key_last($stack)]['node'];
                $title = 'Item ' . $ordinal;
                $numberedNode = new MutableNode(
                    type: 'item',
                    title: $title,
                    structuralPath: $parent->nextChildPath($title),
                    depth: $parent->depth + 1,
                    order: count($parent->children),
                    startLine: $lineNumber,
                    endLine: $lineNumber,
                    metadata: [
                        'markdown_block' => 'ordered_item',
                        'ordinal' => $ordinal,
                        'marker' => $marker,
                    ]
                );
                $numberedNode->addContent($line, $lineNumber);
                $parent->addChild($numberedNode);
                continue;
            }

            $currentNode->addContent($line, $lineNumber);
        }

        return new NormalizedDocument(
            format: $this->format(),
            title: $documentTitle,
            sourceHash: $sourceHash,
            root: $root->toNormalizedNode()
        );
    }

    /** @return array{int, string}|null */
    private function atxHeading(string $line): ?array
    {
        if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+|$)(.*)$/u', $line, $match) !== 1) {
            return null;
        }

        $title = preg_replace('/[ \t]+#+[ \t]*$/u', '', $match[2]) ?? $match[2];

        return [strlen($match[1]), $this->headingTitle($title)];
    }

    /** @return array{int, string}|null */
    private function setextHeading(string $line, string $underline): ?array
    {
        if (trim($line) === '' || preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $underline, $match) !== 1) {
            return null;
        }

        return [$match[1][0] === '=' ? 1 : 2, $this->headingTitle($line)];
    }

    private function headingTitle(string $title): string
    {
        $title = trim($title);

        return $title === '' ? 'Seção sem título' : $title;
    }

    /** @return array{string, string}|null */
    private function numberedBlock(string $line): ?array
    {
        if (preg_match('/^ {0,3}(\d{1,4})([.)])[ \t]+\S/u', $line, $match) !== 1) {
            return null;
        }

        return [$match[1], $match[1] . $match[2]];
    }
}
