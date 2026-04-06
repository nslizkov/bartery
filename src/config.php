<?php

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'name' => getenv('DB_NAME') ?: 'skills_exchange',
        'user' => getenv('DB_USER') ?: 'skills_user',
        'pass' => getenv('DB_PASS') ?: 'skills_pass',
        'charset' => 'utf8mb4',
    ],
    'fcm' => [
        // Service account key file is used directly: src/bartery-1-firebase-adminsdk-fbsvc-20493bcfca.json
        'project_id' => 'bartery-1',
    ],
    'app' => [
        'url' => getenv('APP_URL') ?: 'http://localhost:8080',
    ],
];
