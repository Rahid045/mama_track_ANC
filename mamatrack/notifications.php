<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $m = process_missed_appointments(); $r = send_reminders();
    audit('manual_check', "missed=$m reminders=$r");
    flash("Check complete: $m newly missed appointment(s) flagged, $r reminder(s) queued.");
    redirect('notifications.php');
}
$rows = db()->query('SELECT * FROM notifications ORDER BY id DESC LIMIT 200')->fetchAll();
page_header('Alerts & notifications log', 'notif'); ?>
<div class="bar"><p class="muted">Mode: <b><?= h(cfg('notify')['mode']) ?></b> — <?= cfg('notify')['mode'] === 'live' ?></p>
<form method="post"><?= csrf_field() ?><button class="btn">Run missed-visit check now</button></form></div>
<div class="card"><div class="tbl"><table><tr><th>When</th><th>To</th><th>Channel</th><th>Message</th><th>Status</th></tr>
<?php foreach ($rows as $n): ?><tr><td class="nowrap"><?= h($n['created_at']) ?></td><td><?= h($n['recipient']) ?><br><small><?= h($n['to_addr']) ?></small></td>
 <td><?= h($n['channel']) ?></td><td><?= h($n['message']) ?></td><td><span class="badge n-<?= h($n['status']) ?>" title="<?= h($n['response']) ?>"><?= h($n['status']) ?></span></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="muted">No notifications yet.</td></tr><?php endif; ?></table></div></div>
<?php page_footer();
