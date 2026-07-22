<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

use Eva\Domain\Document\NormalizedDocument;
use Eva\Domain\Document\NormalizedNode;
use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMText;

final class XmlParser implements DocumentParserInterface
{
    public function format(): string
    {
        return 'xml';
    }

    public function parse(string $content, string $documentTitle): NormalizedDocument
    {
        $sourceHash = hash('sha256', $content);
        $documentTitle = ParserInput::title($documentTitle);

        if (trim($content) === '') {
            throw new ParserException('O documento XML está vazio.');
        }

        if (preg_match('/<!DOCTYPE/i', $content) === 1) {
            throw new ParserException('Documentos XML com DOCTYPE não são permitidos.');
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new DOMDocument();
        $document->preserveWhiteSpace = true;

        try {
            $loaded = $document->loadXML($content, LIBXML_NONET | LIBXML_COMPACT);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }

        if (!$loaded || $document->documentElement === null) {
            $line = $errors[0]->line ?? 0;
            $suffix = $line > 0 ? ' próximo à linha ' . $line : '';
            throw new ParserException('XML inválido' . $suffix . '.');
        }

        if ($document->doctype !== null) {
            throw new ParserException('Documentos XML com DOCTYPE não são permitidos.');
        }

        $element = $document->documentElement;
        $elementPath = '/' . $element->nodeName . '[1]';
        $elementNode = $this->buildElement($element, $elementPath, 1, 0);

        $root = new NormalizedNode(
            type: 'document',
            title: $documentTitle,
            structuralPath: '/',
            depth: 0,
            order: 0,
            content: '',
            sourceReference: 'xml-document:/',
            metadata: [
                'format' => 'xml',
                'xml_version' => $document->xmlVersion,
                'encoding' => $document->encoding ?: 'UTF-8',
            ],
            children: [$elementNode]
        );

        return new NormalizedDocument($this->format(), $documentTitle, $sourceHash, $root);
    }

    private function buildElement(DOMElement $element, string $path, int $depth, int $order): NormalizedNode
    {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->nodeName] = $attribute->nodeValue;
        }

        $children = [];
        $textParts = [];
        $occurrences = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText || $child instanceof DOMCdataSection) {
                $textParts[] = $child->nodeValue;
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $name = $child->nodeName;
            $occurrences[$name] = ($occurrences[$name] ?? 0) + 1;
            $childPath = $path . '/' . $name . '[' . $occurrences[$name] . ']';
            $children[] = $this->buildElement($child, $childPath, $depth + 1, count($children));
        }

        $content = implode('', $textParts);
        $content = trim($content) === '' ? '' : trim($content);

        return new NormalizedNode(
            type: 'element',
            title: $element->nodeName,
            structuralPath: $path,
            depth: $depth,
            order: $order,
            content: $content,
            sourceReference: 'xpath:' . $path,
            metadata: [
                'attributes' => $attributes,
                'namespace_uri' => $element->namespaceURI,
                'line' => $element->getLineNo(),
            ],
            children: $children
        );
    }
}

