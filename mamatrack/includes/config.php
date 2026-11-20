<?php
// MamaTrack ANC — configuration. Edit values here (no code changes needed elsewhere).
return [
    'app_name'    => 'MamaTrack ANC',
    'timezone'    => 'Africa/Kampala',
    'db_path'     => __DIR__ . '/../data/mamatrack.sqlite',
    // Set DATABASE_URL on Vercel to a hosted PostgreSQL connection string.
    // When it is unset, the application keeps using local SQLite for XAMPP.
    'database_url' => getenv('DATABASE_URL') ?: '',
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
