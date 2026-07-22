<?php

declare(strict_types=1);

use Eva\Application\Ingestion\Parser\JsonParser;
use Eva\Application\Ingestion\Parser\MarkdownParser;
use Eva\Application\Ingestion\Parser\ParserException;
use Eva\Application\Ingestion\Parser\ParserFactory;
use Eva\Application\Ingestion\Parser\XmlParser;
use Eva\Domain\Document\NormalizedNode;

require __DIR__ . '/bootstrap.php';

$assertions = 0;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    global $assertions;
    $assertions++;

    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\nEsperado: %s\nRecebido: %s",
            $message,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assertContainsValue(string $needle, string $haystack, string $message): void
{
    global $assertions;
    $assertions++;

    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message);
    }
}

function assertThrowsParserException(callable $callback, string $message): void
{
    global $assertions;
    $assertions++;

    try {
        $callback();
    } catch (ParserException) {
        return;
    }

    throw new RuntimeException($message);
}

function findNode(NormalizedNode $node, string $path): ?NormalizedNode
{
    if ($node->structuralPath === $path) {
        return $node;
    }

    foreach ($node->children as $child) {
        $found = findNode($child, $path);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

$markdown = <<<'MD'
Apresentação da obra.

# Parte 1
Texto da parte.

## Capítulo A
Conteúdo do capítulo.

```php
# isto não é um título
```

1. Primeira unidade numerada com conteúdo próprio.

Continuação da primeira unidade.

2. Segunda unidade numerada.

Tópico repetido
----------------
Primeiro tópico.

Tópico repetido
----------------
Segundo tópico.
MD;

$markdownDocument = (new MarkdownParser())->parse($markdown, 'Obra Markdown');
assertSameValue('markdown', $markdownDocument->format, 'O formato Markdown deve ser identificado.');
assertContainsValue('Apresentação da obra.', $markdownDocument->root->content, 'O preâmbulo deve permanecer no nó raiz.');
assertSameValue('/parte-1', $markdownDocument->root->children[0]->structuralPath, 'A parte deve possuir caminho estável.');
$chapter = findNode($markdownDocument->root, '/parte-1/capítulo-a');
assertSameValue(true, $chapter !== null, 'O capítulo deve ser encontrado na hierarquia.');
assertContainsValue('# isto não é um título', $chapter?->content ?? '', 'Títulos dentro de blocos de código não devem criar nós.');
assertSameValue('item', $chapter?->children[0]->type ?? null, 'Blocos numerados devem formar unidades estruturais.');
assertSameValue('/parte-1/capítulo-a/item-1', $chapter?->children[0]->structuralPath ?? null, 'A unidade numerada deve possuir caminho estável.');
assertContainsValue('Continuação da primeira unidade.', $chapter?->children[0]->content ?? '', 'A unidade numerada deve preservar sua continuação completa.');
assertSameValue('2', $chapter?->children[1]->metadata['ordinal'] ?? null, 'A ordem autoral deve integrar os metadados da unidade.');
assertSameValue('/parte-1/tópico-repetido-2', $markdownDocument->root->children[0]->children[2]->structuralPath, 'Títulos repetidos devem receber caminhos únicos.');

$json = "\xEF\xBB\xBF" . <<<'JSON'
{
  "título": "Obra JSON",
  "parte": {
    "capítulos": [
      {"nome": "Um"},
      {"nome": "Dois", "ativo": true}
    ]
  }
}
JSON;

$jsonDocument = (new JsonParser())->parse($json, 'Obra JSON');
assertSameValue('object', $jsonDocument->root->metadata['json_type'], 'O objeto raiz deve ser identificado.');
$jsonNode = findNode($jsonDocument->root, '/parte/capítulos/1/nome');
assertSameValue('Dois', $jsonNode?->content, 'O conteúdo escalar JSON deve ser preservado.');
assertSameValue('json-pointer:/parte/capítulos/1/nome', $jsonNode?->sourceReference, 'A origem JSON deve usar JSON Pointer.');
$booleanNode = findNode($jsonDocument->root, '/parte/capítulos/1/ativo');
assertSameValue('true', $booleanNode?->content, 'Booleanos JSON não devem virar números ou texto vazio.');

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<obra idioma="pt-BR">
  <parte numero="1">
    <capitulo>Primeiro</capitulo>
    <capitulo><![CDATA[Segundo & completo]]></capitulo>
  </parte>
</obra>
XML;

$xmlDocument = (new XmlParser())->parse($xml, 'Obra XML');
$xmlRoot = findNode($xmlDocument->root, '/obra[1]');
assertSameValue('pt-BR', $xmlRoot?->metadata['attributes']['idioma'] ?? null, 'Atributos XML devem ser preservados.');
$xmlChapter = findNode($xmlDocument->root, '/obra[1]/parte[1]/capitulo[2]');
assertSameValue('Segundo & completo', $xmlChapter?->content, 'CDATA deve ser preservado como conteúdo.');
assertSameValue('xpath:/obra[1]/parte[1]/capitulo[2]', $xmlChapter?->sourceReference, 'A origem XML deve usar XPath.');

assertSameValue('markdown', ParserFactory::forFilename('arquivo.md')->format(), 'A fábrica deve reconhecer .md.');
assertSameValue('json', ParserFactory::forFormat('.JSON')->format(), 'A fábrica deve ignorar ponto e caixa.');
assertThrowsParserException(
    static fn () => (new JsonParser())->parse('{invalido}', 'Inválido'),
    'JSON inválido deve lançar ParserException.'
);
assertThrowsParserException(
    static fn () => (new XmlParser())->parse('<raiz>', 'Inválido'),
    'XML inválido deve lançar ParserException.'
);
assertThrowsParserException(
    static fn () => (new XmlParser())->parse('<!DOCTYPE raiz><raiz/>', 'Inválido'),
    'DOCTYPE deve ser rejeitado.'
);
assertThrowsParserException(
    static fn () => ParserFactory::forFilename('arquivo.txt'),
    'Extensões não suportadas devem ser rejeitadas.'
);

echo sprintf("Parsers validados com %d asserções.\n", $assertions);
