<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$p = get_patient((int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0));
if (!$p) { http_response_code(404); exit('Mother not found'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $date = post('appt_date');
    if (!valid_date($date) || $date < today()) $errors[] = 'Choose a valid date, today or later.';
    if (!$errors) {
        $id = new_appointment((int)$p['id'], (int)($_POST['doctor_id'] ?: $p['doctor_id']), $date, post('appt_time') ?: null, post('reason') ?: 'ANC visit', (int)$u['id']);
        audit('appointment_created', "#$id patient #{$p['id']}");
        flash('Appointment scheduled for ' . fdate($date) . '.');
        redirect('patient.php?id=' . $p['id']);
    }
}
page_header('Schedule appointment — ' . $p['name'], 'appts'); ?>
<?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="card form narrow"><?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
  <label>Date<input type="date" name="appt_date" min="<?= today() ?>" value="<?= h(suggest_next_date((int)gest_weeks($p['lmp'], today()), today())) ?>" required></label>
  <label>Time<input type="time" name="appt_time" value="09:00"></label>
  <label>Doctor<select name="doctor_id"><?php foreach (doctors() as $d): ?><option value="<?= $d['id'] ?>" <?= (int)$p['doctor_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option><?php endforeach; ?></select></label>
  <label>Reason<input name="reason" value="ANC visit"></label>
  <button class="btn">Schedule</button> <a class="btn ghost" href="patient.php?id=<?= $p['id'] ?>">Cancel</a>
</form>
<?php page_footer();
