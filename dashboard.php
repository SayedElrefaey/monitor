<?php
require_once __DIR__.'/src/bootstrap.php';require_login();
$servers=$pdo->query("SELECT * FROM servers ORDER BY sort_order ASC, id ASC")->fetchAll();
$online=$pdo->query("SELECT COUNT(*) FROM servers WHERE enabled=1 AND last_status='online'")->fetchColumn();
$offline=$pdo->query("SELECT COUNT(*) FROM servers WHERE enabled=1 AND last_status='offline'")->fetchColumn();
$total=(int)$pdo->query("SELECT COALESCE(SUM(m.accounts),0) FROM server_metrics m JOIN (SELECT server_id,MAX(id) mid FROM server_metrics GROUP BY server_id) x ON x.mid=m.id")->fetchColumn();
function latest_metric($pdo,$id){$st=$pdo->prepare("SELECT * FROM server_metrics WHERE server_id=? ORDER BY id DESC LIMIT 1");$st->execute([$id]);return $st->fetch()?:[];}
?>
<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><div style="margin:15px 0;display:flex;align-items:center;gap:10px">
    <label for="refreshInterval">تحديث تلقائي:</label>

    <select id="refreshInterval">
        <option value="0">إيقاف</option>
        <option value="10">10 ثواني</option>
        <option value="30">30 ثانية</option>
        <option value="60">1 دقيقة</option>
        <option value="120">2 دقيقة</option>
        <option value="300">5 دقائق</option>
        <option value="600">10 دقائق</option>
    </select>

    <span id="refreshCountdown"></span>
