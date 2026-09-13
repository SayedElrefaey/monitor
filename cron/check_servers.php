<?php

require_once __DIR__ . '/../src/bootstrap.php';

date_default_timezone_set('Africa/Cairo');

function app_now(): string
{
    return (new DateTime('now', new DateTimeZone('Africa/Cairo')))
        ->format('Y-m-d H:i:s');
}

function alert_once($id, $type, $msg, $state = 'active')
{
    global $pdo;

    $st = $pdo->prepare("
        SELECT *
        FROM alert_states
        WHERE server_id = ?
          AND alert_type = ?
    ");

    $st->execute([$id, $type]);
    $r = $st->fetch();

    $now = app_now();

    if (!$r) {
        $pdo->prepare("
            INSERT INTO alert_states
                (server_id, alert_type, state, last_sent_at)
            VALUES
                (?, ?, ?, ?)
        ")->execute([
            $id,
            $type,
            $state,
            $now
        ]);

        telegram_send($msg, $type);
        return;
    }

    if ($r['state'] !== $state) {
        $pdo->prepare("
            UPDATE alert_states
            SET state = ?,
                last_sent_at = ?
            WHERE id = ?
        ")->execute([
            $state,
            $now,
            $r['id']
        ]);

        telegram_send($msg, $type);
    }
}

function clear_alert($id, $type)
{
    global $pdo;

    $pdo->prepare("
        UPDATE alert_states
        SET state = 'normal'
        WHERE server_id = ?
          AND alert_type = ?
    ")->execute([
        $id,
        $type
    ]);
}

function get_agent($url, $token, $timeout)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'X-Monitor-Token: ' . $token
        ]
    ]);

    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        return [false, null, $err ?: 'HTTP ' . $code];
    }

    $d = json_decode($body, true);

    if (!is_array($d) || empty($d['status'])) {
        return [false, null, 'Invalid Agent response'];
    }

    if ($d['status'] !== 'online') {
        return [false, null, $d['error'] ?? 'Agent error'];
    }

    return [true, $d, null];
}

function svc_alerts($id, $name, $services)
{
    foreach (['apache', 'mysql', 'dns', 'exim'] as $svc) {
        $v = strtolower(trim((string)($services[$svc] ?? 'unknown')));
        $healthy = in_array($v, ['active', 'enabled'], true);
        $type = 'service_' . $svc;

        if (!$healthy) {
            alert_once(
                $id,
                $type,
                "🔴 SERVICE DOWN\n" .
                "Server: {$name}\n" .
                "Service: " . strtoupper($svc) . "\n" .
                "Status: {$v}"
            );
        } else {
            clear_alert($id, $type);
        }
    }
}

