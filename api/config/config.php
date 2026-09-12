<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'monitoroxserver_mnv2',
        'user' => 'monitoroxserver_mnuser',
        'pass' => 'Fer;$#s^;q2gj4Q6',
        'charset' => 'utf8mb4',
    ],
    'api' => [
        // php -r "echo bin2hex(random_bytes(32)),PHP_EOL;"
        'jwt_secret' => 'bfa25cf3697e25c740fe88353e5049360c76ab160d355736ce57b260f2f415c9',
        'token_ttl' => 86400,
    ],
];
