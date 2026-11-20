<?php
/** Convert 07xx / 7xx / 256... into +256 E.164 format. Returns '' if it doesn't look like a phone number. */
function normalize_phone(?string $p): string {
    $d = preg_replace('/\D+/', '', (string)$p);
    if ($d === '') return '';
    if (str_starts_with($d, '256')) return '+' . $d;
    if (str_starts_with($d, '0')) return '+256' . substr($d, 1);
    if (strlen($d) === 9) return '+256' . $d;
    return '+' . $d;
}

function send_sms(string $to, string $msg): array {
    $c = cfg('notify');
    $to = normalize_phone($to);
    if ($to === '') return ['failed', 'No phone number on file'];
    if (($c['mode'] ?? 'log') !== 'live') return ['simulated', 'mode=log: message recorded but not sent'];
    $url = ($c['at_username'] === 'sandbox')
        ? 'https://api.sandbox.africastalking.com/version1/messaging'
        : 'https://api.africastalking.com/version1/messaging';
    $post = ['username' => $c['at_username'], 'to' => $to, 'message' => $msg];
    if (!empty($c['at_sender'])) $post['from'] = $c['at_sender'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['apiKey: ' . $c['at_api_key'], 'Accept: application/json']]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    return ($code >= 200 && $code < 300) ? ['sent', substr((string)$r, 0, 400)] : ['failed', "HTTP $code $err " . substr((string)$r, 0, 300)];
}

function send_email(string $to, string $subject, string $msg): array {
    $c = cfg('notify');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return ['failed', 'No valid email on file'];
    if (($c['mode'] ?? 'log') !== 'live') return ['simulated', 'mode=log: message recorded but not sent'];
    $ok = @mail($to, $subject, $msg, "From: {$c['email_from']}\r\nContent-Type: text/plain; charset=UTF-8");
    return $ok ? ['sent', 'accepted by mail()'] : ['failed', 'mail() returned false — configure SMTP/sendmail'];
}

/** Send via SMS and/or email, log every attempt in the notifications table. */
function notify_person(?int $apptId, string $who, ?string $phone, ?string $email, string $subject, string $msg): void {
    $log = db()->prepare('INSERT INTO notifications(appointment_id,recipient,channel,to_addr,message,status,response,created_at) VALUES(?,?,?,?,?,?,?,?)');
    if ($phone !== null && $phone !== '') {
        [$st, $resp] = send_sms($phone, $msg);
        $log->execute([$apptId, $who, 'sms', normalize_phone($phone), $msg, $st, $resp, now()]);
    }
    if ($email !== null && $email !== '') {
        [$st, $resp] = send_email($email, $subject, $msg);
        $log->execute([$apptId, $who, 'email', $email, $msg, $st, $resp, now()]);
    }
    if (!$phone && !$email) $log->execute([$apptId, $who, 'none', '', $msg, 'failed', 'No contact details on file', now()]);
}

function base_url(): string {
    if ($env = getenv('BASE_URL')) return rtrim($env, '/');
    $b = cfg('base_url');
    if ($b) return rtrim($b, '/');
    $scheme = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (str_ends_with($dir, '/cron')) $dir = substr($dir, 0, -5);
    return "$scheme://$host$dir";
}
function reschedule_link(string $token): string { return base_url() . '/reschedule.php?t=' . $token; }

/** Mark overdue scheduled appointments as MISSED and alert the attending physician (and the mother). */
function process_missed_appointments(): int {
    $pdo = db();
    $cutoff = date('Y-m-d', strtotime('-' . (int)cfg('grace_days', 0) . ' days'));
    $s = $pdo->prepare("SELECT a.*, p.name AS pname, p.mrn, p.phone AS pphone, p.email AS pemail, p.doctor_id AS pdoc
                        FROM appointments a JOIN patients p ON p.id=a.patient_id
                        WHERE a.status='scheduled' AND a.appt_date < ?");
    $s->execute([$cutoff]);
    $rows = $s->fetchAll();
    foreach ($rows as $a) {
        $pdo->prepare("UPDATE appointments SET status='missed', missed_at=? WHERE id=?")->execute([now(), $a['id']]);
        $r = refresh_risk((int)$a['patient_id']);
        $docId = $a['doctor_id'] ?: $a['pdoc'];
        $d = $pdo->prepare('SELECT * FROM users WHERE id=?'); $d->execute([$docId]); $doc = $d->fetch() ?: [];
        $risk = strtoupper($r['level']);
        $msg = "MamaTrack ALERT: {$a['pname']} ({$a['mrn']}) missed her ANC visit on " . fdate($a['appt_date'])
             . ". Risk: $risk. Tel: " . normalize_phone($a['pphone']) . ". Please follow up and reschedule.";
        notify_person((int)$a['id'], 'doctor:' . ($doc['name'] ?? 'unassigned'), $doc['phone'] ?? null, $doc['email'] ?? null,
                      "Missed ANC appointment: {$a['pname']}", $msg);
        if (cfg('notify')['notify_patient'] ?? false) {
            $first = explode(' ', trim($a['pname']))[0];
            notify_person((int)$a['id'], 'patient:' . $a['pname'], $a['pphone'], null, 'Missed antenatal visit',
                "Dear $first, you missed your antenatal visit on " . fdate($a['appt_date']) . ". Your health and baby's matter - please rebook here: " . reschedule_link($a['token']));
        }
        audit('appointment_missed', "appt #{$a['id']} patient #{$a['patient_id']}");
    }
    return count($rows);
}

/** SMS reminder to mothers whose appointment is tomorrow. */
function send_reminders(): int {
    $pdo = db();
    $s = $pdo->prepare("SELECT a.*, p.name AS pname, p.phone AS pphone FROM appointments a JOIN patients p ON p.id=a.patient_id
                        WHERE a.status='scheduled' AND a.reminder_sent=0 AND a.appt_date=?");
    $s->execute([date('Y-m-d', strtotime('+1 day'))]);
    $rows = $s->fetchAll();
    foreach ($rows as $a) {
        $first = explode(' ', trim($a['pname']))[0];
        $t = $a['appt_time'] ? ' at ' . $a['appt_time'] : '';
        notify_person((int)$a['id'], 'patient:' . $a['pname'], $a['pphone'], null, 'ANC appointment reminder',
            "Dear $first, reminder: your antenatal visit is tomorrow, " . fdate($a['appt_date']) . "$t. Can't make it? Reschedule: " . reschedule_link($a['token']));
        $pdo->prepare('UPDATE appointments SET reminder_sent=1 WHERE id=?')->execute([$a['id']]);
    }
    return count($rows);
}

function new_appointment(int $patientId, ?int $doctorId, string $date, ?string $time, string $reason, ?int $by, ?int $from = null): int {
    $pdo = db();
    $pdo->prepare('INSERT INTO appointments(patient_id,doctor_id,appt_date,appt_time,reason,token,rescheduled_from,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)')
        ->execute([$patientId, $doctorId, $date, $time ?: null, $reason, bin2hex(random_bytes(16)), $from, $by, now()]);
    return (int)$pdo->lastInsertId();
}
