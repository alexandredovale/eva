<?php

declare(strict_types=1);

namespace Eva\Http\Upload;

use Eva\Application\Ingestion\DocumentIngestionService;
use Eva\Application\Ingestion\IngestionException;
use Eva\Application\Ingestion\Parser\ParserException;
use Eva\Infrastructure\Logging\FileLogger;
use Eva\Infrastructure\Logging\SafeFailureDiagnostics;
use Throwable;

final class DocumentUploadHandler
{
    public function __construct(
        private readonly DocumentUploadValidator $validator,
        private readonly DocumentIngestionService $ingestion,
        private readonly FileLogger $logger
    ) {
    }

    /**
     * @param array<string, mixed> $files
     * @param array<string, mixed> $post
     * @return array{status: int, payload: array<string, mixed>}
     */
    public function handle(array $files, array $post): array
    {
        try {
            $file = isset($files['document']) && is_array($files['document'])
                ? $files['document']
                : null;
            $upload = $this->validator->validate($file, $post['title'] ?? null);
            $result = $this->ingestion->ingest(
                $upload->originalName,
                $upload->content,
                $upload->title
            );

            $this->logger->info('document_ingested', [
                'document_public_id' => $result->documentPublicId,
                'format' => $result->format,
                'source_bytes' => $upload->size,
                'nodes' => $result->nodeCount,
                'primary_evidences' => $result->primaryEvidenceCount,
            ]);

            return [
                'status' => 201,
                'payload' => [
                    'message' => 'Documento recebido e persistido.',
                    'document' => $result->toArray(),
                ],
            ];
        } catch (UploadException $exception) {
            $this->logger->warning('document_upload_rejected', [
                'reason' => $exception->getMessage(),
                'http_status' => $exception->httpStatus,
            ]);

            return [
                'status' => $exception->httpStatus,
                'payload' => ['error' => $exception->getMessage()],
            ];
        } catch (ParserException $exception) {
            $this->logger->warning('document_parse_rejected', SafeFailureDiagnostics::context($exception));

            return [
                'status' => 422,
                'payload' => ['error' => 'O conteúdo não corresponde a um documento estruturado válido.'],
            ];
        } catch (IngestionException $exception) {
            $this->logger->error('document_ingestion_failed', SafeFailureDiagnostics::context($exception));

            return [
                'status' => 500,
                'payload' => ['error' => 'Não foi possível persistir o documento.'],
            ];
        } catch (Throwable $exception) {
            $this->logger->error('document_upload_failed', SafeFailureDiagnostics::context($exception));

            return [
                'status' => 500,
                'payload' => ['error' => 'Erro interno ao processar o documento.'],
            ];
        }
    }
}
