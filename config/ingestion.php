<?php

declare(strict_types=1);

use Eva\Support\Env;

return [
    'max_document_bytes' => (int) Env::get('DOCUMENT_MAX_BYTES', '10485760'),
    'document_storage' => dirname(__DIR__) . '/storage/documents',
];

