<?php

$configFile = __DIR__ . '/../config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'config.php not found'
    ]);

    exit;
}

$config = require $configFile;

date_default_timezone_set(
    $config['app']['timezone'] ?? 'Africa/Cairo'
);

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $config['db']['host'],
    $config['db']['name'],
    $config['db']['charset'] ?? 'utf8mb4'
);

try {

    $pdo = new PDO(
        $dsn,
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (Throwable $e) {

    http_response_code(500);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'database_error',
        'message' => 'Database connection failed'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Base64 URL
|--------------------------------------------------------------------------
*/

function b64url_encode(string $value): string
{
    return rtrim(
        strtr(
            base64_encode($value),
            '+/',
            '-_'
        ),
        '='
    );
}

function b64url_decode(string $value): string|false
{
    $padding = (4 - strlen($value) % 4) % 4;

    return base64_decode(
        strtr($value, '-_', '+/') .
        str_repeat('=', $padding),
        true
    );
}

/*
|--------------------------------------------------------------------------
| JWT Secret
|--------------------------------------------------------------------------
*/

function jwt_secret(): string
{
    global $config;

    $secret = trim(
        (string)($config['api']['jwt_secret'] ?? '')
    );

    if ($secret === '') {
        json_response([
            'status' => 'configuration_error',
            'message' => 'jwt_secret is missing'
        ], 500);
    }

    return $secret;
}

/*
|--------------------------------------------------------------------------
| Create Token
|--------------------------------------------------------------------------
*/

function issue_token(int $userId, int $ttl = 86400): string
{
    $header = b64url_encode(
        json_encode([
            'alg' => 'HS256',
            'typ' => 'JWT'
        ], JSON_UNESCAPED_SLASHES)
    );

    $payload = b64url_encode(
        json_encode([
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $ttl
        ], JSON_UNESCAPED_SLASHES)
    );

    $signature = b64url_encode(
        hash_hmac(
            'sha256',
            $header . '.' . $payload,
            jwt_secret(),
            true
        )
    );

    return $header . '.' . $payload . '.' . $signature;
}

/*
|--------------------------------------------------------------------------
| Bearer Token
|--------------------------------------------------------------------------
*/

function get_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (
        preg_match(
            '/^Bearer\s+(.+)$/i',
            trim($header),
            $matches
        )
    ) {
        return trim($matches[1]);
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Require API User
|--------------------------------------------------------------------------
*/

function require_api_user(): int
{
    $token = get_bearer_token();

    if (!$token) {
        json_response([
            'status' => 'unauthorized'
        ], 401);
    }

    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        json_response([
            'status' => 'unauthorized'
        ], 401);
    }

    [$header, $payload, $signature] = $parts;

    $expectedSignature = b64url_encode(
        hash_hmac(
            'sha256',
            $header . '.' . $payload,
            jwt_secret(),
            true
        )
    );

    if (
        !hash_equals(
            $expectedSignature,
            $signature
        )
    ) {
        json_response([
            'status' => 'unauthorized'
        ], 401);
    }

    $decoded = b64url_decode($payload);

    if ($decoded === false) {
        json_response([
            'status' => 'unauthorized'
        ], 401);
    }

    $data = json_decode(
        $decoded,
        true
    );

    if (
        !is_array($data) ||
        empty($data['sub']) ||
        empty($data['exp']) ||
        (int)$data['exp'] < time()
    ) {
        json_response([
            'status' => 'unauthorized'
        ], 401);
    }

    return (int)$data['sub'];
}