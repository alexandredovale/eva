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

$markdownWithFigure = <<<'MD'
# Atlas geográfico

## América do Sul

### Figura 1 — Mapa político

Arquivo: figuras/america-do-sul.png
Tipo: mapa político
Descrição factual: O mapa apresenta os países da América do Sul.
Texto visível: Brasil, Argentina e Chile.
Relações representadas: as fronteiras delimitam os territórios nacionais.
MD;

$figureDocument = (new MarkdownParser())->parse($markdownWithFigure, 'Atlas');
$figureNode = findNode($figureDocument->root, '/atlas-geográfico/américa-do-sul/figura-1-mapa-político');
assertSameValue(true, $figureNode !== null, 'A figura filha de tópico temático deve ser aceita.');
assertContainsValue('Arquivo: figuras/america-do-sul.png', $figureNode?->content ?? '', 'O contrato visual deve permanecer no conteúdo da evidência filha.');
assertThrowsParserException(
    static fn () => (new MarkdownParser())->parse(
        "# Figura 1 — Mapa órfão\n\nArquivo: figuras/orfao.png",
        'Figura sem tópico'
    ),
    'Uma figura diretamente sob a raiz documental deve ser rejeitada.'
);
assertThrowsParserException(
    static fn () => (new MarkdownParser())->parse(
        "# Atlas\n\n## Figura 1 — Mapa geral\n\n### Figura 2 — Detalhe sem tópico\n\nArquivo: figuras/detalhe.png",
        'Figura filha de figura'
    ),
    'Uma figura não pode usar outra figura como pai temático.'
);

$englishMarkdownWithFigure = <<<'MD'
# Astronomy

## Earth orbit

### Figure 1 — Orbital motion

File: figures/earth-orbit.png
Type: educational diagram
Factual description: Earth appears around the Sun.
Visible text: Earth and Sun.
Represented relationships: the line represents Earth's orbit.
MD;
$englishFigureDocument = (new MarkdownParser())->parse($englishMarkdownWithFigure, 'Astronomy');
assertSameValue(
    true,
    findNode($englishFigureDocument->root, '/astronomy/earth-orbit/figure-1-orbital-motion') !== null,
    'A seção Figure em inglês deve ser aceita sob tópico temático.'
);
assertThrowsParserException(
    static fn () => (new MarkdownParser())->parse(
        "# Figure 1 — Orphan figure\n\nFile: figures/orphan.png",
        'Orphan figure'
    ),
    'Uma seção Figure em inglês diretamente na raiz deve ser rejeitada.'
);

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

$jsonFigure = (new JsonParser())->parse(<<<'JSON'
{
  "Astronomy": {
    "Figure 1 — Orbital motion": {
      "File": "figures/earth-orbit.png",
      "Factual description": "Earth appears around the Sun."
    }
  }
}
JSON, 'JSON figure');
assertSameValue(
    'figures/earth-orbit.png',
    findNode($jsonFigure->root, '/Astronomy/Figure 1 — Orbital motion/File')?->content,
    'O contrato Figure em JSON deve preservar seus campos estruturados.'
);
assertThrowsParserException(
    static fn () => (new JsonParser())->parse(
        '{"Figure 1 — Orphan figure":{"File":"figures/orphan.png"}}',
        'Orphan JSON figure'
    ),
    'Um contrato Figure no objeto raiz JSON deve ser rejeitado.'
);

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

$xmlFigure = (new XmlParser())->parse(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<document>
  <topic>
    <figure title="Figure 1 — Orbital motion">
      <file>figures/earth-orbit.png</file>
      <factual_description>Earth appears around the Sun.</factual_description>
    </figure>
  </topic>
</document>
XML, 'XML figure');
assertSameValue(
    'figures/earth-orbit.png',
    findNode($xmlFigure->root, '/document[1]/topic[1]/figure[1]/file[1]')?->content,
    'O contrato figure em XML deve preservar seus campos estruturados.'
);
assertThrowsParserException(
    static fn () => (new XmlParser())->parse(
        '<figure title="Figure 1 — Orphan figure"><file>figures/orphan.png</file></figure>',
        'Orphan XML figure'
    ),
    'Um elemento figure usado como raiz XML deve ser rejeitado.'
);

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

$figureTemplatePaths = glob(dirname(__DIR__) . '/docs/examples/figure-contracts/{pt-BR,en}/figure.{md,json,xml}', GLOB_BRACE);
assertSameValue(6, count($figureTemplatePaths ?: []), 'Os seis templates de figura devem estar disponíveis.');

foreach ($figureTemplatePaths ?: [] as $templatePath) {
    $templateContent = file_get_contents($templatePath);
    assertSameValue(true, is_string($templateContent), 'O template de figura deve ser legível.');
    $templateDocument = ParserFactory::forFilename($templatePath)->parse(
        (string) $templateContent,
        basename($templatePath)
    );
    assertSameValue(
        pathinfo($templatePath, PATHINFO_EXTENSION) === 'md'
            ? 'markdown'
            : pathinfo($templatePath, PATHINFO_EXTENSION),
        $templateDocument->format,
        'O template deve usar um formato aceito pelo parser correspondente.'
    );
}

echo sprintf("Parsers validados com %d asserções.\n", $assertions);
