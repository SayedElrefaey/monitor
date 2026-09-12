<?php

require_once __DIR__ . '/src/bootstrap.php';
require_login();

date_default_timezone_set('Africa/Cairo');

$success = '';
$error   = '';

/*
|--------------------------------------------------------------------------
| Load current settings
|--------------------------------------------------------------------------
*/
$st = $pdo->query("
    SELECT *
    FROM monitor_settings
    WHERE id = 1
    LIMIT 1
");

$settings = $st->fetch();

if (!$settings) {

    $now = date('Y-m-d H:i:s');

    $pdo->prepare("
        INSERT INTO monitor_settings
        (
            id,
            telegram_enabled,
            telegram_bot_token_enc,
            telegram_chat_id,
            created_at,
            updated_at
        )
        VALUES
        (1, 0, NULL, '', ?, ?)
    ")->execute([
        $now,
        $now
    ]);

    $st = $pdo->query("
        SELECT *
        FROM monitor_settings
        WHERE id = 1
        LIMIT 1
    ");

    $settings = $st->fetch();
}


/*
|--------------------------------------------------------------------------
| Save settings
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'save';

    /*
     * Telegram test
     */
    if ($action === 'test_telegram') {

        $token = trim((string)($_POST['telegram_bot_token'] ?? ''));
        $chat  = trim((string)($_POST['telegram_chat_id'] ?? ''));

        try {

            /*
             * If token field is empty, use saved token.
             */
            if ($token === '') {

                $enc = $settings['telegram_bot_token_enc'] ?? null;

                if (!$enc) {
                    throw new RuntimeException('لم يتم حفظ Bot Token بعد.');
                }

                $token = decrypt_secret($enc);
            }

            if ($chat === '') {
                $chat = trim((string)($settings['telegram_chat_id'] ?? ''));
            }

            if ($token === '') {
                throw new RuntimeException('Bot Token غير موجود.');
            }

            if ($chat === '') {
                throw new RuntimeException('Chat ID غير موجود.');
            }

            $message =
                "✅ Telegram Test\n\n" .
                "WHM Server Monitor\n" .
                "التنبيهات تعمل بشكل صحيح.\n" .
                "Time: " . date('Y-m-d H:i:s');

            $ch = curl_init(
                'https://api.telegram.org/bot' .
                rawurlencode($token) .
                '/sendMessage'
            );

            curl_setopt_array($ch, [
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => http_build_query([
                    'chat_id' => $chat,
                    'text'    => $message
                ]),
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_CONNECTTIMEOUT  => 5,
                CURLOPT_TIMEOUT         => 10,
                CURLOPT_SSL_VERIFYPEER  => true,
                CURLOPT_SSL_VERIFYHOST  => 2
            ]);

            $body = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($body === false) {
                throw new RuntimeException(
                    $curlError ?: 'فشل الاتصال بـ Telegram.'
                );
            }

            $result = json_decode($body, true);

            if (
                $httpCode !== 200 ||
                !is_array($result) ||
                empty($result['ok'])
            ) {
                $description = $result['description'] ?? 'Telegram API error';
                throw new RuntimeException($description);
            }

            $success = 'تم إرسال رسالة الاختبار إلى Telegram بنجاح ✅';

        } catch (Throwable $e) {

            $error = 'فشل اختبار Telegram: ' . $e->getMessage();
        }

    } else {

        /*
         * Save
         */
        try {

            $telegramEnabled = !empty($_POST['telegram_enabled']) ? 1 : 0;

            $telegramToken = trim(
                (string)($_POST['telegram_bot_token'] ?? '')
            );

            $telegramChatId = trim(
                (string)($_POST['telegram_chat_id'] ?? '')
            );

            /*
             * Keep old encrypted token if the field is empty.
             */
            if ($telegramToken !== '') {

                $telegramTokenEnc = encrypt_secret(
                    $telegramToken
                );

            } else {

                $telegramTokenEnc =
                    $settings['telegram_bot_token_enc'] ?? null;
            }


            $alertServerOffline =
                !empty($_POST['alert_server_offline']) ? 1 : 0;

            $alertServerOnline =
                !empty($_POST['alert_server_online']) ? 1 : 0;

            $alertApache =
                !empty($_POST['alert_apache']) ? 1 : 0;

            $alertMysql =
                !empty($_POST['alert_mysql']) ? 1 : 0;

            $alertDns =
                !empty($_POST['alert_dns']) ? 1 : 0;

            $alertExim =
                !empty($_POST['alert_exim']) ? 1 : 0;

            $alertSitesWarning =
                !empty($_POST['alert_sites_warning']) ? 1 : 0;

            $alertSitesHard =
                !empty($_POST['alert_sites_hard']) ? 1 : 0;

            $alertDiskHigh =
                !empty($_POST['alert_disk_high']) ? 1 : 0;

            $alertRamHigh =
                !empty($_POST['alert_ram_high']) ? 1 : 0;

            $alertLoadHigh =
                !empty($_POST['alert_load_high']) ? 1 : 0;


            $now = date('Y-m-d H:i:s');

            $update = $pdo->prepare("
                UPDATE monitor_settings
                SET
                    telegram_enabled = ?,
                    telegram_bot_token_enc = ?,
                    telegram_chat_id = ?,

                    alert_server_offline = ?,
                    alert_server_online = ?,

                    alert_apache = ?,
                    alert_mysql = ?,
                    alert_dns = ?,
                    alert_exim = ?,

                    alert_sites_warning = ?,
                    alert_sites_hard = ?,

                    alert_disk_high = ?,
                    alert_ram_high = ?,
                    alert_load_high = ?,

                    updated_at = ?
                WHERE id = 1
            ");

            $update->execute([
                $telegramEnabled,
                $telegramTokenEnc,
                $telegramChatId,

                $alertServerOffline,
                $alertServerOnline,

                $alertApache,
                $alertMysql,
                $alertDns,
                $alertExim,

                $alertSitesWarning,
                $alertSitesHard,

                $alertDiskHigh,
                $alertRamHigh,
                $alertLoadHigh,

                $now
            ]);

            $success = 'تم حفظ إعدادات التنبيهات بنجاح ✅';

            /*
             * Reload settings after save
             */
            $st = $pdo->query("
                SELECT *
                FROM monitor_settings
                WHERE id = 1
                LIMIT 1
            ");

            $settings = $st->fetch();

        } catch (Throwable $e) {

            $error = 'حدث خطأ أثناء حفظ الإعدادات: ' . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Token exists
|--------------------------------------------------------------------------
*/
$hasTelegramToken =
    !empty($settings['telegram_bot_token_enc']);
?>
<!doctype html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>إعدادات التنبيهات</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            color: #172033;
        }

        .nav {
            background: #172033;
            color: #fff;
            padding: 15px;
        }

        .wrap {
            max-width: 1000px;
            margin: auto;
            padding: 20px;
        }

        .nav a {
            color: #fff;
            text-decoration: none;
            float: left;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px #0001;
        }

        h2 {
            margin-top: 0;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
        }

        .check-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .check-item {
            border: 1px solid #e4e7eb;
            border-radius: 8px;
            padding: 12px;
            background: #fafbfc;
        }

        .check-item label {
            margin: 0;
            font-weight: normal;
            cursor: pointer;
        }

        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 11px 18px;
            cursor: pointer;
            text-decoration: none;
            font-size: 15px;
        }

        .btn-primary {
            background: #172033;
            color: #fff;
        }

        .btn-success {
            background: #16843a;
            color: #fff;
        }

        .btn-secondary {
            background: #6b7280;
            color: #fff;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .success {
            background: #e9f7ef;
            color: #176b36;
        }

        .error {
            background: #fdecec;
            color: #a51f1f;
        }

        .note {
            color: #666;
            font-size: 13px;
            margin-top: 7px;
        }

        @media (max-width: 700px) {
            .check-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="nav">
    <div class="wrap">
        <b>🔔 إعدادات التنبيهات</b>
        <a href="dashboard.php">العودة للوحة</a>
    </div>
</div>

<div class="wrap">

    <?php if ($success !== ''): ?>
        <div class="alert success">
            <?=htmlspecialchars($success)?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert error">
            <?=htmlspecialchars($error)?>
        </div>
    <?php endif; ?>


    <form method="post">

        <div class="card">

            <div class="section-title">
                Telegram
            </div>

            <div class="field">
                <label>
                    <input
                        type="checkbox"
                        name="telegram_enabled"
                        value="1"
                        <?=$settings['telegram_enabled'] ? 'checked' : ''?>
                    >
                    تفعيل تنبيهات Telegram
                </label>
            </div>

            <div class="field">
                <label for="telegram_bot_token">
                    Bot Token
                </label>

                <input
                    type="password"
                    id="telegram_bot_token"
                    name="telegram_bot_token"
                    placeholder="<?=$hasTelegramToken ? 'تم حفظ Bot Token — اتركه فارغًا للاحتفاظ به' : 'أدخل Bot Token'?>"
                    autocomplete="new-password"
                >

                <div class="note">
                    يتم حفظ Bot Token مشفرًا في قاعدة البيانات.
                </div>
            </div>

            <div class="field">
                <label for="telegram_chat_id">
                    Chat ID
                </label>

                <input
                    type="text"
                    id="telegram_chat_id"
                    name="telegram_chat_id"
                    value="<?=htmlspecialchars($settings['telegram_chat_id'] ?? '')?>"
                    placeholder="مثال: -1001234567890"
                >
            </div>

        </div>


        <div class="card">

            <div class="section-title">
                تنبيهات السيرفر
            </div>

            <div class="check-grid">

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_server_offline"
                            value="1"
                            <?=$settings['alert_server_offline'] ? 'checked' : ''?>
                        >
                        🔴 السيرفر Offline
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_server_online"
                            value="1"
                            <?=$settings['alert_server_online'] ? 'checked' : ''?>
                        >
                        🟢 السيرفر عاد Online
                    </label>
                </div>

            </div>

        </div>


        <div class="card">

            <div class="section-title">
                تنبيهات الخدمات
            </div>

            <div class="check-grid">

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_apache"
                            value="1"
                            <?=$settings['alert_apache'] ? 'checked' : ''?>
                        >
                        🔴 Apache Down
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_mysql"
                            value="1"
                            <?=$settings['alert_mysql'] ? 'checked' : ''?>
                        >
                        🔴 MySQL Down
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_dns"
                            value="1"
                            <?=$settings['alert_dns'] ? 'checked' : ''?>
                        >
                        🔴 DNS Down
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_exim"
                            value="1"
                            <?=$settings['alert_exim'] ? 'checked' : ''?>
                        >
                        🔴 Exim Down
                    </label>
                </div>

            </div>

        </div>


        <div class="card">

            <div class="section-title">
                تنبيهات المواقع والموارد
            </div>

            <div class="check-grid">

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_sites_warning"
                            value="1"
                            <?=$settings['alert_sites_warning'] ? 'checked' : ''?>
                        >
                        🟡 تجاوز Warning للمواقع
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_sites_hard"
                            value="1"
                            <?=$settings['alert_sites_hard'] ? 'checked' : ''?>
                        >
                        🔴 تجاوز Limit للمواقع
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_disk_high"
                            value="1"
                            <?=$settings['alert_disk_high'] ? 'checked' : ''?>
                        >
                        ⚠️ Disk مرتفع
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_ram_high"
                            value="1"
                            <?=$settings['alert_ram_high'] ? 'checked' : ''?>
                        >
                        ⚠️ RAM مرتفعة
                    </label>
                </div>

                <div class="check-item">
                    <label>
                        <input
                            type="checkbox"
                            name="alert_load_high"
                            value="1"
                            <?=$settings['alert_load_high'] ? 'checked' : ''?>
                        >
                        ⚠️ Load مرتفع
                    </label>
                </div>

            </div>

        </div>


        <div class="buttons">

            <button
                type="submit"
                name="action"
                value="save"
                class="btn btn-primary"
            >
                💾 حفظ الإعدادات
            </button>

            <button
                type="submit"
                name="action"
                value="test_telegram"
                class="btn btn-success"
            >
                📤 إرسال رسالة اختبار
            </button>

            <a
                href="dashboard.php"
                class="btn btn-secondary"
            >
                ↩ العودة للوحة
            </a>

        </div>

    </form>

</div>

</body>
</html>