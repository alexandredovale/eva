<?php

declare(strict_types=1);

namespace Eva\Http\Upload;

final readonly class ValidatedUpload
{
    public function __construct(
        public string $originalName,
        public string $title,
        public string $content,
        public int $size
    ) {
    }
}

