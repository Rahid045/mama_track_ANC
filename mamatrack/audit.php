<?php
require __DIR__ . '/includes/bootstrap.php';
require_role('admin');
$rows = db()->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 300')->fetchAll();
page_header('Audit trail', 'audit'); ?>
<div class="card"><div class="tbl"><table><tr><th>When</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr>
<?php foreach ($rows as $r): ?><tr><td class="nowrap"><?= h($r['created_at']) ?></td><td><?= h($r['user_name']) ?></td><td><?= h($r['action']) ?></td><td><?= h($r['detail']) ?></td><td><?= h($r['ip']) ?></td></tr><?php endforeach; ?></table></div></div>
<?php page_footer();
