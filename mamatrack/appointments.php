<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
maybe_run_checks();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('do') === 'cancel') {
        db()->prepare("UPDATE appointments SET status='cancelled' WHERE id=? AND status IN('scheduled','missed')")->execute([(int)post('id')]);
        audit('appointment_cancelled', '#' . (int)post('id')); flash('Appointment cancelled.');
    }
    redirect('appointments.php?f=' . urlencode($_GET['f'] ?? 'open'));
}
$f = $_GET['f'] ?? 'open';
$where = ['open' => "a.status IN('scheduled','missed')", 'missed' => "a.status='missed'", 'today' => "a.status='scheduled' AND a.appt_date='" . today() . "'", 'all' => '1=1'][$f] ?? "a.status IN('scheduled','missed')";
$rows = db()->query("SELECT a.*, p.name, p.mrn, p.phone, d.name AS doctor FROM appointments a JOIN patients p ON p.id=a.patient_id
   LEFT JOIN users d ON d.id=a.doctor_id WHERE $where ORDER BY a.appt_date, a.appt_time LIMIT 300")->fetchAll();
page_header('Appointments', 'appts'); ?>
<div class="tabs"><?php foreach (['open' => 'Open', 'missed' => 'Missed', 'today' => 'Today', 'all' => 'All'] as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="?f=<?= $k ?>"><?= $l ?></a><?php endforeach; ?></div>
<div class="card"><div class="tbl"><table><tr><th>Date</th><th>Mother</th><th>Doctor</th><th>Reason</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $a): ?><tr><td><?= fdate($a['appt_date']) ?> <small><?= h($a['appt_time']) ?></small></td>
 <td><a href="patient.php?id=<?= $a['patient_id'] ?>"><?= h($a['name']) ?></a><br><small><?= h(normalize_phone($a['phone'])) ?></small></td>
 <td><?= h($a['doctor']) ?></td><td><?= h($a['reason']) ?></td><td><?= status_badge($a['status']) ?></td>
 <td class="nowrap"><?php if (in_array($a['status'], ['scheduled', 'missed'], true)): ?>
   <a class="btn sm" href="reschedule.php?id=<?= $a['id'] ?>">Reschedule</a>
   <form method="post" class="inline" onsubmit="return confirm('Cancel this appointment?')"><?= csrf_field() ?><input type="hidden" name="do" value="cancel"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="btn sm ghost">Cancel</button></form>
 <?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="muted">Nothing here.</td></tr><?php endif; ?></table></div></div>
<?php page_footer();
