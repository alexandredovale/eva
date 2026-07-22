<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

use JsonException;

final class StructuredEmbeddingTextBuilder
{
    /**
     * @param array{
     *   public_id: string,
     *   evidence_class: string,
     *   evidence_type: string,
     *   content: string,
     *   summary: ?string,
     *   document_title: string,
     *   node_type: string,
     *   node_title: string,
     *   structural_path: string,
     *   source_reference: ?string
     * } $record
     */
    public function build(array $record): StructuredEmbeddingUnit
    {
        $organizedContent = is_string($record['summary']) && trim($record['summary']) !== ''
            ? $record['summary']
            : $record['content'];

        if (trim($organizedContent) === '') {
            throw new CognitiveBuildException('A evidência não possui conteúdo semântico organizado.');
        }

        $context = [
            'document' => $record['document_title'],
            'structural_path' => $record['structural_path'],
            'node' => [
                'type' => $record['node_type'],
                'title' => $record['node_title'],
                'source_reference' => $record['source_reference'],
            ],
            'evidence' => [
                'class' => $record['evidence_class'],
                'type' => $record['evidence_type'],
                'organized_content' => $organizedContent,
            ],
        ];

        try {
            $text = json_encode(
                $context,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
        } catch (JsonException $exception) {
            throw new CognitiveBuildException('Não foi possível organizar a unidade de embedding.', 0, $exception);
        }

        return new StructuredEmbeddingUnit($record['public_id'], $text);
    }
}