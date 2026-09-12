<?php

require_once __DIR__ . '/src/bootstrap.php';

require_api_user();

try {

    $sql = "
        SELECT
            s.id,
            s.name,
            s.sort_order,
            s.website_warning_limit,
            s.website_hard_limit,
            s.last_status,
            s.last_error,
            s.last_checked_at,
            s.last_online_at,

            m.accounts,
            m.active_accounts,
            m.suspended_accounts,
            m.load_1m,
            m.ram_percent,
            m.disk_percent,
            m.backup_percent,
            m.uptime_seconds,
            m.apache_status,
            m.mysql_status,
            m.dns_status,
            m.exim_status

        FROM servers s

        LEFT JOIN server_metrics m
            ON m.id = (
                SELECT MAX(m2.id)
                FROM server_metrics m2
                WHERE m2.server_id = s.id
            )

        WHERE s.enabled = 1

        ORDER BY
            s.sort_order ASC,
            s.id ASC
    ";

    $stmt = $pdo->query($sql);

    $servers = $stmt->fetchAll();

    foreach ($servers as &$s) {

        $s['id'] = (int)($s['id'] ?? 0);

        $s['sort_order'] = (int)($s['sort_order'] ?? 0);

        $s['website_warning_limit'] =
            (int)($s['website_warning_limit'] ?? 0);

        $s['website_hard_limit'] =
            (int)($s['website_hard_limit'] ?? 0);

        $s['accounts'] =
            (int)($s['accounts'] ?? 0);

        $s['active_accounts'] =
            (int)($s['active_accounts'] ?? 0);

        $s['suspended_accounts'] =
            (int)($s['suspended_accounts'] ?? 0);

        $s['limit_reached'] =
            $s['website_hard_limit'] > 0 &&
            $s['accounts'] >= $s['website_hard_limit'];

        $s['warning_reached'] =
            $s['website_warning_limit'] > 0 &&
            $s['accounts'] >= $s['website_warning_limit'];
    }

    unset($s);

    json_response([
        'status' => 'ok',
        'servers' => $servers
    ]);

} catch (Throwable $e) {

    json_response([
        'status' => 'error',
        'message' => 'Servers query failed',
        'error' => $e->getMessage()
    ], 500);
}