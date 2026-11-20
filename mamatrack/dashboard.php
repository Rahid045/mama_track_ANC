<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
maybe_run_checks();
$pdo = db();
$mine = $u['role'] === 'doctor';            // doctors see their own mothers; nurses/admin see all
$pw = $mine ? ' AND p.doctor_id=' . (int)$u['id'] : '';
$q = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
    ['Mothers in care', $q("SELECT COUNT(*) FROM patients p WHERE 1=1 $pw"), ''],
    ['High risk', $q("SELECT COUNT(*) FROM patients p WHERE risk_level='high' $pw"), 'bad'],
    ['Moderate risk', $q("SELECT COUNT(*) FROM patients p WHERE risk_level='moderate' $pw"), 'warn'],
    ['Missed — need follow-up', $q("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.status='missed' $pw"), 'bad'],
    ['Due today', $q("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.status='scheduled' AND a.appt_date='" . today() . "' $pw"), ''],
];
$missed = $pdo->query("SELECT a.*, p.name, p.mrn, p.phone, p.risk_level FROM appointments a JOIN patients p ON p.id=a.patient_id
    WHERE a.status='missed' $pw ORDER BY a.appt_date LIMIT 15")->fetchAll();
$high = $pdo->query("SELECT p.* FROM patients p WHERE risk_level='high' $pw ORDER BY risk_score DESC LIMIT 10")->fetchAll();
$upcoming = $pdo->query("SELECT a.*, p.name, p.mrn FROM appointments a JOIN patients p ON p.id=a.patient_id
    WHERE a.status='scheduled' AND a.appt_date BETWEEN '" . today() . "' AND '" . date('Y-m-d', strtotime('+7 days')) . "' $pw
    ORDER BY a.appt_date, a.appt_time LIMIT 15")->fetchAll();
page_header('Dashboard', 'dash'); ?>
<?php if ($mine): ?><p class="muted">Showing mothers assigned to you.</p><?php endif; ?>
<div class="stats"><?php foreach ($stats as [$l, $v, $c]): ?><div class="stat <?= $c ?>"><div class="n"><?= $v ?></div><div><?= h($l) ?></div></div><?php endforeach; ?></div>

<div class="card"><h2>⚠ Missed appointments needing follow-up</h2>
<?php if (!$missed): ?><p class="muted">No missed appointments.</p><?php else: ?>
<div class="tbl"><table><tr><th>Mother</th><th>Missed date</th><th>Phone</th><th>Risk</th><th></th></tr>
<?php foreach ($missed as $a): ?><tr>
  <td><a href="patient.php?id=<?= $a['patient_id'] ?>"><?= h($a['name']) ?></a><br><small><?= h($a['mrn']) ?></small></td>
  <td><?= fdate($a['appt_date']) ?></td><td><?= h(normalize_phone($a['phone'])) ?></td><td><?= risk_badge($a['risk_level']) ?></td>
  <td><a class="btn sm" href="reschedule.php?id=<?= $a['id'] ?>">Reschedule</a></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>

<div class="grid2">
<div class="card"><h2>🔴 High-risk mothers</h2>
<?php if (!$high): ?><p class="muted">None flagged.</p><?php else: ?><div class="tbl"><table>
<?php foreach ($high as $p): ?><tr><td><a href="patient.php?id=<?= $p['id'] ?>"><?= h($p['name']) ?></a><br><small><?= h($p['mrn']) ?> · <?= gest_weeks($p['lmp'], today()) ?> wks</small></td><td>Score <?= $p['risk_score'] ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<div class="card"><h2>📅 Next 7 days</h2>
<?php if (!$upcoming): ?><p class="muted">No appointments scheduled.</p><?php else: ?><div class="tbl"><table>
<?php foreach ($upcoming as $a): ?><tr><td><a href="patient.php?id=<?= $a['patient_id'] ?>"><?= h($a['name']) ?></a></td><td><?= fdate($a['appt_date']) ?> <?= h($a['appt_time']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
</div>
<?php page_footer();
