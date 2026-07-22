<?php

declare(strict_types=1);

namespace Eva\Application\Ingestion\Parser;

use Eva\Domain\Document\NormalizedDocument;

interface DocumentParserInterface
{
    public function format(): string;

    public function parse(string $content, string $documentTitle): NormalizedDocument;
}

