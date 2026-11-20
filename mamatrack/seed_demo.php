<?php
/** Optional demo data:  php seed_demo.php   (CLI only). Creates sample staff, mothers, visits and appointments. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/includes/bootstrap.php';
$pdo = db();
if ((int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn() > 0) exit("Patients already exist — demo seed skipped.\n");
$pw = password_hash('Demo@1234', PASSWORD_DEFAULT);
$ins = $pdo->prepare('INSERT INTO users(name,email,phone,role,password_hash,must_change,created_at) VALUES(?,?,?,?,?,0,?)');
$ins->execute(['Dr. Sarah Nakato', 'sarah@mamatrack.local', '+256700000001', 'doctor', $pw, now()]); $d1 = (int)$pdo->lastInsertId();
$ins->execute(['Dr. Peter Okello', 'peter@mamatrack.local', '+256700000002', 'doctor', $pw, now()]); $d2 = (int)$pdo->lastInsertId();
$ins->execute(['Grace Auma (Midwife)', 'grace@mamatrack.local', '+256700000003', 'nurse', $pw, now()]);

function mk(string $name, int $age, string $phone, int $gwNow, string $hiv, int $parity, int $doc, array $visitRows): int {
    $pdo = db(); $lmp = date('Y-m-d', strtotime("-" . ($gwNow * 7 + 2) . " days"));
    $pdo->prepare('INSERT INTO patients(name,dob,phone,village,district,lmp,edd,gravida,parity,hiv_status,doctor_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$name, date('Y-m-d', strtotime("-$age years -100 days")), $phone, 'Nansana', 'Wakiso', $lmp, date('Y-m-d', strtotime("$lmp +280 days")), $parity + 1, $parity, $hiv, $doc, now()]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE patients SET mrn=? WHERE id=?')->execute([sprintf('ANC-%s-%04d', date('Y'), $id), $id]);
    foreach ($visitRows as [$wk, $sys, $dia, $hr, $wt, $fh, $fhr, $hb, $up]) {
        $date = date('Y-m-d', strtotime("$lmp +" . ($wk * 7) . ' days'));
        $pdo->prepare('INSERT INTO visits(patient_id,visit_date,gest_weeks,systolic,diastolic,heart_rate,weight,temperature,fundal_height,fetal_hr,hemoglobin,urine_protein,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$id, $date, $wk, $sys, $dia, $hr, $wt, 36.7, $fh, $fhr, $hb, $up, now()]);
    }
    return $id;
}
//                 wk  sys dia hr  kg    FH  FHR  Hb    urine
$a = mk('Aisha Namusoke', 24, '0772100001', 30, 'negative', 1, $d1, [[12,112,70,78,58.0,null,null,12.1,'neg'],[16,116,72,80,60.0,null,null,11.8,'neg'],[20,122,78,82,62.5,20,145,11.6,'neg'],[24,132,86,84,65.0,24,142,11.4,'neg'],[28,142,92,88,69.5,28,140,11.2,'1+']]);
$b = mk('Mary Atim', 29, '0772100002', 26, 'negative', 1, $d1, [[10,108,68,76,60.0,null,null,12.5,'neg'],[14,110,70,76,61.0,null,null,12.4,'neg'],[18,110,70,78,62.5,18,150,12.2,'neg'],[22,112,72,78,64.0,22,148,12.1,'neg'],[26,112,72,80,66.0,26,146,12.0,'neg']]);
$c = mk('Esther Nabirye', 17, '0772100003', 28, 'negative', 0, $d2, [[16,106,66,84,50.0,null,null,11.5,'neg'],[22,108,68,88,53.0,22,150,10.8,'neg'],[28,118,76,96,59.5,28,152,9.6,'trace']]);
$d = mk('Joan Akello', 36, '0772100004', 32, 'positive', 4, $d2, [[20,118,76,82,70.0,20,144,10.9,'neg'],[26,120,78,84,72.0,26,142,10.5,'neg']]);
$e = mk('Prossy Nantume', 26, '0772100005', 22, 'negative', 0, $d1, [[14,110,70,76,55.0,null,null,12.0,'neg'],[18,112,72,78,57.0,18,148,11.9,'neg']]);
$f = mk('Betty Kyomuhendo', 22, '0772100006', 9, 'unknown', 0, $d2, []);

// appointments: Aisha overdue by 3 days (will be flagged MISSED), Joan missed earlier, Prossy overdue yesterday, Mary & Betty upcoming
foreach ([[$a, -3], [$e, -1], [$b, 2], [$f, 1]] as [$pid, $off]) {
    $doc = (int)$pdo->query("SELECT doctor_id FROM patients WHERE id=$pid")->fetchColumn();
    new_appointment($pid, $doc, date('Y-m-d', strtotime("$off days")), '09:00', 'ANC visit', null);
}
$old = new_appointment($d, $d2, date('Y-m-d', strtotime('-30 days')), '09:00', 'ANC visit', null);
$pdo->prepare("UPDATE appointments SET status='missed', missed_at=? WHERE id=?")->execute([now(), $old]);
foreach ([$a, $b, $c, $d, $e, $f] as $pid) refresh_risk($pid);
echo "Demo data created. Staff login: sarah@mamatrack.local / grace@mamatrack.local / peter@mamatrack.local — password Demo@1234\n";
