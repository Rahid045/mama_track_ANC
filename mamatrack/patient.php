<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login();
$p = get_patient((int)($_GET['id'] ?? 0));
if (!$p) { http_response_code(404); exit('Mother not found'); }
audit('patient_viewed', '#' . $p['id']);
$visits = patient_visits((int)$p['id']);
$risk = assess_risk($p, $visits, missed_count((int)$p['id']));
$a = db()->prepare('SELECT * FROM appointments WHERE patient_id=? ORDER BY appt_date DESC, id DESC'); $a->execute([$p['id']]); $appts = $a->fetchAll();

// chart payloads
$labels = array_map(fn($v) => 'Wk ' . ($v['gest_weeks'] ?? '?'), $visits);
$col = fn(string $k) => array_map(fn($v) => $v[$k] !== null ? (float)$v[$k] : null, $visits);
$charts = [
  ['title' => 'Blood pressure (mmHg)', 'labels' => $labels, 'series' => [['name' => 'Systolic', 'color' => '#c0392b', 'data' => $col('systolic')], ['name' => 'Diastolic', 'color' => '#2471a3', 'data' => $col('diastolic')]],
   'refs' => [['y' => 140, 'label' => '140', 'color' => '#c0392b'], ['y' => 90, 'label' => '90', 'color' => '#2471a3']]],
  ['title' => 'Weight (kg)', 'labels' => $labels, 'series' => [['name' => 'Weight', 'color' => '#117a65', 'data' => $col('weight')]], 'refs' => []],
  ['title' => 'Haemoglobin (g/dL)', 'labels' => $labels, 'series' => [['name' => 'Hb', 'color' => '#8e44ad', 'data' => $col('hemoglobin')]], 'refs' => [['y' => 11, 'label' => '11 (anaemia <)', 'color' => '#8e44ad']]],
  ['title' => 'Fetal heart rate (bpm)', 'labels' => $labels, 'series' => [['name' => 'FHR', 'color' => '#d35400', 'data' => $col('fetal_hr')]], 'refs' => [['y' => 110, 'label' => '110', 'color' => '#d35400'], ['y' => 160, 'label' => '160', 'color' => '#d35400']]],
];
$gw = gest_weeks($p['lmp'], today());
page_header($p['name'], 'patients'); ?>
<div class="card head">
  <div><span class="muted"><?= h($p['mrn']) ?></span> · <?= age_years($p['dob']) ?> yrs · <?= h(normalize_phone($p['phone'])) ?> · <?= h(trim($p['village'] . ', ' . $p['district'], ', ')) ?><br>
   <b><?= $gw ?> weeks</b> pregnant · EDD <?= fdate($p['edd']) ?> · G<?= $p['gravida'] ?>P<?= $p['parity'] ?> · HIV: <?= h($p['hiv_status']) ?> · Doctor: <?= h($p['doctor_name']) ?></div>
  <div class="actions"><a class="btn" href="visit_form.php?patient_id=<?= $p['id'] ?>">+ Record visit</a>
    <a class="btn ghost" href="appointment_form.php?patient_id=<?= $p['id'] ?>">+ Appointment</a>
    <a class="btn ghost" href="patient_form.php?id=<?= $p['id'] ?>">Edit</a></div>
</div>

<div class="card risk-<?= h($risk['level']) ?>">
  <h2>Risk assessment <?= risk_badge($risk['level']) ?> <small class="muted">score <?= $risk['score'] ?></small></h2>
  <p><b>Suggested action:</b> <?= h($risk['action']) ?></p>
  <?php if ($risk['factors']): ?><ul class="factors"><?php foreach ($risk['factors'] as $f): ?>
    <li class="<?= $f['critical'] ? 'crit' : '' ?>"><b>+<?= $f['pts'] ?></b> <?= h($f['label']) ?> — <span class="muted"><?= h($f['detail']) ?></span></li><?php endforeach; ?></ul>
  <?php elseif ($visits): ?><p class="muted">No risk factors identified from recorded data.</p><?php endif; ?>
  <p class="muted small">Decision-support only — based on the latest values <i>and</i> trends across <?= count($visits) ?> visit(s). Clinical judgement prevails.</p>
</div>

<div class="card"><h2>Trends across visits</h2>
  <div class="charts"><?php foreach ($charts as $c): ?><div class="chart" data-chart='<?= h(json_encode($c)) ?>'></div><?php endforeach; ?></div></div>

<div class="card"><h2>Visit history</h2><div class="tbl"><table>
<tr><th>Date</th><th>Wk</th><th>BP</th><th>Pulse</th><th>Weight</th><th>Temp</th><th>Fundal</th><th>FHR</th><th>Hb</th><th>Urine prot.</th><th>Notes</th></tr>
<?php foreach (array_reverse($visits) as $v): ?><tr>
 <td><?= fdate($v['visit_date']) ?></td><td><?= $v['gest_weeks'] ?></td>
 <td class="<?= is_htn($v) ? 'bad' : '' ?>"><?= $v['systolic'] !== null ? "{$v['systolic']}/{$v['diastolic']}" : '—' ?></td>
 <td><?= $v['heart_rate'] ?? '—' ?></td><td><?= $v['weight'] ?? '—' ?></td><td><?= $v['temperature'] ?? '—' ?></td>
 <td><?= $v['fundal_height'] ?? '—' ?></td><td><?= $v['fetal_hr'] ?? '—' ?></td><td><?= $v['hemoglobin'] ?? '—' ?></td>
 <td><?= h($v['urine_protein'] ?? '—') ?></td><td><?= h($v['notes']) ?></td></tr>
<?php endforeach; if (!$visits): ?><tr><td colspan="11" class="muted">No visits recorded yet.</td></tr><?php endif; ?></table></div></div>

<div class="card"><h2>Appointments</h2><div class="tbl"><table>
<tr><th>Date</th><th>Time</th><th>Reason</th><th>Status</th><th></th></tr>
<?php foreach ($appts as $x): ?><tr><td><?= fdate($x['appt_date']) ?></td><td><?= h($x['appt_time']) ?></td><td><?= h($x['reason']) ?><?= $x['rescheduled_from'] ? ' <small class="muted">(rescheduled)</small>' : '' ?></td>
 <td><?= status_badge($x['status']) ?></td>
 <td><?php if (in_array($x['status'], ['scheduled', 'missed'], true)): ?><a class="btn sm" href="reschedule.php?id=<?= $x['id'] ?>">Reschedule</a><?php endif; ?></td></tr>
<?php endforeach; if (!$appts): ?><tr><td colspan="5" class="muted">No appointments.</td></tr><?php endif; ?></table></div></div>
<?php page_footer();
