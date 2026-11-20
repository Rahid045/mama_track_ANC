<?php
// MamaTrack ANC — configuration. Edit values here (no code changes needed elsewhere).
$config = [
    'app_name'    => 'MamaTrack ANC',
    'timezone'    => 'Africa/Kampala',
    'db_path'     => __DIR__ . '/../data/mamatrack.sqlite',
    // InfinityFree uses MySQL. Put the actual credentials in config.local.php
    // (created from config.local.php.example), never in this tracked file.
    'database' => [
        'driver' => 'sqlite', // sqlite for XAMPP, mysql for InfinityFree
        'host' => '',
        'port' => 3306,
        'name' => '',
        'user' => '',
        'password' => '',
    ],
    'backup_dir'  => __DIR__ . '/../data/backups',
    'grace_days'  => 0,          // an appointment becomes "missed" when appt_date < today - grace_days
    'session_minutes' => 30,     // auto sign-out after inactivity
    'base_url'    => '',         // e.g. 'https://anc.example.org' — used in SMS reschedule links. Blank = auto-detect.
    'notify' => [
        'mode'         => 'log',   // 'log' = record messages only (safe for demo) | 'live' = actually send
        'notify_patient' => true,  // also SMS the mother (with her self-reschedule link) when she misses a visit
        'email_from'   => 'no-reply@mamatrack.local',
        // SMS via Africa's Talking (https://africastalking.com) — works for MTN/Airtel Uganda
        'at_username'  => 'sandbox',   // 'sandbox' for testing, or your live app username
        'at_api_key'   => '',
        'at_sender'    => '',          // optional approved sender ID
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (!is_array($override)) throw new RuntimeException('config.local.php must return an array.');
    $config = array_replace_recursive($config, $override);
}
return $config;
