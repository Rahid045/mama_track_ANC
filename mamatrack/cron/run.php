<?php
/**
 * Scheduled job — run daily (or hourly) from the server:
 *   Linux cron : 0 * * * *  php /path/to/mamatrack/cron/run.php
 *   Windows    : Task Scheduler → php.exe C:\xampp\htdocs\mamatrack\cron\run.php
 * 1) flags overdue appointments as MISSED and alerts the attending doctor (SMS/email) + mother
 * 2) sends next-day reminders  3) takes a daily database backup (keeps the last 14)
 */
if (PHP_SAPI !== 'cli') {
    $secret = getenv('CRON_SECRET');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$secret || !hash_equals('Bearer ' . $secret, $auth)) { http_response_code(401); exit('Unauthorized'); }
}
require __DIR__ . '/../includes/bootstrap.php';
$m = process_missed_appointments();
$r = send_reminders();
if (cfg('database_url')) {
    header('Content-Type: application/json');
    echo json_encode(['missed' => $m, 'reminders' => $r]);
    exit;
}
$dir = cfg('backup_dir');
if (!is_dir($dir)) mkdir($dir, 0775, true);
$file = $dir . '/mamatrack_' . date('Ymd') . '.sqlite';
if (!file_exists($file)) db()->exec("VACUUM INTO '" . str_replace("'", "''", $file) . "'");
$all = glob($dir . '/mamatrack_*.sqlite'); sort($all);
foreach (array_slice($all, 0, max(0, count($all) - 14)) as $old) unlink($old);
echo date('c') . " missed=$m reminders=$r backup=" . basename($file) . "\n";
