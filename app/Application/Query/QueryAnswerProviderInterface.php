<?php

declare(strict_types=1);

namespace Eva\Application\Query;

interface QueryAnswerProviderInterface
{
    public function model(): string;

    /** @param array{code: string, evidence_id?: string} $validationFeedback */
    public function answer(
        string $input,
        QueryContext $context,
        array $validationFeedback
    ): GeneratedAnswer;
}
