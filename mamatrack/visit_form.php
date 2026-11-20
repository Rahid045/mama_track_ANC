<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$p = get_patient((int)($_GET['patient_id'] ?? $_POST['patient_id'] ?? 0));
if (!$p) { http_response_code(404); exit('Mother not found'); }
$open = db()->prepare("SELECT * FROM appointments WHERE patient_id=? AND status IN('scheduled','missed') ORDER BY appt_date"); $open->execute([$p['id']]); $open = $open->fetchAll();
$errors = []; $d = ['visit_date' => today(), 'appointment_id' => $open[0]['id'] ?? '', 'urine_protein' => 'neg', 'notes' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d['visit_date'] = post('visit_date'); $d['appointment_id'] = (int)post('appointment_id'); $d['urine_protein'] = post('urine_protein'); $d['notes'] = post('notes');
    if (!valid_date($d['visit_date']) || $d['visit_date'] > today() || $d['visit_date'] < $p['lmp']) $errors[] = 'Visit date must be between the LMP and today.';
    $sys = num_field('systolic', 'Systolic BP', 50, 300, $errors, true);   $dia = num_field('diastolic', 'Diastolic BP', 30, 200, $errors, true);
    if (($sys === null) !== ($dia === null)) $errors[] = 'Enter both systolic and diastolic BP, or neither.';
    if ($sys !== null && $dia !== null && $dia >= $sys) $errors[] = 'Diastolic BP must be lower than systolic.';
    $hr = num_field('heart_rate', 'Pulse', 30, 220, $errors, true);        $wt = num_field('weight', 'Weight', 20, 200, $errors);
    $tp = num_field('temperature', 'Temperature', 30, 45, $errors);        $fh = num_field('fundal_height', 'Fundal height', 0, 60, $errors);
    $fhr = num_field('fetal_hr', 'Fetal heart rate', 60, 220, $errors, true); $hb = num_field('hemoglobin', 'Haemoglobin', 2, 20, $errors);
    if ($sys === null && $wt === null && $hb === null) $errors[] = 'Record at least blood pressure, weight or haemoglobin.';
    if (!isset(URINE_SCALE[$d['urine_protein']])) $errors[] = 'Invalid urine protein value.';
    $next = post('next_date');
    if ($next !== '' && (!valid_date($next) || $next <= $d['visit_date'])) $errors[] = 'Next appointment must be after the visit date.';
    if (!$errors) {
        $gwk = gest_weeks($p['lmp'], $d['visit_date']);
        db()->prepare('INSERT INTO visits(patient_id,appointment_id,visit_date,gest_weeks,systolic,diastolic,heart_rate,weight,temperature,fundal_height,fetal_hr,hemoglobin,urine_protein,notes,recorded_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$p['id'], $d['appointment_id'] ?: null, $d['visit_date'], $gwk, $sys, $dia, $hr, $wt, $tp, $fh, $fhr, $hb, $d['urine_protein'], $d['notes'], $u['id'], now()]);
        if ($d['appointment_id']) db()->prepare("UPDATE appointments SET status='attended' WHERE id=? AND patient_id=?")->execute([$d['appointment_id'], $p['id']]);
        if ($next !== '') new_appointment((int)$p['id'], (int)$p['doctor_id'], $next, post('next_time') ?: null, 'Routine ANC follow-up', (int)$u['id']);
        $r = refresh_risk((int)$p['id']);
        audit('visit_recorded', "patient #{$p['id']} wk $gwk risk {$r['level']}");
        flash("Visit recorded. Risk assessment: " . strtoupper($r['level']) . " (score {$r['score']}).", $r['level'] === 'high' ? 'err' : ($r['level'] === 'moderate' ? 'warn' : 'ok'));
        redirect('patient.php?id=' . $p['id']);
    }
}
$lastGw = gest_weeks($p['lmp'], today());
page_header('Record ANC visit — ' . $p['name'], 'patients'); ?>
<?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="patient_id" value="<?= $p['id'] ?>">
  <div class="cols">
    <label>Visit date<input type="date" name="visit_date" value="<?= h($d['visit_date']) ?>" max="<?= today() ?>" required></label>
    <label>Linked appointment<select name="appointment_id"><option value="">— walk-in / none —</option>
      <?php foreach ($open as $o): ?><option value="<?= $o['id'] ?>" <?= (int)$d['appointment_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= fdate($o['appt_date']) ?> (<?= $o['status'] ?>)</option><?php endforeach; ?></select></label>
  </div>
  <h2>Vitals &amp; measurements</h2>
  <div class="cols">
    <label>Systolic BP (mmHg)<input type="number" name="systolic" value="<?= h($_POST['systolic'] ?? '') ?>"></label>
    <label>Diastolic BP (mmHg)<input type="number" name="diastolic" value="<?= h($_POST['diastolic'] ?? '') ?>"></label>
    <label>Pulse (bpm)<input type="number" name="heart_rate" value="<?= h($_POST['heart_rate'] ?? '') ?>"></label>
    <label>Weight (kg)<input type="number" step="0.1" name="weight" value="<?= h($_POST['weight'] ?? '') ?>"></label>
    <label>Temperature (°C)<input type="number" step="0.1" name="temperature" value="<?= h($_POST['temperature'] ?? '') ?>"></label>
    <label>Fundal height (cm)<input type="number" step="0.5" name="fundal_height" value="<?= h($_POST['fundal_height'] ?? '') ?>"></label>
    <label>Fetal heart rate (bpm)<input type="number" name="fetal_hr" value="<?= h($_POST['fetal_hr'] ?? '') ?>"></label>
    <label>Haemoglobin (g/dL)<input type="number" step="0.1" name="hemoglobin" value="<?= h($_POST['hemoglobin'] ?? '') ?>"></label>
    <label>Urine protein<select name="urine_protein"><?php foreach (array_keys(URINE_SCALE) as $o): ?><option <?= $d['urine_protein'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></label>
  </div>
  <label>Clinical notes<textarea name="notes" rows="3"><?= h($d['notes']) ?></textarea></label>
  <h2>Next appointment</h2>
  <div class="cols">
    <label>Next visit date <small>(suggested from gestation)</small><input type="date" name="next_date" value="<?= h($_POST['next_date'] ?? suggest_next_date($lastGw, today())) ?>"></label>
    <label>Time<input type="time" name="next_time" value="<?= h($_POST['next_time'] ?? '09:00') ?>"></label>
  </div>
  <p class="muted">Leave the date blank to skip scheduling. Risk is recalculated using this visit and the trend across earlier visits.</p>
  <button class="btn">Save visit</button> <a class="btn ghost" href="patient.php?id=<?= $p['id'] ?>">Cancel</a>
</form>
<?php page_footer();
