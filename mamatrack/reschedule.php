<?php
/** Rescheduling for both staff (login, ?id=) and mothers (secure SMS link, ?t=token). */
require __DIR__ . '/includes/bootstrap.php';
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$staff = $token === '';
$u = $staff ? require_login() : null;
if ($staff) { $s = db()->prepare('SELECT * FROM appointments WHERE id=?'); $s->execute([(int)($_GET['id'] ?? $_POST['id'] ?? 0)]); }
else        { $s = db()->prepare('SELECT * FROM appointments WHERE token=?'); $s->execute([$token]); }
$a = $s->fetch();
$p = $a ? get_patient((int)$a['patient_id']) : null;
$usable = $a && $p && in_array($a['status'], ['scheduled', 'missed'], true);
$errors = []; $done = null;
if ($usable && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $date = post('appt_date'); $time = post('appt_time');
    $min = $staff ? today() : date('Y-m-d', strtotime('+1 day'));
    if (!valid_date($date) || $date < $min || $date > date('Y-m-d', strtotime('+60 days'))) $errors[] = 'Pick a date between ' . fdate($min) . ' and ' . fdate(date('Y-m-d', strtotime('+60 days'))) . '.';
    if ($time !== '' && !preg_match('/^\d{2}:\d{2}$/', $time)) $errors[] = 'Invalid time.';
    if (!$errors) {
        $newId = new_appointment((int)$p['id'], (int)($a['doctor_id'] ?: $p['doctor_id']), $date, $time ?: null, $a['reason'] ?: 'ANC visit', $u['id'] ?? null, (int)$a['id']);
        db()->prepare("UPDATE appointments SET status='rescheduled' WHERE id=?")->execute([$a['id']]);
        $who = $staff ? "staff ({$u['name']})" : 'the mother (self-service link)';
        $dd = db()->prepare('SELECT * FROM users WHERE id=?'); $dd->execute([$a['doctor_id'] ?: $p['doctor_id']]); $doc = $dd->fetch() ?: [];
        notify_person($newId, 'doctor:' . ($doc['name'] ?? 'unassigned'), $doc['phone'] ?? null, $doc['email'] ?? null, "ANC appointment rescheduled: {$p['name']}",
            "MamaTrack: {$p['name']} ({$p['mrn']}) ANC visit rescheduled from " . fdate($a['appt_date']) . " to " . fdate($date) . ($time ? " $time" : '') . " by $who.");
        if ($staff) notify_person($newId, 'patient:' . $p['name'], $p['phone'], null, 'ANC appointment rescheduled',
            'Dear ' . explode(' ', $p['name'])[0] . ', your antenatal visit is now on ' . fdate($date) . ($time ? " at $time" : '') . '. See you then!');
        audit('appointment_rescheduled', "#{$a['id']} -> #$newId");
        if ($staff) { flash('Appointment rescheduled to ' . fdate($date) . '.'); redirect('patient.php?id=' . $p['id']); }
        $done = ['date' => $date, 'time' => $time];
    }
}
page_header($staff ? 'Reschedule appointment' : 'Rebook your antenatal visit', $staff ? 'appts' : ''); ?>
<?php if ($done): ?>
  <div class="card narrow"><div class="flash ok">✅ Thank you! Your visit is now booked for <b><?= fdate($done['date']) ?></b><?= $done['time'] ? ' at ' . h($done['time']) : '' ?>. Your clinic has been notified.</div></div>
<?php elseif (!$usable): ?>
  <div class="card narrow"><div class="flash warn">This link is no longer valid — the appointment was already rebooked or cancelled. Please contact your clinic.</div></div>
<?php else: ?>
  <?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post" class="card form narrow"><?= csrf_field() ?><input type="hidden" name="t" value="<?= h($token) ?>"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
    <p><?= $staff ? '<b>' . h($p['name']) . '</b> (' . h($p['mrn']) . ')' : 'Hello <b>' . h(explode(' ', $p['name'])[0]) . '</b>,' ?>
       <?= $a['status'] === 'missed' ? 'the visit on' : 'your visit on' ?> <b><?= fdate($a['appt_date']) ?></b> <?= $a['status'] === 'missed' ? 'was missed.' : 'is scheduled.' ?> Choose a new date:</p>
    <label>New date<input type="date" name="appt_date" min="<?= $staff ? today() : date('Y-m-d', strtotime('+1 day')) ?>" max="<?= date('Y-m-d', strtotime('+60 days')) ?>" value="<?= h($_POST['appt_date'] ?? date('Y-m-d', strtotime('+2 days'))) ?>" required></label>
    <label>Preferred time<select name="appt_time"><?php foreach (['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00'] as $t): ?><option><?= $t ?></option><?php endforeach; ?></select></label>
    <button class="btn">Confirm new date</button>
    <?php if ($staff): ?><a class="btn ghost" href="patient.php?id=<?= $p['id'] ?>">Cancel</a><?php endif; ?>
  </form>
<?php endif; ?>
<?php page_footer();
