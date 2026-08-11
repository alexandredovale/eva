<?php

declare(strict_types=1);

namespace Eva\Application\Query;

final readonly class FigureContract
{
    public function __construct(
        public string $title,
        public string $declaredPath,
        public ?string $type,
        public ?string $description,
        public ?string $visibleText,
        public ?string $representedRelationships
    ) {
    }
}
