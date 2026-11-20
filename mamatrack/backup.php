<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
if (cfg('database_url')) {
    http_response_code(501);
    exit('Database downloads are unavailable with hosted PostgreSQL. Use your database provider backups.');
}
$tmp = sys_get_temp_dir() . '/mamatrack_' . bin2hex(random_bytes(6)) . '.sqlite';
db()->exec("VACUUM INTO '" . str_replace("'", "''", $tmp) . "'");
audit('backup_downloaded');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="mamatrack_backup_' . date('Ymd_His') . '.sqlite"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp); unlink($tmp);
