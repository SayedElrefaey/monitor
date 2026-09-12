<?php

require_once __DIR__ . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'status' => 'method_not_allowed',
        'message' => 'POST required'
    ], 405);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);

if (!is_array($input)) {
    json_response([
        'status' => 'invalid_request',
        'message' => 'Invalid JSON'
    ], 422);
}

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    json_response([
        'status' => 'invalid_request',
        'message' => 'Username and password are required'
    ], 422);
}

$stmt = $pdo->prepare("
    SELECT id, username, password_hash
    FROM users
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$username]);

$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    json_response([
        'status' => 'unauthorized',
        'message' => 'Invalid username or password'
    ], 401);
}

$token = issue_token(
    (int)$user['id'],
    (int)($config['api']['token_ttl'] ?? 86400)
);

json_response([
    'status' => 'ok',
    'token' => $token,
    'user' => [
        'id' => (int)$user['id'],
        'username' => $user['username']
    ]
]);