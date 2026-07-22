<?php

declare(strict_types=1);

use Eva\Support\Env;

return [
    'max_failures' => max(1, min(10, (int) Env::get('QUEUE_MAX_FAILURES', '3'))),
    'worker_id' => Env::get('QUEUE_WORKER_ID', 'local-worker'),
];
