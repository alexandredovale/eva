<?php

declare(strict_types=1);

namespace Eva\Application\Query;

interface QueryAnswerProviderInterface
{
    public function model(): string;

    public function answer(string $input, QueryContext $context): GeneratedAnswer;
}