$servers = $pdo->query("
    SELECT *
    FROM servers
    WHERE enabled = 1
    ORDER BY id
")->fetchAll();

foreach ($servers as $s) {
    $prev = $s['last_status'];

    try {
        $agentToken = decrypt_secret($s['agent_token_enc']);
    } catch (Throwable $e) {
        continue;
    }

    [$ok, $d, $err] = get_agent(
        $s['agent_url'],
        $agentToken,
        $config['monitor']['request_timeout']
    );

    if (!$ok) {
        $st = $pdo->prepare("
            UPDATE servers
            SET consecutive_failures = consecutive_failures + 1,
                last_error = ?,
                last_checked_at = ?
            WHERE id = ?
        ");

        $st->execute([
            $err,
            app_now(),
            $s['id']
        ]);

        $st = $pdo->prepare("
            SELECT consecutive_failures, last_status
            FROM servers
            WHERE id = ?
            LIMIT 1
        ");

        $st->execute([$s['id']]);
        $state = $st->fetch();

        $failures = (int)($state['consecutive_failures'] ?? 0);

        $pdo->prepare("
            INSERT INTO server_metrics
            (server_id,status,error_message)
            VALUES(?,?,?)
        ")->execute([
            $s['id'],
            'check_failed',
            $err
        ]);

        if ($failures >= (int)$config['monitor']['offline_after_failures']) {
            $pdo->prepare("
                UPDATE servers
                SET last_status='offline'
                WHERE id=?
            ")->execute([$s['id']]);

            alert_once(
                $s['id'],
                'offline',
                "🔴 SERVER OFFLINE\n" .
                "Server: {$s['name']}\n" .
                "Failed checks: {$failures}\n" .
                "Last error: {$err}"
            );
        }

        continue;
    }

    $a    = (int)($d['accounts'] ?? 0);
    $act  = (int)($d['active_accounts'] ?? 0);
    $sus  = (int)($d['suspended_accounts'] ?? 0);
    $load = (float)($d['load_1m'] ?? 0);
    $ram  = isset($d['ram_percent']) ? (float)$d['ram_percent'] : null;
    $disk = isset($d['disk_percent']) ? (float)$d['disk_percent'] : null;
    $backup = isset($d['backup_percent']) ? (float)$d['backup_percent'] : null;
    $up = (int)($d['uptime_seconds'] ?? 0);
    $sv = $d['services'] ?? [];
    $now = app_now();

    $pdo->prepare("
        UPDATE servers
        SET last_status='online',
            consecutive_failures=0,
            last_error=NULL,
            last_checked_at=?,
            last_online_at=?
        WHERE id=?
    ")->execute([
        $now,
        $now,
        $s['id']
    ]);

    $pdo->prepare("
        INSERT INTO server_metrics
        (
            server_id,
            status,
            accounts,
            active_accounts,
            suspended_accounts,
            load_1m,
            ram_percent,
            disk_percent,
            backup_percent,
            uptime_seconds,
            apache_status,
            mysql_status,
            dns_status,
            exim_status,
            checked_at
        )
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $s['id'],
        'online',
        $a,
        $act,
        $sus,
        $load,
        $ram,
        $disk,
        $backup,
        $up,
        $sv['apache'] ?? 'unknown',
        $sv['mysql'] ?? 'unknown',
        $sv['dns'] ?? 'unknown',
        $sv['exim'] ?? 'unknown',
        $now
    ]);

    clear_alert($s['id'], 'offline');

    if ($prev === 'offline') {
        telegram_send(
            "🟢 SERVER ONLINE\n" .
            "Server: {$s['name']}\n" .
            "Sites: {$a}",
            'online'
        );
    }

    if (
        $s['website_warning_limit'] > 0 &&
        $a >= $s['website_warning_limit'] &&
        (
            $s['website_hard_limit'] == 0 ||
            $a <= $s['website_hard_limit']
        )
    ) {
        alert_once(
            $s['id'],
            'sites_warning',
            "🟡 WEBSITE WARNING\n" .
            "Server: {$s['name']}\n" .
            "Sites: {$a}\n" .
            "Warning: {$s['website_warning_limit']}"
        );
    } else {
        clear_alert($s['id'], 'sites_warning');
    }

    if (
        $s['website_hard_limit'] > 0 &&
        $a > $s['website_hard_limit']
    ) {
        alert_once(
            $s['id'],
            'sites_hard',
            "🔴 WEBSITE LIMIT EXCEEDED\n" .
            "Server: {$s['name']}\n" .
            "Sites: {$a}\n" .
            "Limit: {$s['website_hard_limit']}\n" .
            "Over: " . ($a - $s['website_hard_limit'])
        );
    } else {
        clear_alert($s['id'], 'sites_hard');
    }

    if (
        $disk !== null &&
        $disk >= $config['monitor']['disk_threshold']
    ) {
        alert_once(
            $s['id'],
            'disk_high',
            "⚠️ DISK HIGH\n" .
            "Server: {$s['name']}\n" .
            "Disk: {$disk}%"
        );
    } else {
        clear_alert($s['id'], 'disk_high');
    }

    if (
        $backup !== null &&
        $backup >= ($config['monitor']['backup_threshold'] ?? 90)
    ) {
        alert_once(
            $s['id'],
            'backup_high',
            "⚠️ BACKUP DISK HIGH\n" .
            "Server: {$s['name']}\n" .
            "Backup: {$backup}%"
        );
    } else {
        clear_alert($s['id'], 'backup_high');
    }

    if (
        $ram !== null &&
        $ram >= $config['monitor']['ram_threshold']
    ) {
        alert_once(
            $s['id'],
            'ram_high',
            "⚠️ RAM HIGH\n" .
            "Server: {$s['name']}\n" .
            "RAM: {$ram}%"
        );
    } else {
        clear_alert($s['id'], 'ram_high');
    }

    if ($load >= $config['monitor']['high_load_threshold']) {
        alert_once(
            $s['id'],
            'load_high',
            "⚠️ LOAD HIGH\n" .
            "Server: {$s['name']}\n" .
            "Load: {$load}"
        );
    } else {
        clear_alert($s['id'], 'load_high');
    }

    svc_alerts(
        $s['id'],
        $s['name'],
        $sv
    );
}

echo "Checked " . count($servers) . " servers\n";
