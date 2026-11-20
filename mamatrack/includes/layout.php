<?php
function nav_items(string $role): array {
    $n = [['dashboard.php', ' Dashboard', 'dash'], ['patients.php', ' Mothers', 'patients'],
          ['appointments.php', ' Appointments', 'appts'], ['notifications.php', ' Alerts log', 'notif']];
    if ($role === 'admin') { $n[] = ['users.php', ' Staff users', 'users']; $n[] = ['audit.php', ' Audit trail', 'audit']; }
    return $n;
}
function risk_badge(string $level): string {
    $labels = ['high' => 'High risk', 'moderate' => 'Moderate', 'low' => 'Low risk', 'unassessed' => 'Not assessed'];
    return '<span class="badge r-' . h($level) . '">' . h($labels[$level] ?? $level) . '</span>';
}
function status_badge(string $s): string { return '<span class="badge s-' . h($s) . '">' . h(ucfirst($s)) . '</span>'; }

function page_header(string $title, string $active = ''): void {
    $u = current_user(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · <?= h(cfg('app_name')) ?></title><link rel="stylesheet" href="assets/style.css"></head><body>
<?php if ($u): ?>
<aside class="side"><div class="brand"> <?= h(cfg('app_name')) ?></div>
<nav><?php foreach (nav_items($u['role']) as [$file, $label, $key]): ?><a href="<?= $file ?>" class="<?= $active === $key ? 'on' : '' ?>"><?= $label ?></a><?php endforeach; ?></nav>
<div class="who"><b><?= h($u['name']) ?></b><br><small><?= h(ucfirst($u['role'])) ?></small><br><a href="profile.php">Password</a> · <a href="logout.php">Sign out</a></div></aside>
<main>
<?php else: ?><main class="solo"><?php endif; ?>
<h1><?= h($title) ?></h1>
<?php foreach (flash() as [$t, $m]): ?><div class="flash <?= h($t) ?>"><?= h($m) ?></div><?php endforeach;
}
function page_footer(): void { echo '</main><script src="assets/app.js"></script></body></html>'; }