</div><title>Dashboard</title>
<style>body{font-family:Arial;background:#f4f6f8;margin:0}.nav{background:#172033;color:#fff;padding:15px}.wrap{max-width:1400px;margin:auto;padding:20px}.cards{display:flex;gap:15px;flex-wrap:wrap}.card{background:#fff;padding:18px;border-radius:12px;min-width:180px;box-shadow:0 2px 8px #0001}table{width:100%;border-collapse:collapse;background:#fff;margin-top:20px}th,td{padding:10px;border-bottom:1px solid #eee;text-align:center}th{background:#eef1f5}.online{color:#16843a;font-weight:bold}.offline{color:#c62828;font-weight:bold}.warn{color:#b26a00;font-weight:bold}
.limit-reached td{
    background:#ffc7c7 !important;
    color:#8b0000;
    font-weight:bold;
    border-bottom:1px solid #ff8a8a;
}
.btn{padding:7px 11px;border-radius:6px;background:#172033;color:#fff;text-decoration:none}</style>
<div class="nav"><div class="wrap"><b>WHM Server Monitor</b> <a style="color:#fff;float:left" href="logout.php">خروج</a></div></div>
<div class="wrap"><div class="cards"><div class="card">السيرفرات <b><?=count($servers)?></b></div><div class="card">Online <b><?=$online?></b></div><div class="card">Offline <b><?=$offline?></b></div><div class="card">إجمالي المواقع <b><?=number_format($total)?></b></div></div>
<p><a class="btn" href="server_form.php">+ إضافة سيرفر</a></p>
<table>
<tr>
<th>#</th>
<th>السيرفر</th><th>الحالة</th><th>المواقع</th><th>Warning</th><th>Limit</th><th>Load</th><th>RAM</th><th>Disk</th><th>Backup</th><th>Services</th><th>آخر فحص</th></tr>
<?php foreach($servers as $s):

$m=latest_metric($pdo,$s['id']);

$sites=(int)($m['accounts'] ?? 0);

$limitReached=(
    $s['website_hard_limit'] > 0 &&
    $sites >= $s['website_hard_limit']
);

$sc=$limitReached
    ? 'offline'
    : (
        ($s['website_warning_limit'] > 0 && $sites >= $s['website_warning_limit'])
        ? 'warn'
        : ''
    );

$rowClass=$limitReached ? 'limit-reached' : '';
?>

<tr class="<?=$rowClass?>">
<td><?=htmlspecialchars($s['sort_order'] ?? '')?></td>
    <td><?=htmlspecialchars($s['name'])?></td><td class="<?=$s['last_status']==='online'?'online':($s['last_status']==='offline'?'offline':'warn')?>"><?=$s['last_status']==='online'?'🟢 Online':($s['last_status']==='offline'?'🔴 Offline':'🟠 '.$s['last_status'])?></td><td class="<?=$sc?>"><?=number_format($sites)?></td><td><?=number_format($s['website_warning_limit'])?></td><td><?=number_format($s['website_hard_limit'])?></td><td><?=htmlspecialchars($m['load_1m']??'-')?></td><td><?=isset($m['ram_percent'])?$m['ram_percent'].'%':'-'?></td>
    <td><?=isset($m['disk_percent'])?$m['disk_percent'].'%':'-'?></td>
    <td>
<?php 
$backupPercent = isset($m['backup_percent']) ? (float)$m['backup_percent'] : null; 
$backupThreshold = (float)($config['monitor']['backup_threshold'] ?? 90); 
?> 
<span class="<?=$backupPercent !== null && $backupPercent >= $backupThreshold ? 'backup-high' : ''?>"> 
    <?= $backupPercent !== null ? $backupPercent.'%' : '-' ?> 
</span> 
</td>
    <td>
    Apache <?= in_array(($m['apache_status'] ?? ''), ['active', 'enabled'], true) ? '🟢' : '🔴' ?>
    MySQL <?= in_array(($m['mysql_status'] ?? ''), ['active', 'enabled'], true) ? '🟢' : '🔴' ?>
    DNS <?= in_array(($m['dns_status'] ?? ''), ['active', 'enabled'], true) ? '🟢' : '🔴' ?>
    Exim <?= in_array(($m['exim_status'] ?? ''), ['active', 'enabled'], true) ? '🟢' : '🔴' ?>
</td><td><?=htmlspecialchars($s['last_checked_at']??'-')?></td><td>
    <a class="btn" href="server_form.php?id=<?=$s['id']?>">تعديل</a>

<a class="btn"
   style="background:#c62828"
   href="delete_server.php?id=<?=$s['id']?>"
   onclick="return confirm('هل أنت متأكد من حذف السيرفر <?=htmlspecialchars($s['name'], ENT_QUOTES)?> ؟\\nسيتم حذف بيانات المراقبة والتنبيهات الخاصة به أيضًا.');">
   حذف
</a></td></tr>
<?php endforeach;?>
<script>
(function () {
    const select = document.getElementById('refreshInterval');
    const countdown = document.getElementById('refreshCountdown');

    let secondsLeft = 0;
    let timer = null;

    const saved = localStorage.getItem('monitor_refresh_interval');

    if (saved !== null) {
        select.value = saved;
    } else {
        select.value = '60';
    }

    function startRefresh() {
        clearInterval(timer);
        secondsLeft = parseInt(select.value || '0', 10);

        if (secondsLeft <= 0) {
            countdown.textContent = 'متوقف';
            return;
        }

        updateCountdown();

        timer = setInterval(function () {
            secondsLeft--;

            if (secondsLeft <= 0) {
                location.reload();
                return;
            }

            updateCountdown();
        }, 1000);
    }

    function updateCountdown() {
        if (secondsLeft >= 60) {
            const min = Math.floor(secondsLeft / 60);
            const sec = secondsLeft % 60;

            countdown.textContent =
                'التحديث التالي: ' +
                min + ':' + String(sec).padStart(2, '0');
        } else {
            countdown.textContent =
                'التحديث التالي: ' + secondsLeft + ' ثانية';
        }
    }

    select.addEventListener('change', function () {
        localStorage.setItem(
            'monitor_refresh_interval',
            select.value
        );

        startRefresh();
    });

    startRefresh();
})();
</script>
</table></div>
