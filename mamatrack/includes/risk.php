<?php
/**
 * Trend-based maternal risk assessment.
 *
 * Looks at the LATEST visit (absolute values) AND at the DIRECTION of change across
 * previous visits (trends), plus demographic factors and missed appointments.
 * Every point added is returned with a plain-language explanation so clinicians can see WHY.
 *
 * !! Clinical decision SUPPORT only. Thresholds below are common WHO/ACOG-style cut-offs and
 * !! must be reviewed and approved by clinicians against Uganda MoH ANC guidelines before real use.
 */
const URINE_SCALE = ['neg' => 0, 'trace' => 0.5, '1+' => 1, '2+' => 2, '3+' => 3];
const RISK_HIGH_SCORE = 7;
const RISK_MODERATE_SCORE = 3;

function gest_weeks(?string $lmp, string $date): ?int {
    if (!$lmp) return null;
    return max(0, (int)floor((strtotime($date) - strtotime($lmp)) / 86400 / 7));
}

function is_htn(array $v): bool {
    return $v['systolic'] !== null && $v['diastolic'] !== null && ($v['systolic'] >= 140 || $v['diastolic'] >= 90);
}

function assess_risk(array $p, array $visits, int $missed): array {
    $n = count($visits);
    if ($n === 0) {
        return ['level' => 'unassessed', 'score' => 0, 'factors' => [],
                'action' => 'No clinical visit recorded yet. Record the first ANC visit to start risk assessment.'];
    }
    $f = [];
    $add = function (string $label, int $pts, string $detail, bool $crit = false) use (&$f) {
        $f[] = ['label' => $label, 'pts' => $pts, 'detail' => $detail, 'critical' => $crit];
    };
    $last = $visits[$n - 1];
    $gw = $last['gest_weeks'] ?? gest_weeks($p['lmp'], $last['visit_date']) ?? 0;
    $sys = $last['systolic']; $dia = $last['diastolic'];

    // --- Blood pressure: absolute ---
    if ($sys !== null && $dia !== null) {
        if ($sys >= 160 || $dia >= 110)      $add('Severe-range blood pressure', 5, "$sys/$dia mmHg at latest visit (≥160/110)", true);
        elseif ($sys >= 140 || $dia >= 90)   $add('Hypertension', 3, "$sys/$dia mmHg at latest visit (≥140/90)");
        elseif ($sys >= 130 || $dia >= 80)   $add('Elevated blood pressure', 1, "$sys/$dia mmHg at latest visit");
    }
    // --- Blood pressure: trend ---
    if ($n >= 2 && is_htn($visits[$n - 2]) && is_htn($last)) {
        $add('Persistent hypertension', 2, 'Raised BP at two consecutive visits');
    }
    $bp = array_slice(array_values(array_filter($visits, fn($v) => $v['systolic'] !== null && $v['diastolic'] !== null)), -3);
    if (count($bp) >= 2) {
        $ds = end($bp)['systolic'] - $bp[0]['systolic'];
        $dd = end($bp)['diastolic'] - $bp[0]['diastolic'];
        if ($ds >= 15 || $dd >= 10) {
            $add('Rising blood-pressure trend', 2, sprintf('Systolic %+d / diastolic %+d mmHg over the last %d visits', $ds, $dd, count($bp)));
        }
    }
    // --- Proteinuria ---
    $u = URINE_SCALE[$last['urine_protein'] ?? ''] ?? 0;
    $htnNow = $sys !== null && $dia !== null && ($sys >= 140 || $dia >= 90);
    if ($u >= 1 && $htnNow)  $add('Possible pre-eclampsia', 4, "Hypertension with proteinuria ({$last['urine_protein']})", true);
    elseif ($u >= 2)         $add('Significant proteinuria', 2, "Urine protein {$last['urine_protein']}");
    // --- Weight trend ---
    $w = array_values(array_filter($visits, fn($v) => $v['weight'] !== null));
    if (count($w) >= 2) {
        [$a, $b] = [$w[count($w) - 2], $w[count($w) - 1]];
        $days = (strtotime($b['visit_date']) - strtotime($a['visit_date'])) / 86400;
        $delta = $b['weight'] - $a['weight'];
        if ($days >= 5) {
            $rate = $delta / ($days / 7);
            if ($gw >= 20 && $rate > 1.0) $add('Rapid weight gain', 2, sprintf('%+.1f kg in %d days (%.1f kg/week) — consider oedema/pre-eclampsia', $delta, $days, $rate));
            elseif ($delta <= -1.0)      $add('Weight loss since last visit', 1, sprintf('%+.1f kg in %d days', $delta, $days));
        }
    }
    // --- Haemoglobin: absolute + trend ---
    $hb = array_values(array_filter($visits, fn($v) => $v['hemoglobin'] !== null));
    if ($hb) {
        $cur = end($hb)['hemoglobin'];
        if ($cur < 7)        $add('Severe anaemia', 5, "Hb $cur g/dL (<7)", true);
        elseif ($cur < 10)   $add('Moderate anaemia', 3, "Hb $cur g/dL (<10)");
        elseif ($cur < 11)   $add('Mild anaemia', 1, "Hb $cur g/dL (<11)");
        if (count($hb) >= 2 && ($hb[count($hb) - 2]['hemoglobin'] - $cur) >= 1.0) {
            $add('Falling haemoglobin', 1, sprintf('Dropped %.1f g/dL since previous result', $hb[count($hb) - 2]['hemoglobin'] - $cur));
        }
    }
    // --- Other vitals / fetal ---
    if ($last['heart_rate'] !== null) {
        if ($last['heart_rate'] > 120)     $add('Marked maternal tachycardia', 2, "Pulse {$last['heart_rate']} bpm");
        elseif ($last['heart_rate'] > 100) $add('Maternal tachycardia', 1, "Pulse {$last['heart_rate']} bpm");
    }
    if ($last['temperature'] !== null && $last['temperature'] >= 38.0) $add('Fever', 2, "Temperature {$last['temperature']} °C");
    if ($last['fetal_hr'] !== null && $gw >= 20 && ($last['fetal_hr'] < 110 || $last['fetal_hr'] > 160)) {
        $add('Abnormal fetal heart rate', 4, "FHR {$last['fetal_hr']} bpm (normal 110–160)", true);
    }
    if ($last['fundal_height'] !== null && $gw >= 24 && abs($last['fundal_height'] - $gw) > 3) {
        $add('Fundal height mismatch', 2, "Fundal height {$last['fundal_height']} cm vs {$gw} weeks gestation");
    }
    // --- Demographic / history ---
    $age = age_years($p['dob'], $last['visit_date']);
    if ($age < 18 || $age >= 35) $add('Age-related risk', 1, "Age $age years");
    if (($p['hiv_status'] ?? '') === 'positive') $add('HIV-positive', 2, 'Needs ART adherence, viral-load and PMTCT follow-up');
    if (($p['parity'] ?? 0) >= 5) $add('Grand multiparity', 1, "Parity {$p['parity']}");
    $firstGw = $visits[0]['gest_weeks'] ?? gest_weeks($p['lmp'], $visits[0]['visit_date']);
    if ($firstGw !== null && $firstGw > 12) $add('Late first ANC contact', 1, "First visit at $firstGw weeks (recommended ≤12)");
    // --- Attendance ---
    if ($missed >= 2)      $add('Repeated missed appointments', 2, "$missed missed visits");
    elseif ($missed === 1) $add('Missed appointment', 1, '1 missed visit');

    $score = array_sum(array_column($f, 'pts'));
    $crit = (bool)array_filter($f, fn($x) => $x['critical']);
    if ($crit || $score >= RISK_HIGH_SCORE) {
        $level = 'high';
        $action = 'Doctor review within 24 hours. Repeat key measurements, consider referral to a higher-level facility and shorten the ANC interval.';
    } elseif ($score >= RISK_MODERATE_SCORE) {
        $level = 'moderate';
        $action = 'Flag for doctor review at the next contact. Repeat abnormal measurements and consider an earlier follow-up visit.';
    } else {
        $level = 'low';
        $action = 'Continue routine ANC schedule and health education.';
    }
    usort($f, fn($a, $b) => $b['pts'] <=> $a['pts']);
    return ['level' => $level, 'score' => $score, 'factors' => $f, 'action' => $action];
}

function patient_visits(int $pid): array {
    $s = db()->prepare('SELECT * FROM visits WHERE patient_id=? ORDER BY visit_date, id');
    $s->execute([$pid]);
    return $s->fetchAll();
}
function missed_count(int $pid): int {
    $s = db()->prepare('SELECT COUNT(*) FROM appointments WHERE patient_id=? AND missed_at IS NOT NULL');
    $s->execute([$pid]);
    return (int)$s->fetchColumn();
}
/** Recompute and cache the risk level on the patient row. Returns the assessment. */
function refresh_risk(int $pid): array {
    $p = get_patient($pid);
    $r = assess_risk($p, patient_visits($pid), missed_count($pid));
    db()->prepare('UPDATE patients SET risk_level=?, risk_score=? WHERE id=?')->execute([$r['level'], $r['score'], $pid]);
    return $r;
}
/** WHO-style spacing: monthly to 28w, fortnightly to 36w, then weekly. */
function suggest_next_date(int $gw, string $from): string {
    $days = $gw < 28 ? 28 : ($gw < 36 ? 14 : 7);
    return date('Y-m-d', strtotime("$from +$days days"));
}
