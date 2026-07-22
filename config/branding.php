<?php

declare(strict_types=1);

use Eva\Support\Env;

return [
    'name' => Env::get('BRAND_NAME', 'EVA'),
    'tagline' => Env::get('BRAND_TAGLINE', 'Verifiable documentary cognitive memory'),
    'primary_color' => Env::get('BRAND_PRIMARY_COLOR', '#151515'),
    'secondary_color' => Env::get('BRAND_SECONDARY_COLOR', '#090909'),
    'accent_color' => Env::get('BRAND_ACCENT_COLOR', '#dfff00'),
    'logo_url' => Env::get('BRAND_LOGO_URL', ''),
];
