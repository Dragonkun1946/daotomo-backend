<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'uploads/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'https://daotomo.site,https://www.daotomo.site,http://localhost:8000,http://127.0.0.1:8000,http://localhost:5500,http://127.0.0.1:5500'
        ))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
