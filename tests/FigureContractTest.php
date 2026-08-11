<?php

declare(strict_types=1);

use Eva\Application\Query\FigureContractParser;
use Eva\Infrastructure\Storage\FigureStorage;

require __DIR__ . '/bootstrap.php';

$assertions = 0;
$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eva-figures-' . bin2hex(random_bytes(8));
$documentPublicId = 'EVA-D123456';
$documentDirectory = $temporaryRoot . DIRECTORY_SEPARATOR . $documentPublicId;

function assertFigure(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function removeFigureFixture(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink()
            ? rmdir($entry->getPathname())
            : unlink($entry->getPathname());
    }

    rmdir($path);
}

try {
    if (!mkdir($documentDirectory, 0775, true) && !is_dir($documentDirectory)) {
        throw new RuntimeException('Não foi possível criar o armazenamento temporário de figuras.');
    }

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    assertFigure(is_string($png), 'A figura sintética não pôde ser preparada.');
    file_put_contents($documentDirectory . DIRECTORY_SEPARATOR . 'translacao-terra.png', $png);
    file_put_contents($documentDirectory . DIRECTORY_SEPARATOR . 'falso.png', 'não é uma imagem');

    $parser = new FigureContractParser();
    $contract = $parser->parse(
        'Figura 4 — Movimento de translação',
        "Arquivo: figuras/translacao-terra.png\n"
            . "Tipo: diagrama didático\n"
            . "Descrição factual: A Terra aparece em quatro posições ao redor do Sol.\n"
            . "Texto visível: março, junho, setembro e dezembro.\n"
            . "Relações representadas: as setas indicam movimento orbital no sentido anti-horário.\n\n---"
    );

    assertFigure($contract !== null, 'O contrato válido de figura não foi reconhecido.');
    assertFigure($contract?->declaredPath === 'figuras/translacao-terra.png', 'O caminho declarado foi alterado.');
    assertFigure($contract?->type === 'diagrama didático', 'O tipo da figura não foi extraído.');
    assertFigure(
        $contract?->description === 'A Terra aparece em quatro posições ao redor do Sol.',
        'A descrição factual não foi extraída.'
    );
    $englishContract = $parser->parse(
        'Figure 4 — Orbital motion',
        "File: figures/translacao-terra.png\n"
            . "Type: educational diagram\n"
            . "Factual description: Earth appears in four positions around the Sun.\n"
            . "Visible text: March, June, September, and December.\n"
            . "Represented relationships: arrows indicate counterclockwise orbital motion."
    );
    assertFigure($englishContract !== null, 'O contrato em inglês não foi reconhecido.');
    assertFigure(
        $englishContract?->declaredPath === 'figures/translacao-terra.png',
        'O campo File do contrato em inglês não foi extraído.'
    );
    assertFigure(
        $englishContract?->representedRelationships === 'arrows indicate counterclockwise orbital motion.',
        'As relações representadas em inglês não foram extraídas.'
    );
    $structuredContract = $parser->parseFields('Figura 5 — Órbita', [
        'arquivo' => 'figuras/translacao-terra.png',
        'descricao_factual' => 'A Terra orbita o Sol.',
        'texto-visivel' => 'Sol e Terra.',
        'relacoes_representadas' => 'A linha representa a órbita.',
    ]);
    assertFigure($structuredContract !== null, 'Campos estruturados de JSON/XML não foram reconhecidos.');
    assertFigure(
        $structuredContract?->visibleText === 'Sol e Terra.',
        'Separadores de campos estruturados não foram normalizados.'
    );
    assertFigure(
        $parser->parse('Seção comum', 'Arquivo: figuras/translacao-terra.png') === null,
        'Uma seção comum foi interpretada indevidamente como figura.'
    );
    assertFigure(
        $parser->parse('Figura sem arquivo', 'Tipo: fotografia') === null,
        'Um contrato sem Arquivo foi aceito.'
    );

    $storage = new FigureStorage($temporaryRoot, 1_000_000);
    $located = $storage->locate($documentPublicId, 'figuras/translacao-terra.png');
    assertFigure($located !== null, 'A figura existente não foi localizada.');
    assertFigure($located['mime_type'] === 'image/png', 'O MIME real da figura não foi validado.');
    assertFigure(
        $storage->locate($documentPublicId, 'figures/translacao-terra.png') !== null,
        'O prefixo lógico inglês figures/ não foi reconhecido.'
    );
    assertFigure(
        $storage->locate($documentPublicId, 'figuras/falso.png') === null,
        'Um arquivo textual com extensão de imagem foi aceito.'
    );
    assertFigure(
        $storage->locate($documentPublicId, 'figuras/../segredo.png') === null,
        'Um caminho com travessia de diretório foi aceito.'
    );
    assertFigure(
        $storage->locate($documentPublicId, 'https://example.com/figura.png') === null,
        'Uma URL externa foi aceita como figura local.'
    );
    assertFigure(
        $storage->locate($documentPublicId, 'figuras/inexistente.png') === null,
        'Uma figura ausente foi retornada.'
    );

    $read = $storage->read($documentPublicId, 'translacao-terra.png');
    assertFigure($read !== null && $read['content'] === $png, 'A figura não foi lida integralmente.');

    $storage->removeDocument($documentPublicId);
    assertFigure(!is_dir($documentDirectory), 'O diretório de figuras permaneceu após a exclusão documental.');
} finally {
    removeFigureFixture($temporaryRoot);
}

echo sprintf("Contrato de figuras validado com %d asserções.\n", $assertions);
