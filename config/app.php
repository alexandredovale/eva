<?php

declare(strict_types=1);

use Eva\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'Cnode EVA'),
    'environment' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => Env::get('APP_URL', ''),
];

