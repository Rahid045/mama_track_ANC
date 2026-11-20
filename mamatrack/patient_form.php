<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$id = (int)($_GET['id'] ?? 0);
$p = $id ? get_patient($id) : null;
if ($id && !$p) { http_response_code(404); exit('Mother not found'); }
$errors = []; $d = $p ?: ['name' => '', 'dob' => '', 'phone' => '', 'email' => '', 'village' => '', 'district' => '', 'next_of_kin' => '', 'nok_phone' => '',
    'lmp' => '', 'gravida' => 1, 'parity' => 0, 'hiv_status' => 'unknown', 'history' => '', 'doctor_id' => $u['role'] === 'doctor' ? $u['id'] : ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'dob', 'phone', 'email', 'village', 'district', 'next_of_kin', 'nok_phone', 'lmp', 'hiv_status', 'history', 'doctor_id'] as $k) $d[$k] = post($k);
    $d['gravida'] = (int)post('gravida'); $d['parity'] = (int)post('parity');
    if (mb_strlen($d['name']) < 3) $errors[] = 'Full name is required.';
    if (!valid_date($d['dob']) || age_years($d['dob']) < 10 || age_years($d['dob']) > 60) $errors[] = 'Enter a valid date of birth (age 10–60).';
    if (!valid_date($d['lmp']) || $d['lmp'] > today() || $d['lmp'] < date('Y-m-d', strtotime('-310 days'))) $errors[] = 'Last menstrual period (LMP) must be a valid date within the last ~44 weeks.';
    if (normalize_phone($d['phone']) === '' || strlen(normalize_phone($d['phone'])) < 12) $errors[] = 'A valid phone number is required (for appointment SMS).';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    if (!in_array($d['hiv_status'], ['unknown', 'negative', 'positive'], true)) $errors[] = 'Invalid HIV status.';
    if ($d['gravida'] < 1 || $d['parity'] < 0 || $d['parity'] >= $d['gravida'] + 1) $errors[] = 'Check gravida / parity values.';
    if (!(int)$d['doctor_id']) $errors[] = 'Assign an attending doctor — missed-visit alerts go to this doctor.';
    if (!$errors) {
        $edd = date('Y-m-d', strtotime($d['lmp'] . ' +280 days'));   // Naegele's rule
        $cols = [$d['name'], $d['dob'], normalize_phone($d['phone']), $d['email'], $d['village'], $d['district'], $d['next_of_kin'],
                 normalize_phone($d['nok_phone']), $d['lmp'], $edd, $d['gravida'], $d['parity'], $d['hiv_status'], $d['history'], (int)$d['doctor_id']];
        if ($p) {
            db()->prepare('UPDATE patients SET name=?,dob=?,phone=?,email=?,village=?,district=?,next_of_kin=?,nok_phone=?,lmp=?,edd=?,gravida=?,parity=?,hiv_status=?,history=?,doctor_id=? WHERE id=?')
                ->execute([...$cols, $id]);
            refresh_risk($id); audit('patient_updated', "#$id");
        } else {
            db()->prepare('INSERT INTO patients(name,dob,phone,email,village,district,next_of_kin,nok_phone,lmp,edd,gravida,parity,hiv_status,history,doctor_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([...$cols, $u['id'], now()]);
            $id = (int)db()->lastInsertId();
            db()->prepare('UPDATE patients SET mrn=? WHERE id=?')->execute([sprintf('ANC-%s-%04d', date('Y'), $id), $id]);
            audit('patient_registered', "#$id");
        }
        flash($p ? 'Mother updated.' : 'Mother registered. You can now record her first visit.');
        redirect('patient.php?id=' . $id);
    }
}
page_header($p ? 'Edit mother' : 'Register mother', 'patients'); ?>
<?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
<form method="post" class="card form"><?= csrf_field() ?>
  <h2>Personal details</h2>
  <div class="cols">
    <label>Full name *<input name="name" value="<?= h($d['name']) ?>" required></label>
    <label>Date of birth *<input type="date" name="dob" value="<?= h($d['dob']) ?>" max="<?= today() ?>" required></label>
    <label>Phone * <small>(07xx… or +256…)</small><input name="phone" value="<?= h($d['phone']) ?>" required></label>
    <label>Email<input type="email" name="email" value="<?= h($d['email']) ?>"></label>
    <label>Village / parish<input name="village" value="<?= h($d['village']) ?>"></label>
    <label>District<input name="district" value="<?= h($d['district']) ?>"></label>
    <label>Next of kin<input name="next_of_kin" value="<?= h($d['next_of_kin']) ?>"></label>
    <label>Next of kin phone<input name="nok_phone" value="<?= h($d['nok_phone']) ?>"></label>
  </div>
  <h2>Pregnancy</h2>
  <div class="cols">
    <label>Last menstrual period (LMP) *<input type="date" name="lmp" value="<?= h($d['lmp']) ?>" max="<?= today() ?>" required></label>
    <label>Gravida (pregnancies incl. this)<input type="number" name="gravida" min="1" max="20" value="<?= h($d['gravida']) ?>"></label>
    <label>Parity (previous births)<input type="number" name="parity" min="0" max="20" value="<?= h($d['parity']) ?>"></label>
    <label>HIV status<select name="hiv_status"><?php foreach (['unknown', 'negative', 'positive'] as $o): ?><option <?= $d['hiv_status'] === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></label>
    <label>Attending doctor *<select name="doctor_id"><option value="">— select —</option>
      <?php foreach (doctors() as $doc): ?><option value="<?= $doc['id'] ?>" <?= (int)$d['doctor_id'] === (int)$doc['id'] ? 'selected' : '' ?>><?= h($doc['name']) ?></option><?php endforeach; ?></select></label>
  </div>
  <label>Medical / obstetric history<textarea name="history" rows="3"><?= h($d['history']) ?></textarea></label>
  <p class="muted">EDD is calculated automatically (LMP + 280 days).</p>
  <button class="btn"><?= $p ? 'Save changes' : 'Register mother' ?></button> <a class="btn ghost" href="<?= $p ? 'patient.php?id=' . $id : 'patients.php' ?>">Cancel</a>
</form>
<?php page_footer();
