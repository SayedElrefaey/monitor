<?php
require_once __DIR__ . '/../src/bootstrap.php';

require_api_user();

try {
    $rows = $pdo->query(
        "SELECT
            a.server_id,
            s.name AS server_name,
            a.alert_type,
            a.state,
            a.last_sent_at
         FROM alert_states a
         JOIN servers s ON s.id = a.server_id
         WHERE a.state='active'
         ORDER BY a.last_sent_at DESC
         LIMIT 100"
    )->fetchAll();

    json_response([
        'status' => 'ok',
        'alerts' => $rows
    ]);
} catch (Throwable $e) {
    json_response([
        'status' => 'error',
        'message' => 'Alerts query failed'
    ], 500);
}
