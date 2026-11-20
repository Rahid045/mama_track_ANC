<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$q = trim($_GET['q'] ?? ''); $risk = $_GET['risk'] ?? ''; $mineOnly = isset($_GET['mine']);
$sql = "SELECT p.*, d.name AS doctor_name,
  (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id=p.id) AS last_visit,
  (SELECT MIN(appt_date) FROM appointments a WHERE a.patient_id=p.id AND a.status='scheduled') AS next_appt
  FROM patients p LEFT JOIN users d ON d.id=p.doctor_id WHERE 1=1";
$args = [];
if ($q !== '') { $sql .= ' AND (p.name LIKE ? OR p.mrn LIKE ? OR p.phone LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
if (in_array($risk, ['high', 'moderate', 'low', 'unassessed'], true)) { $sql .= ' AND p.risk_level=?'; $args[] = $risk; }
if ($mineOnly) { $sql .= ' AND p.doctor_id=?'; $args[] = $u['id']; }
$sql .= " ORDER BY CASE p.risk_level WHEN 'high' THEN 0 WHEN 'moderate' THEN 1 ELSE 2 END, p.name LIMIT 300";
$s = db()->prepare($sql); $s->execute($args); $rows = $s->fetchAll();
page_header('Mothers', 'patients'); ?>
<div class="bar">
  <form method="get" class="inline">
    <input name="q" value="<?= h($q) ?>" placeholder="Search name, MRN or phone">
    <select name="risk"><option value="">All risk levels</option>
      <?php foreach (['high' => 'High', 'moderate' => 'Moderate', 'low' => 'Low', 'unassessed' => 'Not assessed'] as $k => $l): ?>
      <option value="<?= $k ?>" <?= $risk === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <?php if ($u['role'] === 'doctor'): ?><label class="chk"><input type="checkbox" name="mine" <?= $mineOnly ? 'checked' : '' ?>> My mothers</label><?php endif; ?>
    <button class="btn sm">Filter</button>
  </form>
  <a class="btn" href="patient_form.php">+ Register mother</a>
</div>
<div class="card"><div class="tbl"><table>
<tr><th>Mother</th><th>Age</th><th>Gest.</th><th>EDD</th><th>Risk</th><th>Doctor</th><th>Last visit</th><th>Next appt</th></tr>
<?php foreach ($rows as $p): ?><tr>
 <td><a href="patient.php?id=<?= $p['id'] ?>"><b><?= h($p['name']) ?></b></a><br><small><?= h($p['mrn']) ?></small></td>
 <td><?= age_years($p['dob']) ?></td><td><?= gest_weeks($p['lmp'], today()) ?> wks</td><td><?= fdate($p['edd']) ?></td>
 <td><?= risk_badge($p['risk_level']) ?></td><td><?= h($p['doctor_name']) ?></td><td><?= fdate($p['last_visit']) ?></td><td><?= fdate($p['next_appt']) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="8" class="muted">No mothers found.</td></tr><?php endif; ?></table></div></div>
<?php page_footer();
