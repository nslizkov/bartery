<?php

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'name' => getenv('DB_NAME') ?: 'skills_exchange',
        'user' => getenv('DB_USER') ?: 'skills_user',
        'pass' => getenv('DB_PASS') ?: 'skills_pass',
        'charset' => 'utf8mb4',
    ],
];
