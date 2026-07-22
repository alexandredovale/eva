<?php

declare(strict_types=1);

namespace Eva\Infrastructure\Ai;

use Eva\Application\Cognitive\EmbeddingBatchResult;
use Eva\Application\Cognitive\EmbeddingProviderInterface;
use Eva\Application\Cognitive\EmbeddingVector;
use Eva\Application\Cognitive\StructuredEmbeddingUnit;

final class EmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        private readonly JsonHttpClientInterface $http,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $endpoint,
        private readonly int $timeoutSeconds = 30,
        private readonly int $maxUnitsPerRequest = 64
    ) {
        if (trim($this->apiKey) === '') {
            throw new AiProviderException('A credencial do provedor de embeddings não está configurada.');
        }

        if (trim($this->model) === '' || filter_var($this->endpoint, FILTER_VALIDATE_URL) === false
            || $this->timeoutSeconds < 1 || $this->maxUnitsPerRequest < 1) {
            throw new AiProviderException('A configuração do provedor de embeddings é inválida.');
        }
    }

    public function model(): string
    {
        return $this->model;
    }

    public function embed(array $units): EmbeddingBatchResult
    {
        if ($units === []) {
            return new EmbeddingBatchResult([]);
        }

        $seen = [];
        $inputs = [];

        foreach ($units as $unit) {
            if (!$unit instanceof StructuredEmbeddingUnit) {
                throw new AiProviderException('O lote contém uma unidade de embedding inválida.');
            }

            if (isset($seen[$unit->evidencePublicId])) {
                throw new AiProviderException('O lote contém evidências duplicadas.');
            }

            $seen[$unit->evidencePublicId] = true;
            $inputs[] = $unit->text;
        }

        if (count($units) > $this->maxUnitsPerRequest) {
            $vectors = [];
            $inputTokens = 0;

            foreach (array_chunk($units, $this->maxUnitsPerRequest) as $batchUnits) {
                $batch = $this->embed($batchUnits);
                array_push($vectors, ...$batch->vectors);
                $inputTokens += $batch->inputTokens;
            }

            return new EmbeddingBatchResult($vectors, $inputTokens);
        }

        $response = $this->http->post(
            $this->endpoint,
            ['Authorization: Bearer ' . $this->apiKey],
            [
                'model' => $this->model,
                'input' => $inputs,
                'encoding_format' => 'float',
            ],
            $this->timeoutSeconds
        );

        $data = $response['data'] ?? null;

        if (!is_array($data) || count($data) !== count($units)) {
            throw new AiProviderException('O provedor de embeddings retornou uma quantidade inesperada de vetores.');
        }

        usort($data, static fn (mixed $left, mixed $right): int => ($left['index'] ?? -1) <=> ($right['index'] ?? -1));
        $model = is_string($response['model'] ?? null) ? $response['model'] : $this->model;
        $vectors = [];
        $dimensions = null;

        foreach ($units as $index => $unit) {
            $item = $data[$index] ?? null;

            if (!is_array($item) || ($item['index'] ?? null) !== $index || !is_array($item['embedding'] ?? null)) {
                throw new AiProviderException('O provedor de embeddings retornou um vetor sem correspondência estrutural.');
            }

            $vector = [];

            foreach ($item['embedding'] as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new AiProviderException('O provedor de embeddings retornou um componente vetorial inválido.');
                }

                $vector[] = (float) $value;
            }

            if ($vector === [] || ($dimensions !== null && count($vector) !== $dimensions)) {
                throw new AiProviderException('O provedor de embeddings retornou vetores com dimensões inválidas.');
            }

            $dimensions ??= count($vector);
            $vectors[] = new EmbeddingVector(
                evidencePublicId: $unit->evidencePublicId,
                model: $model,
                vector: $vector,
                contentHash: $unit->contentHash
            );
        }

        $inputTokens = $response['usage']['prompt_tokens'] ?? $response['usage']['total_tokens'] ?? 0;

        return new EmbeddingBatchResult($vectors, is_int($inputTokens) ? $inputTokens : 0);
    }
}
