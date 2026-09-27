<?php

$configFile = __DIR__ . '/../config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'message' => 'config.php not found'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

$config = require $configFile;

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";

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
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    exit;
}

date_default_timezone_set($config['app']['timezone'] ?? 'Africa/Cairo');

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        http_response_code(500);
        $json = '{"status":"error","message":"Failed to encode JSON response"}';
    }

    echo $json;
    exit;
}

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

function jwt_secret(): string
{
    global $config;

    $secret = trim((string)($config['api']['jwt_secret'] ?? ''));

    if ($secret === '') {
        json_response([
            'status' => 'configuration_error',
            'message' => 'jwt_secret is missing'
        ], 500);
    }

    return $secret;
}

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

function get_bearer_token(): ?string
{
    $header = '';

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if ($header === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();

        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $header = $value;
                break;
            }
        }
    }

    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function require_api_user(): int
{
    $token = get_bearer_token();

    if (!$token) {
        json_response(['status' => 'unauthorized'], 401);
    }

    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        json_response(['status' => 'unauthorized'], 401);
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

    if (!hash_equals($expectedSignature, $signature)) {
        json_response(['status' => 'unauthorized'], 401);
    }

    $decoded = b64url_decode($payload);

    if ($decoded === false) {
        json_response(['status' => 'unauthorized'], 401);
    }

    $data = json_decode($decoded, true);

    if (
        !is_array($data) ||
        empty($data['sub']) ||
        empty($data['exp']) ||
        (int)$data['exp'] < time()
    ) {
        json_response(['status' => 'unauthorized'], 401);
    }

    return (int)$data['sub'];
}
