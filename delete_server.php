<?php
require_once __DIR__.'/src/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

$st = $pdo->prepare("SELECT id, name FROM servers WHERE id=? LIMIT 1");
$st->execute([$id]);
$server = $st->fetch();

if (!$server) {
    header('Location: dashboard.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM alert_states WHERE server_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM server_metrics WHERE server_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM servers WHERE id=?")->execute([$id]);

    $pdo->commit();

    header('Location: dashboard.php?deleted=1');
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo 'Delete failed: '.htmlspecialchars($e->getMessage());
}