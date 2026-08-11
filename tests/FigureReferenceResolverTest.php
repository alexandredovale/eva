<?php

declare(strict_types=1);

use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Query\FigureReferenceResolver;
use Eva\Application\Query\RetrievedEvidence;
use Eva\Http\Product\ProductApi;
use Eva\Infrastructure\Database\Connection;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Storage\DocumentStorage;
use Eva\Infrastructure\Storage\FigureStorage;

$container = require __DIR__ . '/bootstrap.php';
$database = Connection::create($container['database']);
$documentStorage = new DocumentStorage($container['ingestion']['document_storage']);
$figureStorage = new FigureStorage(
    $container['ingestion']['figure_storage'],
    $container['ingestion']['max_figure_bytes']
);
$result = null;
$results = [];
$assertions = 0;
$adminTokenEnvironment = $container['security']['admin_token_environment'];
$originalAdminToken = $_ENV[$adminTokenEnvironment] ?? null;

function assertResolvedFigure(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{evidence: RetrievedEvidence, record: array<string, mixed>} */
function loadFigureEvidence(PDO $database, int $documentId, string $nodeTitle): array
{
    $statement = $database->prepare(
        "SELECT e.id, e.public_id, e.content, d.title AS document_title,
                n.id AS node_id, n.parent_id, n.title AS node_title,
                n.structural_path, n.source_reference
           FROM evidences e
           JOIN documents d ON d.id = e.document_id
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.document_id = :document_id AND n.title = :title"
    );
    $statement->execute(['document_id' => $documentId, 'title' => $nodeTitle]);
    $record = $statement->fetch();

    if (!is_array($record)) {
        throw new RuntimeException('A evidência solicitada da figura não foi persistida.');
    }

    return [
        'evidence' => new RetrievedEvidence(
            (int) $record['id'],
            (string) $record['public_id'],
            (string) $record['document_title'],
            (string) $record['node_title'],
            (string) $record['structural_path'],
            is_string($record['source_reference']) ? $record['source_reference'] : null,
            (string) $record['content']
        ),
        'record' => $record,
    ];
}

/** @return list<RetrievedEvidence> */
function loadDocumentEvidences(PDO $database, int $documentId): array
{
    $statement = $database->prepare(
        "SELECT e.id, e.public_id, e.content, d.title AS document_title,
                n.title AS node_title, n.structural_path, n.source_reference
           FROM evidences e
           JOIN documents d ON d.id = e.document_id
           JOIN document_nodes n ON n.id = e.node_id
          WHERE e.document_id = :document_id
       ORDER BY e.id"
    );
    $statement->execute(['document_id' => $documentId]);
    $evidences = [];

    foreach ($statement->fetchAll() as $record) {
        $evidences[] = new RetrievedEvidence(
            (int) $record['id'],
            (string) $record['public_id'],
            (string) $record['document_title'],
            (string) $record['node_title'],
            (string) $record['structural_path'],
            is_string($record['source_reference']) ? $record['source_reference'] : null,
            (string) $record['content']
        );
    }

    return $evidences;
}

$publicIndex = file_get_contents(dirname(__DIR__) . '/public/index.php');
assertResolvedFigure(
    is_string($publicIndex) && str_contains($publicIndex, "img-src 'self' data: blob: https:"),
    'A CSP pública não autoriza as URLs blob usadas pelas figuras autenticadas.'
);

try {
    $content = <<<'MD'
# Mecânica celeste

## Figura 4 — Movimento de translação

Arquivo: figuras/translacao-terra.png
Tipo: diagrama didático
Descrição factual: A Terra aparece em quatro posições ao redor do Sol.
Texto visível: março, junho, setembro e dezembro.
Relações representadas: as setas indicam movimento orbital no sentido anti-horário.

---
MD;
    $result = (new DocumentIngestionService(
        $database,
        $documentStorage,
        $container['ingestion']['max_document_bytes']
    ))->ingest('figure-resolver-' . bin2hex(random_bytes(6)) . '.md', $content, 'Mecânica celeste');
    $results[] = $result;

    $documentDirectory = $container['ingestion']['figure_storage']
        . DIRECTORY_SEPARATOR . $result->documentPublicId;

    if (!mkdir($documentDirectory, 0775, true) && !is_dir($documentDirectory)) {
        throw new RuntimeException('Não foi possível preparar o diretório documental da figura.');
    }

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    file_put_contents($documentDirectory . DIRECTORY_SEPARATOR . 'translacao-terra.png', $png);

    $loaded = loadFigureEvidence($database, $result->documentId, 'Figura 4 — Movimento de translação');
    $record = $loaded['record'];
    $evidence = $loaded['evidence'];
    assertResolvedFigure($evidence instanceof RetrievedEvidence, 'A evidência da figura não foi persistida.');
    $figures = (new FigureReferenceResolver($database, $figureStorage))->resolve([$evidence]);
    assertResolvedFigure(count($figures) === 1, 'A figura citada e existente não foi resolvida.');
    assertResolvedFigure($figures[0]['evidence_id'] === $evidence->publicId, 'A figura perdeu o vínculo com a evidência.');
    assertResolvedFigure(
        $figures[0]['url'] === 'documents/' . $result->documentPublicId . '/figures/translacao-terra.png',
        'A URL autenticada da figura está incorreta.'
    );

    $jsonContent = <<<'JSON'
{
  "Astronomy": {
    "Figure 5 — Orbital motion": {
      "File": "figures/translacao-terra.png",
      "Type": "educational diagram",
      "Factual description": "Earth appears in four positions around the Sun.",
      "Visible text": "March, June, September, and December.",
      "Represented relationships": "Arrows indicate counterclockwise orbital motion."
    }
  }
}
JSON;
    $jsonResult = (new DocumentIngestionService(
        $database,
        $documentStorage,
        $container['ingestion']['max_document_bytes']
    ))->ingest('figure-resolver-' . bin2hex(random_bytes(6)) . '.json', $jsonContent, 'Astronomy');
    $results[] = $jsonResult;
    $jsonDirectory = $container['ingestion']['figure_storage']
        . DIRECTORY_SEPARATOR . $jsonResult->documentPublicId;
    if (!mkdir($jsonDirectory, 0775, true) && !is_dir($jsonDirectory)) {
        throw new RuntimeException('Não foi possível preparar o diretório da figura JSON.');
    }
    file_put_contents($jsonDirectory . DIRECTORY_SEPARATOR . 'translacao-terra.png', $png);
    $jsonEvidence = loadFigureEvidence($database, $jsonResult->documentId, 'File')['evidence'];
    $jsonFigures = (new FigureReferenceResolver($database, $figureStorage))->resolve([$jsonEvidence]);
    assertResolvedFigure(count($jsonFigures) === 1, 'A figura estruturada em JSON não foi resolvida.');
    assertResolvedFigure(
        $jsonFigures[0]['title'] === 'Figure 5 — Orbital motion'
            && $jsonFigures[0]['description'] === 'Earth appears in four positions around the Sun.',
        'O contrato em inglês da figura JSON não foi recomposto.'
    );

    $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<documento>
  <astronomia>
    <figura titulo="Figura 6 — Movimento orbital">
      <arquivo>figuras/translacao-terra.png</arquivo>
      <tipo>diagrama didático</tipo>
      <descricao_factual>A Terra aparece em quatro posições ao redor do Sol.</descricao_factual>
      <texto_visivel>março, junho, setembro e dezembro.</texto_visivel>
      <relacoes_representadas>As setas indicam movimento orbital anti-horário.</relacoes_representadas>
    </figura>
  </astronomia>
</documento>
XML;
    $xmlResult = (new DocumentIngestionService(
        $database,
        $documentStorage,
        $container['ingestion']['max_document_bytes']
    ))->ingest('figure-resolver-' . bin2hex(random_bytes(6)) . '.xml', $xmlContent, 'Astronomia');
    $results[] = $xmlResult;
    $xmlDirectory = $container['ingestion']['figure_storage']
        . DIRECTORY_SEPARATOR . $xmlResult->documentPublicId;
    if (!mkdir($xmlDirectory, 0775, true) && !is_dir($xmlDirectory)) {
        throw new RuntimeException('Não foi possível preparar o diretório da figura XML.');
    }
    file_put_contents($xmlDirectory . DIRECTORY_SEPARATOR . 'translacao-terra.png', $png);
    $xmlEvidence = loadFigureEvidence($database, $xmlResult->documentId, 'arquivo')['evidence'];
    $xmlFigures = (new FigureReferenceResolver($database, $figureStorage))->resolve([$xmlEvidence]);
    assertResolvedFigure(count($xmlFigures) === 1, 'A figura estruturada em XML não foi resolvida.');
    assertResolvedFigure(
        $xmlFigures[0]['title'] === 'Figura 6 — Movimento orbital'
            && $xmlFigures[0]['visible_text'] === 'março, junho, setembro e dezembro.',
        'O contrato em português da figura XML não foi recomposto.'
    );

    $templatePaths = glob(
        dirname(__DIR__) . '/docs/examples/figure-contracts/{pt-BR,en}/figure.{md,json,xml}',
        GLOB_BRACE
    );
    assertResolvedFigure(count($templatePaths ?: []) === 6, 'Os seis templates de figura não foram encontrados.');

    foreach ($templatePaths ?: [] as $templatePath) {
        $templateContent = file_get_contents($templatePath);

        if (!is_string($templateContent)) {
            throw new RuntimeException('Não foi possível ler o template ' . $templatePath . '.');
        }

        $templateResult = (new DocumentIngestionService(
            $database,
            $documentStorage,
            $container['ingestion']['max_document_bytes']
        ))->ingest(
            'template-' . bin2hex(random_bytes(6)) . '.' . pathinfo($templatePath, PATHINFO_EXTENSION),
            $templateContent,
            basename(dirname($templatePath)) . ' ' . basename($templatePath)
        );
        $results[] = $templateResult;
        $templateDirectory = $container['ingestion']['figure_storage']
            . DIRECTORY_SEPARATOR . $templateResult->documentPublicId;

        if (!mkdir($templateDirectory, 0775, true) && !is_dir($templateDirectory)) {
            throw new RuntimeException('Não foi possível preparar o diretório da figura de template.');
        }

        file_put_contents($templateDirectory . DIRECTORY_SEPARATOR . 'profile-1.jpg', base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABD/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/EP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8Q/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=',
            true
        ));
        $templateFigures = (new FigureReferenceResolver($database, $figureStorage))->resolve(
            loadDocumentEvidences($database, $templateResult->documentId)
        );
        assertResolvedFigure(
            count($templateFigures) === 1,
            'O template não resolveu exatamente uma figura: ' . $templatePath
        );
    }

    $adminToken = bin2hex(random_bytes(24));
    putenv($adminTokenEnvironment . '=' . $adminToken);
    $_ENV[$adminTokenEnvironment] = $adminToken;
    $api = new ProductApi(
        $database,
        $container,
        new FileLogger($container['logging']['path']),
        bin2hex(random_bytes(8))
    );
    $server = [
        'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
        'REMOTE_ADDR' => '198.51.100.90',
    ];
    $response = $api->handle(
        'GET',
        '/api/' . $figures[0]['url'],
        $server,
        [],
        [],
        ''
    );
    assertResolvedFigure($response->status === 200, 'A rota autenticada não entregou a figura.');
    assertResolvedFigure($response->headers['Content-Type'] === 'image/png', 'A rota não preservou o MIME validado.');
    assertResolvedFigure($response->body === $png, 'A rota não entregou os bytes originais da figura.');

    $unauthenticated = $api->handle('GET', '/api/' . $figures[0]['url'], [], [], [], '');
    assertResolvedFigure($unauthenticated->status === 401, 'A figura pôde ser acessada sem autenticação.');

    $statement = $database->prepare('UPDATE document_nodes SET parent_id = NULL WHERE id = :id');
    $statement->execute(['id' => (int) $record['node_id']]);
    assertResolvedFigure(
        (new FigureReferenceResolver($database, $figureStorage))->resolve([$evidence]) === [],
        'Uma figura sem pai temático permaneceu no resultado da consulta.'
    );
    $statement = $database->prepare('UPDATE document_nodes SET parent_id = :parent_id WHERE id = :id');
    $statement->execute([
        'parent_id' => (int) $record['parent_id'],
        'id' => (int) $record['node_id'],
    ]);

    unlink($documentDirectory . DIRECTORY_SEPARATOR . 'translacao-terra.png');
    assertResolvedFigure(
        (new FigureReferenceResolver($database, $figureStorage))->resolve([$evidence]) === [],
        'Uma figura ausente permaneceu no resultado da consulta.'
    );
} finally {
    foreach ($results as $storedResult) {
        $statement = $database->prepare('DELETE FROM documents WHERE id = :id');
        $statement->execute(['id' => $storedResult->documentId]);

        try {
            $documentStorage->remove($storedResult->storagePath);
        } catch (Throwable) {
        }

        try {
            $figureStorage->removeDocument($storedResult->documentPublicId);
        } catch (Throwable) {
        }
    }

    if ($originalAdminToken === null) {
        putenv($adminTokenEnvironment);
        unset($_ENV[$adminTokenEnvironment]);
    } else {
        putenv($adminTokenEnvironment . '=' . $originalAdminToken);
        $_ENV[$adminTokenEnvironment] = $originalAdminToken;
    }
}

echo sprintf("Resolução de figuras validada com %d asserções.\n", $assertions);
