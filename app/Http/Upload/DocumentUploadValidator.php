<?php

declare(strict_types=1);

namespace Eva\Http\Upload;

use Eva\Application\Ingestion\Parser\ParserFactory;

final class DocumentUploadValidator
{
    public function __construct(private readonly int $maxDocumentBytes)
    {
        if ($this->maxDocumentBytes < 1) {
            throw new UploadException('O limite de upload está configurado incorretamente.', 500);
        }
    }

    /**
     * @param array<string, mixed>|null $file
     */
    public function validate(?array $file, mixed $title): ValidatedUpload
    {
        if ($file === null) {
            throw new UploadException('Envie o documento no campo document.', 400);
        }

        foreach (['name', 'tmp_name', 'error', 'size'] as $field) {
            if (!array_key_exists($field, $file) || is_array($file[$field])) {
                throw new UploadException('A estrutura do upload é inválida.', 400);
            }
        }

        $error = (int) $file['error'];

        if ($error !== UPLOAD_ERR_OK) {
            throw $this->uploadError($error);
        }

        $originalName = (string) $file['name'];
        $temporaryPath = (string) $file['tmp_name'];
        $reportedSize = (int) $file['size'];

        if ($reportedSize < 1) {
            throw new UploadException('O documento enviado está vazio.');
        }

        if ($reportedSize > $this->maxDocumentBytes) {
            throw new UploadException('O documento excede o limite permitido.', 413);
        }

        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new UploadException('O arquivo não foi recebido por um upload HTTP válido.', 400);
        }

        $actualSize = filesize($temporaryPath);

        if ($actualSize === false || $actualSize < 1) {
            throw new UploadException('Não foi possível validar o tamanho do documento.');
        }

        if ($actualSize > $this->maxDocumentBytes) {
            throw new UploadException('O documento excede o limite permitido.', 413);
        }

        ParserFactory::forFilename($originalName);
        $content = file_get_contents($temporaryPath);

        if ($content === false || strlen($content) !== $actualSize) {
            throw new UploadException('Não foi possível ler integralmente o documento enviado.');
        }

        return new ValidatedUpload(
            originalName: $originalName,
            title: $this->resolveTitle($title, $originalName),
            content: $content,
            size: $actualSize
        );
    }

    private function resolveTitle(mixed $title, string $originalName): string
    {
        if ($title !== null && !is_string($title)) {
            throw new UploadException('O título do documento é inválido.');
        }

        $title = trim((string) $title);

        if ($title === '') {
            $title = pathinfo(str_replace('\\', '/', $originalName), PATHINFO_FILENAME);
            $title = preg_replace('/[_-]+/u', ' ', $title) ?? $title;
            $title = trim($title);
        }

        if ($title === '' || preg_match('//u', $title) !== 1) {
            throw new UploadException('O título do documento é inválido.');
        }

        if (mb_strlen($title, 'UTF-8') > 255) {
            throw new UploadException('O título do documento excede 255 caracteres.');
        }

        return $title;
    }

    private function uploadError(int $error): UploadException
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new UploadException(
                'O documento excede o limite permitido.',
                413
            ),
            UPLOAD_ERR_PARTIAL => new UploadException('O upload do documento ficou incompleto.', 400),
            UPLOAD_ERR_NO_FILE => new UploadException('Nenhum documento foi enviado.', 400),
            default => new UploadException('O servidor não conseguiu receber o documento.', 500),
        };
    }
}

