<?php
declare(strict_types=1);
$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($CONFIG['timezone']);

function cfg(string $key, $default = null) {
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    session_name('mamatrack');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    session_start();
}

require __DIR__ . '/db.php';
require __DIR__ . '/risk.php';
require __DIR__ . '/notify.php';
require __DIR__ . '/layout.php';

// ---------- small helpers ----------
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function fdate(?string $d): string { return $d ? date('d M Y', strtotime($d)) : '—'; }
function valid_date(?string $d): bool {
    $x = DateTime::createFromFormat('Y-m-d', (string)$d);
    return $x && $x->format('Y-m-d') === $d;
}
function age_years(string $dob, ?string $on = null): int {
    return (int)(new DateTime($dob))->diff(new DateTime($on ?? today()))->y;
}
function flash(?string $msg = null, string $type = 'ok'): array {
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return []; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}

// ---------- CSRF ----------
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419); exit('Session expired or invalid request. Go back, refresh the page and try again.');
    }
}

// ---------- auth ----------
function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $idle = time() - ($_SESSION['last'] ?? 0);
        if ($idle > cfg('session_minutes', 30) * 60) { session_unset(); return $u; }
        $s = db()->prepare('SELECT * FROM users WHERE id=? AND active=1');
        $s->execute([$_SESSION['uid']]);
        $u = $s->fetch() ?: null;
        if ($u) $_SESSION['last'] = time();
    }
    return $u;
}
function require_login(bool $allowMustChange = false): array {
    $u = current_user();
    if (!$u) redirect('login.php');
    if ($u['must_change'] && !$allowMustChange) redirect('profile.php');
    return $u;
}
function require_role(string ...$roles): array {
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('Access denied for your role.'); }
    return $u;
}
function audit(string $action, string $detail = ''): void {
    $u = current_user();
    db()->prepare('INSERT INTO audit_log(user_id,user_name,action,detail,ip,created_at) VALUES(?,?,?,?,?,?)')
        ->execute([$u['id'] ?? null, $u['name'] ?? 'system', $action, $detail, $_SERVER['REMOTE_ADDR'] ?? 'cli', now()]);
}

// ---------- validation helpers ----------
function post(string $k): string { return trim((string)($_POST[$k] ?? '')); }
/** Read an optional numeric field, collecting an error if out of range. */
function num_field(string $k, string $label, float $min, float $max, array &$errors, bool $int = false) {
    $v = post($k);
    if ($v === '') return null;
    if (!is_numeric($v) || $v < $min || $v > $max) { $errors[] = "$label must be between $min and $max."; return null; }
    return $int ? (int)$v : (float)$v;
}
function doctors(): array {
    return db()->query("SELECT id,name FROM users WHERE role='doctor' AND active=1 ORDER BY name")->fetchAll();
}
function get_patient(int $id): ?array {
    $s = db()->prepare('SELECT p.*, u.name AS doctor_name FROM patients p LEFT JOIN users u ON u.id=p.doctor_id WHERE p.id=?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
/** Run the missed-appointment check at most once every 5 minutes, triggered by normal page use. */
function maybe_run_checks(): void {
    $f = dirname(cfg('db_path')) . '/last_check.txt';
    if (file_exists($f) && time() - filemtime($f) < 300) return;
    @touch($f);
    process_missed_appointments();
    send_reminders();
}
