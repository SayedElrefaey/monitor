<?php
require_once __DIR__ . '/../src/bootstrap.php';

require_api_user();

try {
    $summary = [];

    $summary['servers'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM servers WHERE enabled=1"
    )->fetchColumn();

    $summary['online'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM servers WHERE enabled=1 AND last_status='online'"
    )->fetchColumn();

    $summary['offline'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM servers WHERE enabled=1 AND last_status='offline'"
    )->fetchColumn();

    $summary['websites'] = (int)$pdo->query(
        "SELECT COALESCE(SUM(accounts),0)
         FROM (
             SELECT m.accounts
             FROM server_metrics m
             JOIN (
                 SELECT server_id, MAX(id) id
                 FROM server_metrics
                 GROUP BY server_id
             ) x ON x.id = m.id
             WHERE m.status='online'
         ) t"
    )->fetchColumn();

    json_response([
        'status' => 'ok',
        'summary' => $summary
    ]);
} catch (Throwable $e) {
    json_response([
        'status' => 'error',
        'message' => 'Dashboard query failed'
    ], 500);
}
