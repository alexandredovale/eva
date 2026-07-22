<?php

declare(strict_types=1);

namespace Eva\Application\Cognitive;

interface SummaryProviderInterface
{
    public function model(): string;

    public function summarize(StructuredSummaryUnit $unit): SummaryResult;
}